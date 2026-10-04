<?php

namespace Modules\Core\Tests\Feature;

use Modules\Core\Providers\CoreRoleProvider;
use Tests\TestCase;

class CoreRoleProviderTest extends TestCase
{
    public function test_core_role_provider_returns_expected_roles(): void
    {
        $provider = new CoreRoleProvider();

        $roles = $provider->roles();

        $this->assertCount(1, $roles);

        $this->assertSame(
            'super_admin',
            $roles[0]['code']
        );

        $this->assertSame(
            'Super Administrator',
            $roles[0]['name']
        );
    }
}