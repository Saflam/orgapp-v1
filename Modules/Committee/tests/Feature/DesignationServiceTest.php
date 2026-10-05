<?php

namespace Modules\Committee\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Committee\Enums\DesignationScope;
use Modules\Committee\Models\Designation;
use Modules\Committee\Services\DesignationService;
use Modules\Core\Models\Organization;
use Tests\TestCase;

class DesignationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_designation(): void
    {
        $organization = Organization::factory()->create();

        $service = app(DesignationService::class);

        $designation = $service->create(
            organizationId: $organization->id,
            name: 'Treasurer',
            code: 'TREASURER',
            scope: DesignationScope::CENTRAL,
            description: 'Responsible for committee finances.',
        );

        $this->assertDatabaseHas('designations', [
            'id' => $designation->id,
            'scope' => DesignationScope::CENTRAL->value,
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);
    }

    public function test_it_creates_a_unit_scoped_designation(): void
    {
        $organization = Organization::factory()->create();

        $designation = app(DesignationService::class)->create(
            organizationId: $organization->id,
            name: 'Unit President',
            code: 'UNIT_PRESIDENT',
            scope: DesignationScope::UNIT,
        );

        $this->assertSame(
            DesignationScope::UNIT,
            $designation->scope,
        );

        $this->assertDatabaseHas('designations', [
            'id' => $designation->id,
            'scope' => DesignationScope::UNIT->value,
        ]);
    }

    public function test_it_updates_a_designation(): void
    {
        $organization = Organization::factory()->create();

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'description' => null,
        ]);

        $service = app(DesignationService::class);

        $updated = $service->update(
            designation: $designation,
            name: 'Chief Treasurer',
            description: 'Updated description.',
        );

        $this->assertSame(
            'Chief Treasurer',
            $updated->name
        );

        $this->assertSame(
            'TREASURER',
            $updated->code
        );
    }

    public function test_it_can_deactivate_a_designation(): void
    {
        $organization = Organization::factory()->create();

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $service = app(DesignationService::class);

        $service->deactivate($designation);

        $this->assertFalse(
            $designation->refresh()->is_active
        );
    }

    public function test_it_can_activate_a_designation(): void
    {
        $organization = Organization::factory()->create();

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => false,
        ]);

        $service = app(DesignationService::class);

        $service->activate($designation);

        $this->assertTrue(
            $designation->refresh()->is_active
        );
    }
}