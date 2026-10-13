<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Models\Organization;
use Modules\Finance\Enums\FinanceJournalStatus;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceJournal;
use Modules\Finance\Models\FinancePeriod;
use Modules\Finance\Models\FinanceScope;

class FinanceJournalService
{
    public function post(
        Organization $organization,
        FinanceScope $scope,
        FinancePeriod $period,
        array $data,
    ): FinanceJournal {
        $this->assertScopeBelongsToOrganization($organization, $scope);
        $this->assertPeriodBelongsToScope($period, $scope, $organization);
        app(FinancePeriodService::class)->assertOpen($period);

        $transactionDate = $data['transaction_date'];

        if ($transactionDate < $period->starts_on->toDateString()
            || $transactionDate > $period->ends_on->toDateString()) {
            throw new \InvalidArgumentException(
                'The journal transaction date is outside the financial period.'
            );
        }

        $lines = $data['lines'] ?? [];

        if (count($lines) < 2) {
            throw new \InvalidArgumentException(
                'A journal must contain at least two lines.'
            );
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

        return DB::transaction(function () use (
            $organization,
            $scope,
            $period,
            $data,
            $transactionDate,
            $preparedLines,
        ): FinanceJournal {
            $journal = FinanceJournal::create([
                'organization_id' => $organization->id,
                'finance_scope_id' => $scope->id,
                'finance_period_id' => $period->id,
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

    private function prepareLine(
        Organization $organization,
        FinanceScope $scope,
        array $line,
    ): array {
        $accountId = $line['finance_account_id'] ?? null;
        $account = FinanceAccount::query()->find($accountId);

        if (!$account) {
            throw new \InvalidArgumentException(
                'The journal account does not exist.'
            );
        }

        if ($account->organization_id !== $organization->id
            || $account->finance_scope_id !== $scope->id) {
            throw new \InvalidArgumentException(
                'The journal account does not belong to the finance scope.'
            );
        }

        if (!$account->is_active) {
            throw new \InvalidArgumentException(
                'The journal account is inactive.'
            );
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

        return [
            'finance_account_id' => $account->id,
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

        if (!is_string($value) || !preg_match('/^\d+(?:\.\d{1,4})?$/', $value)) {
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
            throw new \InvalidArgumentException(
                'The finance scope does not belong to the organization.'
            );
        }
    }

    private function assertPeriodBelongsToScope(
        FinancePeriod $period,
        FinanceScope $scope,
        Organization $organization,
    ): void {
        if ($period->organization_id !== $organization->id
            || $period->finance_scope_id !== $scope->id) {
            throw new \InvalidArgumentException(
                'The financial period does not belong to the finance scope.'
            );
        }
    }
}
