<?php

namespace Modules\Member\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Tests\TestCase;

class UnitAuthorizationContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_unit_can_be_used_as_an_authorization_context(): void
    {
        $organization = Organization::factory()->create();

        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $this->assertSame(
            'unit',
            $unit->contextType()
        );

        $this->assertSame(
            $unit->id,
            $unit->contextId()
        );

        $this->assertSame(
            $organization->id,
            $unit->organizationId()
        );
    }
}