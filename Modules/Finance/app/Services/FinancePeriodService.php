<?php

namespace Modules\Finance\Services;

use Carbon\CarbonImmutable;
use Modules\Core\Models\Organization;
use Modules\Finance\Enums\FinancePeriodStatus;
use Modules\Finance\Models\FinancePeriod;
use Modules\Finance\Models\FinanceScope;

class FinancePeriodService
{
    /**
     * Create a manually defined period. Existing callers retain the current
     * behavior: manually created periods are opened immediately.
     */
    public function create(
        Organization $organization,
        FinanceScope $scope,
        array $data,
    ): FinancePeriod {
        $this->assertScopeBelongsToOrganization($organization, $scope);
        $this->assertValidDateRange($data['starts_on'], $data['ends_on']);
        $this->assertNoOverlap($organization, $scope, $data['starts_on'], $data['ends_on']);

        return FinancePeriod::create([
            'organization_id' => $organization->id,
            'finance_scope_id' => $scope->id,
            'name' => $data['name'],
            'code' => strtoupper(trim($data['code'])),
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'status' => FinancePeriodStatus::OPEN,
            'metadata' => $data['metadata'] ?? [],
        ]);
    }

    /**
     * Generate a period from an organization's recurring month/day pattern.
     * Generated periods start as drafts and require an explicit open action.
     *
     * Pattern keys: start_month, start_day, end_month, end_day.
     */
    public function generateDraft(
        Organization $organization,
        FinanceScope $scope,
        int $startYear,
        array $pattern,
    ): FinancePeriod {
        $this->assertScopeBelongsToOrganization($organization, $scope);

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
        $this->assertNoOverlap($organization, $scope, $startsOn, $endsOn);

        $code = $endYear === $startYear
            ? 'FY' . $startYear
            : sprintf('FY%02d-%02d', $startYear % 100, $endYear % 100);
        $name = $endYear === $startYear
            ? 'Financial Year ' . $startYear
            : sprintf('Financial Year %d-%02d', $startYear, $endYear % 100);

        return FinancePeriod::create([
            'organization_id' => $organization->id,
            'finance_scope_id' => $scope->id,
            'name' => $name,
            'code' => $code,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'status' => FinancePeriodStatus::DRAFT,
            'metadata' => [
                'generated_from_financial_year_pattern' => true,
                'financial_year_start_year' => $startYear,
                'financial_year_pattern' => [
                    'start_month' => $startMonth,
                    'start_day' => $startDay,
                    'end_month' => $endMonth,
                    'end_day' => $endDay,
                ],
            ],
        ]);
    }

    public function open(FinancePeriod $period): FinancePeriod
    {
        if ($period->status === FinancePeriodStatus::CLOSED) {
            throw new \InvalidArgumentException('A closed financial period cannot be reopened.');
        }

        if ($period->status === FinancePeriodStatus::OPEN) {
            return $period;
        }

        $period->update([
            'status' => FinancePeriodStatus::OPEN,
            'closed_at' => null,
        ]);

        return $period->refresh();
    }

    public function update(
        Organization $organization,
        FinanceScope $scope,
        FinancePeriod $period,
        array $data,
    ): FinancePeriod {
        $this->assertScopeBelongsToOrganization($organization, $scope);

        if ($period->organization_id !== $organization->id || $period->finance_scope_id !== $scope->id) {
            throw new \InvalidArgumentException('The financial period does not belong to this organization scope.');
        }

        if (! $period->isOpen()) {
            throw new \InvalidArgumentException('Only open financial periods can be edited.');
        }

        if ($period->journals()->exists()) {
            throw new \InvalidArgumentException('A financial period with posted journals cannot be edited.');
        }

        $this->assertValidDateRange($data['starts_on'], $data['ends_on']);
        $this->assertNoOverlap($organization, $scope, $data['starts_on'], $data['ends_on'], $period->id);

        $period->update([
            'name' => $data['name'],
            'code' => strtoupper(trim($data['code'])),
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
        ]);

        return $period->refresh();
    }

    public function close(FinancePeriod $period): FinancePeriod
    {
        if ($period->isClosed()) {
            return $period;
        }

        if ($period->status === FinancePeriodStatus::DRAFT) {
            throw new \InvalidArgumentException('A draft financial period must be opened before it can be closed.');
        }

        $period->update([
            'status' => FinancePeriodStatus::CLOSED,
            'closed_at' => now(),
        ]);

        return $period->refresh();
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

    public function forDate(
        FinanceScope $scope,
        \DateTimeInterface|string $date,
    ): ?FinancePeriod {
        return FinancePeriod::query()
            ->where('organization_id', $scope->organization_id)
            ->where('finance_scope_id', $scope->id)
            ->where('status', FinancePeriodStatus::OPEN->value)
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->first();
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

    private function assertScopeBelongsToOrganization(
        Organization $organization,
        FinanceScope $scope,
    ): void {
        if ($scope->organization_id !== $organization->id) {
            throw new \InvalidArgumentException('The finance scope does not belong to the organization.');
        }
    }
}
