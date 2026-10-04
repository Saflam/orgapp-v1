<?php

namespace Modules\Committee\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Committee\Enums\CommitteeKind;
use Modules\Committee\Enums\CommitteeStatus;
use Modules\Committee\Models\Committee;
use Modules\Committee\Models\CommitteeType;
use Modules\Core\Models\Organization;
use Tests\TestCase;

class CommitteeAuthorizationContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_committee_can_be_used_as_an_authorization_context(): void
    {
        $organization = Organization::factory()->create();

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Central Committee',
            'code' => 'central',
            'description' => 'Central organization committee.',
            'is_active' => true,
        ]);

        $committee = Committee::create([
            'organization_id' => $organization->id,
            'committee_type_id' => $committeeType->id,
            'unit_id' => null,
            'parent_committee_id' => null,
            'name' => 'Central Committee 2026',
            'kind' => CommitteeKind::RECURRING,
            'status' => CommitteeStatus::ACTIVE,
        ]);

        $this->assertSame(
            'committee',
            $committee->contextType()
        );

        $this->assertSame(
            $committee->id,
            $committee->contextId()
        );

        $this->assertSame(
            $organization->id,
            $committee->organizationId()
        );
    }
}