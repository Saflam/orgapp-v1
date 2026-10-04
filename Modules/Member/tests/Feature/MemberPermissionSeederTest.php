<?php

namespace Modules\Member\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Permission;
use Modules\Member\Database\Seeders\MemberPermissionSeeder;
use Tests\TestCase;

class MemberPermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_permissions_are_registered(): void
    {
        $this->seed(MemberPermissionSeeder::class);

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

    public function test_member_permission_seeder_is_idempotent(): void
    {
        $this->seed(MemberPermissionSeeder::class);
        $this->seed(MemberPermissionSeeder::class);

        $this->assertSame(
            1,
            Permission::where('code', 'members.view')->count()
        );

        $this->assertSame(
            1,
            Permission::where('code', 'members.update')->count()
        );

        $this->assertSame(
            1,
            Permission::where('code', 'members.profile.view')->count()
        );

        $this->assertSame(
            1,
            Permission::where('code', 'members.profile.update')->count()
        );
    }
}