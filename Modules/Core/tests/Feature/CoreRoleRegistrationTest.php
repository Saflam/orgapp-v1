<?php

namespace Modules\Core\Tests\Feature;

use Modules\Core\Providers\CoreRoleProvider;
use Modules\Core\Services\RoleRegistry;
use Tests\TestCase;

class CoreRoleRegistrationTest extends TestCase
{
    public function test_core_module_registers_its_role_provider(): void
    {
        $registry = app(RoleRegistry::class);

        $providers = $registry->providers();

        $coreProvider = collect($providers)
            ->first(
                fn ($provider) => $provider instanceof CoreRoleProvider
            );

        $this->assertNotNull($coreProvider);
    }
}