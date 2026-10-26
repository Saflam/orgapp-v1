<?php

namespace Modules\Member\Tests\Feature;

use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Member\Enums\MemberRelationshipType;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberRelationship;
use Modules\Member\Services\MemberRelationshipService;
use Tests\TestCase;

class MemberRelationshipServiceTest extends TestCase
{
    use RefreshDatabase;

    private MemberRelationshipService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(MemberRelationshipService::class);
    }

    private function createOrganization(
        string $name = 'Test Organization',
    ): Organization {
        $slug = strtolower(str_replace(' ', '-', $name));

        return Organization::create([
            'name' => $name,
            'code' => $slug,
            'slug' => $slug,
        ]);
    }

    private function createMember(
        int $organizationId,
        string $firstName = 'John',
    ): Member {
        $organization = Organization::query()->findOrFail($organizationId);

        $user = \App\Models\User::factory()->create([
            'name' => $firstName . ' Test',
        ]);

        \App\Models\OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        return Member::factory()
            ->forOrganization($organization)
            ->forUser($user)
            ->create([
                'membership_number' => fake()->unique()->numerify('MEM-#####'),
            ]);
    }

    public function test_it_can_add_a_member_relationship(): void
    {
        $organization = $this->createOrganization();

        $member = $this->createMember(
            $organization->id,
            'John'
        );

        $child = $this->createMember(
            $organization->id,
            'Michael'
        );

        $relationship = $this->service->add(
            member: $member,
            relatedMember: $child,
            relationshipType: MemberRelationshipType::CHILD,
            startedAt: Carbon::parse('2026-01-01'),
        );

        $this->assertInstanceOf(
            MemberRelationship::class,
            $relationship
        );

        $this->assertDatabaseHas('member_relationships', [
            'id' => $relationship->id,
            'member_id' => $member->id,
            'related_member_id' => $child->id,
            'relationship_type' => MemberRelationshipType::CHILD->value,
            'started_at' => '2026-01-01 00:00:00',
            'ended_at' => null,
        ]);
    }

    public function test_it_rejects_a_relationship_with_the_same_member(): void
    {
        $organization = $this->createOrganization();

        $member = $this->createMember(
            $organization->id
        );

        $this->expectException(DomainException::class);

        $this->expectExceptionMessage(
            'A member cannot have a relationship with themselves.'
        );

        $this->service->add(
            member: $member,
            relatedMember: $member,
            relationshipType: MemberRelationshipType::CHILD,
        );
    }

    public function test_it_rejects_members_from_different_organizations(): void
    {
        $organizationA = $this->createOrganization('Organization A');
        $organizationB = $this->createOrganization('Organization B');

        $member = $this->createMember(
            $organizationA->id,
            'John'
        );

        $relatedMember = $this->createMember(
            $organizationB->id,
            'Michael'
        );

        $this->expectException(DomainException::class);

        $this->expectExceptionMessage(
            'Both members must belong to the same organization.'
        );

        $this->service->add(
            member: $member,
            relatedMember: $relatedMember,
            relationshipType: MemberRelationshipType::CHILD,
        );
    }

    public function test_it_rejects_a_duplicate_active_relationship(): void
    {
        $organization = $this->createOrganization();

        $member = $this->createMember(
            $organization->id,
            'John'
        );

        $child = $this->createMember(
            $organization->id,
            'Michael'
        );

        $this->service->add(
            member: $member,
            relatedMember: $child,
            relationshipType: MemberRelationshipType::CHILD,
        );

        $this->expectException(DomainException::class);

        $this->expectExceptionMessage(
            'This active relationship already exists.'
        );

        $this->service->add(
            member: $member,
            relatedMember: $child,
            relationshipType: MemberRelationshipType::CHILD,
        );
    }

    public function test_it_can_update_a_relationship(): void
    {
        $organization = $this->createOrganization();

        $member = $this->createMember(
            $organization->id,
            'John'
        );

        $child = $this->createMember(
            $organization->id,
            'Michael'
        );

        $sibling = $this->createMember(
            $organization->id,
            'David'
        );

        $relationship = $this->service->add(
            member: $member,
            relatedMember: $child,
            relationshipType: MemberRelationshipType::CHILD,
            startedAt: Carbon::parse('2026-01-01'),
        );

        $updated = $this->service->update(
            relationship: $relationship,
            relatedMember: $sibling,
            relationshipType: MemberRelationshipType::SIBLING,
            startedAt: Carbon::parse('2026-02-01'),
            metadata: [
                'note' => 'Corrected relationship',
            ],
        );

        $this->assertSame(
            $sibling->id,
            $updated->related_member_id
        );

        $this->assertSame(
            MemberRelationshipType::SIBLING->value,
            $updated->relationship_type
        );

        $this->assertDatabaseHas('member_relationships', [
            'id' => $relationship->id,
            'related_member_id' => $sibling->id,
            'relationship_type' => MemberRelationshipType::SIBLING->value,
            'started_at' => '2026-02-01 00:00:00',
        ]);
    }

    public function test_it_rejects_an_end_date_before_the_start_date(): void
    {
        $organization = $this->createOrganization();

        $member = $this->createMember(
            $organization->id,
            'John'
        );

        $child = $this->createMember(
            $organization->id,
            'Michael'
        );

        $relationship = $this->service->add(
            member: $member,
            relatedMember: $child,
            relationshipType: MemberRelationshipType::CHILD,
            startedAt: Carbon::parse('2026-06-01'),
        );

        $this->expectException(DomainException::class);

        $this->expectExceptionMessage(
            'The relationship end date cannot be before the start date.'
        );

        $this->service->update(
            relationship: $relationship,
            relatedMember: $child,
            relationshipType: MemberRelationshipType::CHILD,
            startedAt: Carbon::parse('2026-06-01'),
            endedAt: Carbon::parse('2026-05-31'),
        );
    }

    public function test_it_can_end_an_active_relationship(): void
    {
        $organization = $this->createOrganization();

        $member = $this->createMember(
            $organization->id,
            'John'
        );

        $child = $this->createMember(
            $organization->id,
            'Michael'
        );

        $relationship = $this->service->add(
            member: $member,
            relatedMember: $child,
            relationshipType: MemberRelationshipType::CHILD,
            startedAt: Carbon::parse('2026-01-01'),
        );

        $ended = $this->service->end(
            relationship: $relationship,
            endedAt: Carbon::parse('2026-06-30'),
        );

        $this->assertSame(
            '2026-06-30',
            $ended->ended_at->format('Y-m-d')
        );

        $this->assertDatabaseHas('member_relationships', [
            'id' => $relationship->id,
            'ended_at' => '2026-06-30 00:00:00',
        ]);
    }

    public function test_it_rejects_ending_an_already_ended_relationship(): void
    {
        $organization = $this->createOrganization();

        $member = $this->createMember(
            $organization->id,
            'John'
        );

        $child = $this->createMember(
            $organization->id,
            'Michael'
        );

        $relationship = $this->service->add(
            member: $member,
            relatedMember: $child,
            relationshipType: MemberRelationshipType::CHILD,
            startedAt: Carbon::parse('2026-01-01'),
        );

        $this->service->end(
            relationship: $relationship,
            endedAt: Carbon::parse('2026-06-30'),
        );

        $this->expectException(DomainException::class);

        $this->expectExceptionMessage(
            'This relationship has already ended.'
        );

        $this->service->end(
            relationship: $relationship,
            endedAt: Carbon::parse('2026-07-01'),
        );
    }

    public function test_it_cannot_reopen_an_ended_relationship(): void
    {
        $organization = $this->createOrganization();

        $member = $this->createMember(
            $organization->id,
            'John'
        );

        $child = $this->createMember(
            $organization->id,
            'Michael'
        );

        $relationship = $this->service->add(
            member: $member,
            relatedMember: $child,
            relationshipType: MemberRelationshipType::CHILD,
            startedAt: Carbon::parse('2026-01-01'),
        );

        $this->service->end(
            relationship: $relationship,
            endedAt: Carbon::parse('2026-06-30'),
        );

        $this->expectException(DomainException::class);

        $this->expectExceptionMessage(
            'An ended relationship cannot be reopened through an update.'
        );

        $this->service->update(
            relationship: $relationship,
            relatedMember: $child,
            relationshipType: MemberRelationshipType::CHILD,
            startedAt: Carbon::parse('2026-01-01'),
            endedAt: null,
        );
    }
}