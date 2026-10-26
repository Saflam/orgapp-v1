<?php

namespace Modules\Member\Tests\Feature;

use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberDependant;
use Modules\Member\Models\MemberDependantRelationship;
use Modules\Member\Services\MemberDependantRelationshipService;
use Tests\TestCase;

class MemberDependantRelationshipServiceTest extends TestCase
{
    use RefreshDatabase;

    private MemberDependantRelationshipService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(MemberDependantRelationshipService::class);
    }

    public function test_it_can_add_a_child_relationship(): void
    {
        $organization = Organization::factory()->create();

        $member = Member::factory()
            ->forOrganization($organization)
            ->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Alice',
            'last_name' => 'Smith',
        ]);

        $relationship = $this->service->add(
            member: $member,
            dependant: $dependant,
            relationshipType: 'child',
            startedAt: '2026-01-01',
        );

        $this->assertInstanceOf(
            MemberDependantRelationship::class,
            $relationship
        );

        $this->assertDatabaseHas('member_dependant_relationships', [
            'id' => $relationship->id,
            'member_id' => $member->id,
            'dependant_id' => $dependant->id,
            'relationship_type' => 'child',
            'started_at' => '2026-01-01 00:00:00',
            'ended_at' => null,
        ]);
    }

    public function test_it_can_add_multiple_children_for_the_same_member(): void
    {
        $organization = Organization::factory()->create();

        $member = Member::factory()
            ->forOrganization($organization)
            ->create();

        $childOne = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Alice',
        ]);

        $childTwo = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Bob',
        ]);

        $this->service->add(
            member: $member,
            dependant: $childOne,
            relationshipType: 'child',
        );

        $this->service->add(
            member: $member,
            dependant: $childTwo,
            relationshipType: 'child',
        );

        $this->assertDatabaseCount(
            'member_dependant_relationships',
            2
        );
    }

    public function test_it_rejects_duplicate_active_relationship(): void
    {
        $organization = Organization::factory()->create();

        $member = Member::factory()
            ->forOrganization($organization)
            ->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Alice',
        ]);

        $this->service->add(
            member: $member,
            dependant: $dependant,
            relationshipType: 'child',
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'This active dependant relationship already exists.'
        );

        $this->service->add(
            member: $member,
            dependant: $dependant,
            relationshipType: 'child',
        );
    }

    public function test_it_rejects_members_and_dependants_from_different_organizations(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $member = Member::factory()
            ->forOrganization($organizationA)
            ->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organizationB->id,
            'first_name' => 'Alice',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'The member and dependant must belong to the same organization.'
        );

        $this->service->add(
            member: $member,
            dependant: $dependant,
            relationshipType: 'child',
        );
    }

    public function test_it_rejects_a_converted_dependant(): void
    {
        $organization = Organization::factory()->create();

        $member = Member::factory()
            ->forOrganization($organization)
            ->create();

        $convertedMember = Member::factory()
            ->forOrganization($organization)
            ->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Alice',
            'converted_member_id' => $convertedMember->id,
            'converted_at' => '2026-01-01',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'A converted dependant cannot be assigned as a dependant relationship.'
        );

        $this->service->add(
            member: $member,
            dependant: $dependant,
            relationshipType: 'child',
        );
    }

    public function test_it_allows_only_one_active_spouse_relationship(): void
    {
        $organization = Organization::factory()->create();

        $member = Member::factory()
            ->forOrganization($organization)
            ->create();

        $spouseOne = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Mary',
        ]);

        $spouseTwo = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Jane',
        ]);

        $this->service->add(
            member: $member,
            dependant: $spouseOne,
            relationshipType: 'spouse',
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'A member can have only one active spouse relationship.'
        );

        $this->service->add(
            member: $member,
            dependant: $spouseTwo,
            relationshipType: 'spouse',
        );
    }

    public function test_it_can_have_another_spouse_after_previous_relationship_ended(): void
    {
        $organization = Organization::factory()->create();

        $member = Member::factory()
            ->forOrganization($organization)
            ->create();

        $spouseOne = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Mary',
        ]);

        $spouseTwo = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Jane',
        ]);

        $relationship = $this->service->add(
            member: $member,
            dependant: $spouseOne,
            relationshipType: 'spouse',
            startedAt: '2020-01-01',
        );

        $this->service->end(
            relationship: $relationship,
            endedAt: '2025-12-31',
        );

        $newRelationship = $this->service->add(
            member: $member,
            dependant: $spouseTwo,
            relationshipType: 'spouse',
            startedAt: '2026-01-01',
        );

        $this->assertSame(
            $spouseTwo->id,
            $newRelationship->dependant_id
        );

        $this->assertNull($newRelationship->ended_at);
    }

    public function test_it_can_update_a_relationship(): void
    {
        $organization = Organization::factory()->create();

        $member = Member::factory()
            ->forOrganization($organization)
            ->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Alice',
        ]);

        $relationship = $this->service->add(
            member: $member,
            dependant: $dependant,
            relationshipType: 'child',
            startedAt: '2026-01-01',
        );

        $updated = $this->service->update(
            relationship: $relationship,
            startedAt: '2026-02-01',
        );

        $this->assertSame(
            '2026-02-01',
            $updated->started_at->toDateString()
        );
    }

    public function test_it_rejects_an_end_date_before_the_start_date(): void
    {
        $organization = Organization::factory()->create();

        $member = Member::factory()
            ->forOrganization($organization)
            ->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Alice',
        ]);

        $relationship = $this->service->add(
            member: $member,
            dependant: $dependant,
            relationshipType: 'child',
            startedAt: '2026-06-01',
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'The relationship end date cannot be before the start date.'
        );

        $this->service->end(
            relationship: $relationship,
            endedAt: '2026-05-31',
        );
    }

    public function test_it_can_end_an_active_relationship(): void
    {
        $organization = Organization::factory()->create();

        $member = Member::factory()
            ->forOrganization($organization)
            ->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Alice',
        ]);

        $relationship = $this->service->add(
            member: $member,
            dependant: $dependant,
            relationshipType: 'child',
            startedAt: '2026-01-01',
        );

        $ended = $this->service->end(
            relationship: $relationship,
            endedAt: '2026-06-30',
        );

        $this->assertSame(
            '2026-06-30',
            $ended->ended_at->toDateString()
        );
    }

    public function test_it_rejects_ending_an_already_ended_relationship(): void
    {
        $organization = Organization::factory()->create();

        $member = Member::factory()
            ->forOrganization($organization)
            ->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Alice',
        ]);

        $relationship = $this->service->add(
            member: $member,
            dependant: $dependant,
            relationshipType: 'child',
            startedAt: '2026-01-01',
        );

        $this->service->end(
            relationship: $relationship,
            endedAt: '2026-06-30',
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Only an active dependant relationship can be ended.'
        );

        $this->service->end(
            relationship: $relationship,
            endedAt: '2026-07-01',
        );
    }
}