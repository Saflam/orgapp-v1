<?php

namespace Modules\Core\Tests\Feature\Organization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Core\Services\ModuleDependencyRegistry;
use Modules\Core\Services\OrganizationModuleService;
use Tests\TestCase;

class OrganizationModuleDependencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_saradhi_registers_member_as_a_dependency(): void
    {
        $registry = app(ModuleDependencyRegistry::class);

        $this->assertSame(
            ['Member'],
            $registry->dependencies('Saradhi')
        );
    }

    public function test_saradhi_can_be_enabled_when_member_is_enabled(): void
    {
        $organization = Organization::factory()->create();

        $service = app(OrganizationModuleService::class);

        $service->enable(
            $organization,
            'Member'
        );

        $saradhi = $service->enable(
            $organization,
            'Saradhi'
        );

        $this->assertTrue($saradhi->is_enabled);

        $this->assertTrue(
            $service->isEnabled(
                $organization,
                'Member'
            )
        );

        $this->assertTrue(
            $service->isEnabled(
                $organization,
                'Saradhi'
            )
        );

        $this->assertDatabaseHas('organization_modules', [
            'organization_id' => $organization->id,
            'module' => 'Saradhi',
            'is_enabled' => true,
        ]);
    }

    public function test_saradhi_cannot_be_enabled_when_member_is_disabled(): void
    {
        $organization = Organization::factory()->create();

        $service = app(OrganizationModuleService::class);

        $this->expectException(ValidationException::class);

        $service->enable(
            $organization,
            'Saradhi'
        );

        $this->assertDatabaseMissing('organization_modules', [
            'organization_id' => $organization->id,
            'module' => 'Saradhi',
            'is_enabled' => true,
        ]);
    }

    public function test_member_cannot_be_disabled_while_saradhi_is_enabled(): void
    {
        $organization = Organization::factory()->create();

        $service = app(OrganizationModuleService::class);

        $service->enable(
            $organization,
            'Member'
        );

        $service->enable(
            $organization,
            'Saradhi'
        );

        $this->expectException(ValidationException::class);

        $service->disable(
            $organization,
            'Member'
        );

        $this->assertDatabaseHas('organization_modules', [
            'organization_id' => $organization->id,
            'module' => 'Member',
            'is_enabled' => true,
        ]);

        $this->assertDatabaseHas('organization_modules', [
            'organization_id' => $organization->id,
            'module' => 'Saradhi',
            'is_enabled' => true,
        ]);
    }

    public function test_member_can_be_disabled_when_saradhi_is_disabled(): void
    {
        $organization = Organization::factory()->create();

        $service = app(OrganizationModuleService::class);

        $service->enable(
            $organization,
            'Member'
        );

        $service->disable(
            $organization,
            'Member'
        );

        $this->assertFalse(
            $service->isEnabled(
                $organization,
                'Member'
            )
        );

        $this->assertDatabaseHas('organization_modules', [
            'organization_id' => $organization->id,
            'module' => 'Member',
            'is_enabled' => false,
        ]);
    }

    public function test_module_dependencies_are_scoped_to_each_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $service = app(OrganizationModuleService::class);

        $service->enable(
            $organizationA,
            'Member'
        );

        $service->enable(
            $organizationA,
            'Saradhi'
        );

        $this->assertTrue(
            $service->isEnabled(
                $organizationA,
                'Saradhi'
            )
        );

        $this->assertFalse(
            $service->isEnabled(
                $organizationB,
                'Member'
            )
        );

        $this->assertFalse(
            $service->isEnabled(
                $organizationB,
                'Saradhi'
            )
        );

        $this->expectException(ValidationException::class);

        $service->enable(
            $organizationB,
            'Saradhi'
        );
    }
}