<?php

namespace Modules\Committee\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Modules\Committee\Models\Designation;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Organization;
use Tests\TestCase;

class DesignationPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_designation_belongs_to_an_organization(): void
    {
        $organization = Organization::factory()->create();

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'description' => 'Committee treasurer',
            'is_active' => true,
        ]);

        $this->assertTrue(
            $designation->organization->is($organization)
        );
    }

    public function test_two_organizations_can_have_same_designation_code(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $designationA = Designation::create([
            'organization_id' => $organizationA->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $designationB = Designation::create([
            'organization_id' => $organizationB->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $this->assertNotSame(
            $designationA->id,
            $designationB->id
        );
    }

    public function test_same_organization_cannot_have_duplicate_designation_code(): void
    {
        $organization = Organization::factory()->create();

        Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $this->expectException(QueryException::class);

        Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Another Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);
    }

    public function test_inactive_designation_can_exist(): void
    {
        $organization = Organization::factory()->create();

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Former Treasurer',
            'code' => 'FORMER_TREASURER',
            'is_active' => false,
        ]);

        $this->assertFalse($designation->is_active);
    }

    public function test_permission_code_is_globally_unique(): void
    {
        Permission::create([
            'module' => 'finance',
            'name' => 'View Accounts',
            'code' => 'finance.accounts.view',
        ]);

        $this->expectException(QueryException::class);

        Permission::create([
            'module' => 'committee',
            'name' => 'Another Permission',
            'code' => 'finance.accounts.view',
        ]);
    }

    public function test_permissions_can_belong_to_same_module(): void
    {
        $view = Permission::create([
            'module' => 'finance',
            'name' => 'View Accounts',
            'code' => 'finance.accounts.view',
        ]);

        $create = Permission::create([
            'module' => 'finance',
            'name' => 'Create Transactions',
            'code' => 'finance.transactions.create',
        ]);

        $this->assertSame('finance', $view->module);
        $this->assertSame('finance', $create->module);
    }

    public function test_designation_can_have_multiple_permissions(): void
    {
        $organization = Organization::factory()->create();

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $view = Permission::create([
            'module' => 'finance',
            'name' => 'View Accounts',
            'code' => 'finance.accounts.view',
        ]);

        $create = Permission::create([
            'module' => 'finance',
            'name' => 'Create Transactions',
            'code' => 'finance.transactions.create',
        ]);

        $designation->permissions()->attach([
            $view->id,
            $create->id,
        ]);

        $this->assertCount(2, $designation->fresh()->permissions);
    }

    public function test_permission_can_belong_to_multiple_designations(): void
    {
        $organization = Organization::factory()->create();

        $treasurer = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $secretary = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Secretary',
            'code' => 'SECRETARY',
            'is_active' => true,
        ]);

        $permission = Permission::create([
            'module' => 'finance',
            'name' => 'View Accounts',
            'code' => 'finance.accounts.view',
        ]);

        $treasurer->permissions()->attach($permission->id);
        $secretary->permissions()->attach($permission->id);

        $this->assertCount(
            2,
            Designation::query()
                ->whereHas('permissions', function ($query) use ($permission) {
                    $query->whereKey($permission->id);
                })
                ->get()
        );
    }

    public function test_same_designation_permission_pair_cannot_be_assigned_twice(): void
    {
        $organization = Organization::factory()->create();

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $permission = Permission::create([
            'module' => 'finance',
            'name' => 'View Accounts',
            'code' => 'finance.accounts.view',
        ]);

        $designation->permissions()->attach($permission->id);

        $this->expectException(QueryException::class);

        $designation->permissions()->attach($permission->id);
    }

    public function test_removing_permission_does_not_delete_designation_or_permission(): void
    {
        $organization = Organization::factory()->create();

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $permission = Permission::create([
            'module' => 'finance',
            'name' => 'View Accounts',
            'code' => 'finance.accounts.view',
        ]);

        $designation->permissions()->attach($permission->id);

        $designation->permissions()->detach($permission->id);

        $this->assertDatabaseHas('designations', [
            'id' => $designation->id,
        ]);

        $this->assertDatabaseHas('permissions', [
            'id' => $permission->id,
        ]);

        $this->assertDatabaseMissing('designation_permissions', [
            'designation_id' => $designation->id,
            'permission_id' => $permission->id,
        ]);
    }
}