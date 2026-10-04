<?php

namespace Modules\Core\Tests\Feature\Organization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationSettingsService;
use Tests\TestCase;

class OrganizationSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_store_and_retrieve_a_setting(): void
    {
        $organization = Organization::factory()->create();

        $service = app(OrganizationSettingsService::class);

        $service->set(
            organization: $organization,
            key: 'timezone',
            value: 'Asia/Kuwait',
        );

        $this->assertSame(
            'Asia/Kuwait',
            $service->get(
                organization: $organization,
                key: 'timezone',
            )
        );
    }

    public function test_it_returns_default_when_setting_does_not_exist(): void
    {
        $organization = Organization::factory()->create();

        $service = app(OrganizationSettingsService::class);

        $this->assertSame(
            'UTC',
            $service->get(
                organization: $organization,
                key: 'timezone',
                default: 'UTC',
            )
        );
    }

    public function test_setting_can_be_updated(): void
    {
        $organization = Organization::factory()->create();

        $service = app(OrganizationSettingsService::class);

        $service->set(
            organization: $organization,
            key: 'timezone',
            value: 'Asia/Kuwait',
        );

        $service->set(
            organization: $organization,
            key: 'timezone',
            value: 'Asia/Dubai',
        );

        $this->assertSame(
            'Asia/Dubai',
            $service->get(
                organization: $organization,
                key: 'timezone',
            )
        );
    }
}