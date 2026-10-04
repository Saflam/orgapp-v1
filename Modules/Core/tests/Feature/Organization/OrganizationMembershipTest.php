<?php

namespace Modules\Core\Tests\Feature\Organization;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Tests\TestCase;

class OrganizationMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_membership_can_be_created(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->assertDatabaseHas('organization_memberships', [
            'id' => $membership->id,
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);
    }

    public function test_organization_membership_belongs_to_organization(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $this->assertTrue(
            $membership->organization->is($organization)
        );
    }

    public function test_organization_membership_belongs_to_user(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $this->assertTrue(
            $membership->user->is($user)
        );
    }

    public function test_user_can_have_organization_memberships(): void
    {
        $user = User::factory()->create();

        $organization1 = Organization::factory()->create();
        $organization2 = Organization::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization1->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        OrganizationMembership::create([
            'organization_id' => $organization2->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $this->assertCount(
            2,
            $user->organizationMemberships
        );
    }

    public function test_same_user_cannot_have_duplicate_membership_in_same_organization(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);
    }

    public function test_same_user_can_belong_to_different_organizations(): void
    {
        $user = User::factory()->create();

        $organization1 = Organization::factory()->create();
        $organization2 = Organization::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization1->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        OrganizationMembership::create([
            'organization_id' => $organization2->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseCount('organization_memberships', 2);
    }
}