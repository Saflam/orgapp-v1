<?php

namespace Modules\Member\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberDependant;
use Modules\Member\Models\MemberDependantRelationship;
use Tests\TestCase;

class MemberDependantModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_dependant_belongs_to_an_organization(): void
    {
        $organization = Organization::factory()->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Mary',
        ]);

        $this->assertTrue(
            $dependant->organization->is($organization)
        );
    }

    public function test_a_dependant_can_have_multiple_member_relationships(): void
    {
        $organization = Organization::factory()->create();

        $john = Member::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $mary = Member::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'David',
        ]);

        MemberDependantRelationship::create([
            'member_id' => $john->id,
            'dependant_id' => $dependant->id,
            'relationship_type' => 'child',
        ]);

        MemberDependantRelationship::create([
            'member_id' => $mary->id,
            'dependant_id' => $dependant->id,
            'relationship_type' => 'child',
        ]);

        $this->assertCount(
            2,
            $dependant->relationships
        );
    }

    public function test_a_relationship_belongs_to_a_member(): void
    {
        $organization = Organization::factory()->create();

        $member = Member::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'David',
        ]);

        $relationship = MemberDependantRelationship::create([
            'member_id' => $member->id,
            'dependant_id' => $dependant->id,
            'relationship_type' => 'child',
        ]);

        $this->assertTrue(
            $relationship->member->is($member)
        );
    }

    public function test_a_relationship_belongs_to_a_dependant(): void
    {
        $organization = Organization::factory()->create();

        $member = Member::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'David',
        ]);

        $relationship = MemberDependantRelationship::create([
            'member_id' => $member->id,
            'dependant_id' => $dependant->id,
            'relationship_type' => 'child',
        ]);

        $this->assertTrue(
            $relationship->dependant->is($dependant)
        );
    }

    public function test_a_member_can_retrieve_dependant_relationships(): void
    {
        $organization = Organization::factory()->create();

        $member = Member::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'David',
        ]);

        MemberDependantRelationship::create([
            'member_id' => $member->id,
            'dependant_id' => $dependant->id,
            'relationship_type' => 'child',
        ]);

        $this->assertCount(
            1,
            $member->dependantRelationships
        );
    }

    public function test_a_member_can_retrieve_dependants(): void
    {
        $organization = Organization::factory()->create();

        $member = Member::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'David',
        ]);

        MemberDependantRelationship::create([
            'member_id' => $member->id,
            'dependant_id' => $dependant->id,
            'relationship_type' => 'child',
        ]);

        $this->assertCount(
            1,
            $member->dependants
        );

        $this->assertTrue(
            $member->dependants->first()->is($dependant)
        );
    }

    public function test_a_converted_dependant_can_resolve_the_member(): void
    {
        $organization = Organization::factory()->create();

        $member = Member::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Mary',
            'converted_member_id' => $member->id,
            'converted_at' => '2026-09-24',
        ]);

        $this->assertTrue(
            $dependant->convertedMember->is($member)
        );
    }
}