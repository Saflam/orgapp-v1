<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Finance\Enums\FinanceScopeType;
use Modules\Finance\Models\FinanceScope;

class FinanceScopeService
{
    public function organizationScope(Organization $organization): FinanceScope
    {
        return FinanceScope::firstOrCreate(
            ['organization_id' => $organization->id, 'scope_type' => FinanceScopeType::ORGANIZATION],
            ['scope_id' => $organization->id, 'name' => $organization->name, 'code' => $organization->code, 'is_active' => true, 'metadata' => []]
        );
    }

    public function unitScope(Organization $organization, Unit $unit): FinanceScope
    {
        if ($unit->organization_id !== $organization->id) {
            throw new \InvalidArgumentException('The unit does not belong to the organization.');
        }

        return FinanceScope::firstOrCreate(
            ['organization_id' => $organization->id, 'scope_type' => FinanceScopeType::UNIT, 'scope_id' => $unit->id],
            ['name' => $unit->name, 'code' => $unit->code, 'is_active' => true, 'metadata' => []]
        );
    }

    public function provisionOrganizationScopes(Organization $organization): void
    {
        DB::transaction(function () use ($organization): void {
            $this->organizationScope($organization);
            $organization->units()->where('is_active', true)->each(fn (Unit $unit) => $this->unitScope($organization, $unit));
        });
    }
}
