<?php

namespace Modules\Finance\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Organization;
use Modules\Finance\Enums\FinancePeriodStatus;
use Modules\Finance\Models\FinancePeriod;
use Modules\Finance\Models\FinanceScope;

class FinancePeriodService
{
    public function create(Organization $organization, FinanceScope $scope, array $data): FinancePeriod
    {
        return DB::transaction(function () use ($organization, $scope, $data): FinancePeriod {
            $lockedScope = $this->lockOrganizationScope($organization, $scope);
            $this->assertValidDateRange($data['starts_on'], $data['ends_on']);
            $this->assertNoOverlap($organization, $lockedScope, $data['starts_on'], $data['ends_on']);

            return FinancePeriod::create([
                'organization_id' => $organization->id,
                'finance_scope_id' => $lockedScope->id,
                'name' => $data['name'],
                'code' => strtoupper(trim($data['code'])),
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'],
                'status' => FinancePeriodStatus::OPEN,
                'metadata' => $data['metadata'] ?? [],
            ]);
        });
    }

    public function generateDraft(
        Organization $organization,
        FinanceScope $scope,
        int $startYear,
        array $pattern,
    ): FinancePeriod {
        $startMonth = (int) ($pattern['start_month'] ?? 4);
        $startDay = (int) ($pattern['start_day'] ?? 1);
        $endMonth = (int) ($pattern['end_month'] ?? 3);
        $endDay = (int) ($pattern['end_day'] ?? 31);

        if (! checkdate($startMonth, $startDay, $startYear)) {
            throw new \InvalidArgumentException('The financial year start date is not valid for the selected year.');
        }

        $startKey = ($startMonth * 100) + $startDay;
        $endKey = ($endMonth * 100) + $endDay;
        $endYear = $endKey < $startKey ? $startYear + 1 : $startYear;

        if (! checkdate($endMonth, $endDay, $endYear)) {
            throw new \InvalidArgumentException('The financial year end date is not valid for the generated year.');
        }

        $startsOn = CarbonImmutable::createFromDate($startYear, $startMonth, $startDay)->toDateString();
        $endsOn = CarbonImmutable::createFromDate($endYear, $endMonth, $endDay)->toDateString();
        $this->assertValidDateRange($startsOn, $endsOn);

        $code = $endYear === $startYear
            ? 'FY' . $startYear
            : sprintf('FY%02d-%02d', $startYear % 100, $endYear % 100);
        $name = $endYear === $startYear
            ? 'Financial Year ' . $startYear
            : sprintf('Financial Year %d-%02d', $startYear, $endYear % 100);

        return DB::transaction(function () use ($organization, $scope, $startYear, $pattern, $startsOn, $endsOn, $code, $name): FinancePeriod {
            $lockedScope = $this->lockOrganizationScope($organization, $scope);
            $this->assertNoOverlap($organization, $lockedScope, $startsOn, $endsOn);

            return FinancePeriod::create([
                'organization_id' => $organization->id,
                'finance_scope_id' => $lockedScope->id,
                'name' => $name,
                'code' => $code,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'status' => FinancePeriodStatus::DRAFT,
                'metadata' => [
                    'generated_from_financial_year_pattern' => true,
                    'financial_year_start_year' => $startYear,
                    'financial_year_pattern' => [
                        'start_month' => (int) ($pattern['start_month'] ?? 4),
                        'start_day' => (int) ($pattern['start_day'] ?? 1),
                        'end_month' => (int) ($pattern['end_month'] ?? 3),
                        'end_day' => (int) ($pattern['end_day'] ?? 31),
                    ],
                ],
            ]);
        });
    }

    public function open(FinancePeriod $period): FinancePeriod
    {
        return DB::transaction(function () use ($period): FinancePeriod {
            $lockedPeriod = FinancePeriod::query()->whereKey($period->id)->lockForUpdate()->firstOrFail();

            if ($lockedPeriod->status === FinancePeriodStatus::CLOSED) {
                throw new \InvalidArgumentException('A closed financial period cannot be reopened.');
            }
            if ($lockedPeriod->status === FinancePeriodStatus::OPEN) {
                return $lockedPeriod;
            }

            $lockedPeriod->update(['status' => FinancePeriodStatus::OPEN, 'closed_at' => null]);

            return $lockedPeriod->refresh();
        });
    }

