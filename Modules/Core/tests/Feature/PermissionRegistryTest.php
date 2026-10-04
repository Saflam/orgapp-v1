<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Contracts\PermissionProvider;
use Modules\Core\Services\PermissionRegistrationService;
use Modules\Core\Services\PermissionRegistry;
use Tests\TestCase;

class PermissionRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_register_multiple_permission_providers(): void
    {
        $registry = new PermissionRegistry();

        $registry->registerProvider(
            $this->provider('member', 'members.view')
        );

        $registry->registerProvider(
            $this->provider('finance', 'finance.view')
        );

        $this->assertCount(
            2,
            $registry->providers()
        );
    }

    public function test_it_registers_permissions_from_all_providers(): void
    {
        $registry = new PermissionRegistry();

        $registry->registerProvider(
            $this->provider('member', 'members.view')
        );

        $registry->registerProvider(
            $this->provider('finance', 'finance.view')
        );

        $registrationService = app(
            PermissionRegistrationService::class
        );

        $registry->registerPermissions(
            $registrationService
        );

        $this->assertDatabaseHas('permissions', [
            'module' => 'member',
            'code' => 'members.view',
        ]);

        $this->assertDatabaseHas('permissions', [
            'module' => 'finance',
            'code' => 'finance.view',
        ]);
    }

    private function provider(
        string $module,
        string $code
    ): PermissionProvider {
        return new class($module, $code) implements PermissionProvider {
            public function __construct(
                private string $module,
                private string $code,
            ) {
            }

            public function permissions(): array
            {
                return [
                    [
                        'module' => $this->module,
                        'name' => 'Test Permission',
                        'code' => $this->code,
                        'description' => null,
                    ],
                ];
            }
        };
    }
}