<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Providers\CoreRoleProvider;
use Modules\Core\Services\PermissionRegistry;
use Modules\Core\Services\RoleRegistry;
use Modules\Member\Providers\MemberPermissionProvider;
use Modules\Member\Providers\MemberRoleProvider;
use Tests\TestCase;

class ModuleProviderRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_role_provider_is_registered_in_the_role_registry(): void
    {
        $registry = app(RoleRegistry::class);

        $providers = $registry->providers();

        $this->assertTrue(
            collect($providers)->contains(
                fn ($provider) => $provider instanceof CoreRoleProvider
            )
        );
    }

    public function test_member_role_provider_is_registered_in_the_role_registry(): void
    {
        $registry = app(RoleRegistry::class);

        $providers = $registry->providers();

        $this->assertTrue(
            collect($providers)->contains(
                fn ($provider) => $provider instanceof MemberRoleProvider
            )
        );
    }

    public function test_member_permission_provider_is_registered_in_the_permission_registry(): void
    {
        $registry = app(PermissionRegistry::class);

        $providers = $registry->providers();

        $this->assertTrue(
            collect($providers)->contains(
                fn ($provider) => $provider instanceof MemberPermissionProvider
            )
        );
    }
}