    public function update(
        Organization $organization,
        FinanceScope $scope,
        FinancePeriod $period,
        array $data,
    ): FinancePeriod {
        return DB::transaction(function () use ($organization, $scope, $period, $data): FinancePeriod {
            // All period-range mutations lock the same scope row first. This makes
            // the overlap check and write a serialized operation per organization ledger.
            $lockedScope = $this->lockOrganizationScope($organization, $scope);
            $lockedPeriod = FinancePeriod::query()->whereKey($period->id)->lockForUpdate()->firstOrFail();

            if ((int) $lockedPeriod->organization_id !== (int) $organization->id
                || (int) $lockedPeriod->finance_scope_id !== (int) $lockedScope->id) {
                throw new \InvalidArgumentException('The financial period does not belong to this organization scope.');
            }
            if (! $lockedPeriod->isOpen()) {
                throw new \InvalidArgumentException('Only open financial periods can be edited.');
            }
            if ($lockedPeriod->journals()->exists()) {
                throw new \InvalidArgumentException('A financial period with posted journals cannot be edited.');
            }

            $this->assertValidDateRange($data['starts_on'], $data['ends_on']);
            $this->assertNoOverlap($organization, $lockedScope, $data['starts_on'], $data['ends_on'], $lockedPeriod->id);

            $lockedPeriod->update([
                'name' => $data['name'],
                'code' => strtoupper(trim($data['code'])),
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'],
            ]);

            return $lockedPeriod->refresh();
        });
    }

    public function close(FinancePeriod $period): FinancePeriod
    {
        return DB::transaction(function () use ($period): FinancePeriod {
            // Posting takes this same period-row lock before checking period status.
            $lockedPeriod = FinancePeriod::query()->whereKey($period->id)->lockForUpdate()->firstOrFail();

            if ($lockedPeriod->isClosed()) {
                return $lockedPeriod;
            }
            if ($lockedPeriod->status === FinancePeriodStatus::DRAFT) {
                throw new \InvalidArgumentException('A draft financial period must be opened before it can be closed.');
            }

            $lockedPeriod->update(['status' => FinancePeriodStatus::CLOSED, 'closed_at' => now()]);

            return $lockedPeriod->refresh();
        });
    }

    public function assertOpen(FinancePeriod $period): void
    {
        if (! $period->isOpen()) {
            throw new \InvalidArgumentException(
                $period->status === FinancePeriodStatus::DRAFT
                    ? 'The financial period has not been opened.'
                    : 'The financial period is closed.'
            );
        }
    }

    public function forDate(FinanceScope $scope, \DateTimeInterface|string $date): ?FinancePeriod
    {
        return FinancePeriod::query()
            ->where('organization_id', $scope->organization_id)
            ->where('finance_scope_id', $scope->id)
            ->where('status', FinancePeriodStatus::OPEN->value)
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->first();
    }

    private function lockOrganizationScope(Organization $organization, FinanceScope $scope): FinanceScope
    {
        $lockedScope = FinanceScope::query()->whereKey($scope->id)->lockForUpdate()->firstOrFail();

        if ((int) $lockedScope->organization_id !== (int) $organization->id) {
            throw new \InvalidArgumentException('The finance scope does not belong to the organization.');
        }
        if (! $lockedScope->isOrganizationScope()) {
            throw new \InvalidArgumentException('Financial periods must belong to the organization-wide ledger.');
        }

        return $lockedScope;
    }

    private function assertValidDateRange(string $startsOn, string $endsOn): void
    {
        if ($startsOn > $endsOn) {
            throw new \InvalidArgumentException('The financial period start date cannot be after the end date.');
        }
    }

    private function assertNoOverlap(
        Organization $organization,
        FinanceScope $scope,
        string $startsOn,
        string $endsOn,
        ?int $ignoreId = null,
    ): void {
        $query = FinancePeriod::query()
            ->where('organization_id', $organization->id)
            ->where('finance_scope_id', $scope->id)
            ->whereDate('starts_on', '<=', $endsOn)
            ->whereDate('ends_on', '>=', $startsOn);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }
        if ($query->exists()) {
            throw new \InvalidArgumentException('The financial period overlaps an existing period.');
        }
    }
}
