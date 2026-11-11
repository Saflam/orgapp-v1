<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceJournal;
use Modules\Finance\Models\FinanceSettlement;
use Modules\Finance\Models\FinanceSettlementAudit;
use Modules\Finance\Models\FinanceSettlementObligation;
use Modules\Member\Models\MemberPayment;

class FinanceSettlementService
{
    public function __construct(
        private readonly FinanceScopeService $scopes,
        private readonly FinancePeriodService $periods,
        private readonly FinanceJournalService $journals,
        private readonly FinanceAccountProvisioningService $accounts,
    ) {}

    /**
     * Snapshot the actual collected allocation amounts as inter-scope obligations.
     * The collector is the debtor; each different beneficiary is the creditor.
     */
    public function createObligationsForPayment(MemberPayment $payment): void
    {
        $payment->loadMissing('organization', 'lines.allocations');
        $organization = $payment->organization;

        if (! $organization instanceof Organization) {
            throw new \InvalidArgumentException('The membership payment organization is unavailable.');
        }

        $debtor = $this->normalizeScope(
            $organization,
            $payment->collector_scope_type,
            $payment->collector_scope_id,
        );

        DB::transaction(function () use ($payment, $organization, $debtor): void {
            foreach ($payment->lines as $line) {
                $grouped = [];
                foreach ($line->allocations as $allocation) {
                    $creditor = $this->normalizeScope($organization, $allocation->scope_type, $allocation->scope_id);
                    if ($this->sameScope($debtor, $creditor)) {
                        continue;
                    }

                    $key = $creditor['type'] . ':' . ($creditor['id'] ?? 'null');
                    $grouped[$key] ??= ['scope' => $creditor, 'amount' => 0];
                    $grouped[$key]['amount'] += $this->toMinorUnits((string) $allocation->amount);
                }

                foreach ($grouped as $item) {
                    if ($item['amount'] <= 0) {
                        continue;
                    }

                    $scope = $item['scope'];
                    FinanceSettlementObligation::query()->firstOrCreate(
                        [
                            'member_payment_line_id' => $line->id,
                            'creditor_scope_type' => $scope['type'],
                            'creditor_scope_id' => $scope['id'],
                        ],
                        [
                            'organization_id' => $organization->id,
                            'debtor_scope_type' => $debtor['type'],
                            'debtor_scope_id' => $debtor['id'],
                            'member_payment_id' => $payment->id,
                            'original_amount' => $this->fromMinorUnits($item['amount']),
                            'outstanding_amount' => $this->fromMinorUnits($item['amount']),
                            'currency' => $payment->currency,
                            'status' => 'outstanding',
                            'transaction_date' => ($payment->received_at ?? now())->toDateString(),
                            'metadata' => [
                                'receipt_number' => $payment->receipt_number,
                                'payment_line_id' => $line->id,
                            ],
                        ],
                    );
                }
            }
        });
    }

