<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Models\Organization;
use Modules\Finance\Enums\FinanceJournalStatus;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceJournal;
use Modules\Finance\Models\FinancePeriod;
use Modules\Finance\Models\FinanceCostCenter;
use Modules\Finance\Services\FinanceCostCenterService;
use Modules\Finance\Models\FinanceScope;

class FinanceJournalService
{
    public function post(
        Organization $organization,
        FinanceScope $scope,
        FinancePeriod $period,
        array $data,
    ): FinanceJournal {
        return DB::transaction(function () use ($organization, $scope, $period, $data): FinanceJournal {
            $this->assertScopeBelongsToOrganization($organization, $scope);

            if (! $scope->isOrganizationScope()) {
                throw new \InvalidArgumentException('Finance journals must use the organization-wide ledger.');
            }

            // Always lock and validate the current database row, not a potentially stale model.
            $lockedPeriod = FinancePeriod::query()
                ->whereKey($period->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertPeriodBelongsToScope($lockedPeriod, $scope, $organization);
            app(FinancePeriodService::class)->assertOpen($lockedPeriod);

            $transactionDate = $data['transaction_date'];
            if ($transactionDate < $lockedPeriod->starts_on->toDateString()
                || $transactionDate > $lockedPeriod->ends_on->toDateString()) {
                throw new \InvalidArgumentException(
                    'The journal transaction date is outside the financial period.'
                );
            }

            $unitId = $data['unit_id'] ?? null;
            if ($unitId !== null && ! $organization->units()->whereKey($unitId)->exists()) {
                throw new \InvalidArgumentException('The journal unit does not belong to the organization.');
            }

            $lines = $data['lines'] ?? [];
            if (count($lines) < 2) {
                throw new \InvalidArgumentException('A journal must contain at least two lines.');
            }

            $preparedLines = [];
            $totalDebit = '0.0000';
            $totalCredit = '0.0000';

            foreach ($lines as $line) {
                $prepared = $this->prepareLine($organization, $scope, $line);
                $totalDebit = $this->addDecimals($totalDebit, $prepared['debit']);
                $totalCredit = $this->addDecimals($totalCredit, $prepared['credit']);
                $preparedLines[] = $prepared;
            }

            if ($this->compareDecimals($totalDebit, $totalCredit) !== 0) {
                throw new \InvalidArgumentException(
                    'The journal is not balanced. Total debits must equal total credits.'
                );
            }

            $journal = FinanceJournal::create([
                'organization_id' => $organization->id,
                'finance_scope_id' => $scope->id,
                'finance_period_id' => $lockedPeriod->id,
                'unit_id' => $unitId,
                'journal_number' => $data['journal_number'] ?? $this->generateJournalNumber(),
                'transaction_date' => $transactionDate,
                'description' => $data['description'] ?? null,
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'status' => FinanceJournalStatus::POSTED,
                'metadata' => $data['metadata'] ?? [],
            ]);

            foreach ($preparedLines as $line) {
                $journal->lines()->create($line);
            }

            return $journal->load('lines');
        });
    }

    /**
     * Create a posted, balanced reversal in an open period.
     *
     * The original journal row is locked first so concurrent attempts serialize.
     * The unique reversal_of_journal_id index is the final database-level guard
     * against more than one reversal for the same original journal.
     */
    public function reverse(
        Organization $organization,
        FinanceScope $scope,
        FinanceJournal $journal,
        FinancePeriod $reversalPeriod,
        string $transactionDate,
        string $reason,
    ): FinanceJournal {
        return DB::transaction(function () use (
            $organization,
            $scope,
            $journal,
            $reversalPeriod,
            $transactionDate,
            $reason,
        ): FinanceJournal {
            $this->assertScopeBelongsToOrganization($organization, $scope);

            if (! $scope->isOrganizationScope()) {
                throw new \InvalidArgumentException('Finance journals must use the organization-wide ledger.');
            }

            $original = FinanceJournal::query()
                ->whereKey($journal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $original->organization_id !== (int) $organization->id
                || (int) $original->finance_scope_id !== (int) $scope->id) {
                throw new \InvalidArgumentException('The journal does not belong to the finance scope.');
            }

            if ($original->status !== FinanceJournalStatus::POSTED) {
                throw new \InvalidArgumentException('Only posted journals can be reversed.');
            }

            if ($original->reversal_of_journal_id !== null) {
                throw new \InvalidArgumentException('A reversal journal cannot itself be reversed.');
            }

            if (FinanceJournal::query()->where('reversal_of_journal_id', $original->id)->exists()) {
                throw new \InvalidArgumentException('This journal has already been reversed.');
            }

            $lockedPeriod = FinancePeriod::query()
                ->whereKey($reversalPeriod->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertPeriodBelongsToScope($lockedPeriod, $scope, $organization);
            app(FinancePeriodService::class)->assertOpen($lockedPeriod);

            if ($transactionDate < $lockedPeriod->starts_on->toDateString()
                || $transactionDate > $lockedPeriod->ends_on->toDateString()) {
                throw new \InvalidArgumentException(
                    'The reversal transaction date is outside the financial period.'
                );
            }

            $reason = trim($reason);
            if ($reason === '') {
                throw new \InvalidArgumentException('A reversal reason is required.');
            }

            $originalLines = $original->lines()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($originalLines->count() < 2) {
                throw new \InvalidArgumentException('The original journal does not contain valid journal lines.');
            }

            $reversal = FinanceJournal::create([
                'organization_id' => $organization->id,
                'finance_scope_id' => $scope->id,
                'finance_period_id' => $lockedPeriod->id,
                'unit_id' => $original->unit_id,
                'journal_number' => $this->generateJournalNumber(),
                'transaction_date' => $transactionDate,
                'description' => 'Reversal of ' . $original->journal_number,
                'reversal_reason' => $reason,
                'reversal_of_journal_id' => $original->id,
                'source_type' => 'journal_reversal',
                'source_id' => $original->id,
                'status' => FinanceJournalStatus::POSTED,
                'metadata' => [
                    'reversal_of_journal_id' => $original->id,
                    'original_journal_number' => $original->journal_number,
                ],
            ]);

            foreach ($originalLines as $line) {
                $reversal->lines()->create([
                    'finance_account_id' => $line->finance_account_id,
                    'finance_cost_center_id' => $line->finance_cost_center_id,
                    'description' => $line->description,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'metadata' => $line->metadata ?? [],
                ]);
            }

            return $reversal->load('lines');
        });
    }

    private function prepareLine(
        Organization $organization,
        FinanceScope $scope,
        array $line,
    ): array {
        // Called only inside post()'s transaction. This serializes posting against
        // account deactivation, which locks the same account row.
        $accountId = $line['finance_account_id'] ?? null;
        $account = FinanceAccount::query()
            ->whereKey($accountId)
            ->lockForUpdate()
            ->first();

        if (! $account) {
            throw new \InvalidArgumentException('The journal account does not exist.');
        }

        if ($account->organization_id !== $organization->id
            || $account->finance_scope_id !== $scope->id) {
            throw new \InvalidArgumentException('The journal account does not belong to the finance scope.');
        }

        if (! $account->is_active) {
            throw new \InvalidArgumentException('The journal account is inactive.');
        }

        $debit = $this->normalizeAmount($line['debit'] ?? 0);
        $credit = $this->normalizeAmount($line['credit'] ?? 0);

        $hasDebit = $this->compareDecimals($debit, '0.0000') > 0;
        $hasCredit = $this->compareDecimals($credit, '0.0000') > 0;

        if ($hasDebit === $hasCredit) {
            throw new \InvalidArgumentException(
                'Each journal line must contain either a debit or a credit amount.'
            );
        }

        $costCenterId = $line['finance_cost_center_id'] ?? null;
        if ($costCenterId !== null) {
            $costCenter = FinanceCostCenter::query()
                ->whereKey($costCenterId)
                ->where('organization_id', $organization->id)
                ->where('is_active', true)
                ->first();

            if (! $costCenter) {
                throw new \InvalidArgumentException('The journal cost center is inactive or does not belong to the organization.');
            }
        } elseif (isset($line['cost_center_type'])) {
            $costCenter = app(FinanceCostCenterService::class)->resolve(
                $organization,
                (string) $line['cost_center_type'],
                isset($line['cost_center_id']) ? (int) $line['cost_center_id'] : null,
            );
            $costCenterId = $costCenter->id;
        }

        return [
            'finance_account_id' => $account->id,
            'finance_cost_center_id' => $costCenterId,
            'description' => $line['description'] ?? null,
            'debit' => $debit,
            'credit' => $credit,
            'metadata' => $line['metadata'] ?? [],
        ];
    }

    private function normalizeAmount(mixed $value): string
    {
        if (is_int($value)) {
            $value = (string) $value;
        }

        if (is_float($value)) {
            $value = number_format($value, 4, '.', '');
        }

        if (! is_string($value) || ! preg_match('/^\d+(?:\.\d{1,4})?$/', $value)) {
            throw new \InvalidArgumentException(
                'Journal amounts must be non-negative decimal numbers with up to four decimal places.'
            );
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return (ltrim($whole, '0') ?: '0') . '.' . str_pad($fraction, 4, '0');
    }

    private function addDecimals(string $left, string $right): string
    {
        [$leftWhole, $leftFraction] = explode('.', $left);
        [$rightWhole, $rightFraction] = explode('.', $right);

        $leftDigits = ltrim($leftWhole . $leftFraction, '0') ?: '0';
        $rightDigits = ltrim($rightWhole . $rightFraction, '0') ?: '0';

        $leftDigits = str_pad($leftDigits, max(strlen($leftDigits), strlen($rightDigits)), '0', STR_PAD_LEFT);
        $rightDigits = str_pad($rightDigits, strlen($leftDigits), '0', STR_PAD_LEFT);

        $carry = 0;
        $result = '';

        for ($i = strlen($leftDigits) - 1; $i >= 0; $i--) {
            $sum = (int) $leftDigits[$i] + (int) $rightDigits[$i] + $carry;
            $result = ($sum % 10) . $result;
            $carry = intdiv($sum, 10);
        }

        if ($carry > 0) {
            $result = $carry . $result;
        }

        $result = str_pad($result, 5, '0', STR_PAD_LEFT);
        $whole = substr($result, 0, -4);
        $fraction = substr($result, -4);

        return (ltrim($whole, '0') ?: '0') . '.' . $fraction;
    }

    private function compareDecimals(string $left, string $right): int
    {
        [$leftWhole, $leftFraction] = explode('.', $left);
        [$rightWhole, $rightFraction] = explode('.', $right);

        $leftWhole = ltrim($leftWhole, '0') ?: '0';
        $rightWhole = ltrim($rightWhole, '0') ?: '0';

        if (strlen($leftWhole) !== strlen($rightWhole)) {
            return strlen($leftWhole) <=> strlen($rightWhole);
        }

        if ($leftWhole !== $rightWhole) {
            return $leftWhole <=> $rightWhole;
        }

        return $leftFraction <=> $rightFraction;
    }

    private function generateJournalNumber(): string
    {
        return 'JRN-' . strtoupper((string) Str::ulid());
    }

    private function assertScopeBelongsToOrganization(
        Organization $organization,
        FinanceScope $scope,
    ): void {
        if ($scope->organization_id !== $organization->id) {
            throw new \InvalidArgumentException('The finance scope does not belong to the organization.');
        }
    }

    private function assertPeriodBelongsToScope(
        FinancePeriod $period,
        FinanceScope $scope,
        Organization $organization,
    ): void {
        $periodScope = $period->scope;

        if ($period->organization_id !== $organization->id
            || $period->finance_scope_id !== $scope->id
            || ! $periodScope
            || $periodScope->organization_id !== $organization->id
            || ! $periodScope->isOrganizationScope()
            || $scope->organization_id !== $organization->id) {
            throw new \InvalidArgumentException(
                'The financial period must be an organization-level period for the same organization and finance scope.'
            );
        }
    }
}
