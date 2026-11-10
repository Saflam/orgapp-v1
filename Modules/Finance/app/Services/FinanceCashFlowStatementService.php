<?php

namespace Modules\Finance\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Finance\Enums\FinanceJournalStatus;
use Modules\Finance\Models\FinanceScope;

class FinanceCashFlowStatementService
{
    /**
     * Generate a direct-method Cash Flow Statement for posted journals in an
     * inclusive date range. Cash movement is classified by the category on
     * each non-cash counterpart account.
     *
     * @return array{
     *   start_date:string,
     *   end_date:string,
     *   unit_id:int|null,
     *   operating_net_cash_flow:string,
     *   investing_net_cash_flow:string,
     *   financing_net_cash_flow:string,
     *   net_change_in_cash:string,
     *   opening_cash_balance:string,
     *   closing_cash_balance:string
     * }
     */
    public function generate(
        Organization $organization,
        FinanceScope $scope,
        string $startDate,
        string $endDate,
        ?int $unitId = null,
    ): array {
        $this->assertScope($organization, $scope);
        $this->assertDateRange($startDate, $endDate);

        if ($unitId !== null && ! Unit::query()
            ->whereKey($unitId)
            ->where('organization_id', $organization->id)
            ->exists()) {
            throw new InvalidArgumentException('The selected unit does not belong to the organization.');
        }

        $cashAccountIds = DB::table('finance_accounts')
            ->where('organization_id', $organization->id)
            ->where('finance_scope_id', $scope->id)
            ->where('is_cash_account', true)
            ->pluck('id');

        $totals = [
            'operating' => '0.0000',
            'investing' => '0.0000',
            'financing' => '0.0000',
        ];

        if ($cashAccountIds->isNotEmpty()) {
            // In a balanced journal, the signed credit-minus-debit movement
            // on non-cash counterpart lines equals the cash debit-minus-credit
            // movement. Cash-to-cash transfers have no non-cash counterpart
            // and therefore contribute nothing to the three categories.
            $cashJournalIds = DB::table('finance_journals as cash_journals')
                ->select('cash_journals.id')
                ->where('cash_journals.organization_id', $organization->id)
                ->where('cash_journals.finance_scope_id', $scope->id)
                ->where('cash_journals.status', FinanceJournalStatus::POSTED->value)
                ->whereDate('cash_journals.transaction_date', '>=', $startDate)
                ->whereDate('cash_journals.transaction_date', '<=', $endDate)
                ->when($unitId !== null, fn ($query) => $query->where('cash_journals.unit_id', $unitId))
                ->whereExists(function ($query) use ($cashAccountIds): void {
                    $query->selectRaw('1')
                        ->from('finance_journal_lines as cash_lines')
                        ->whereColumn('cash_lines.journal_id', 'cash_journals.id')
                        ->whereIn('cash_lines.finance_account_id', $cashAccountIds);
                });

            $hasUnclassifiedCounterpart = DB::table('finance_journal_lines as counterpart_lines')
                ->join('finance_journals as counterpart_journals', 'counterpart_journals.id', '=', 'counterpart_lines.journal_id')
                ->join('finance_accounts as counterpart_accounts', 'counterpart_accounts.id', '=', 'counterpart_lines.finance_account_id')
                ->whereIn('counterpart_journals.id', $cashJournalIds)
                ->whereNotIn('counterpart_lines.finance_account_id', $cashAccountIds)
                ->where(function ($query): void {
                    $query->whereNull('counterpart_accounts.cash_flow_category')
                        ->orWhereNotIn('counterpart_accounts.cash_flow_category', ['operating', 'investing', 'financing']);
                })
                ->exists();

            if ($hasUnclassifiedCounterpart) {
                throw new InvalidArgumentException(
                    'Every non-cash account in a cash journal must have an operating, investing, or financing cash flow category.'
                );
            }

            $movements = DB::table('finance_journal_lines as lines')
                ->join('finance_journals as journals', 'journals.id', '=', 'lines.journal_id')
                ->join('finance_accounts as accounts', 'accounts.id', '=', 'lines.finance_account_id')
                ->whereIn('journals.id', $cashJournalIds)
                ->where('journals.organization_id', $organization->id)
                ->where('journals.finance_scope_id', $scope->id)
                ->where('journals.status', FinanceJournalStatus::POSTED->value)
                ->whereDate('journals.transaction_date', '>=', $startDate)
                ->whereDate('journals.transaction_date', '<=', $endDate)
                ->when($unitId !== null, fn ($query) => $query->where('journals.unit_id', $unitId))
                ->whereNotIn('lines.finance_account_id', $cashAccountIds)
                ->whereIn('accounts.cash_flow_category', ['operating', 'investing', 'financing'])
                ->select('accounts.cash_flow_category')
                ->selectRaw('COALESCE(SUM(lines.credit), 0) as total_credit')
                ->selectRaw('COALESCE(SUM(lines.debit), 0) as total_debit')
                ->groupBy('accounts.cash_flow_category')
                ->get();

            foreach ($movements as $movement) {
                $category = (string) $movement->cash_flow_category;
                $credit = $this->normalizeAmount($movement->total_credit);
                $debit = $this->normalizeAmount($movement->total_debit);
                $totals[$category] = $this->subtractSigned($credit, $debit);
            }
        }

        $netChange = $this->addSigned(
            $this->addSigned($totals['operating'], $totals['investing']),
            $totals['financing'],
        );

        $openingCash = $this->openingCashBalance(
            $organization,
            $scope,
            $cashAccountIds,
            $startDate,
            $unitId,
        );

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'unit_id' => $unitId,
            'operating_net_cash_flow' => $totals['operating'],
            'investing_net_cash_flow' => $totals['investing'],
            'financing_net_cash_flow' => $totals['financing'],
            'net_change_in_cash' => $netChange,
            'opening_cash_balance' => $openingCash,
            'closing_cash_balance' => $this->addSigned($openingCash, $netChange),
        ];
    }

    private function openingCashBalance(
        Organization $organization,
        FinanceScope $scope,
        $cashAccountIds,
        string $startDate,
        ?int $unitId,
    ): string {
        if ($cashAccountIds->isEmpty()) {
            return '0.0000';
        }

        $movement = DB::table('finance_journal_lines as lines')
            ->join('finance_journals as journals', 'journals.id', '=', 'lines.journal_id')
            ->where('journals.organization_id', $organization->id)
            ->where('journals.finance_scope_id', $scope->id)
            ->where('journals.status', FinanceJournalStatus::POSTED->value)
            ->whereDate('journals.transaction_date', '<', $startDate)
            ->when($unitId !== null, fn ($query) => $query->where('journals.unit_id', $unitId))
            ->whereIn('lines.finance_account_id', $cashAccountIds)
            ->selectRaw('COALESCE(SUM(lines.debit), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(lines.credit), 0) as total_credit')
            ->first();

        return $this->subtractSigned(
            $this->normalizeAmount($movement->total_debit ?? '0'),
            $this->normalizeAmount($movement->total_credit ?? '0'),
        );
    }

    private function assertScope(Organization $organization, FinanceScope $scope): void
    {
        if ((int) $scope->organization_id !== (int) $organization->id) {
            throw new InvalidArgumentException('The finance scope does not belong to the organization.');
        }

        if (! $scope->isOrganizationScope()) {
            throw new InvalidArgumentException('Cash Flow Statement reports must use the organization-wide ledger.');
        }
    }

    private function assertDateRange(string $startDate, string $endDate): void
    {
        foreach ([$startDate, $endDate] as $date) {
            try {
                $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date);
            } catch (\Throwable) {
                $parsed = false;
            }

            if (! $parsed || $parsed->format('Y-m-d') !== $date) {
                throw new InvalidArgumentException('Cash Flow Statement dates must use the YYYY-MM-DD format.');
            }
        }

        if ($startDate > $endDate) {
            throw new InvalidArgumentException('The Cash Flow Statement start date must not be after its end date.');
        }
    }

    private function normalizeAmount(mixed $amount): string
    {
        $value = (string) ($amount ?? '0');

        if (! preg_match('/^\d+(?:\.\d{1,4})?$/', $value)) {
            throw new InvalidArgumentException('A finance amount has an invalid decimal representation.');
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return (ltrim($whole, '0') ?: '0') . '.' . str_pad($fraction, 4, '0');
    }

    private function toUnits(string $amount): string
    {
        $negative = str_starts_with($amount, '-');
        $unsigned = $negative ? substr($amount, 1) : $amount;
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $units = ltrim($whole . str_pad($fraction, 4, '0'), '0') ?: '0';

        return $negative && $units !== '0' ? '-' . $units : $units;
    }

    private function fromUnits(string $units): string
    {
        $negative = str_starts_with($units, '-');
        $digits = $negative ? substr($units, 1) : $units;
        $digits = str_pad($digits, 5, '0', STR_PAD_LEFT);
        $whole = ltrim(substr($digits, 0, -4), '0') ?: '0';
        $fraction = substr($digits, -4);

        return ($negative && ($whole !== '0' || $fraction !== '0000') ? '-' : '')
            . $whole . '.' . $fraction;
    }

    private function addSigned(string $left, string $right): string
    {
        return $this->fromUnits($this->addSignedUnits($this->toUnits($left), $this->toUnits($right)));
    }

    private function subtractSigned(string $left, string $right): string
    {
        $rightUnits = $this->toUnits($right);
        if ($rightUnits !== '0') {
            $rightUnits = str_starts_with($rightUnits, '-')
                ? substr($rightUnits, 1)
                : '-' . $rightUnits;
        }

        return $this->fromUnits($this->addSignedUnits($this->toUnits($left), $rightUnits));
    }

    private function addSignedUnits(string $left, string $right): string
    {
        $leftNegative = str_starts_with($left, '-');
        $rightNegative = str_starts_with($right, '-');
        $leftAbs = $leftNegative ? substr($left, 1) : $left;
        $rightAbs = $rightNegative ? substr($right, 1) : $right;

        if ($leftNegative === $rightNegative) {
            $sum = $this->addUnsigned($leftAbs, $rightAbs);
            return $leftNegative && $sum !== '0' ? '-' . $sum : $sum;
        }

        $comparison = $this->compareUnsigned($leftAbs, $rightAbs);
        if ($comparison === 0) {
            return '0';
        }
        if ($comparison > 0) {
            $difference = $this->subtractUnsigned($leftAbs, $rightAbs);
            return $leftNegative ? '-' . $difference : $difference;
        }

        $difference = $this->subtractUnsigned($rightAbs, $leftAbs);
        return $rightNegative ? '-' . $difference : $difference;
    }

    private function addUnsigned(string $left, string $right): string
    {
        $length = max(strlen($left), strlen($right));
        $left = str_pad($left, $length, '0', STR_PAD_LEFT);
        $right = str_pad($right, $length, '0', STR_PAD_LEFT);
        $carry = 0;
        $result = '';

        for ($index = $length - 1; $index >= 0; $index--) {
            $sum = (int) $left[$index] + (int) $right[$index] + $carry;
            $result = ($sum % 10) . $result;
            $carry = intdiv($sum, 10);
        }

        if ($carry > 0) {
            $result = $carry . $result;
        }

        return ltrim($result, '0') ?: '0';
    }

    private function subtractUnsigned(string $left, string $right): string
    {
        $length = strlen($left);
        $right = str_pad($right, $length, '0', STR_PAD_LEFT);
        $borrow = 0;
        $result = '';

        for ($index = $length - 1; $index >= 0; $index--) {
            $digit = (int) $left[$index] - (int) $right[$index] - $borrow;
            if ($digit < 0) {
                $digit += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }
            $result = $digit . $result;
        }

        return ltrim($result, '0') ?: '0';
    }

    private function compareUnsigned(string $left, string $right): int
    {
        $left = ltrim($left, '0') ?: '0';
        $right = ltrim($right, '0') ?: '0';

        if (strlen($left) !== strlen($right)) {
            return strlen($left) <=> strlen($right);
        }

        return strcmp($left, $right);
    }
}
