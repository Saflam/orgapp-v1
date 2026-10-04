<?php

namespace Modules\Core\Tests\Feature;

use Modules\Core\Services\RoleRegistry;
use Tests\TestCase;

class RoleServiceProviderTest extends TestCase
{
    public function test_role_registry_is_registered_in_the_container(): void
    {
        $registry = app(RoleRegistry::class);

        $this->assertInstanceOf(
            RoleRegistry::class,
            $registry
        );

        $this->assertSame(
            $registry,
            app(RoleRegistry::class)
        );
    }
}