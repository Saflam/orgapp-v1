<?php

namespace Modules\Core\Tests\Feature\Organization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationModuleService;
use Tests\TestCase;

class OrganizationModuleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_enable_a_module(): void
    {
        $organization = Organization::factory()->create();

        $service = app(OrganizationModuleService::class);

        $module = $service->enable(
            $organization,
            'Member',
        );

        $this->assertSame(
            $organization->id,
            $module->organization_id
        );

        $this->assertSame(
            'Member',
            $module->module
        );

        $this->assertTrue(
            $module->is_enabled
        );

        $this->assertTrue(
            $service->isEnabled(
                $organization,
                'Member'
            )
        );
    }

    public function test_it_can_disable_a_module(): void
    {
        $organization = Organization::factory()->create();

        $service = app(OrganizationModuleService::class);

        $service->enable(
            $organization,
            'Member',
        );

        $service->disable(
            $organization,
            'Member',
        );

        $this->assertFalse(
            $service->isEnabled(
                $organization,
                'Member'
            )
        );
    }

    public function test_modules_are_isolated_between_organizations(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $service = app(OrganizationModuleService::class);

        $service->enable(
            $organizationA,
            'Member',
        );

        $this->assertTrue(
            $service->isEnabled(
                $organizationA,
                'Member'
            )
        );

        $this->assertFalse(
            $service->isEnabled(
                $organizationB,
                'Member'
            )
        );
    }
}