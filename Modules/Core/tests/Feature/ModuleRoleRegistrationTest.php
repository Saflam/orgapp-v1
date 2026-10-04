<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Services\RoleRegistry;
use Modules\Member\Providers\MemberRoleProvider;
use Tests\TestCase;

class ModuleRoleRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_module_registers_its_role_provider(): void
    {
        $registry = app(RoleRegistry::class);

        $providers = $registry->providers();

        $providerClasses = array_map(
            fn ($provider) => $provider::class,
            $providers
        );

        $this->assertContains(
            MemberRoleProvider::class,
            $providerClasses
        );
    }
}