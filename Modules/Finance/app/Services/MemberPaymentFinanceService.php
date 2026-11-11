<?php

namespace Modules\Finance\Services;

use Illuminate\Database\QueryException;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceItem;
use Modules\Finance\Models\FinanceJournal;
use Modules\Finance\Models\FinanceReceipt;
use Modules\Finance\Models\FinanceScope;
use Modules\Member\Models\MemberPayment;

class MemberPaymentFinanceService
{
    public function __construct(
        private readonly OrganizationModuleService $modules,
        private readonly FinanceScopeService $scopes,
        private readonly FinanceAccountProvisioningService $accounts,
        private readonly FinancePeriodService $periods,
        private readonly FinanceJournalService $journals,
        private readonly FinanceReceiptService $receipts,
        private readonly FinanceSettlementService $settlements,
    ) {}

    public function handle(MemberPayment $payment): ?FinanceReceipt
    {
        $organization = $payment->organization;

        if (! $this->modules->isEnabled($organization, 'Finance')) {
            return null;
        }

        $existing = FinanceJournal::query()
            ->where('organization_id', $organization->id)
            ->where('source_type', 'member_payment')
            ->where('source_id', $payment->id)
            ->first();

        if ($existing) {
            $this->settlements->createObligationsForPayment($payment);
            return $this->receiptForExistingJournal($existing);
        }

        // All accounting entries use the organization's single ledger. Preserve
        // the collecting unit as transaction attribution only.
        $scope = $this->scopes->organizationScope($organization);
        $unitId = $this->resolveUnitAttribution($organization, $payment);

        $this->accounts->provisionDefaults($organization, $scope);

        $receivedAt = $payment->received_at ?? now();

        $period = $this->periods->forDate($scope, $receivedAt);

        if (! $period) {
            throw new \InvalidArgumentException(
                'No open financial period exists for the membership payment date.'
            );
        }

        $depositAccount = $this->resolveDepositAccount(
            $organization,
            $scope,
            $payment->payment_method,
        );

        $creditLines = $this->resolveIncomeLines(
            $organization,
            $payment,
            $scope,
        );

        $interScopeLines = $this->resolveInterScopeRecognitionLines(
            $organization,
            $payment,
            $scope,
        );

        $lines = [
            [
                'finance_account_id' => $depositAccount->id,
                'description' => 'Membership payment received',
                'cost_center_type' => $payment->collector_scope_type,
                'cost_center_id' => $payment->collector_scope_id,
                'debit' => (string) $payment->total_amount,
                'credit' => '0',
            ],
            ...$creditLines,
            ...$interScopeLines,
        ];

        try {
            $journal = $this->journals->post(
                $organization,
                $scope,
                $period,
                [
                    'transaction_date' => $receivedAt->toDateString(),
                    'description' => 'Membership payment ' . $payment->receipt_number,
                    'source_type' => 'member_payment',
                    'source_id' => $payment->id,
                    'unit_id' => $unitId,
                    'metadata' => [
                        'member_payment_id' => $payment->id,
                        'member_receipt_number' => $payment->receipt_number,
                        'payment_method' => $payment->payment_method,
                        'reference' => $payment->reference,
                        'currency' => $payment->currency,
                    ],
                    'lines' => $lines,
                ],
            );
        } catch (QueryException $exception) {
            // A concurrent handler may have inserted the same source journal
            // after the initial lookup. Recover only if that row now exists;
            // otherwise preserve the original integrity error.
            $journal = FinanceJournal::query()
                ->where('organization_id', $organization->id)
                ->where('finance_scope_id', $scope->id)
                ->where('source_type', 'member_payment')
                ->where('source_id', $payment->id)
                ->first();

            if (! $journal) {
                throw $exception;
            }
        }

        $this->settlements->createObligationsForPayment($payment);

        return $this->receipts->issue(
            $organization,
            $scope,
            $journal,
            [
                'metadata' => [
                    'member_payment_id' => $payment->id,
                    'member_receipt_number' => $payment->receipt_number,
                    'currency' => $payment->currency,
                ],
            ],
        );
    }

    private function receiptForExistingJournal(FinanceJournal $journal): ?FinanceReceipt
    {
        $scope = $journal->scope;
        $organization = $journal->organization;

        $receipt = $this->receipts->forJournal($journal);

        if ($receipt) {
            return $receipt;
        }

        return $this->receipts->issue($organization, $scope, $journal);
    }

    private function resolveUnitAttribution(
        Organization $organization,
        MemberPayment $payment,
    ): ?int {
        if ($payment->collector_scope_type !== 'unit') {
            if ($payment->collector_scope_type !== 'organization') {
                throw new \InvalidArgumentException('Unsupported membership payment collector scope.');
            }

            return null;
        }

        if ($payment->collector_scope_id === null) {
            throw new \InvalidArgumentException('A unit-scoped membership payment requires a collector scope ID.');
        }

        $unit = $organization->units()->find($payment->collector_scope_id);

        if (! $unit) {
            throw new \InvalidArgumentException('The membership payment collector unit does not belong to the organization.');
        }

        return (int) $unit->id;
    }

