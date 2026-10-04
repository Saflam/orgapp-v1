<?php

namespace Modules\Committee\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Committee\Enums\CommitteeKind;
use Modules\Committee\Enums\CommitteeMembershipStatus;
use Modules\Committee\Enums\CommitteeStatus;
use Modules\Committee\Enums\CommitteeTermStatus;
use Modules\Committee\Models\Committee;
use Modules\Committee\Models\CommitteeMembership;
use Modules\Committee\Models\CommitteeTerm;
use Modules\Committee\Models\CommitteeType;
use Modules\Committee\Models\Designation;
use Modules\Committee\Services\CommitteeMembershipService;
use Modules\Core\Enums\ApplicationModule;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Member\Models\Member;
use Tests\TestCase;

class CommitteeMembershipApiTest extends TestCase
{
    use RefreshDatabase;

    private function createCommitteeContext(): array
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $organization,
            module: 'Committee',
        );

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive Committee',
            'code' => 'EXECUTIVE',
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

        $term = CommitteeTerm::create([
            'committee_id' => $committee->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => CommitteeTermStatus::ACTIVE,
        ]);

        return [
            'organization' => $organization,
            'committee' => $committee,
            'term' => $term,
        ];
    }

    public function test_it_lists_memberships_for_a_committee_term(): void
    {
        $context = $this->createCommitteeContext();

        $organization = $context['organization'];
        $term = $context['term'];

        $member = Member::factory()
            ->forOrganization($organization->id)
            ->create([
                'membership_number' => 'TEST-002',
            ]);

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $membership = CommitteeMembership::create([
            'committee_term_id' => $term->id,
            'member_id' => $member->id,
            'designation_id' => $designation->id,
            'status' => CommitteeMembershipStatus::ACTIVE,
        ]);

        $response = $this->getJson(
            "/api/v1/organizations/{$organization->id}/committee-terms/{$term->id}/memberships"
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $membership->id)
            ->assertJsonPath('data.0.member_id', $member->id)
            ->assertJsonPath('data.0.designation_id', $designation->id)
            ->assertJsonPath(
                'data.0.status',
                CommitteeMembershipStatus::ACTIVE->value
            );
    }

    public function test_it_does_not_return_memberships_from_another_term(): void
    {
        $context = $this->createCommitteeContext();

        $organization = $context['organization'];
        $committee = $context['committee'];
        $termA = $context['term'];

        $termA->update([
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
        ]);

        $termB = CommitteeTerm::create([
            'committee_id' => $committee->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'status' => CommitteeTermStatus::ACTIVE,
        ]);

        $member = Member::factory()
            ->forOrganization($organization->id)
            ->create([
                'membership_number' => 'TEST-001',
            ]);

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        CommitteeMembership::create([
            'committee_term_id' => $termB->id,
            'member_id' => $member->id,
            'designation_id' => $designation->id,
            'status' => CommitteeMembershipStatus::ACTIVE,
        ]);

        $response = $this->getJson(
            "/api/v1/organizations/{$organization->id}/committee-terms/{$termA->id}/memberships"
        );

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_it_cannot_list_memberships_from_another_organization(): void
    {
        $contextA = $this->createCommitteeContext();

        $organizationA = $contextA['organization'];

        $organizationB = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $organizationB,
            module: 'Committee',
        );

        $committeeTypeB = CommitteeType::create([
            'organization_id' => $organizationB->id,
            'name' => 'Executive Committee',
            'code' => 'EXECUTIVE',
            'is_active' => true,
        ]);

        $committeeB = Committee::create([
            'organization_id' => $organizationB->id,
            'committee_type_id' => $committeeTypeB->id,
            'unit_id' => null,
            'parent_committee_id' => null,
            'name' => 'Central Committee 2026',
            'kind' => CommitteeKind::RECURRING,
            'status' => CommitteeStatus::ACTIVE,
        ]);

        $termB = CommitteeTerm::create([
            'committee_id' => $committeeB->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => CommitteeTermStatus::ACTIVE,
        ]);

        $response = $this->getJson(
            "/api/v1/organizations/{$organizationA->id}/committee-terms/{$termB->id}/memberships"
        );

        $response->assertNotFound();
    }

    public function test_it_rejects_membership_list_when_module_is_disabled(): void
    {
        $organization = Organization::factory()->create();

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive Committee',
            'code' => 'EXECUTIVE',
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

        $term = CommitteeTerm::create([
            'committee_id' => $committee->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => CommitteeTermStatus::ACTIVE,
        ]);

        $response = $this->getJson(
            "/api/v1/organizations/{$organization->id}/committee-terms/{$term->id}/memberships"
        );

        $response->assertForbidden();
    }

    public function test_it_can_add_a_member_to_an_active_committee_term(): void
    {
        $context = $this->createCommitteeContext();

        $organization = $context['organization'];
        $term = $context['term'];

        $member = Member::factory()
            ->forOrganization($organization->id)
            ->create([
                'membership_number' => 'TEST-003',
            ]);

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/committee-terms/{$term->id}/memberships",
            [
                'member_id' => $member->id,
                'designation_id' => $designation->id,
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.member_id', $member->id)
            ->assertJsonPath('data.designation_id', $designation->id)
            ->assertJsonPath(
                'data.status',
                CommitteeMembershipStatus::ACTIVE->value
            );

        $this->assertDatabaseHas('committee_memberships', [
            'committee_term_id' => $term->id,
            'member_id' => $member->id,
            'designation_id' => $designation->id,
            'status' => CommitteeMembershipStatus::ACTIVE->value,
        ]);
    }

    public function test_it_rejects_a_member_from_another_organization(): void
    {
        $context = $this->createCommitteeContext();

        $organization = $context['organization'];
        $term = $context['term'];

        $otherOrganization = Organization::factory()->create();

        $member = Member::factory()
            ->forOrganization($otherOrganization->id)
            ->create([
                'membership_number' => 'TEST-004',
            ]);

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Secretary',
            'code' => 'SECRETARY',
            'is_active' => true,
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/committee-terms/{$term->id}/memberships",
            [
                'member_id' => $member->id,
                'designation_id' => $designation->id,
            ]
        );

        $response->assertStatus(422);

        $this->assertDatabaseMissing('committee_memberships', [
            'committee_term_id' => $term->id,
            'member_id' => $member->id,
        ]);
    }

    public function test_it_rejects_a_designation_from_another_organization(): void
    {
        $context = $this->createCommitteeContext();

        $organization = $context['organization'];
        $term = $context['term'];

        $otherOrganization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $otherOrganization,
            module: 'Committee',
        );

        $member = Member::factory()
            ->forOrganization($organization->id)
            ->create([
                'membership_number' => 'TEST-005',
            ]);

        $designation = Designation::create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Other Organization Treasurer',
            'code' => 'OTHER-TREASURER',
            'is_active' => true,
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/committee-terms/{$term->id}/memberships",
            [
                'member_id' => $member->id,
                'designation_id' => $designation->id,
            ]
        );

        $response->assertStatus(422);

        $this->assertDatabaseMissing('committee_memberships', [
            'committee_term_id' => $term->id,
            'member_id' => $member->id,
            'designation_id' => $designation->id,
        ]);
    }

    public function test_it_rejects_an_inactive_designation(): void
    {
        $context = $this->createCommitteeContext();

        $organization = $context['organization'];
        $term = $context['term'];

        $member = Member::factory()
            ->forOrganization($organization->id)
            ->create([
                'membership_number' => 'TEST-006',
            ]);

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Inactive Treasurer',
            'code' => 'INACTIVE-TREASURER',
            'is_active' => false,
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/committee-terms/{$term->id}/memberships",
            [
                'member_id' => $member->id,
                'designation_id' => $designation->id,
            ]
        );

        $response->assertStatus(422);

        $this->assertDatabaseMissing('committee_memberships', [
            'committee_term_id' => $term->id,
            'member_id' => $member->id,
            'designation_id' => $designation->id,
        ]);
    }

    public function test_it_rejects_membership_for_a_completed_committee_term(): void
    {
        $context = $this->createCommitteeContext();

        $organization = $context['organization'];
        $term = $context['term'];

        $term->update([
            'status' => CommitteeTermStatus::COMPLETED,
        ]);

        $member = Member::factory()
            ->forOrganization($organization->id)
            ->create([
                'membership_number' => 'TEST-007',
            ]);

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Secretary',
            'code' => 'SECRETARY',
            'is_active' => true,
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/committee-terms/{$term->id}/memberships",
            [
                'member_id' => $member->id,
                'designation_id' => $designation->id,
            ]
        );

        $response->assertStatus(422);

        $this->assertDatabaseMissing('committee_memberships', [
            'committee_term_id' => $term->id,
            'member_id' => $member->id,
            'designation_id' => $designation->id,
        ]);
    }

    public function test_it_rejects_duplicate_membership_in_the_same_term(): void
    {
        $context = $this->createCommitteeContext();

        $organization = $context['organization'];
        $term = $context['term'];

        $member = Member::factory()
            ->forOrganization($organization->id)
            ->create([
                'membership_number' => 'TEST-008',
            ]);

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        CommitteeMembership::create([
            'committee_term_id' => $term->id,
            'member_id' => $member->id,
            'designation_id' => $designation->id,
            'status' => CommitteeMembershipStatus::ACTIVE,
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/committee-terms/{$term->id}/memberships",
            [
                'member_id' => $member->id,
                'designation_id' => $designation->id,
            ]
        );

        $response->assertStatus(422);

        $this->assertDatabaseCount('committee_memberships', 1);

        $this->assertDatabaseHas('committee_memberships', [
            'committee_term_id' => $term->id,
            'member_id' => $member->id,
            'designation_id' => $designation->id,
            'status' => CommitteeMembershipStatus::ACTIVE->value,
        ]);
    }

    public function test_it_rejects_membership_creation_when_module_is_disabled(): void
    {
        $organization = Organization::factory()->create();

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive Committee',
            'code' => 'EXECUTIVE',
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

        $term = CommitteeTerm::create([
            'committee_id' => $committee->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => CommitteeTermStatus::ACTIVE,
        ]);

        $member = Member::factory()
            ->forOrganization($organization->id)
            ->create([
                'membership_number' => 'TEST-009',
            ]);

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/committee-terms/{$term->id}/memberships",
            [
                'member_id' => $member->id,
                'designation_id' => $designation->id,
            ]
        );

        $response->assertForbidden();

        $this->assertDatabaseMissing('committee_memberships', [
            'committee_term_id' => $term->id,
            'member_id' => $member->id,
            'designation_id' => $designation->id,
        ]);
    }

    public function test_it_can_end_an_active_committee_membership(): void
    {
        $context = $this->createCommitteeContext();

        $organization = $context['organization'];
        $term = $context['term'];

        $member = Member::factory()
            ->forOrganization($organization->id)
            ->create([
                'membership_number' => 'TEST-010',
            ]);

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $membership = CommitteeMembership::create([
            'committee_term_id' => $term->id,
            'member_id' => $member->id,
            'designation_id' => $designation->id,
            'status' => CommitteeMembershipStatus::ACTIVE,
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/committee-memberships/{$membership->id}/end"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $membership->id
            )
            ->assertJsonPath(
                'data.status',
                CommitteeMembershipStatus::ENDED->value
            );

        $this->assertDatabaseHas('committee_memberships', [
            'id' => $membership->id,
            'status' => CommitteeMembershipStatus::ENDED->value,
        ]);
    }

    public function test_it_rejects_ending_an_already_ended_membership(): void
    {
        $context = $this->createCommitteeContext();

        $organization = $context['organization'];
        $term = $context['term'];

        $member = Member::factory()
            ->forOrganization($organization->id)
            ->create([
                'membership_number' => 'TEST-011',
            ]);

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $membership = CommitteeMembership::create([
            'committee_term_id' => $term->id,
            'member_id' => $member->id,
            'designation_id' => $designation->id,
            'status' => CommitteeMembershipStatus::ENDED,
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/committee-memberships/{$membership->id}/end"
        );

        $response->assertStatus(422);

        $this->assertDatabaseHas('committee_memberships', [
            'id' => $membership->id,
            'status' => CommitteeMembershipStatus::ENDED->value,
        ]);
    }

    public function test_it_cannot_end_a_membership_from_another_organization(): void
    {
        $contextA = $this->createCommitteeContext();

        $organizationA = $contextA['organization'];

        $contextB = $this->createCommitteeContext();

        $organizationB = $contextB['organization'];
        $termB = $contextB['term'];

        $member = Member::factory()
            ->forOrganization($organizationB->id)
            ->create([
                'membership_number' => 'TEST-012',
            ]);

        $designation = Designation::create([
            'organization_id' => $organizationB->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $membership = CommitteeMembership::create([
            'committee_term_id' => $termB->id,
            'member_id' => $member->id,
            'designation_id' => $designation->id,
            'status' => CommitteeMembershipStatus::ACTIVE,
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$organizationA->id}/committee-memberships/{$membership->id}/end"
        );

        $response->assertNotFound();

        $this->assertDatabaseHas('committee_memberships', [
            'id' => $membership->id,
            'status' => CommitteeMembershipStatus::ACTIVE->value,
        ]);
    }

    public function test_it_rejects_ending_a_membership_when_module_is_disabled(): void
    {
        $organization = Organization::factory()->create();

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive Committee',
            'code' => 'EXECUTIVE',
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

        $term = CommitteeTerm::create([
            'committee_id' => $committee->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => CommitteeTermStatus::ACTIVE,
        ]);

        $member = Member::factory()
            ->forOrganization($organization->id)
            ->create([
                'membership_number' => 'TEST-013',
            ]);

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $membership = CommitteeMembership::create([
            'committee_term_id' => $term->id,
            'member_id' => $member->id,
            'designation_id' => $designation->id,
            'status' => CommitteeMembershipStatus::ACTIVE,
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/committee-memberships/{$membership->id}/end"
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('committee_memberships', [
            'id' => $membership->id,
            'status' => CommitteeMembershipStatus::ACTIVE->value,
        ]);
    }

    public function test_it_can_cancel_an_active_committee_membership(): void
    {
        $context = $this->createCommitteeContext();

        $organization = $context['organization'];
        $term = $context['term'];

        $member = Member::factory()
            ->forOrganization($organization->id)
            ->create([
                'membership_number' => 'TEST-014',
            ]);

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $membership = CommitteeMembership::create([
            'committee_term_id' => $term->id,
            'member_id' => $member->id,
            'designation_id' => $designation->id,
            'status' => CommitteeMembershipStatus::ACTIVE,
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/committee-memberships/{$membership->id}/cancel"
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $membership->id)
            ->assertJsonPath(
                'data.status',
                CommitteeMembershipStatus::CANCELLED->value
            );

        $this->assertDatabaseHas('committee_memberships', [
            'id' => $membership->id,
            'status' => CommitteeMembershipStatus::CANCELLED->value,
        ]);
    }

    public function test_it_rejects_cancelling_an_already_cancelled_membership(): void
    {
        $context = $this->createCommitteeContext();

        $organization = $context['organization'];
        $term = $context['term'];

        $member = Member::factory()
            ->forOrganization($organization->id)
            ->create([
                'membership_number' => 'TEST-015',
            ]);

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Secretary',
            'code' => 'SECRETARY',
            'is_active' => true,
        ]);

        $membership = CommitteeMembership::create([
            'committee_term_id' => $term->id,
            'member_id' => $member->id,
            'designation_id' => $designation->id,
            'status' => CommitteeMembershipStatus::CANCELLED,
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/committee-memberships/{$membership->id}/cancel"
        );

        $response->assertStatus(422);

        $this->assertDatabaseHas('committee_memberships', [
            'id' => $membership->id,
            'status' => CommitteeMembershipStatus::CANCELLED->value,
        ]);
    }

    public function test_it_rejects_cancelling_an_ended_membership(): void
    {
        $context = $this->createCommitteeContext();

        $organization = $context['organization'];
        $term = $context['term'];

        $member = Member::factory()
            ->forOrganization($organization->id)
            ->create([
                'membership_number' => 'TEST-016',
            ]);

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Secretary',
            'code' => 'SECRETARY',
            'is_active' => true,
        ]);

        $membership = CommitteeMembership::create([
            'committee_term_id' => $term->id,
            'member_id' => $member->id,
            'designation_id' => $designation->id,
            'status' => CommitteeMembershipStatus::ENDED,
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/committee-memberships/{$membership->id}/cancel"
        );

        $response->assertStatus(422);

        $this->assertDatabaseHas('committee_memberships', [
            'id' => $membership->id,
            'status' => CommitteeMembershipStatus::ENDED->value,
        ]);
    }

    public function test_it_cannot_cancel_a_membership_from_another_organization(): void
    {
        $contextA = $this->createCommitteeContext();

        $organizationA = $contextA['organization'];

        $contextB = $this->createCommitteeContext();

        $organizationB = $contextB['organization'];
        $termB = $contextB['term'];

        $member = Member::factory()
            ->forOrganization($organizationB->id)
            ->create([
                'membership_number' => 'TEST-017',
            ]);

        $designation = Designation::create([
            'organization_id' => $organizationB->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $membership = CommitteeMembership::create([
            'committee_term_id' => $termB->id,
            'member_id' => $member->id,
            'designation_id' => $designation->id,
            'status' => CommitteeMembershipStatus::ACTIVE,
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$organizationA->id}/committee-memberships/{$membership->id}/cancel"
        );

        $response->assertNotFound();

        $this->assertDatabaseHas('committee_memberships', [
            'id' => $membership->id,
            'status' => CommitteeMembershipStatus::ACTIVE->value,
        ]);
    }

    public function test_it_rejects_cancelling_a_membership_when_module_is_disabled(): void
    {
        $organization = Organization::factory()->create();

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive Committee',
            'code' => 'EXECUTIVE',
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

        $term = CommitteeTerm::create([
            'committee_id' => $committee->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => CommitteeTermStatus::ACTIVE,
        ]);

        $member = Member::factory()
            ->forOrganization($organization->id)
            ->create([
                'membership_number' => 'TEST-018',
            ]);

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $membership = CommitteeMembership::create([
            'committee_term_id' => $term->id,
            'member_id' => $member->id,
            'designation_id' => $designation->id,
            'status' => CommitteeMembershipStatus::ACTIVE,
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/committee-memberships/{$membership->id}/cancel"
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('committee_memberships', [
            'id' => $membership->id,
            'status' => CommitteeMembershipStatus::ACTIVE->value,
        ]);
    }

    public function test_it_can_correct_a_membership_designation(): void
    {
        $context = $this->createCommitteeContext();

        $member = Member::factory()
            ->forOrganization($context['organization']->id)
            ->create([
                'membership_number' => 'TEST-001',
            ]);

        $oldDesignation = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $newDesignation = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Secretary',
            'code' => 'SECRETARY',
            'is_active' => true,
        ]);

        $membership = CommitteeMembership::create([
            'committee_term_id' => $context['term']->id,
            'member_id' => $member->id,
            'designation_id' => $oldDesignation->id,
            'status' => CommitteeMembershipStatus::ACTIVE,
        ]);

        $response = $this->patchJson(
            "/api/v1/organizations/{$context['organization']->id}/committee-memberships/{$membership->id}",
            [
                'designation_id' => $newDesignation->id,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $membership->id)
            ->assertJsonPath('data.designation_id', $newDesignation->id);

        $this->assertDatabaseHas('committee_memberships', [
            'id' => $membership->id,
            'designation_id' => $newDesignation->id,
            'status' => CommitteeMembershipStatus::ACTIVE->value,
        ]);
    }


    public function test_it_can_update_to_the_same_designation_without_creating_a_new_membership(): void
    {
        $context = $this->createCommitteeContext();

        $member = Member::factory()
            ->forOrganization($context['organization']->id)
            ->create([
                'membership_number' => 'TEST-002',
            ]);

        $designation = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $membership = CommitteeMembership::create([
            'committee_term_id' => $context['term']->id,
            'member_id' => $member->id,
            'designation_id' => $designation->id,
            'status' => CommitteeMembershipStatus::ACTIVE,
        ]);

        $response = $this->patchJson(
            "/api/v1/organizations/{$context['organization']->id}/committee-memberships/{$membership->id}",
            [
                'designation_id' => $designation->id,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $membership->id)
            ->assertJsonPath('data.designation_id', $designation->id);

        $this->assertDatabaseCount('committee_memberships', 1);
    }


    public function test_it_rejects_updating_an_ended_membership(): void
    {
        $context = $this->createCommitteeContext();

        $member = Member::factory()
            ->forOrganization($context['organization']->id)
            ->create([
                'membership_number' => 'TEST-003',
            ]);

        $oldDesignation = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $newDesignation = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Secretary',
            'code' => 'SECRETARY',
            'is_active' => true,
        ]);

        $membership = CommitteeMembership::create([
            'committee_term_id' => $context['term']->id,
            'member_id' => $member->id,
            'designation_id' => $oldDesignation->id,
            'status' => CommitteeMembershipStatus::ENDED,
        ]);

        $response = $this->patchJson(
            "/api/v1/organizations/{$context['organization']->id}/committee-memberships/{$membership->id}",
            [
                'designation_id' => $newDesignation->id,
            ]
        );

        $response->assertStatus(422);

        $this->assertDatabaseHas('committee_memberships', [
            'id' => $membership->id,
            'designation_id' => $oldDesignation->id,
            'status' => CommitteeMembershipStatus::ENDED->value,
        ]);
    }


    public function test_it_rejects_updating_a_cancelled_membership(): void
    {
        $context = $this->createCommitteeContext();

        $member = Member::factory()
            ->forOrganization($context['organization']->id)
            ->create([
                'membership_number' => 'TEST-004',
            ]);

        $oldDesignation = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $newDesignation = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Secretary',
            'code' => 'SECRETARY',
            'is_active' => true,
        ]);

        $membership = CommitteeMembership::create([
            'committee_term_id' => $context['term']->id,
            'member_id' => $member->id,
            'designation_id' => $oldDesignation->id,
            'status' => CommitteeMembershipStatus::CANCELLED,
        ]);

        $response = $this->patchJson(
            "/api/v1/organizations/{$context['organization']->id}/committee-memberships/{$membership->id}",
            [
                'designation_id' => $newDesignation->id,
            ]
        );

        $response->assertStatus(422);

        $this->assertDatabaseHas('committee_memberships', [
            'id' => $membership->id,
            'designation_id' => $oldDesignation->id,
            'status' => CommitteeMembershipStatus::CANCELLED->value,
        ]);
    }


    public function test_it_rejects_an_inactive_designation_when_updating_membership(): void
    {
        $context = $this->createCommitteeContext();

        $member = Member::factory()
            ->forOrganization($context['organization']->id)
            ->create([
                'membership_number' => 'TEST-005',
            ]);

        $oldDesignation = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $inactiveDesignation = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Secretary',
            'code' => 'SECRETARY',
            'is_active' => false,
        ]);

        $membership = CommitteeMembership::create([
            'committee_term_id' => $context['term']->id,
            'member_id' => $member->id,
            'designation_id' => $oldDesignation->id,
            'status' => CommitteeMembershipStatus::ACTIVE,
        ]);

        $response = $this->patchJson(
            "/api/v1/organizations/{$context['organization']->id}/committee-memberships/{$membership->id}",
            [
                'designation_id' => $inactiveDesignation->id,
            ]
        );

        $response->assertStatus(422);

        $this->assertDatabaseHas('committee_memberships', [
            'id' => $membership->id,
            'designation_id' => $oldDesignation->id,
        ]);
    }

    public function test_it_rejects_a_designation_from_another_organization_when_updating_membership(): void
    {
        $context = $this->createCommitteeContext();

        $otherOrganization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $otherOrganization,
            module: 'Committee',
        );

        $member = Member::factory()
            ->forOrganization($context['organization']->id)
            ->create([
                'membership_number' => 'TEST-006',
            ]);

        $oldDesignation = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $foreignDesignation = Designation::create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Secretary',
            'code' => 'SECRETARY',
            'is_active' => true,
        ]);

        $membership = CommitteeMembership::create([
            'committee_term_id' => $context['term']->id,
            'member_id' => $member->id,
            'designation_id' => $oldDesignation->id,
            'status' => CommitteeMembershipStatus::ACTIVE,
        ]);

        $response = $this->patchJson(
            "/api/v1/organizations/{$context['organization']->id}/committee-memberships/{$membership->id}",
            [
                'designation_id' => $foreignDesignation->id,
            ]
        );

        $response->assertStatus(422);

        $this->assertDatabaseHas('committee_memberships', [
            'id' => $membership->id,
            'designation_id' => $oldDesignation->id,
        ]);
    }


    public function test_it_cannot_update_a_membership_from_another_organization(): void
    {
        $contextA = $this->createCommitteeContext();
        $contextB = $this->createCommitteeContext();

        $member = Member::factory()
            ->forOrganization($contextB['organization']->id)
            ->create([
                'membership_number' => 'TEST-007',
            ]);

        $designation = Designation::create([
            'organization_id' => $contextB['organization']->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $newDesignation = Designation::create([
            'organization_id' => $contextB['organization']->id,
            'name' => 'Secretary',
            'code' => 'SECRETARY',
            'is_active' => true,
        ]);

        $membership = CommitteeMembership::create([
            'committee_term_id' => $contextB['term']->id,
            'member_id' => $member->id,
            'designation_id' => $designation->id,
            'status' => CommitteeMembershipStatus::ACTIVE,
        ]);

        $response = $this->patchJson(
            "/api/v1/organizations/{$contextA['organization']->id}/committee-memberships/{$membership->id}",
            [
                'designation_id' => $newDesignation->id,
            ]
        );

        $response->assertNotFound();
    }


    public function test_it_rejects_membership_update_when_module_is_disabled(): void
    {
        $organization = Organization::factory()->create();

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive Committee',
            'code' => 'EXECUTIVE',
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

        $term = CommitteeTerm::create([
            'committee_id' => $committee->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => CommitteeTermStatus::ACTIVE,
        ]);

        $member = Member::factory()
            ->forOrganization($organization->id)
            ->create([
                'membership_number' => 'TEST-008',
            ]);

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $newDesignation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Secretary',
            'code' => 'SECRETARY',
            'is_active' => true,
        ]);

        $membership = CommitteeMembership::create([
            'committee_term_id' => $term->id,
            'member_id' => $member->id,
            'designation_id' => $designation->id,
            'status' => CommitteeMembershipStatus::ACTIVE,
        ]);

        $response = $this->patchJson(
            "/api/v1/organizations/{$organization->id}/committee-memberships/{$membership->id}",
            [
                'designation_id' => $newDesignation->id,
            ]
        );

        $response->assertForbidden();
    }

    public function test_it_can_change_a_members_designation_with_an_effective_date(): void
    {
        $context = $this->createCommitteeContext();

        $member = Member::factory()
            ->forOrganization($context['organization']->id)
            ->create([
                'membership_number' => 'TEST-009',
            ]);

        $treasurer = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $secretary = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Secretary',
            'code' => 'SECRETARY',
            'is_active' => true,
        ]);

        $membership = CommitteeMembership::create([
            'committee_term_id' => $context['term']->id,
            'member_id' => $member->id,
            'designation_id' => $treasurer->id,
            'start_date' => '2026-01-01',
            'end_date' => null,
            'status' => CommitteeMembershipStatus::ACTIVE,
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$context['organization']->id}/committee-memberships/{$membership->id}/change-designation",
            [
                'designation_id' => $secretary->id,
                'effective_date' => '2026-07-01',
            ]
        );

        $response->assertCreated();

        $response->assertJsonPath(
            'data.designation_id',
            $secretary->id
        );

        $this->assertDatabaseHas('committee_memberships', [
            'id' => $membership->id,
            'designation_id' => $treasurer->id,
            'start_date' => '2026-01-01 00:00:00',
            'end_date' => '2026-06-30 00:00:00',
            'status' => CommitteeMembershipStatus::ENDED->value,
        ]);

        $this->assertDatabaseHas('committee_memberships', [
            'committee_term_id' => $context['term']->id,
            'member_id' => $member->id,
            'designation_id' => $secretary->id,
            'start_date' => '2026-07-01 00:00:00',
            'end_date' => null,
            'status' => CommitteeMembershipStatus::ACTIVE->value,
        ]);

        $this->assertDatabaseCount('committee_memberships', 2);
    }


    public function test_it_rejects_a_designation_change_before_the_membership_start_date(): void
    {
        $context = $this->createCommitteeContext();

        $member = Member::factory()
            ->forOrganization($context['organization']->id)
            ->create([
                'membership_number' => 'TEST-010',
            ]);

        $treasurer = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $secretary = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Secretary',
            'code' => 'SECRETARY',
            'is_active' => true,
        ]);

        $membership = CommitteeMembership::create([
            'committee_term_id' => $context['term']->id,
            'member_id' => $member->id,
            'designation_id' => $treasurer->id,
            'start_date' => '2026-01-01',
            'end_date' => null,
            'status' => CommitteeMembershipStatus::ACTIVE,
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$context['organization']->id}/committee-memberships/{$membership->id}/change-designation",
            [
                'designation_id' => $secretary->id,
                'effective_date' => '2025-12-31',
            ]
        );

        $response->assertStatus(422);

        $this->assertDatabaseCount('committee_memberships', 1);

        $this->assertDatabaseHas('committee_memberships', [
            'id' => $membership->id,
            'designation_id' => $treasurer->id,
            'start_date' => '2026-01-01 00:00:00',
            'end_date' => null,
            'status' => CommitteeMembershipStatus::ACTIVE->value,
        ]);
    }


    public function test_it_rejects_a_designation_change_on_the_membership_start_date(): void
    {
        $context = $this->createCommitteeContext();

        $member = Member::factory()
            ->forOrganization($context['organization']->id)
            ->create([
                'membership_number' => 'TEST-011',
            ]);

        $treasurer = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $secretary = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Secretary',
            'code' => 'SECRETARY',
            'is_active' => true,
        ]);

        $membership = CommitteeMembership::create([
            'committee_term_id' => $context['term']->id,
            'member_id' => $member->id,
            'designation_id' => $treasurer->id,
            'start_date' => '2026-01-01',
            'end_date' => null,
            'status' => CommitteeMembershipStatus::ACTIVE,
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$context['organization']->id}/committee-memberships/{$membership->id}/change-designation",
            [
                'designation_id' => $secretary->id,
                'effective_date' => '2026-01-01',
            ]
        );

        $response->assertStatus(422);

        $this->assertDatabaseCount('committee_memberships', 1);
    }


    public function test_it_rejects_a_designation_change_after_the_committee_term_ends(): void
    {
        $context = $this->createCommitteeContext();

        $member = Member::factory()
            ->forOrganization($context['organization']->id)
            ->create([
                'membership_number' => 'TEST-012',
            ]);

        $treasurer = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $secretary = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Secretary',
            'code' => 'SECRETARY',
            'is_active' => true,
        ]);

        $membership = CommitteeMembership::create([
            'committee_term_id' => $context['term']->id,
            'member_id' => $member->id,
            'designation_id' => $treasurer->id,
            'start_date' => '2026-01-01',
            'end_date' => null,
            'status' => CommitteeMembershipStatus::ACTIVE,
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$context['organization']->id}/committee-memberships/{$membership->id}/change-designation",
            [
                'designation_id' => $secretary->id,
                'effective_date' => '2027-01-01',
            ]
        );

        $response->assertStatus(422);

        $this->assertDatabaseCount('committee_memberships', 1);
    }


    public function test_it_rejects_a_designation_change_to_an_inactive_designation(): void
    {
        $context = $this->createCommitteeContext();

        $member = Member::factory()
            ->forOrganization($context['organization']->id)
            ->create([
                'membership_number' => 'TEST-013',
            ]);

        $treasurer = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $inactiveSecretary = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Secretary',
            'code' => 'SECRETARY',
            'is_active' => false,
        ]);

        $membership = CommitteeMembership::create([
            'committee_term_id' => $context['term']->id,
            'member_id' => $member->id,
            'designation_id' => $treasurer->id,
            'start_date' => '2026-01-01',
            'end_date' => null,
            'status' => CommitteeMembershipStatus::ACTIVE,
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$context['organization']->id}/committee-memberships/{$membership->id}/change-designation",
            [
                'designation_id' => $inactiveSecretary->id,
                'effective_date' => '2026-07-01',
            ]
        );

        $response->assertStatus(422);

        $this->assertDatabaseCount('committee_memberships', 1);
    }


    public function test_it_rejects_a_designation_change_to_another_organization(): void
    {
        $context = $this->createCommitteeContext();

        $otherOrganization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            organization: $otherOrganization,
            module: 'Committee',
        );

        $member = Member::factory()
            ->forOrganization($context['organization']->id)
            ->create([
                'membership_number' => 'TEST-014',
            ]);

        $treasurer = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $foreignDesignation = Designation::create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Secretary',
            'code' => 'SECRETARY',
            'is_active' => true,
        ]);

        $membership = CommitteeMembership::create([
            'committee_term_id' => $context['term']->id,
            'member_id' => $member->id,
            'designation_id' => $treasurer->id,
            'start_date' => '2026-01-01',
            'end_date' => null,
            'status' => CommitteeMembershipStatus::ACTIVE,
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$context['organization']->id}/committee-memberships/{$membership->id}/change-designation",
            [
                'designation_id' => $foreignDesignation->id,
                'effective_date' => '2026-07-01',
            ]
        );

        $response->assertStatus(422);

        $this->assertDatabaseCount('committee_memberships', 1);
    }


    public function test_it_rejects_a_designation_change_for_an_ended_membership(): void
    {
        $context = $this->createCommitteeContext();

        $member = Member::factory()
            ->forOrganization($context['organization']->id)
            ->create([
                'membership_number' => 'TEST-015',
            ]);

        $treasurer = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $secretary = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Secretary',
            'code' => 'SECRETARY',
            'is_active' => true,
        ]);

        $membership = CommitteeMembership::create([
            'committee_term_id' => $context['term']->id,
            'member_id' => $member->id,
            'designation_id' => $treasurer->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
            'status' => CommitteeMembershipStatus::ENDED,
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$context['organization']->id}/committee-memberships/{$membership->id}/change-designation",
            [
                'designation_id' => $secretary->id,
                'effective_date' => '2026-07-01',
            ]
        );

        $response->assertStatus(422);

        $this->assertDatabaseCount('committee_memberships', 1);
    }


    public function test_it_rejects_a_designation_change_when_module_is_disabled(): void
    {
        $organization = Organization::factory()->create();

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive Committee',
            'code' => 'EXECUTIVE',
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

        $term = CommitteeTerm::create([
            'committee_id' => $committee->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => CommitteeTermStatus::ACTIVE,
        ]);

        $member = Member::factory()
            ->forOrganization($organization->id)
            ->create([
                'membership_number' => 'TEST-016',
            ]);

        $treasurer = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $secretary = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Secretary',
            'code' => 'SECRETARY',
            'is_active' => true,
        ]);

        $membership = CommitteeMembership::create([
            'committee_term_id' => $term->id,
            'member_id' => $member->id,
            'designation_id' => $treasurer->id,
            'start_date' => '2026-01-01',
            'end_date' => null,
            'status' => CommitteeMembershipStatus::ACTIVE,
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$organization->id}/committee-memberships/{$membership->id}/change-designation",
            [
                'designation_id' => $secretary->id,
                'effective_date' => '2026-07-01',
            ]
        );

        $response->assertForbidden();

        $this->assertDatabaseCount('committee_memberships', 1);
    }

    public function test_it_cannot_change_a_membership_from_another_organization(): void
    {
        $contextA = $this->createCommitteeContext();
        $contextB = $this->createCommitteeContext();

        $member = Member::factory()
            ->forOrganization($contextB['organization']->id)
            ->create([
                'membership_number' => 'TEST-017',
            ]);

        $treasurer = Designation::create([
            'organization_id' => $contextB['organization']->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $secretary = Designation::create([
            'organization_id' => $contextB['organization']->id,
            'name' => 'Secretary',
            'code' => 'SECRETARY',
            'is_active' => true,
        ]);

        $membership = CommitteeMembership::create([
            'committee_term_id' => $contextB['term']->id,
            'member_id' => $member->id,
            'designation_id' => $treasurer->id,
            'start_date' => '2026-01-01',
            'end_date' => null,
            'status' => CommitteeMembershipStatus::ACTIVE,
        ]);

        $response = $this->postJson(
            "/api/v1/organizations/{$contextA['organization']->id}/committee-memberships/{$membership->id}/change-designation",
            [
                'designation_id' => $secretary->id,
                'effective_date' => '2026-07-01',
            ]
        );

        $response->assertNotFound();

        $this->assertDatabaseCount('committee_memberships', 1);
    }

    public function test_it_returns_memberships_effective_on_a_given_date(): void
    {
        $context = $this->createCommitteeContext();

        $member = Member::factory()
            ->forOrganization($context['organization']->id)
            ->create([
                'membership_number' => 'TEST-001',
            ]);

        $treasurer = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $secretary = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Secretary',
            'code' => 'SECRETARY',
            'is_active' => true,
        ]);

        $membership = CommitteeMembership::create([
            'committee_term_id' => $context['term']->id,
            'member_id' => $member->id,
            'designation_id' => $treasurer->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
            'status' => CommitteeMembershipStatus::ENDED,
        ]);

        CommitteeMembership::create([
            'committee_term_id' => $context['term']->id,
            'member_id' => $member->id,
            'designation_id' => $secretary->id,
            'start_date' => '2026-07-01',
            'end_date' => null,
            'status' => CommitteeMembershipStatus::ACTIVE,
        ]);

        $service = app(CommitteeMembershipService::class);

        $result = $service->getEffectiveMemberships(
            term: $context['term'],
            date: Carbon::parse('2026-08-01'),
        );

        $this->assertCount(1, $result);
        $this->assertSame(
            $secretary->id,
            $result->first()->designation_id
        );
    }

    public function test_it_returns_the_historical_membership_before_a_designation_change(): void
    {
        $context = $this->createCommitteeContext();

        $member = Member::factory()
            ->forOrganization($context['organization']->id)
            ->create([
                'membership_number' => 'TEST-001',
            ]);

        $treasurer = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $secretary = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Secretary',
            'code' => 'SECRETARY',
            'is_active' => true,
        ]);

        $oldMembership = CommitteeMembership::create([
            'committee_term_id' => $context['term']->id,
            'member_id' => $member->id,
            'designation_id' => $treasurer->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
            'status' => CommitteeMembershipStatus::ENDED,
        ]);

        CommitteeMembership::create([
            'committee_term_id' => $context['term']->id,
            'member_id' => $member->id,
            'designation_id' => $secretary->id,
            'start_date' => '2026-07-01',
            'end_date' => null,
            'status' => CommitteeMembershipStatus::ACTIVE,
        ]);

        $service = app(CommitteeMembershipService::class);

        $result = $service->getEffectiveMemberships(
            term: $context['term'],
            date: Carbon::parse('2026-06-15'),
        );

        $this->assertCount(1, $result);

        $this->assertSame(
            $oldMembership->id,
            $result->first()->id
        );

        $this->assertSame(
            $treasurer->id,
            $result->first()->designation_id
        );

        $this->assertSame(
            $member->id,
            $result->first()->member_id
        );
    }

    public function test_it_lists_memberships_effective_on_a_requested_date(): void
    {
        $context = $this->createCommitteeContext();

        $member = Member::factory()
            ->forOrganization($context['organization']->id)
            ->create([
                'membership_number' => 'TEST-001',
            ]);

        $treasurer = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $secretary = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Secretary',
            'code' => 'SECRETARY',
            'is_active' => true,
        ]);

        CommitteeMembership::create([
            'committee_term_id' => $context['term']->id,
            'member_id' => $member->id,
            'designation_id' => $treasurer->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
            'status' => CommitteeMembershipStatus::ENDED,
        ]);

        CommitteeMembership::create([
            'committee_term_id' => $context['term']->id,
            'member_id' => $member->id,
            'designation_id' => $secretary->id,
            'start_date' => '2026-07-01',
            'end_date' => null,
            'status' => CommitteeMembershipStatus::ACTIVE,
        ]);

        $response = $this->getJson(
            "/api/v1/organizations/{$context['organization']->id}"
            . "/committee-terms/{$context['term']->id}"
            . "/memberships/effective?date=2026-06-15"
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.designation_id',
                $treasurer->id
            )
            ->assertJsonPath(
                'data.0.member_id',
                $member->id
            );
    }

    public function test_it_cannot_list_effective_memberships_from_another_organization(): void
    {
        $contextA = $this->createCommitteeContext();
        $contextB = $this->createCommitteeContext();

        $member = Member::factory()
            ->forOrganization($contextB['organization']->id)
            ->create([
                'membership_number' => 'TEST-002',
            ]);

        $designation = Designation::create([
            'organization_id' => $contextB['organization']->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER-B',
            'is_active' => true,
        ]);

        CommitteeMembership::create([
            'committee_term_id' => $contextB['term']->id,
            'member_id' => $member->id,
            'designation_id' => $designation->id,
            'start_date' => '2026-01-01',
            'end_date' => null,
            'status' => CommitteeMembershipStatus::ACTIVE,
        ]);

        $response = $this->getJson(
            "/api/v1/organizations/{$contextA['organization']->id}"
            . "/committee-terms/{$contextB['term']->id}"
            . "/memberships/effective?date=2026-06-15"
        );

        $response->assertNotFound();
    }

    public function test_it_rejects_effective_memberships_when_module_is_disabled(): void
    {
        $organization = Organization::factory()->create();

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive Committee',
            'code' => 'EXECUTIVE',
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

        $term = CommitteeTerm::create([
            'committee_id' => $committee->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => CommitteeTermStatus::ACTIVE,
        ]);

        $response = $this->getJson(
            "/api/v1/organizations/{$organization->id}"
            . "/committee-terms/{$term->id}"
            . "/memberships/effective?date=2026-06-15"
        );

        $response->assertForbidden();
    }
}