<?php

namespace Modules\Core\Tests\Feature\Authorization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Role;
use Modules\Core\Services\AuthorizationDefinitionProvisioningService;
use Tests\TestCase;

class AuthorizationDefinitionProvisioningServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_provisions_global_authorization_definitions(): void
    {
        app(AuthorizationDefinitionProvisioningService::class)
            ->provision();

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

        $this->assertDatabaseHas('roles', [
            'organization_id' => null,
            'code' => 'super_admin',
            'name' => 'Super Administrator',
            'is_system' => true,
        ]);
    }

    public function test_it_is_safe_to_provision_multiple_times(): void
    {
        $service = app(
            AuthorizationDefinitionProvisioningService::class
        );

        $service->provision();
        $service->provision();

        $this->assertSame(
            1,
            Role::query()
                ->whereNull('organization_id')
                ->where('code', 'super_admin')
                ->count()
        );

        $this->assertDatabaseCount('permissions', 11);
    }
}