    public function record(
        Organization $organization,
        FinanceSettlementObligation $obligation,
        string $amount,
        string $transactionDate,
        int $payerCashAccountId,
        int $payeeCashAccountId,
        ?int $actorUserId = null,
        ?string $reference = null,
    ): FinanceSettlement {
        return DB::transaction(function () use (
            $organization, $obligation, $amount, $transactionDate,
            $payerCashAccountId, $payeeCashAccountId, $actorUserId, $reference,
        ): FinanceSettlement {
            $locked = FinanceSettlementObligation::query()
                ->whereKey($obligation->id)
                ->where('organization_id', $organization->id)
                ->lockForUpdate()
                ->firstOrFail();

            $debtorScope = $this->normalizeScope($organization, $locked->debtor_scope_type, $locked->debtor_scope_id);
            $creditorScope = $this->normalizeScope($organization, $locked->creditor_scope_type, $locked->creditor_scope_id);
            if ($this->sameScope($debtorScope, $creditorScope)) {
                throw new \InvalidArgumentException('A settlement obligation must have different debtor and creditor scopes.');
            }

            $minor = $this->toMinorUnits($amount);
            if ($minor <= 0) {
                throw ValidationException::withMessages(['amount' => 'Settlement amount must be greater than zero.']);
            }

            $pending = FinanceSettlement::query()
                ->where('finance_settlement_obligation_id', $locked->id)
                ->where('status', 'pending_confirmation')
                ->sum('amount');
            $available = $this->toMinorUnits((string) $locked->outstanding_amount)
                - $this->toMinorUnits((string) $pending);

            if ($minor > $available) {
                throw ValidationException::withMessages(['amount' => 'Settlement amount exceeds the unreserved outstanding balance.']);
            }

            $scope = $this->scopes->organizationScope($organization);
            // Settlement accounting also requires the system-managed AR/AP accounts.
            // Ensure defaults exist even when the organization has not yet recorded
            // a membership payment through MemberPaymentFinanceService.
            $this->accounts->provisionDefaults($organization, $scope);

            $payer = $this->cashAccount($organization, $scope->id, $payerCashAccountId);
            $payee = $this->cashAccount($organization, $scope->id, $payeeCashAccountId);
            if ($payer->id === $payee->id) {
                throw ValidationException::withMessages(['payee_cash_account_id' => 'Payer and payee cash accounts must be different.']);
            }

            $period = $this->periods->forDate($scope, $transactionDate);
            if (! $period) {
                throw ValidationException::withMessages(['transaction_date' => 'No open financial period exists for the settlement date.']);
            }

            $settlement = FinanceSettlement::create([
                'organization_id' => $organization->id,
                'finance_settlement_obligation_id' => $locked->id,
                'amount' => $this->fromMinorUnits($minor),
                'transaction_date' => $transactionDate,
                'payer_cash_account_id' => $payer->id,
                'payee_cash_account_id' => $payee->id,
                'status' => 'pending_confirmation',
                'reference' => $reference,
                'recorded_by' => $actorUserId,
            ]);

            $payableAccount = FinanceAccount::query()
                ->where('organization_id', $organization->id)
                ->where('finance_scope_id', $scope->id)
                ->where('code', '2000')
                ->where('is_active', true)
                ->firstOrFail();

            $receivableAccount = FinanceAccount::query()
                ->where('organization_id', $organization->id)
                ->where('finance_scope_id', $scope->id)
                ->where('code', '1200')
                ->where('is_active', true)
                ->firstOrFail();

            $journal = $this->journals->post($organization, $scope, $period, [
                'transaction_date' => $transactionDate,
                'description' => 'Inter-scope settlement #' . $settlement->id,
                'source_type' => 'finance_settlement',
                'source_id' => $settlement->id,
                'unit_id' => $locked->debtor_scope_type === 'unit' ? $locked->debtor_scope_id : null,
                'metadata' => [
                    'settlement_id' => $settlement->id,
                    'obligation_id' => $locked->id,
                    'debtor_scope' => ['type' => $locked->debtor_scope_type, 'id' => $locked->debtor_scope_id],
                    'creditor_scope' => ['type' => $locked->creditor_scope_type, 'id' => $locked->creditor_scope_id],
                ],
                'lines' => [
                    [
                        'finance_account_id' => $payableAccount->id,
                        'description' => 'Clear inter-scope payable',
                        'cost_center_type' => $locked->debtor_scope_type,
                        'cost_center_id' => $locked->debtor_scope_id,
                        'debit' => $this->fromMinorUnits($minor),
                        'credit' => '0',
                    ],
                    [
                        'finance_account_id' => $payee->id,
                        'description' => 'Settlement received into cash/bank account',
                        'cost_center_type' => $locked->creditor_scope_type,
                        'cost_center_id' => $locked->creditor_scope_id,
                        'debit' => $this->fromMinorUnits($minor),
                        'credit' => '0',
                    ],
                    [
                        'finance_account_id' => $payer->id,
                        'description' => 'Settlement paid from cash/bank account',
                        'cost_center_type' => $locked->debtor_scope_type,
                        'cost_center_id' => $locked->debtor_scope_id,
                        'debit' => '0',
                        'credit' => $this->fromMinorUnits($minor),
                    ],
                    [
                        'finance_account_id' => $receivableAccount->id,
                        'description' => 'Clear inter-scope receivable',
                        'cost_center_type' => $locked->creditor_scope_type,
                        'cost_center_id' => $locked->creditor_scope_id,
                        'debit' => '0',
                        'credit' => $this->fromMinorUnits($minor),
                    ],
                ],
            ]);

            $settlement->update(['finance_journal_id' => $journal->id]);
            $this->audit($settlement, $actorUserId, 'recorded', null, $settlement->fresh()->toArray());

            return $settlement->fresh(['obligation', 'journal', 'payerCashAccount', 'payeeCashAccount']);
        });
    }

    public function confirm(
        Organization $organization,
        FinanceSettlement $settlement,
        ?int $actorUserId = null,
    ): FinanceSettlement {
        return DB::transaction(function () use ($organization, $settlement, $actorUserId): FinanceSettlement {
            $locked = FinanceSettlement::query()
                ->whereKey($settlement->id)
                ->where('organization_id', $organization->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'pending_confirmation') {
                throw new \InvalidArgumentException('Only pending settlements can be confirmed.');
            }

            $obligation = FinanceSettlementObligation::query()
                ->whereKey($locked->finance_settlement_obligation_id)
                ->lockForUpdate()
                ->firstOrFail();

            $remaining = $this->toMinorUnits((string) $obligation->outstanding_amount)
                - $this->toMinorUnits((string) $locked->amount);
            if ($remaining < 0) {
                throw new \LogicException('Settlement confirmation would make the obligation balance negative.');
            }

            $before = $locked->toArray();
            $obligation->update([
                'outstanding_amount' => $this->fromMinorUnits($remaining),
                'status' => $remaining === 0 ? 'settled' : 'partially_settled',
            ]);
            $locked->update([
                'status' => 'confirmed',
                'confirmed_by' => $actorUserId,
                'confirmed_at' => now(),
            ]);
            $this->audit($locked, $actorUserId, 'confirmed', $before, $locked->fresh()->toArray());

            return $locked->fresh(['obligation', 'journal']);
        });
    }

