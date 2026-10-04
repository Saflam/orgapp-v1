<?php

namespace Modules\Core\Tests\Feature\Organization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Tests\TestCase;

class OrganizationAuthorizationContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_can_be_used_as_an_authorization_context(): void
    {
        $organization = Organization::factory()->create();

        $this->assertSame(
            'organization',
            $organization->contextType()
        );

        $this->assertSame(
            $organization->id,
            $organization->contextId()
        );

        $this->assertSame(
            $organization->id,
            $organization->organizationId()
        );
    }
}