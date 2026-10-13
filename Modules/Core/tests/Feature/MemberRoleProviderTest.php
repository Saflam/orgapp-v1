<?php

namespace Modules\Core\Tests\Feature;

use Modules\Member\Providers\MemberRoleProvider;
use Tests\TestCase;

class MemberRoleProviderTest extends TestCase
{
    public function test_member_role_provider_returns_expected_roles(): void
    {
        $provider = new MemberRoleProvider();

        $roles = $provider->roles();

        $this->assertCount(3, $roles);

        $this->assertSame(
            [
                'organization_admin',
                'unit_transfer_approver',
                'unit_transfer_coordinator',
            ],
            array_column($roles, 'code')
        );

        $this->assertSame(
            'Organization Administrator',
            $roles[0]['name']
        );
    }
}