    public function reject(
        Organization $organization,
        FinanceSettlement $settlement,
        string $reason,
        ?int $actorUserId = null,
    ): FinanceSettlement {
        return DB::transaction(function () use ($organization, $settlement, $reason, $actorUserId): FinanceSettlement {
            $locked = FinanceSettlement::query()
                ->whereKey($settlement->id)
                ->where('organization_id', $organization->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'pending_confirmation') {
                throw new \InvalidArgumentException('Only pending settlements can be rejected.');
            }
            $reason = trim($reason);
            if ($reason === '') {
                throw ValidationException::withMessages(['reason' => 'A rejection reason is required.']);
            }

            $scope = $this->scopes->organizationScope($organization);
            $reversalDate = now()->toDateString();
            $period = $this->periods->forDate($scope, $reversalDate);
            if (! $period) {
                throw new \InvalidArgumentException('No open financial period exists for the reversal date.');
            }

            $journal = FinanceJournal::query()
                ->whereKey($locked->finance_journal_id)
                ->where('organization_id', $organization->id)
                ->firstOrFail();

            $before = $locked->toArray();
            $this->journals->reverse(
                $organization,
                $scope,
                $journal,
                $period,
                $reversalDate,
                'Rejected settlement #' . $locked->id . ': ' . $reason,
            );

            $locked->update([
                'status' => 'rejected',
                'rejected_by' => $actorUserId,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);
            $this->audit($locked, $actorUserId, 'rejected', $before, $locked->fresh()->toArray(), $reason);

            return $locked->fresh(['obligation', 'journal']);
        });
    }

    public function outstandingForScope(Organization $organization, string $scopeType, ?int $scopeId = null)
    {
        $scope = $this->normalizeScope($organization, $scopeType, $scopeId);

        return FinanceSettlementObligation::query()
            ->where('organization_id', $organization->id)
            ->where(function ($query) use ($scope): void {
                $query->where(function ($q) use ($scope): void {
                    $q->where('debtor_scope_type', $scope['type'])->where('debtor_scope_id', $scope['id']);
                })->orWhere(function ($q) use ($scope): void {
                    $q->where('creditor_scope_type', $scope['type'])->where('creditor_scope_id', $scope['id']);
                });
            })
            ->where('outstanding_amount', '>', 0)
            ->with(['payment', 'paymentLine', 'settlements'])
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();
    }

    private function cashAccount(Organization $organization, int $scopeId, int $accountId): FinanceAccount
    {
        $account = FinanceAccount::query()
            ->whereKey($accountId)
            ->where('organization_id', $organization->id)
            ->where('finance_scope_id', $scopeId)
            ->where('is_active', true)
            ->where('is_cash_account', true)
            ->first();

        if (! $account) {
            throw ValidationException::withMessages(['cash_account' => 'Select an active cash/bank account belonging to this organization.']);
        }

        return $account;
    }

    private function normalizeScope(Organization $organization, string $type, ?int $id): array
    {
        if ($type === 'organization') {
            if ($id !== null && $id !== (int) $organization->id) {
                throw new \InvalidArgumentException('The organization settlement scope is invalid.');
            }
            return ['type' => 'organization', 'id' => (int) $organization->id];
        }

        if ($type === 'unit' && $id !== null
            && $organization->units()->whereKey($id)->exists()) {
            return ['type' => 'unit', 'id' => (int) $id];
        }

        throw new \InvalidArgumentException('Settlement scope must be this organization or one of its units.');
    }

    private function sameScope(array $left, array $right): bool
    {
        return $left['type'] === $right['type'] && $left['id'] === $right['id'];
    }

    private function audit(
        FinanceSettlement $settlement,
        ?int $actorUserId,
        string $action,
        ?array $before,
        ?array $after,
        ?string $notes = null,
    ): void {
        FinanceSettlementAudit::create([
            'finance_settlement_id' => $settlement->id,
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'before_state' => $before,
            'after_state' => $after,
            'notes' => $notes,
        ]);
    }

    private function toMinorUnits(string $amount): int
    {
        $amount = trim($amount);
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
            throw ValidationException::withMessages(['amount' => 'Amounts must be non-negative values with up to two decimal places.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function fromMinorUnits(int $minor): string
    {
        return intdiv($minor, 100) . '.' . str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }
}
