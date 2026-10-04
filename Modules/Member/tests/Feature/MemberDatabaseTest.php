<?php

namespace Modules\Member\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Member\Enums\MembershipStatus;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberRelationship;
use Modules\Member\Models\Membership;
use Modules\Member\Models\MembershipType;
use Tests\TestCase;

class MemberDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_type_belongs_to_an_organization(): void
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'code' => 'TEST',
            'slug' => 'test',
        ]);

        $membershipType = MembershipType::create([
            'organization_id' => $organization->id,
            'name' => 'Primary',
            'code' => 'PRIMARY',
        ]);

        $this->assertTrue(
            $membershipType->organization->is($organization)
        );
    }

    public function test_membership_type_code_is_unique_within_an_organization(): void
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'code' => 'TEST',
            'slug' => 'test',
        ]);

        MembershipType::create([
            'organization_id' => $organization->id,
            'name' => 'Primary',
            'code' => 'PRIMARY',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        MembershipType::create([
            'organization_id' => $organization->id,
            'name' => 'Another Primary',
            'code' => 'PRIMARY',
        ]);
    }

    public function test_same_membership_type_code_can_exist_in_different_organizations(): void
    {
        $organizationA = Organization::create([
            'name' => 'Organization A',
            'code' => 'ORGA',
            'slug' => 'organization-a',
        ]);

        $organizationB = Organization::create([
            'name' => 'Organization B',
            'code' => 'ORGB',
            'slug' => 'organization-b',
        ]);

        $typeA = MembershipType::create([
            'organization_id' => $organizationA->id,
            'name' => 'Primary',
            'code' => 'PRIMARY',
        ]);

        $typeB = MembershipType::create([
            'organization_id' => $organizationB->id,
            'name' => 'Primary',
            'code' => 'PRIMARY',
        ]);

        $this->assertNotSame($typeA->id, $typeB->id);
    }

    public function test_membership_belongs_to_member_and_membership_type(): void
    {
        $organization = Organization::factory()->create();

        $membershipType = MembershipType::create([
            'organization_id' => $organization->id,
            'name' => 'Primary',
            'code' => 'PRIMARY',
        ]);

        $member = Member::factory()
            ->forOrganization($organization)
            ->create([
                'membership_number' => 'M-0001',
            ]);

        $membership = Membership::create([
            'member_id' => $member->id,
            'membership_type_id' => $membershipType->id,
            'starts_at' => '2026-01-01',
            'ends_at' => '2026-12-31',
            'status' => 'active',
        ]);

        $this->assertTrue(
            $membership->member->is($member)
        );

        $this->assertTrue(
            $membership->membershipType->is($membershipType)
        );
    }

    public function test_membership_dates_are_cast_to_dates(): void
    {
        $organization = Organization::factory()->create();

        $membershipType = MembershipType::create([
            'organization_id' => $organization->id,
            'name' => 'Primary',
            'code' => 'PRIMARY',
        ]);

        $member = Member::factory()
            ->forOrganization($organization)
            ->create([
                'membership_number' => 'M-0001',
            ]);

        $membership = Membership::create([
            'member_id' => $member->id,
            'membership_type_id' => $membershipType->id,
            'starts_at' => '2026-01-01',
            'ends_at' => '2026-12-31',
            'status' => 'active',
        ]);

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $membership->starts_at
        );

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $membership->ends_at
        );
    }

    public function test_member_relationship_connects_two_members(): void
    {
        $organization = Organization::factory()->create();

        $memberA = Member::factory()
            ->forOrganization($organization)
            ->create([
                'membership_number' => 'M-0001',
            ]);

        $memberB = Member::factory()
            ->forOrganization($organization)
            ->create([
                'membership_number' => 'M-0002',
            ]);

        $relationship = MemberRelationship::create([
            'member_id' => $memberA->id,
            'related_member_id' => $memberB->id,
            'relationship_type' => 'spouse',
            'started_at' => '2026-01-01',
        ]);

        $this->assertTrue(
            $relationship->member->is($memberA)
        );

        $this->assertTrue(
            $relationship->relatedMember->is($memberB)
        );
    }

    public function test_same_relationship_cannot_be_recorded_twice_in_the_same_direction(): void
    {
        $organization = Organization::factory()->create();

        $memberA = Member::factory()
            ->forOrganization($organization)
            ->create([
                'membership_number' => 'M-0001',
            ]);

        $memberB = Member::factory()
            ->forOrganization($organization)
            ->create([
                'membership_number' => 'M-0002',
            ]);

        MemberRelationship::create([
            'member_id' => $memberA->id,
            'related_member_id' => $memberB->id,
            'relationship_type' => 'spouse',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        MemberRelationship::create([
            'member_id' => $memberA->id,
            'related_member_id' => $memberB->id,
            'relationship_type' => 'spouse',
        ]);
    }

    public function test_reverse_relationship_is_a_separate_relationship(): void
    {
        $organization = Organization::factory()->create();

        $memberA = Member::factory()
            ->forOrganization($organization)
            ->create([
                'membership_number' => 'M-0001',
            ]);

        $memberB = Member::factory()
            ->forOrganization($organization)
            ->create([
                'membership_number' => 'M-0002',
            ]);

        $relationshipAtoB = MemberRelationship::create([
            'member_id' => $memberA->id,
            'related_member_id' => $memberB->id,
            'relationship_type' => 'spouse',
        ]);

        $relationshipBtoA = MemberRelationship::create([
            'member_id' => $memberB->id,
            'related_member_id' => $memberA->id,
            'relationship_type' => 'spouse',
        ]);

        $this->assertNotSame(
            $relationshipAtoB->id,
            $relationshipBtoA->id
        );
    }

    public function test_membership_status_is_cast_to_membership_status_enum(): void
    {
        $organization = Organization::factory()->create();

        $membershipType = MembershipType::create([
            'organization_id' => $organization->id,
            'name' => 'Primary',
            'code' => 'PRIMARY',
        ]);

        $member = Member::factory()
            ->forOrganization($organization)
            ->create([
                'membership_number' => 'M-0001',
            ]);

        $membership = Membership::create([
            'member_id' => $member->id,
            'membership_type_id' => $membershipType->id,
            'starts_at' => '2026-01-01',
            'ends_at' => '2026-12-31',
            'status' => MembershipStatus::ACTIVE,
        ]);

        $membership->refresh();

        $this->assertSame(
            MembershipStatus::ACTIVE,
            $membership->status
        );

        $this->assertSame(
            'active',
            $membership->status->value
        );
    }
}