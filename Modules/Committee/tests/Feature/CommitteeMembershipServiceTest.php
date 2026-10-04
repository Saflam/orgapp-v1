<?php

namespace Modules\Committee\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Committee\Enums\CommitteeKind;
use Modules\Committee\Enums\CommitteeMembershipStatus;
use Modules\Committee\Enums\CommitteeStatus;
use Modules\Committee\Enums\CommitteeTermStatus;
use Modules\Committee\Models\Committee;
use Modules\Committee\Models\CommitteeTerm;
use Modules\Committee\Models\CommitteeType;
use Modules\Committee\Models\Designation;
use Modules\Committee\Services\CommitteeMembershipService;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Member\Models\Member;
use Tests\TestCase;

class CommitteeMembershipServiceTest extends TestCase
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

    public function test_it_adds_a_member_to_an_active_committee_term(): void
    {
        $context = $this->createCommitteeContext();

        $designation = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $member = Member::factory()
            ->forOrganization($context['organization']->id)
            ->create([
                'membership_number' => 'TEST-001',
            ]);

        $service = app(CommitteeMembershipService::class);

        $membership = $service->addMember(
            term: $context['term'],
            member: $member,
            designation: $designation,
        );

        $this->assertDatabaseHas('committee_memberships', [
            'id' => $membership->id,
            'committee_term_id' => $context['term']->id,
            'member_id' => $member->id,
            'designation_id' => $designation->id,
            'status' => CommitteeMembershipStatus::ACTIVE->value,
        ]);
    }

    public function test_member_cannot_be_added_to_a_draft_term(): void
    {
        $context = $this->createCommitteeContext();

        $context['term']->update([
            'status' => CommitteeTermStatus::DRAFT,
        ]);

        $designation = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $member = Member::factory()
            ->forOrganization($context['organization']->id)
            ->create([
                'membership_number' => 'TEST-001',
            ]);

        $service = app(CommitteeMembershipService::class);

        $this->expectException(\DomainException::class);

        $service->addMember(
            term: $context['term']->refresh(),
            member: $member,
            designation: $designation,
        );
    }

    public function test_inactive_designation_cannot_be_assigned(): void
    {
        $context = $this->createCommitteeContext();

        $designation = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => false,
        ]);

        $member = Member::factory()
            ->forOrganization($context['organization']->id)
            ->create([
                'membership_number' => 'TEST-001',
            ]);

        $service = app(CommitteeMembershipService::class);

        $this->expectException(\DomainException::class);

        $service->addMember(
            term: $context['term'],
            member: $member,
            designation: $designation,
        );
    }

    public function test_member_cannot_have_two_designations_in_same_term(): void
    {
        $context = $this->createCommitteeContext();

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

        $member = Member::factory()
            ->forOrganization($context['organization']->id)
            ->create([
                'membership_number' => 'TEST-001',
            ]);

        $service = app(CommitteeMembershipService::class);

        $service->addMember(
            term: $context['term'],
            member: $member,
            designation: $treasurer,
        );

        $this->expectException(\DomainException::class);

        $service->addMember(
            term: $context['term']->refresh(),
            member: $member,
            designation: $secretary,
        );
    }

    public function test_member_from_another_organization_cannot_be_added(): void
    {
        $context = $this->createCommitteeContext();

        $otherOrganization = Organization::factory()->create();

        $designation = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $member = Member::factory()
            ->forOrganization($otherOrganization->id)
            ->create([
                'membership_number' => 'TEST-001',
            ]);

        $service = app(CommitteeMembershipService::class);

        $this->expectException(\DomainException::class);

        $service->addMember(
            term: $context['term'],
            member: $member,
            designation: $designation,
        );
    }

    public function test_designation_from_another_organization_cannot_be_assigned(): void
    {
        $context = $this->createCommitteeContext();

        $otherOrganization = Organization::factory()->create();

        $designation = Designation::create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $member = Member::factory()
            ->forOrganization($context['organization']->id)
            ->create([
                'membership_number' => 'TEST-001',
            ]);

        $service = app(CommitteeMembershipService::class);

        $this->expectException(\DomainException::class);

        $service->addMember(
            term: $context['term'],
            member: $member,
            designation: $designation,
        );
    }

    public function test_member_can_belong_to_multiple_committees(): void
    {
        $context = $this->createCommitteeContext();

        $secondCommittee = Committee::create([
            'organization_id' => $context['organization']->id,
            'committee_type_id' => $context['committee']->committee_type_id,
            'unit_id' => null,
            'parent_committee_id' => null,
            'name' => 'Girls Wing Committee 2026',
            'kind' => CommitteeKind::RECURRING,
            'status' => CommitteeStatus::ACTIVE,
        ]);

        $secondTerm = CommitteeTerm::create([
            'committee_id' => $secondCommittee->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => CommitteeTermStatus::ACTIVE,
        ]);

        $designationOne = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $designationTwo = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Secretary',
            'code' => 'SECRETARY',
            'is_active' => true,
        ]);

        $member = Member::factory()
            ->forOrganization($context['organization']->id)
            ->create([
                'membership_number' => 'TEST-001',
            ]);

        $service = app(CommitteeMembershipService::class);

        $firstMembership = $service->addMember(
            term: $context['term'],
            member: $member,
            designation: $designationOne,
        );

        $secondMembership = $service->addMember(
            term: $secondTerm,
            member: $member,
            designation: $designationTwo,
        );

        $this->assertNotSame(
            $firstMembership->id,
            $secondMembership->id
        );

        $this->assertDatabaseCount(
            'committee_memberships',
            2
        );
    }

    public function test_active_membership_can_be_ended(): void
    {
        $context = $this->createCommitteeContext();

        $designation = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $member = Member::factory()
            ->forOrganization($context['organization']->id)
            ->create([
                'membership_number' => 'TEST-001',
            ]);

        $service = app(CommitteeMembershipService::class);

        $membership = $service->addMember(
            term: $context['term'],
            member: $member,
            designation: $designation,
        );

        $membership = $service->end($membership);

        $this->assertSame(
            CommitteeMembershipStatus::ENDED,
            $membership->status
        );
    }

    public function test_active_membership_can_be_cancelled(): void
    {
        $context = $this->createCommitteeContext();

        $designation = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $member = Member::factory()
            ->forOrganization($context['organization']->id)
            ->create([
                'membership_number' => 'TEST-001',
            ]);

        $service = app(CommitteeMembershipService::class);

        $membership = $service->addMember(
            term: $context['term'],
            member: $member,
            designation: $designation,
        );

        $membership = $service->cancel($membership);

        $this->assertSame(
            CommitteeMembershipStatus::CANCELLED,
            $membership->status
        );
    }

    public function test_ended_membership_cannot_be_ended_again(): void
    {
        $context = $this->createCommitteeContext();

        $designation = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $member = Member::factory()
            ->forOrganization($context['organization']->id)
            ->create([
                'membership_number' => 'TEST-001',
            ]);

        $service = app(CommitteeMembershipService::class);

        $membership = $service->addMember(
            term: $context['term'],
            member: $member,
            designation: $designation,
        );

        $service->end($membership);

        $this->expectException(\DomainException::class);

        $service->end($membership->refresh());
    }

    public function test_cancelled_membership_cannot_be_cancelled_again(): void
    {
        $context = $this->createCommitteeContext();

        $designation = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $member = Member::factory()
            ->forOrganization($context['organization']->id)
            ->create([
                'membership_number' => 'TEST-001',
            ]);

        $service = app(CommitteeMembershipService::class);

        $membership = $service->addMember(
            term: $context['term'],
            member: $member,
            designation: $designation,
        );

        $service->cancel($membership);

        $this->expectException(\DomainException::class);

        $service->cancel($membership->refresh());
    }

    public function test_active_membership_in_active_term_is_effective(): void
    {
        $context = $this->createCommitteeContext();

        $designation = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $member = Member::factory()
            ->forOrganization($context['organization']->id)
            ->create([
                'membership_number' => 'TEST-001',
            ]);

        $service = app(CommitteeMembershipService::class);

        $membership = $service->addMember(
            term: $context['term'],
            member: $member,
            designation: $designation,
        );

        $this->assertTrue(
            $service->isEffective($membership)
        );
    }

    public function test_membership_is_not_effective_when_term_is_completed(): void
    {
        $context = $this->createCommitteeContext();

        $designation = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $member = Member::factory()
            ->forOrganization($context['organization']->id)
            ->create([
                'membership_number' => 'TEST-001',
            ]);

        $service = app(CommitteeMembershipService::class);

        $membership = $service->addMember(
            term: $context['term'],
            member: $member,
            designation: $designation,
        );

        $context['term']->update([
            'status' => CommitteeTermStatus::COMPLETED,
        ]);

        $this->assertFalse(
            $service->isEffective($membership->refresh())
        );
    }

    public function test_ended_membership_is_not_effective(): void
    {
        $context = $this->createCommitteeContext();

        $designation = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $member = Member::factory()
            ->forOrganization($context['organization']->id)
            ->create([
                'membership_number' => 'TEST-001',
            ]);

        $service = app(CommitteeMembershipService::class);

        $membership = $service->addMember(
            term: $context['term'],
            member: $member,
            designation: $designation,
        );

        $service->end($membership);

        $this->assertFalse(
            $service->isEffective($membership->refresh())
        );
    }

    public function test_membership_is_not_effective_when_committee_is_archived(): void
    {
        $context = $this->createCommitteeContext();

        $designation = Designation::create([
            'organization_id' => $context['organization']->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);

        $member = Member::factory()
            ->forOrganization($context['organization']->id)
            ->create([
                'membership_number' => 'TEST-001',
            ]);

        $service = app(CommitteeMembershipService::class);

        $membership = $service->addMember(
            term: $context['term'],
            member: $member,
            designation: $designation,
        );

        $context['committee']->update([
            'status' => CommitteeStatus::ARCHIVED,
        ]);

        $this->assertFalse(
            $service->isEffective($membership->refresh())
        );
    }
}