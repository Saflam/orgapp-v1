<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Committee\Models\Committee;
use Modules\Finance\Models\FinanceCostCenter;
use Modules\Finance\Enums\FinanceJournalStatus;
use Modules\Finance\Models\FinanceScope;

class FinanceTrialBalanceService
{
    /**
     * Generate a cumulative trial balance through the supplied date.
     *
     * A selected unit includes only journals attributed to that unit.
     * Passing null includes every unit and organization-wide journals.
     *
     * @return array{
     *     as_of_date: string,
     *     unit_id: int|null,
     *     accounts: array<int, array{
     *         account_id: int,
     *         code: string,
     *         name: string,
     *         account_type: string,
     *         debit: string,
     *         credit: string
     *     }>,
     *     debit_total: string,
     *     credit_total: string,
     *     difference: string
     * }
     */
    public function generate(
        Organization $organization,
        FinanceScope $scope,
        string $asOfDate,
        ?int $unitId = null,
        string $costCenterType = 'all',
        ?int $costCenterOwnerId = null,
    ): array {
        $this->assertScope($organization, $scope);

        // Backward compatibility: the original fourth argument selected a unit.
        if ($unitId !== null && $costCenterType === 'all') {
            $costCenterType = 'unit';
            $costCenterOwnerId = $unitId;
        }

        if (! in_array($costCenterType, ['all', 'organization', 'unit', 'committee'], true)) {
            throw new InvalidArgumentException('The selected financial cost center type is invalid.');
        }

        if ($costCenterType === 'all') {
            if ($costCenterOwnerId !== null) {
                throw new InvalidArgumentException('A cost center owner cannot be supplied when all scopes are selected.');
            }
        } elseif ($costCenterType === 'organization') {
            if ($costCenterOwnerId !== null && $costCenterOwnerId !== (int) $organization->id) {
                throw new InvalidArgumentException('The selected central cost center does not belong to the organization.');
            }
            $costCenterOwnerId = (int) $organization->id;
        } elseif ($costCenterType === 'unit') {
            if ($costCenterOwnerId === null || ! Unit::query()
                ->whereKey($costCenterOwnerId)
                ->where('organization_id', $organization->id)
                ->exists()) {
                throw new InvalidArgumentException('The selected unit does not belong to the organization.');
            }
        } elseif (! Schema::hasTable('committees') || $costCenterOwnerId === null || ! Committee::query()
            ->whereKey($costCenterOwnerId)
            ->where('organization_id', $organization->id)
            ->exists()) {
            throw new InvalidArgumentException('The selected committee does not belong to the organization.');
        }

        $activity = DB::table('finance_journal_lines as lines')
            ->join('finance_journals as journals', 'journals.id', '=', 'lines.journal_id')
            ->leftJoin('finance_cost_centers as cost_centers', 'cost_centers.id', '=', 'lines.finance_cost_center_id')
            ->select('lines.finance_account_id')
            ->selectRaw('COALESCE(SUM(lines.debit), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(lines.credit), 0) as total_credit')
            ->selectRaw('COUNT(*) as activity_count')
            ->where('journals.organization_id', $organization->id)
            ->where('journals.finance_scope_id', $scope->id)
            ->where('journals.status', FinanceJournalStatus::POSTED->value)
            ->whereDate('journals.transaction_date', '<=', $asOfDate)
            ->when($costCenterType === 'organization', function ($query) use ($organization): void {
                $query->where(function ($query) use ($organization): void {
                    $query->where(function ($query) use ($organization): void {
                        $query->where('cost_centers.owner_type', 'organization')
                            ->where('cost_centers.owner_id', $organization->id);
                    })->orWhere(function ($query): void {
                        // Legacy lines without explicit attribution inherit the journal's unit.
                        $query->whereNull('lines.finance_cost_center_id')
                            ->whereNull('journals.unit_id');
                    });
                });
            })
            ->when($costCenterType === 'unit', function ($query) use ($costCenterOwnerId): void {
                $query->where(function ($query) use ($costCenterOwnerId): void {
                    $query->where(function ($query) use ($costCenterOwnerId): void {
                        $query->where('cost_centers.owner_type', 'unit')
                            ->where('cost_centers.owner_id', $costCenterOwnerId);
                    })->orWhere(function ($query) use ($costCenterOwnerId): void {
                        $query->whereNull('lines.finance_cost_center_id')
                            ->where('journals.unit_id', $costCenterOwnerId);
                    });
                });
            })
            ->when($costCenterType === 'committee', function ($query) use ($costCenterOwnerId): void {
                $query->where('cost_centers.owner_type', 'committee')
                    ->where('cost_centers.owner_id', $costCenterOwnerId);
            })
            ->groupBy('lines.finance_account_id');

        $accounts = DB::table('finance_accounts as accounts')
            ->leftJoinSub($activity, 'activity', function ($join): void {
                $join->on('activity.finance_account_id', '=', 'accounts.id');
            })
            ->where('accounts.organization_id', $organization->id)
            ->where('accounts.finance_scope_id', $scope->id)
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
                'activity.total_debit',
                'activity.total_credit',
            ]);

        $rows = [];
        $debitTotal = '0.0000';
        $creditTotal = '0.0000';

        foreach ($accounts as $account) {
            $totalDebit = $this->normalizeAmount($account->total_debit ?? '0');
            $totalCredit = $this->normalizeAmount($account->total_credit ?? '0');

            $comparison = $this->compareAmounts($totalDebit, $totalCredit);

            if ($comparison >= 0) {
                $debit = $this->subtractAmounts($totalDebit, $totalCredit);
                $credit = '0.0000';
            } else {
                $debit = '0.0000';
                $credit = $this->subtractAmounts($totalCredit, $totalDebit);
            }

            $rows[] = [
                'account_id' => (int) $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'account_type' => $account->account_type,
                'debit' => $debit,
                'credit' => $credit,
            ];

            $debitTotal = $this->addAmounts($debitTotal, $debit);
            $creditTotal = $this->addAmounts($creditTotal, $credit);
        }

        $difference = $this->subtractSignedAmounts($debitTotal, $creditTotal);

        return [
            'as_of_date' => $asOfDate,
            'unit_id' => $costCenterType === 'unit' ? $costCenterOwnerId : null,
            'cost_center_type' => $costCenterType,
            'cost_center_owner_id' => $costCenterOwnerId,
            'accounts' => $rows,
            'debit_total' => $debitTotal,
            'credit_total' => $creditTotal,
            'difference' => $difference,
        ];
    }

    private function assertScope(Organization $organization, FinanceScope $scope): void
    {
        if ((int) $scope->organization_id !== (int) $organization->id) {
            throw new InvalidArgumentException('The finance scope does not belong to the organization.');
        }

        if (! $scope->isOrganizationScope()) {
            throw new InvalidArgumentException('Trial Balance reports must use the organization-wide ledger.');
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

    private function compareAmounts(string $left, string $right): int
    {
        [$leftWhole, $leftFraction] = explode('.', $left);
        [$rightWhole, $rightFraction] = explode('.', $right);

        $leftWhole = ltrim($leftWhole, '0') ?: '0';
        $rightWhole = ltrim($rightWhole, '0') ?: '0';

        if (strlen($leftWhole) !== strlen($rightWhole)) {
            return strlen($leftWhole) <=> strlen($rightWhole);
        }

        $wholeComparison = strcmp($leftWhole, $rightWhole);

        return $wholeComparison !== 0 ? $wholeComparison : strcmp($leftFraction, $rightFraction);
    }

    private function addAmounts(string $left, string $right): string
    {
        return $this->fromMinorUnits(
            $this->addUnsigned($this->toMinorUnits($left), $this->toMinorUnits($right))
        );
    }

    private function subtractAmounts(string $left, string $right): string
    {
        $leftUnits = $this->toMinorUnits($left);
        $rightUnits = $this->toMinorUnits($right);

        if ($this->compareUnsigned($leftUnits, $rightUnits) < 0) {
            throw new InvalidArgumentException('Cannot subtract a larger finance amount from a smaller amount.');
        }

        return $this->fromMinorUnits($this->subtractUnsigned($leftUnits, $rightUnits));
    }

    private function subtractSignedAmounts(string $left, string $right): string
    {
        $comparison = $this->compareAmounts($left, $right);

        if ($comparison >= 0) {
            return $this->subtractAmounts($left, $right);
        }

        return '-' . $this->subtractAmounts($right, $left);
    }

    private function toMinorUnits(string $amount): string
    {
        [$whole, $fraction] = explode('.', $amount);

        return ltrim($whole . $fraction, '0') ?: '0';
    }

    private function fromMinorUnits(string $units): string
    {
        $units = str_pad($units, 5, '0', STR_PAD_LEFT);

        return (ltrim(substr($units, 0, -4), '0') ?: '0') . '.' . substr($units, -4);
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
