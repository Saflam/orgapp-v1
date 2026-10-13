<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Services\PermissionRegistry;
use Modules\Core\Services\PermissionRegistrationService;
use Tests\TestCase;

class ModulePermissionRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_module_registers_its_permission_provider(): void
    {
        $registry = app(PermissionRegistry::class);

        $providers = $registry->providers();

        $this->assertCount(4, $providers);

        $this->assertSame(
            'Modules\\Core\\Providers\\CorePermissionProvider',
            $providers[0]::class
        );

        $this->assertSame(
            'Modules\\Member\\Providers\\MemberPermissionProvider',
            $providers[1]::class
        );

        $this->assertSame(
            'Modules\\Events\\Providers\\EventsPermissionProvider',
            $providers[2]::class
        );

        $this->assertSame(
            'Modules\\Finance\\Providers\\FinancePermissionProvider',
            $providers[3]::class
        );
    }

    public function test_member_module_permissions_can_be_registered(): void
    {
        $registry = app(PermissionRegistry::class);

        $registrationService = app(PermissionRegistrationService::class);

        $registry->registerPermissions($registrationService);

        $this->assertDatabaseHas('permissions', [
            'module' => 'member',
            'code' => 'members.view',
        ]);

        $this->assertDatabaseHas('permissions', [
            'module' => 'member',
            'code' => 'members.update',
        ]);

        $this->assertDatabaseHas('permissions', [
            'module' => 'member',
            'code' => 'members.profile.view',
        ]);

        $this->assertDatabaseHas('permissions', [
            'module' => 'member',
            'code' => 'members.profile.update',
        ]);
    }
}