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

        foreach ([
            'members.view',
            'members.update',
            'members.profile.view',
            'members.profile.update',
            'membership.application.view',
            'membership.application.submit',
            'membership.application.verify',
            'membership.application.review',
            'membership.application.approve',
            'membership.application.receive-payment',
            'membership.application.confirm',
        ] as $code) {
            $this->assertDatabaseHas('permissions', [
                'module' => 'member',
                'code' => $code,
            ]);
        }
    }

    public function test_member_permission_seeder_is_idempotent(): void
    {
        $this->seed(MemberPermissionSeeder::class);
        $this->seed(MemberPermissionSeeder::class);

        foreach ([
            'members.view',
            'members.update',
            'members.profile.view',
            'members.profile.update',
            'membership.application.view',
            'membership.application.submit',
            'membership.application.verify',
            'membership.application.review',
            'membership.application.approve',
            'membership.application.receive-payment',
            'membership.application.confirm',
        ] as $code) {
            $this->assertSame(1, Permission::where('code', $code)->count());
        }
    }
}
