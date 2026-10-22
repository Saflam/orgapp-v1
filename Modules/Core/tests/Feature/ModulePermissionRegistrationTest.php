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

        $providerClasses = array_map(
            static fn ($provider): string => $provider::class,
            $providers
        );

        $this->assertCount(5, $providerClasses);

        $this->assertEqualsCanonicalizing([
            'Modules\\Core\\Providers\\CorePermissionProvider',
            'Modules\\Member\\Providers\\MemberPermissionProvider',
            'Modules\\Events\\Providers\\EventsPermissionProvider',
            'Modules\\Finance\\Providers\\FinancePermissionProvider',
            'Modules\\Committee\\Providers\\CommitteePermissionProvider',
        ], $providerClasses);
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