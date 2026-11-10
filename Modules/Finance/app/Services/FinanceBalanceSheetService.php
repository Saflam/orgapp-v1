<?php

namespace Modules\Finance\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Finance\Enums\FinanceJournalStatus;
use Modules\Finance\Models\FinanceScope;

class FinanceBalanceSheetService
{
    /**
     * Generate a cumulative Balance Sheet through the supplied as-of date.
     *
     * Current earnings are calculated as cumulative income less cumulative
     * expenses and included in total liabilities and equity. Equity account
     * balances remain separately identifiable in equity_accounts/total_equity.
     *
     * @return array{
     *     as_of_date:string,
     *     unit_id:int|null,
     *     asset_accounts:array<int, array{account_id:int,code:string,name:string,amount:string}>,
     *     liability_accounts:array<int, array{account_id:int,code:string,name:string,amount:string}>,
     *     equity_accounts:array<int, array{account_id:int,code:string,name:string,amount:string}>,
     *     current_earnings:array{amount:string},
     *     total_assets:string,
     *     total_liabilities:string,
     *     total_equity:string,
     *     total_liabilities_and_equity:string,
     *     difference:string
     * }
     */
    public function generate(
        Organization $organization,
        FinanceScope $scope,
        string $asOfDate,
        ?int $unitId = null,
    ): array {
        $this->assertScope($organization, $scope);
        $this->assertDate($asOfDate);

        if ($unitId !== null && ! Unit::query()
            ->whereKey($unitId)
            ->where('organization_id', $organization->id)
            ->exists()) {
            throw new InvalidArgumentException('The selected unit does not belong to the organization.');
        }

        $activity = DB::table('finance_journal_lines as lines')
            ->join('finance_journals as journals', 'journals.id', '=', 'lines.journal_id')
            ->select('lines.finance_account_id')
            ->selectRaw('COALESCE(SUM(lines.debit), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(lines.credit), 0) as total_credit')
            ->selectRaw('COUNT(*) as activity_count')
            ->where('journals.organization_id', $organization->id)
            ->where('journals.finance_scope_id', $scope->id)
            ->where('journals.status', FinanceJournalStatus::POSTED->value)
            ->whereDate('journals.transaction_date', '<=', $asOfDate)
            ->when($unitId !== null, fn ($query) => $query->where('journals.unit_id', $unitId))
            ->groupBy('lines.finance_account_id');

        $accounts = DB::table('finance_accounts as accounts')
            ->leftJoinSub($activity, 'activity', function ($join): void {
                $join->on('activity.finance_account_id', '=', 'accounts.id');
            })
            ->where('accounts.organization_id', $organization->id)
            ->where('accounts.finance_scope_id', $scope->id)
            ->whereIn('accounts.account_type', ['asset', 'liability', 'equity', 'income', 'expense'])
            ->where(function ($query): void {
                $query->where('accounts.is_active', true)
                    ->orWhere('activity.activity_count', '>', 0);
            })
            ->orderBy('accounts.code')
            ->get([
                'accounts.id',
                'accounts.code',
                'accounts.name',
                'accounts.account_type',
                'accounts.normal_balance',
                'activity.total_debit',
                'activity.total_credit',
            ]);

        $assetRows = [];
        $liabilityRows = [];
        $equityRows = [];
        $totalAssets = '0.0000';
        $totalLiabilities = '0.0000';
        $totalEquity = '0.0000';
        $totalIncome = '0.0000';
        $totalExpenses = '0.0000';

        foreach ($accounts as $account) {
            $debit = $this->normalizeAmount($account->total_debit ?? '0');
            $credit = $this->normalizeAmount($account->total_credit ?? '0');

            $amount = (string) $account->normal_balance === 'credit'
                ? $this->subtractSigned($credit, $debit)
                : $this->subtractSigned($debit, $credit);

            $row = [
                'account_id' => (int) $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'amount' => $amount,
            ];

            switch ($account->account_type) {
                case 'asset':
                    $assetRows[] = $row;
                    $totalAssets = $this->addSigned($totalAssets, $amount);
                    break;

                case 'liability':
                    $liabilityRows[] = $row;
                    $totalLiabilities = $this->addSigned($totalLiabilities, $amount);
                    break;

                case 'equity':
                    $equityRows[] = $row;
                    $totalEquity = $this->addSigned($totalEquity, $amount);
                    break;

                case 'income':
                    $totalIncome = $this->addSigned($totalIncome, $amount);
                    break;

                case 'expense':
                    $totalExpenses = $this->addSigned($totalExpenses, $amount);
                    break;
            }
        }

        $currentEarnings = $this->subtractSigned($totalIncome, $totalExpenses);
        $liabilitiesAndEquity = $this->addSigned(
            $this->addSigned($totalLiabilities, $totalEquity),
            $currentEarnings,
        );

        return [
            'as_of_date' => $asOfDate,
            'unit_id' => $unitId,
            'asset_accounts' => $assetRows,
            'liability_accounts' => $liabilityRows,
            'equity_accounts' => $equityRows,
            'current_earnings' => [
                'amount' => $currentEarnings,
            ],
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'total_equity' => $totalEquity,
            'total_liabilities_and_equity' => $liabilitiesAndEquity,
            'difference' => $this->subtractSigned($totalAssets, $liabilitiesAndEquity),
        ];
    }

    private function assertScope(Organization $organization, FinanceScope $scope): void
    {
        if ((int) $scope->organization_id !== (int) $organization->id) {
            throw new InvalidArgumentException('The finance scope does not belong to the organization.');
        }

        if (! $scope->isOrganizationScope()) {
            throw new InvalidArgumentException('Balance Sheet reports must use the organization-wide ledger.');
        }
    }

    private function assertDate(string $date): void
    {
        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date);
        } catch (\Throwable) {
            $parsed = false;
        }

        if (! $parsed || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Balance Sheet date must use the YYYY-MM-DD format.');
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
        return $this->fromUnits(
            $this->addSignedUnits($this->toUnits($left), $this->toUnits($right))
        );
    }

    private function subtractSigned(string $left, string $right): string
    {
        $rightUnits = $this->toUnits($right);
        if ($rightUnits !== '0') {
            $rightUnits = str_starts_with($rightUnits, '-')
                ? substr($rightUnits, 1)
                : '-' . $rightUnits;
        }

        return $this->fromUnits(
            $this->addSignedUnits($this->toUnits($left), $rightUnits)
        );
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
