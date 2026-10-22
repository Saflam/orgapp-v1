<?php

namespace Modules\Core\Tests\Feature\Authorization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Services\AuthorizationDefinitionProvisioningService;
use Tests\TestCase;

class OrganizationSettingsPermissionRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_provisioning_registers_organization_settings_permission_and_grants_it_to_super_admin(): void
    {
        app(AuthorizationDefinitionProvisioningService::class)->provision();

        $this->assertDatabaseHas('permissions', [
            'module' => 'Core',
            'name' => 'Manage organization settings',
            'code' => 'organization.settings.manage',
        ]);

        $roleId = \DB::table('roles')
            ->whereNull('organization_id')
            ->where('code', 'super_admin')
            ->where('is_system', true)
            ->value('id');

        $permissionId = \DB::table('permissions')
            ->where('code', 'organization.settings.manage')
            ->value('id');

        $this->assertNotNull($roleId);
        $this->assertNotNull($permissionId);

        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $roleId,
            'permission_id' => $permissionId,
        ]);
    }
}
