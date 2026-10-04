<?php

namespace Modules\Core\Tests\Feature\Authorization;

use Modules\Core\Contracts\AuthorizationContext;
use Tests\TestCase;

class AuthorizationContextTest extends TestCase
{
    public function test_it_defines_a_generic_authorization_context(): void
    {
        $context = new class implements AuthorizationContext {
            public function contextType(): string
            {
                return 'event';
            }

            public function contextId(): int|string
            {
                return 42;
            }

            public function organizationId(): int
            {
                return 1;
            }
        };

        $this->assertSame('event', $context->contextType());
        $this->assertSame(42, $context->contextId());
        $this->assertSame(1, $context->organizationId());
    }
}