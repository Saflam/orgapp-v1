<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Services\PermissionRegistry;
use Tests\TestCase;

class ModulePermissionRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_module_registers_its_permission_provider(): void
    {
        $registry = app(PermissionRegistry::class);

        $providers = $registry->providers();

        $this->assertCount(1, $providers);

        $this->assertSame(
            'Modules\\Member\\Providers\\MemberPermissionProvider',
            $providers[0]::class
        );
    }

    public function test_member_module_permissions_can_be_registered(): void
    {
        $registry = app(PermissionRegistry::class);

        $registrationService = app(
            \Modules\Core\Services\PermissionRegistrationService::class
        );

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