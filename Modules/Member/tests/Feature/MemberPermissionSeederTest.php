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
            'membership.application.edit',
            'membership.application.verify',
            'membership.application.review',
            'membership.application.approve',
            'membership.payment-item.view',
            'membership.payment-item.manage',
            'membership.payment.view',
            'membership.payment.receive',
            'membership.payment.discount',
            'membership.application.confirm',
            'member.unit-transfer.view',
            'member.unit-transfer.submit',
            'member.unit-transfer.approve',
            'member.unit-transfer.reject',
            'member.unit-transfer.complete',
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
            'membership.application.edit',
            'membership.application.verify',
            'membership.application.review',
            'membership.application.approve',
            'membership.payment-item.view',
            'membership.payment-item.manage',
            'membership.payment.view',
            'membership.payment.receive',
            'membership.payment.discount',
            'membership.application.confirm',
            'member.unit-transfer.view',
            'member.unit-transfer.submit',
            'member.unit-transfer.approve',
            'member.unit-transfer.reject',
            'member.unit-transfer.complete',
        ] as $code) {
            $this->assertSame(1, Permission::where('code', $code)->count());
        }
    }
}