    private function resolveDepositAccount(
        Organization $organization,
        FinanceScope $scope,
        ?string $paymentMethod,
    ): FinanceAccount {
        $code = strtolower((string) $paymentMethod) === 'cash'
            ? '1000'
            : '1100';

        return FinanceAccount::query()
            ->where('organization_id', $organization->id)
            ->where('finance_scope_id', $scope->id)
            ->where('code', $code)
            ->where('is_active', true)
            ->firstOrFail();
    }


    /**
     * Recognize inter-scope balances in the same organization-wide journal.
     * The beneficiary scope receives a receivable; the collecting scope
     * receives the corresponding payable.
     */
    private function resolveInterScopeRecognitionLines(
        Organization $organization,
        MemberPayment $payment,
        FinanceScope $scope,
    ): array {
        $payment->loadMissing('lines.allocations');

        $result = [];

        foreach ($payment->lines as $line) {
            foreach ($line->allocations as $allocation) {
                $sameScope = $payment->collector_scope_type === $allocation->scope_type
                    && (int) $payment->collector_scope_id === (int) $allocation->scope_id;

                if ($sameScope) {
                    continue;
                }

                $receivableAccount = FinanceAccount::query()
                    ->where('organization_id', $organization->id)
                    ->where('finance_scope_id', $scope->id)
                    ->where('code', '1200')
                    ->where('is_active', true)
                    ->firstOrFail();

                $payableAccount = FinanceAccount::query()
                    ->where('organization_id', $organization->id)
                    ->where('finance_scope_id', $scope->id)
                    ->where('code', '2000')
                    ->where('is_active', true)
                    ->firstOrFail();

                $amount = (string) $allocation->amount;

                $result[] = [
                    'finance_account_id' => $receivableAccount->id,
                    'description' => 'Inter-scope receivable for membership allocation',
                    'cost_center_type' => $allocation->scope_type,
                    'cost_center_id' => $allocation->scope_id,
                    'debit' => $amount,
                    'credit' => '0',
                ];

                $result[] = [
                    'finance_account_id' => $payableAccount->id,
                    'description' => 'Inter-scope payable for membership allocation',
                    'cost_center_type' => $payment->collector_scope_type,
                    'cost_center_id' => $payment->collector_scope_id,
                    'debit' => '0',
                    'credit' => $amount,
                ];
            }
        }

        return $result;
    }

    private function resolveIncomeLines(
        Organization $organization,
        MemberPayment $payment,
        FinanceScope $scope,
    ): array {
        $payment->loadMissing('lines.obligation.paymentItem');

        if ($payment->lines->isEmpty()) {
            $account = FinanceAccount::query()
                ->where('organization_id', $organization->id)
                ->where('finance_scope_id', $scope->id)
                ->where('code', '4000')
                ->where('is_active', true)
                ->firstOrFail();

            return [[
                'finance_account_id' => $account->id,
                'description' => 'Membership income',
                'debit' => '0',
                'credit' => (string) $payment->total_amount,
            ]];
        }

        $result = [];

        foreach ($payment->lines as $line) {
            $itemId = $line->obligation?->member_payment_item_id;

            $financeItem = $itemId
                ? FinanceItem::query()
                    ->where('organization_id', $organization->id)
                    ->where('source_type', 'MemberPaymentItem')
                    ->where('source_id', $itemId)
                    ->where('is_active', true)
                    ->first()
                : null;

            $accountId = $financeItem?->sales_account_id;

            if (! $accountId) {
                $accountId = FinanceAccount::query()
                    ->where('organization_id', $organization->id)
                    ->where('finance_scope_id', $scope->id)
                    ->where('code', '4000')
                    ->where('is_active', true)
                    ->value('id');
            }

            if (! $accountId) {
                throw new \RuntimeException(
                    'No membership income account is available for the payment.'
                );
            }

            $line->loadMissing('allocations');
            if ($line->allocations->isNotEmpty()) {
                foreach ($line->allocations as $allocation) {
                    $result[] = [
                        'finance_account_id' => $accountId,
                        'description' => $line->obligation?->item_title ?? 'Membership income',
                        'cost_center_type' => $allocation->scope_type,
                        'cost_center_id' => $allocation->scope_id,
                        'debit' => '0',
                        'credit' => (string) $allocation->amount,
                    ];
                }
            } else {
                $result[] = [
                    'finance_account_id' => $accountId,
                    'description' => $line->obligation?->item_title ?? 'Membership income',
                    'cost_center_type' => $payment->collector_scope_type,
                    'cost_center_id' => $payment->collector_scope_id,
                    'debit' => '0',
                    'credit' => (string) $line->amount,
                ];
            }
        }

        return $result;
    }
}
