<?php

namespace Modules\Finance\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Finance\Models\FinanceCostCenter;
use Modules\Finance\Services\FinanceCostCenterService;
use Tests\TestCase;

class FinanceCostCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_central_and_unit_cost_centers_are_resolved_idempotently(): void
    {
        $organization = Organization::factory()->create();
        $unit = Unit::factory()->create(['organization_id' => $organization->id]);
        $service = app(FinanceCostCenterService::class);

        $central = $service->central($organization);
        $centralAgain = $service->resolve($organization, 'organization', null);
        $unitCenter = $service->resolve($organization, 'unit', $unit->id);
        $unitAgain = $service->resolve($organization, 'unit', $unit->id);

        $this->assertSame($central->id, $centralAgain->id);
        $this->assertSame($unitCenter->id, $unitAgain->id);
        $this->assertTrue($central->isCentral());
        $this->assertSame('unit', $unitCenter->owner_type);
        $this->assertSame($unit->id, $unitCenter->owner_id);
        $this->assertSame(2, FinanceCostCenter::query()->where('organization_id', $organization->id)->count());
    }

    public function test_cost_center_cannot_be_resolved_for_another_organizations_unit(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $foreignUnit = Unit::factory()->create(['organization_id' => $otherOrganization->id]);

        $this->expectException(\InvalidArgumentException::class);

        app(FinanceCostCenterService::class)->resolve($organization, 'unit', $foreignUnit->id);
    }

    public function test_unknown_cost_center_owner_type_is_rejected(): void
    {
        $organization = Organization::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        app(FinanceCostCenterService::class)->resolve($organization, 'event_committee', 10);
    }
}
