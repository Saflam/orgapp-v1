<?php

namespace Modules\Member\Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Member\Models\Member;
use Modules\Member\Tests\Concerns\ActsAsMemberOrganizationUser;
use Tests\TestCase;

class MemberApiTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsMemberOrganizationUser;

    public function test_members_can_be_listed(): void
    {
        $organization = Organization::factory()->create();

        $this->actAsMemberOrganizationAdmin($organization);

        $firstUser = User::factory()->create([
            'name' => 'John Doe',
        ]);

        $secondUser = User::factory()->create([
            'name' => 'Jane Doe',
        ]);

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $firstUser->id,
            'status' => 'active',
        ]);

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $secondUser->id,
            'status' => 'active',
        ]);

        Member::create([
            'organization_id' => $organization->id,
            'user_id' => $firstUser->id,
            'membership_number' => 'MEM-0002',
        ]);

        Member::create([
            'organization_id' => $organization->id,
            'user_id' => $secondUser->id,
            'membership_number' => 'MEM-0001',
        ]);

        $response = $this->getJson(
            $this->organizationUrl(
                $organization,
                '/api/v1/organizations/' . $organization->id . '/members'
            )
        );

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.membership_number', 'MEM-0001')
            ->assertJsonPath('data.0.name', 'Jane Doe')
            ->assertJsonPath('data.1.membership_number', 'MEM-0002')
            ->assertJsonPath('data.1.name', 'John Doe');
    }

    public function test_a_member_can_be_shown(): void
    {
        $organization = Organization::factory()->create();

        $this->actAsMemberOrganizationAdmin($organization);

        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '9876543210',
        ]);

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $member = Member::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_number' => 'MEM-0001',
        ]);

        $response = $this->getJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/{$member->id}"
            )
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $member->id)
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.name', 'John Doe')
            ->assertJsonPath('data.email', 'john@example.com')
            ->assertJsonPath('data.phone', '9876543210')
            ->assertJsonPath('data.membership_number', 'MEM-0001');
    }

    public function test_a_member_can_be_created_for_an_existing_organization_user(): void
    {
        $organization = Organization::factory()->create();

        $this->actAsMemberOrganizationAdmin($organization);

        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $response = $this->postJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members"
            ),
            [
                'user_id' => $user->id,
                'membership_number' => 'MEM-0001',
                'date_of_birth' => '1990-01-15',
                'joined_at' => '2026-10-03',
                'metadata' => [
                    'source' => 'api',
                ],
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.name', 'John Doe')
            ->assertJsonPath('data.membership_number', 'MEM-0001');

        $this->assertDatabaseHas('members', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_number' => 'MEM-0001',
        ]);
    }

    public function test_member_creation_rejects_a_user_from_another_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $this->actAsMemberOrganizationAdmin($organizationA);

        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organizationB->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $response = $this->postJson(
            $this->organizationUrl(
                $organizationA,
                "/api/v1/organizations/{$organizationA->id}/members"
            ),
            [
                'user_id' => $user->id,
                'membership_number' => 'MEM-0001',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'user_id',
            ]);
    }

    public function test_member_creation_rejects_duplicate_user_in_same_organization(): void
    {
        $organization = Organization::factory()->create();

        $this->actAsMemberOrganizationAdmin($organization);

        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        Member::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_number' => 'MEM-0001',
        ]);

        $response = $this->postJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members"
            ),
            [
                'user_id' => $user->id,
                'membership_number' => 'MEM-0002',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'user_id',
            ]);
    }

    public function test_member_creation_rejects_duplicate_membership_number(): void
    {
        $organization = Organization::factory()->create();

        $this->actAsMemberOrganizationAdmin($organization);

        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $firstUser->id,
            'status' => 'active',
        ]);

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $secondUser->id,
            'status' => 'active',
        ]);

        Member::create([
            'organization_id' => $organization->id,
            'user_id' => $firstUser->id,
            'membership_number' => 'MEM-0001',
        ]);

        $response = $this->postJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members"
            ),
            [
                'user_id' => $secondUser->id,
                'membership_number' => 'MEM-0001',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'membership_number',
            ]);
    }

    public function test_member_can_be_updated(): void
    {
        $organization = Organization::factory()->create();

        $this->actAsMemberOrganizationAdmin($organization);

        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $member = Member::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_number' => 'MEM-0001',
        ]);

        $response = $this->putJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/{$member->id}"
            ),
            [
                'membership_number' => 'MEM-0002',
                'date_of_birth' => '1990-01-15',
                'metadata' => [
                    'updated' => true,
                ],
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $member->id)
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.membership_number', 'MEM-0002')
            ->assertJsonPath('data.date_of_birth', '1990-01-15')
            ->assertJsonPath('data.metadata.updated', true);
    }

    public function test_member_update_cannot_change_user_id(): void
    {
        $organization = Organization::factory()->create();

        $this->actAsMemberOrganizationAdmin($organization);

        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $otherUser->id,
            'status' => 'active',
        ]);

        $member = Member::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_number' => 'MEM-0001',
        ]);

        $response = $this->putJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/{$member->id}"
            ),
            [
                'user_id' => $otherUser->id,
                'membership_number' => 'MEM-0002',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.membership_number', 'MEM-0002');
    }

    public function test_member_from_another_organization_cannot_be_shown(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $this->actAsMemberOrganizationAdmin($organizationA);

        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organizationB->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $member = Member::create([
            'organization_id' => $organizationB->id,
            'user_id' => $user->id,
            'membership_number' => 'MEM-0001',
        ]);

        $response = $this->getJson(
            $this->organizationUrl(
                $organizationA,
                "/api/v1/organizations/{$organizationB->id}/members/{$member->id}"
            )
        );

        $response->assertNotFound();
    }

    public function test_member_from_another_organization_cannot_be_updated(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $this->actAsMemberOrganizationAdmin($organizationA);

        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organizationB->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $member = Member::create([
            'organization_id' => $organizationB->id,
            'user_id' => $user->id,
            'membership_number' => 'MEM-0001',
        ]);

        $response = $this->putJson(
            $this->organizationUrl(
                $organizationA,
                "/api/v1/organizations/{$organizationB->id}/members/{$member->id}"
            ),
            [
                'membership_number' => 'MEM-9999',
            ]
        );

        $response->assertNotFound();

        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'membership_number' => 'MEM-0001',
        ]);
    }

    public function test_member_list_is_scoped_to_the_current_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $this->actAsMemberOrganizationAdmin($organizationA);

        $userA = User::factory()->create([
            'name' => 'Organization A Member',
        ]);

        $userB = User::factory()->create([
            'name' => 'Organization B Member',
        ]);

        OrganizationMembership::create([
            'organization_id' => $organizationA->id,
            'user_id' => $userA->id,
            'status' => 'active',
        ]);

        OrganizationMembership::create([
            'organization_id' => $organizationB->id,
            'user_id' => $userB->id,
            'status' => 'active',
        ]);

        Member::create([
            'organization_id' => $organizationA->id,
            'user_id' => $userA->id,
            'membership_number' => 'A-0001',
        ]);

        Member::create([
            'organization_id' => $organizationB->id,
            'user_id' => $userB->id,
            'membership_number' => 'B-0001',
        ]);

        $response = $this->getJson(
            $this->organizationUrl(
                $organizationA,
                "/api/v1/organizations/{$organizationA->id}/members"
            )
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.membership_number', 'A-0001')
            ->assertJsonMissing([
                'membership_number' => 'B-0001',
            ]);
    }

    public function test_member_list_requires_view_permission(): void
    {
        $organization = Organization::factory()->create();

        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'example.test',
        ]);

        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        app(\Modules\Core\Services\OrganizationModuleService::class)->enable(
            $organization,
            'Member',
        );

        $this->actingAs($user);

        $response = $this->getJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members"
            )
        );

        $response->assertForbidden();
    }

    public function test_member_creation_requires_update_permission(): void
    {
        $organization = Organization::factory()->create();

        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'example.test',
        ]);

        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        app(\Modules\Core\Services\OrganizationModuleService::class)->enable(
            $organization,
            'Member',
        );

        $this->actingAs($user);

        $response = $this->postJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members"
            ),
            [
                'user_id' => $user->id,
                'membership_number' => 'MEM-0001',
            ]
        );

        $response->assertForbidden();
    }

    public function test_member_update_requires_update_permission(): void
    {
        $organization = Organization::factory()->create();

        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'example.test',
        ]);

        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        app(\Modules\Core\Services\OrganizationModuleService::class)->enable(
            $organization,
            'Member',
        );

        $member = Member::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_number' => 'MEM-0001',
        ]);

        $this->actingAs($user);

        $response = $this->putJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/{$member->id}"
            ),
            [
                'membership_number' => 'MEM-0002',
            ]
        );

        $response->assertForbidden();
    }

    public function test_member_endpoints_require_member_module_to_be_enabled(): void
    {
        $organization = Organization::factory()->create();

        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'example.test',
        ]);

        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $this->actingAs($user);

        $response = $this->getJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members"
            )
        );

        $response->assertForbidden();
    }

    public function test_member_creation_rejects_invalid_unit(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();

        $this->actAsMemberOrganizationAdmin($organization);

        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $unit = Unit::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);

        $response = $this->postJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members"
            ),
            [
                'user_id' => $user->id,
                'unit_id' => $unit->id,
                'membership_number' => 'MEM-0001',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'unit_id',
            ]);
    }

    public function test_a_member_can_view_own_profile(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '9876543210',
        ]);

        $user->details()->create([
            'date_of_birth' => '1990-01-15',
        ]);

        $this->actAsMemberOrganizationAdmin(
            $organization,
            $user,
        );

        $member = Member::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_number' => 'MEM-0001',
        ]);

        $response = $this->getJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/me"
            )
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $member->id)
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.name', 'John Doe')
            ->assertJsonPath('data.email', 'john@example.com')
            ->assertJsonPath('data.phone', '9876543210')
            ->assertJsonPath('data.membership_number', 'MEM-0001')
            ->assertJsonPath('data.date_of_birth', '1990-01-15');
    }

    public function test_a_member_can_update_own_profile(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '9876543210',
        ]);

        $user->details()->create([
            'date_of_birth' => '1990-01-15',
        ]);

        $this->actAsMemberOrganizationAdmin(
            $organization,
            $user,
        );

        $member = Member::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_number' => 'MEM-0001',
        ]);

        $response = $this->putJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/me"
            ),
            [
                'name' => 'John Updated',
                'email' => 'john.updated@example.com',
                'phone' => '9999999999',
                'date_of_birth' => '1991-02-20',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $member->id)
            ->assertJsonPath('data.name', 'John Updated')
            ->assertJsonPath('data.email', 'john.updated@example.com')
            ->assertJsonPath('data.phone', '9999999999')
            ->assertJsonPath('data.membership_number', 'MEM-0001')
            ->assertJsonPath('data.date_of_birth', '1991-02-20');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'John Updated',
            'email' => 'john.updated@example.com',
            'phone' => '9999999999',
        ]);

        $user->load('details');

        $this->assertNotNull($user->details);

        $this->assertSame(
            '1991-02-20',
            $user->details->date_of_birth->toDateString()
        );

        $member->refresh();

        $this->assertSame(
            'MEM-0001',
            $member->membership_number
        );
    }

    public function test_member_profile_update_cannot_change_membership_fields(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create();

        $this->actAsMemberOrganizationAdmin(
            $organization,
            $user,
        );

        $member = Member::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_number' => 'MEM-0001',
        ]);

        $response = $this->putJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/me"
            ),
            [
                'membership_number' => 'MEM-9999',
                'user_id' => User::factory()->create()->id,
            ]
        );

        $response->assertOk();

        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'user_id' => $user->id,
            'membership_number' => 'MEM-0001',
        ]);
    }

    public function test_user_without_member_record_cannot_view_member_profile(): void
    {
        $organization = Organization::factory()->create();

        $this->actAsMemberOrganizationAdmin($organization);

        $response = $this->getJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/me"
            )
        );

        $response->assertNotFound();
    }

    public function test_member_profile_requires_view_own_profile_permission(): void
    {
        $organization = Organization::factory()->create();

        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'example.test',
        ]);

        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        app(\Modules\Core\Services\OrganizationModuleService::class)->enable(
            $organization,
            'Member',
        );

        $this->actingAs($user);

        $response = $this->getJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/me"
            )
        );

        $response->assertForbidden();
    }

    public function test_member_profile_update_requires_update_own_profile_permission(): void
    {
        $organization = Organization::factory()->create();

        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'example.test',
        ]);

        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        app(\Modules\Core\Services\OrganizationModuleService::class)->enable(
            $organization,
            'Member',
        );

        $this->actingAs($user);

        $response = $this->putJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/me"
            ),
            [
                'name' => 'Updated Name',
            ]
        );

        $response->assertForbidden();
    }
}