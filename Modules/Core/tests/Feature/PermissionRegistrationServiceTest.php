<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Services\PermissionRegistrationService;
use Modules\Member\Providers\MemberPermissionProvider;
use Tests\TestCase;

class PermissionRegistrationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_registers_permissions_from_a_provider(): void
    {
        $service = app(PermissionRegistrationService::class);

        $service->register(
            new MemberPermissionProvider()
        );

        $this->assertDatabaseHas('permissions', [
            'module' => 'member',
            'code' => 'members.view',
            'name' => 'View Members',
        ]);

        $this->assertDatabaseHas('permissions', [
            'module' => 'member',
            'code' => 'members.update',
            'name' => 'Update Members',
        ]);
    }

    public function test_registering_the_same_provider_twice_does_not_create_duplicates(): void
    {
        $service = app(PermissionRegistrationService::class);

        $provider = new MemberPermissionProvider();

        $service->register($provider);
        $service->register($provider);

        $this->assertDatabaseCount('permissions', 4);
    }
}