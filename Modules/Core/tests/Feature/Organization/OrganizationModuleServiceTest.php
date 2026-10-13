<?php

namespace Modules\Core\Tests\Feature\Organization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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

    public function test_it_can_disable_an_optional_module(): void
    {
        $organization = Organization::factory()->create();

        $service = app(OrganizationModuleService::class);

        $service->enable(
            $organization,
            'Member',
        );

        $service->enable(
            $organization,
            'Saradhi',
        );

        $service->disable(
            $organization,
            'Saradhi',
        );

        $this->assertFalse(
            $service->isEnabled(
                $organization,
                'Saradhi'
            )
        );
    }

    public function test_it_cannot_disable_a_required_module(): void
    {
        $organization = Organization::factory()->create();

        $service = app(OrganizationModuleService::class);

        $service->enable(
            $organization,
            'Member',
        );

        $this->expectException(ValidationException::class);

        $service->disable(
            $organization,
            'Member'
        );

        $this->assertTrue(
            $service->isEnabled(
                $organization,
                'Member'
            )
        );
    }


    public function test_optional_modules_can_be_enabled_for_one_organization_without_enabling_them_for_another(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $service = app(OrganizationModuleService::class);

        // Factory-created organizations bypass OrganizationService, so initialize
        // their required modules explicitly before enabling dependent modules.
        foreach ([$organizationA, $organizationB] as $organization) {
            $service->enable($organization, 'Core');
            $service->enable($organization, 'Member');
        }

        $service->enable($organizationA, 'Saradhi');
        $service->disable($organizationB, 'Saradhi');

        $this->assertTrue($service->isEnabled($organizationA, 'Saradhi'));
        $this->assertFalse($service->isEnabled($organizationB, 'Saradhi'));
    }

    public function test_required_modules_are_reported_by_the_service(): void
    {
        $service = app(OrganizationModuleService::class);

        $this->assertSame(['Core', 'Member'], $service->requiredModules());
        $this->assertTrue($service->isRequired('Core'));
        $this->assertTrue($service->isRequired('Member'));
        $this->assertFalse($service->isRequired('Saradhi'));
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
