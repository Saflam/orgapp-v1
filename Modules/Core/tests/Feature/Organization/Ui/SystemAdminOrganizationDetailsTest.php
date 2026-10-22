<?php

namespace Modules\Core\Tests\Feature\Organization\Ui;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Livewire\Organizations\OrganizationManage;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Role;
use Modules\Core\Models\RoleAssignment;
use Modules\Core\Services\AuthorizationDefinitionProvisioningService;
use Tests\TestCase;

class SystemAdminOrganizationDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(AuthorizationDefinitionProvisioningService::class)->provision();
    }

    public function test_system_admin_can_update_organization_name_timezone_and_currency(): void
    {
        $admin = User::factory()->create(['is_system_admin' => true]);
        $organization = Organization::factory()->create([
            'name' => 'Old Organization',
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
        ]);

        Livewire::actingAs($admin)
            ->test(OrganizationManage::class, ['organization' => $organization])
            ->set('name', 'Updated Organization')
            ->set('timezone', 'Europe/London')
            ->set('currency', 'gbp')
            ->call('saveOrganizationDetails')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'name' => 'Updated Organization',
            'timezone' => 'Europe/London',
            'currency' => 'GBP',
        ]);
    }

    public function test_system_admin_can_update_current_organization_super_admin_details(): void
    {
        $admin = User::factory()->create(['is_system_admin' => true]);
        $organization = Organization::factory()->create();
        $superAdmin = User::factory()->create(['name' => 'Current Admin', 'email' => 'current-admin@example.test']);

        $membershipId = \Illuminate\Support\Facades\DB::table('organization_memberships')->insertGetId([
            'organization_id' => $organization->id,
            'user_id' => $superAdmin->id,
            'status' => 'active',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $role = Role::query()->whereNull('organization_id')->where('code', 'super_admin')->first();
        if (! $role) {
            $role = Role::create([
                'organization_id' => null,
                'name' => 'Super Administrator',
                'code' => 'super_admin',
                'description' => 'Organization Super Admin',
                'is_system' => true,
            ]);
        }
        RoleAssignment::create([
            'organization_membership_id' => $membershipId,
            'role_id' => $role->id,
            'context_type' => 'organization',
            'context_id' => $organization->id,
            'starts_at' => now()->subMinute(),
            'ends_at' => null,
        ]);

        Livewire::actingAs($admin)
            ->test(OrganizationManage::class, ['organization' => $organization])
            ->set('superAdminName', 'Updated Organization Admin')
            ->set('superAdminEmail', 'updated-admin@example.test')
            ->call('saveSuperAdminDetails')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $superAdmin->id,
            'name' => 'Updated Organization Admin',
            'email' => 'updated-admin@example.test',
        ]);
    }


    public function test_system_admin_can_update_organization_theme(): void
    {
        $admin = User::factory()->create(['is_system_admin' => true]);
        $organization = Organization::factory()->create();

        Livewire::actingAs($admin)
            ->test(OrganizationManage::class, ['organization' => $organization])
            ->set('theme', 'dark')
            ->call('saveTheme')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('organization_settings', [
            'organization_id' => $organization->id,
            'key' => 'ui.theme',
            'value' => 'dark',
        ]);
    }

    public function test_non_system_admin_cannot_update_organization_details(): void
    {
        $user = User::factory()->create(['is_system_admin' => false]);
        $organization = Organization::factory()->create();

        Livewire::actingAs($user)
            ->test(OrganizationManage::class, ['organization' => $organization])
            ->assertForbidden();
    }
}
