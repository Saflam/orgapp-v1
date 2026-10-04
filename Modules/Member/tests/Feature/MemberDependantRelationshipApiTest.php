<?php

namespace Modules\Member\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberDependant;
use Modules\Member\Tests\Concerns\ActsAsMemberOrganizationUser;
use Tests\TestCase;

class MemberDependantRelationshipApiTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsMemberOrganizationUser;

    public function test_it_can_add_a_dependant_relationship(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => true,
        ]);

        $this->actAsMemberOrganizationAdmin($organization);

        $member = Member::factory()
            ->forOrganization($organization)
            ->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Mary',
            'last_name' => 'Smith',
        ]);

        $response = $this->postJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/{$member->id}/dependant-relationships"
            ),
            [
                'dependant_id' => $dependant->id,
                'relationship_type' => 'spouse',
                'started_at' => '2026-01-01 00:00:00',
            ],
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.dependant_id', $dependant->id)
            ->assertJsonPath('data.relationship_type', 'spouse');

        $this->assertDatabaseHas('member_dependant_relationships', [
            'member_id' => $member->id,
            'dependant_id' => $dependant->id,
            'relationship_type' => 'spouse',
            'started_at' => '2026-01-01 00:00:00',
            'ended_at' => null,
        ]);
    }

    public function test_it_cannot_add_a_dependant_from_another_organization(): void
    {
        $organizationA = Organization::factory()->create([
            'is_active' => true,
        ]);

        $this->actAsMemberOrganizationAdmin($organizationA);

        $organizationB = Organization::factory()->create([
            'is_active' => true,
        ]);

        $member = Member::factory()
            ->forOrganization($organizationA)
            ->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organizationB->id,
            'first_name' => 'Mary',
        ]);

        $response = $this->postJson(
            $this->organizationUrl(
                $organizationA,
                "/api/v1/organizations/{$organizationA->id}/members/{$member->id}/dependant-relationships"
            ),
            [
                'dependant_id' => $dependant->id,
                'relationship_type' => 'spouse',
                'started_at' => '2026-01-01 00:00:00',
            ],
        );

        $response->assertNotFound();

        $this->assertDatabaseCount(
            'member_dependant_relationships',
            0
        );
    }

    public function test_a_member_can_have_multiple_children(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => true,
        ]);

        $this->actAsMemberOrganizationAdmin($organization);

        $member = Member::factory()
            ->forOrganization($organization)
            ->create();

        $childOne = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Peter',
        ]);

        $childTwo = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Sarah',
        ]);

        foreach ([$childOne, $childTwo] as $child) {
            $response = $this->postJson(
                $this->organizationUrl(
                    $organization,
                    "/api/v1/organizations/{$organization->id}/members/{$member->id}/dependant-relationships"
                ),
                [
                    'dependant_id' => $child->id,
                    'relationship_type' => 'child',
                    'started_at' => '2026-01-01 00:00:00',
                ],
            );

            $response->assertCreated();
        }

        $this->assertDatabaseCount(
            'member_dependant_relationships',
            2
        );
    }

    public function test_a_member_cannot_have_two_active_spouses(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => true,
        ]);

        $this->actAsMemberOrganizationAdmin($organization);

        $member = Member::factory()
            ->forOrganization($organization)
            ->create();

        $mary = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Mary',
        ]);

        $jane = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Jane',
        ]);

        $this->postJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/{$member->id}/dependant-relationships"
            ),
            [
                'dependant_id' => $mary->id,
                'relationship_type' => 'spouse',
                'started_at' => '2026-01-01 00:00:00',
            ],
        )->assertCreated();

        $response = $this->postJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/{$member->id}/dependant-relationships"
            ),
            [
                'dependant_id' => $jane->id,
                'relationship_type' => 'spouse',
                'started_at' => '2026-02-01 00:00:00',
            ],
        );

        $response->assertUnprocessable();

        $this->assertDatabaseCount(
            'member_dependant_relationships',
            1
        );
    }

    public function test_it_cannot_add_the_member_as_their_own_dependant(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => true,
        ]);

        $this->actAsMemberOrganizationAdmin($organization);

        $member = Member::factory()
            ->forOrganization($organization)
            ->create();

        /*
         * A Member ID is not a dependant ID, so this should be
         * rejected rather than creating an invalid relationship.
         */
        $response = $this->postJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/{$member->id}/dependant-relationships"
            ),
            [
                'dependant_id' => $member->id,
                'relationship_type' => 'child',
                'started_at' => '2026-01-01 00:00:00',
            ],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['dependant_id']);

        $this->assertDatabaseCount(
            'member_dependant_relationships',
            0
        );
    }

    public function test_it_validates_required_relationship_fields(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => true,
        ]);

        $this->actAsMemberOrganizationAdmin($organization);

        $member = Member::factory()
            ->forOrganization($organization)
            ->create();

        $response = $this->postJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/{$member->id}/dependant-relationships"
            ),
            [],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'dependant_id',
                'relationship_type',
                'started_at',
            ]);
    }

    public function test_it_can_list_a_members_dependant_relationships(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => true,
        ]);

        $this->actAsMemberOrganizationAdmin($organization);

        $member = Member::factory()
            ->forOrganization($organization)
            ->create();

        $spouse = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Mary',
        ]);

        $child = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Peter',
        ]);

        $this->postJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/{$member->id}/dependant-relationships"
            ),
            [
                'dependant_id' => $spouse->id,
                'relationship_type' => 'spouse',
                'started_at' => '2026-01-01',
            ],
        )->assertCreated();

        $this->postJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/{$member->id}/dependant-relationships"
            ),
            [
                'dependant_id' => $child->id,
                'relationship_type' => 'child',
                'started_at' => '2026-01-01',
            ],
        )->assertCreated();

        $response = $this->getJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/{$member->id}/dependant-relationships"
            )
        );

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'dependant_id' => $spouse->id,
                'relationship_type' => 'spouse',
            ])
            ->assertJsonFragment([
                'dependant_id' => $child->id,
                'relationship_type' => 'child',
            ]);
    }

    public function test_it_does_not_list_another_members_dependant_relationships(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => true,
        ]);

        $this->actAsMemberOrganizationAdmin($organization);

        $memberA = Member::factory()
            ->forOrganization($organization)
            ->create();

        $memberB = Member::factory()
            ->forOrganization($organization)
            ->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Mary',
        ]);

        $this->postJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/{$memberA->id}/dependant-relationships"
            ),
            [
                'dependant_id' => $dependant->id,
                'relationship_type' => 'spouse',
                'started_at' => '2026-01-01',
            ],
        )->assertCreated();

        $response = $this->getJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/{$memberB->id}/dependant-relationships"
            )
        );

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_it_can_show_a_dependant_relationship(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => true,
        ]);

        $this->actAsMemberOrganizationAdmin($organization);

        $member = Member::factory()
            ->forOrganization($organization)
            ->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Mary',
            'last_name' => 'Smith',
        ]);

        $relationship = $this->postJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/{$member->id}/dependant-relationships"
            ),
            [
                'dependant_id' => $dependant->id,
                'relationship_type' => 'spouse',
                'started_at' => '2026-01-01',
            ],
        )->assertCreated();

        $relationshipId = $relationship->json('data.id');

        $response = $this->getJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/{$member->id}/dependant-relationships/{$relationshipId}"
            )
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $relationshipId)
            ->assertJsonPath('data.member_id', $member->id)
            ->assertJsonPath('data.dependant_id', $dependant->id)
            ->assertJsonPath('data.relationship_type', 'spouse');
    }

    public function test_it_cannot_show_another_members_dependant_relationship(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => true,
        ]);

        $this->actAsMemberOrganizationAdmin($organization);

        $memberA = Member::factory()
            ->forOrganization($organization)
            ->create();

        $memberB = Member::factory()
            ->forOrganization($organization)
            ->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Mary',
        ]);

        $relationship = $memberA->dependantRelationships()->create([
            'dependant_id' => $dependant->id,
            'relationship_type' => 'spouse',
            'started_at' => '2026-01-01',
        ]);

        $response = $this->getJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/{$memberB->id}/dependant-relationships/{$relationship->id}"
            )
        );

        $response->assertNotFound();
    }

    public function test_it_can_update_a_dependant_relationship(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => true,
        ]);

        $this->actAsMemberOrganizationAdmin($organization);

        $member = Member::factory()
            ->forOrganization($organization)
            ->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Mary',
        ]);

        $relationship = $member->dependantRelationships()->create([
            'dependant_id' => $dependant->id,
            'relationship_type' => 'child',
            'started_at' => '2026-01-01',
        ]);

        $response = $this->putJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/{$member->id}/dependant-relationships/{$relationship->id}"
            ),
            [
                'relationship_type' => 'spouse',
                'started_at' => '2026-02-01',
            ],
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $relationship->id)
            ->assertJsonPath('data.relationship_type', 'spouse');

        $this->assertDatabaseHas('member_dependant_relationships', [
            'id' => $relationship->id,
            'relationship_type' => 'spouse',
            'started_at' => '2026-02-01 00:00:00',
        ]);
    }

    public function test_it_cannot_update_another_members_dependant_relationship(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => true,
        ]);

        $this->actAsMemberOrganizationAdmin($organization);

        $memberA = Member::factory()
            ->forOrganization($organization)
            ->create();

        $memberB = Member::factory()
            ->forOrganization($organization)
            ->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Mary',
        ]);

        $relationship = $memberA->dependantRelationships()->create([
            'dependant_id' => $dependant->id,
            'relationship_type' => 'child',
            'started_at' => '2026-01-01',
        ]);

        $response = $this->putJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/{$memberB->id}/dependant-relationships/{$relationship->id}"
            ),
            [
                'relationship_type' => 'spouse',
            ],
        );

        $response->assertNotFound();

        $this->assertDatabaseHas('member_dependant_relationships', [
            'id' => $relationship->id,
            'relationship_type' => 'child',
        ]);
    }

    public function test_it_can_end_a_dependant_relationship(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => true,
        ]);

        $this->actAsMemberOrganizationAdmin($organization);

        $member = Member::factory()
            ->forOrganization($organization)
            ->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Mary',
        ]);

        $relationship = $member->dependantRelationships()->create([
            'dependant_id' => $dependant->id,
            'relationship_type' => 'spouse',
            'started_at' => '2026-01-01',
        ]);

        $response = $this->postJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/{$member->id}/dependant-relationships/{$relationship->id}/end"
            ),
            [
                'ended_at' => '2026-06-30',
            ],
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $relationship->id)
            ->assertJsonPath('data.ended_at', '2026-06-30');

        $this->assertDatabaseHas('member_dependant_relationships', [
            'id' => $relationship->id,
            'ended_at' => '2026-06-30 00:00:00',
        ]);
    }

    public function test_it_can_end_a_relationship_without_specifying_an_end_date(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => true,
        ]);

        $this->actAsMemberOrganizationAdmin($organization);

        $member = Member::factory()
            ->forOrganization($organization)
            ->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Mary',
        ]);

        $relationship = $member->dependantRelationships()->create([
            'dependant_id' => $dependant->id,
            'relationship_type' => 'child',
            'started_at' => '2026-01-01',
        ]);

        $response = $this->postJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/{$member->id}/dependant-relationships/{$relationship->id}/end"
            ),
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $relationship->id)
            ->assertJsonPath(
                'data.ended_at',
                now()->toDateString()
            );
    }

    public function test_it_cannot_end_an_already_ended_relationship(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => true,
        ]);

        $this->actAsMemberOrganizationAdmin($organization);

        $member = Member::factory()
            ->forOrganization($organization)
            ->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Mary',
        ]);

        $relationship = $member->dependantRelationships()->create([
            'dependant_id' => $dependant->id,
            'relationship_type' => 'child',
            'started_at' => '2026-01-01',
            'ended_at' => '2026-06-01',
        ]);

        $response = $this->postJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/{$member->id}/dependant-relationships/{$relationship->id}/end"
            ),
            [
                'ended_at' => '2026-07-01',
            ],
        );

        $response->assertUnprocessable();

        $this->assertDatabaseHas('member_dependant_relationships', [
            'id' => $relationship->id,
            'ended_at' => '2026-06-01 00:00:00',
        ]);
    }

    public function test_it_cannot_end_another_members_dependant_relationship(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => true,
        ]);

        $this->actAsMemberOrganizationAdmin($organization);

        $memberA = Member::factory()
            ->forOrganization($organization)
            ->create();

        $memberB = Member::factory()
            ->forOrganization($organization)
            ->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Mary',
        ]);

        $relationship = $memberA->dependantRelationships()->create([
            'dependant_id' => $dependant->id,
            'relationship_type' => 'spouse',
            'started_at' => '2026-01-01',
        ]);

        $response = $this->postJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/{$memberB->id}/dependant-relationships/{$relationship->id}/end"
            ),
            [
                'ended_at' => '2026-06-30',
            ],
        );

        $response->assertNotFound();

        $this->assertDatabaseHas('member_dependant_relationships', [
            'id' => $relationship->id,
            'ended_at' => null,
        ]);
    }

    public function test_it_cannot_end_a_relationship_before_its_start_date(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => true,
        ]);

        $this->actAsMemberOrganizationAdmin($organization);

        $member = Member::factory()
            ->forOrganization($organization)
            ->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Mary',
        ]);

        $relationship = $member->dependantRelationships()->create([
            'dependant_id' => $dependant->id,
            'relationship_type' => 'child',
            'started_at' => '2026-06-01',
        ]);

        $response = $this->postJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/{$member->id}/dependant-relationships/{$relationship->id}/end"
            ),
            [
                'ended_at' => '2026-05-01',
            ],
        );

        $response->assertUnprocessable();

        $this->assertDatabaseHas('member_dependant_relationships', [
            'id' => $relationship->id,
            'ended_at' => null,
        ]);
    }
}