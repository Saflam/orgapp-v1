<?php

namespace Modules\Finance\Services;

use Modules\Core\Models\Organization;
use Modules\Finance\Enums\FinancePeriodStatus;
use Modules\Finance\Models\FinancePeriod;
use Modules\Finance\Models\FinanceScope;

class FinancePeriodService
{
    public function create(
        Organization $organization,
        FinanceScope $scope,
        array $data,
    ): FinancePeriod {
        $this->assertScopeBelongsToOrganization($organization, $scope);

        $startsOn = $data['starts_on'];
        $endsOn = $data['ends_on'];

        if ($startsOn > $endsOn) {
            throw new \InvalidArgumentException(
                'The financial period start date cannot be after the end date.'
            );
        }

        $overlaps = FinancePeriod::query()
            ->where('organization_id', $organization->id)
            ->where('finance_scope_id', $scope->id)
            ->whereDate('starts_on', '<=', $endsOn)
            ->whereDate('ends_on', '>=', $startsOn)
            ->exists();

        if ($overlaps) {
            throw new \InvalidArgumentException(
                'The financial period overlaps an existing period.'
            );
        }

        return FinancePeriod::create([
            'organization_id' => $organization->id,
            'finance_scope_id' => $scope->id,
            'name' => $data['name'],
            'code' => $data['code'],
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'status' => FinancePeriodStatus::OPEN,
            'metadata' => $data['metadata'] ?? [],
        ]);
    }

    public function close(FinancePeriod $period): FinancePeriod
    {
        if ($period->isClosed()) {
            return $period;
        }

        $period->update([
            'status' => FinancePeriodStatus::CLOSED,
            'closed_at' => now(),
        ]);

        return $period->refresh();
    }

    public function assertOpen(FinancePeriod $period): void
    {
        if ($period->isClosed()) {
            throw new \InvalidArgumentException(
                'The financial period is closed.'
            );
        }
    }

    public function forDate(
        FinanceScope $scope,
        \DateTimeInterface|string $date,
    ): ?FinancePeriod {
        return FinancePeriod::query()
            ->where('finance_scope_id', $scope->id)
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->first();
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
}
