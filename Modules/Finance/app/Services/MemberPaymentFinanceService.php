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
            return $this->receiptForExistingJournal($existing);
        }

        $scope = $this->resolveScope($organization, $payment);

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

        $lines = [
            [
                'finance_account_id' => $depositAccount->id,
                'description' => 'Membership payment received',
                'debit' => (string) $payment->total_amount,
                'credit' => '0',
            ],
            ...$creditLines,
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
            if (! $this->isSourceUniquenessConflict($exception)) {
                throw $exception;
            }

            $journal = FinanceJournal::query()
                ->where('organization_id', $organization->id)
                ->where('source_type', 'member_payment')
                ->where('source_id', $payment->id)
                ->firstOrFail();
        }

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

    private function resolveScope(
        Organization $organization,
        MemberPayment $payment,
    ): FinanceScope {
        if ($payment->collector_scope_type === 'unit') {
            if ($payment->collector_scope_id === null) {
                throw new \InvalidArgumentException(
                    'A unit-scoped membership payment requires a collector scope ID.'
                );
            }

            $unit = $organization->units()->find($payment->collector_scope_id);

            if (! $unit) {
                throw new \InvalidArgumentException(
                    'The membership payment collector unit does not belong to the organization.'
                );
            }

            return $this->scopes->unitScope($organization, $unit);
        }

        if ($payment->collector_scope_type === 'organization') {
            return $this->scopes->organizationScope($organization);
        }

        throw new \InvalidArgumentException(
            'Unsupported membership payment collector scope.'
        );
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

            $result[] = [
                'finance_account_id' => $accountId,
                'description' => $line->obligation?->item_title ?? 'Membership income',
                'debit' => '0',
                'credit' => (string) $line->amount,
            ];
        }

        return $result;
    }

    private function isSourceUniquenessConflict(QueryException $exception): bool
    {
        return $exception->getCode() === '23000';
    }
}
