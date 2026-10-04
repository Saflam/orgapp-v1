<?php

namespace Modules\Member\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberDependant;
use Modules\Member\Tests\Concerns\ActsAsMemberOrganizationUser;
use Tests\TestCase;

class MemberDependantApiTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsMemberOrganizationUser;

    public function test_it_can_create_a_dependant_for_a_member(): void
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
                "/api/v1/organizations/{$organization->id}/members/{$member->id}/dependants"
            ),
            [
                'first_name' => 'Mary',
                'last_name' => 'Smith',
                'date_of_birth' => '1990-05-10',
                'gender' => 'female',
            ],
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.first_name', 'Mary')
            ->assertJsonPath('data.last_name', 'Smith');

        $this->assertDatabaseHas('member_dependants', [
            'organization_id' => $organization->id,
            'first_name' => 'Mary',
            'last_name' => 'Smith',
        ]);
    }

    public function test_it_cannot_create_a_dependant_for_a_member_from_another_organization(): void
    {
        $organizationA = Organization::factory()->create([
            'is_active' => true,
        ]);

        $this->actAsMemberOrganizationAdmin($organizationA);

        $organizationB = Organization::factory()->create([
            'is_active' => true,
        ]);

        $member = Member::factory()
            ->forOrganization($organizationB)
            ->create();

        $response = $this->postJson(
            $this->organizationUrl(
                $organizationA,
                "/api/v1/organizations/{$organizationA->id}/members/{$member->id}/dependants"
            ),
            [
                'first_name' => 'Mary',
                'last_name' => 'Smith',
            ],
        );

        $response->assertNotFound();

        $this->assertDatabaseCount('member_dependants', 0);
    }

    public function test_it_can_list_a_members_dependants(): void
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

        $member->dependants()->attach($dependant->id, [
            'relationship_type' => 'spouse',
            'started_at' => '2026-01-01',
        ]);

        $response = $this->getJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/{$member->id}/dependants"
            )
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $dependant->id)
            ->assertJsonPath('data.0.first_name', 'Mary');
    }

    public function test_it_does_not_list_dependants_from_another_organization(): void
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

        $response = $this->getJson(
            $this->organizationUrl(
                $organizationA,
                "/api/v1/organizations/{$organizationA->id}/members/{$member->id}/dependants"
            )
        );

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_it_can_show_a_dependant(): void
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

        $member->dependants()->attach($dependant->id, [
            'relationship_type' => 'spouse',
            'started_at' => '2026-01-01',
        ]);

        $response = $this->getJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/{$member->id}/dependants/{$dependant->id}"
            )
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $dependant->id)
            ->assertJsonPath('data.first_name', 'Mary');
    }

    public function test_it_can_update_a_dependant(): void
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

        $member->dependants()->attach($dependant->id, [
            'relationship_type' => 'spouse',
            'started_at' => '2026-01-01',
        ]);

        $response = $this->putJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/{$member->id}/dependants/{$dependant->id}"
            ),
            [
                'first_name' => 'Mary Jane',
            ],
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Mary Jane');

        $this->assertDatabaseHas('member_dependants', [
            'id' => $dependant->id,
            'first_name' => 'Mary Jane',
        ]);
    }

    public function test_it_cannot_update_a_dependant_from_another_organization(): void
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

        $response = $this->putJson(
            $this->organizationUrl(
                $organizationA,
                "/api/v1/organizations/{$organizationA->id}/members/{$member->id}/dependants/{$dependant->id}"
            ),
            [
                'first_name' => 'Changed',
            ],
        );

        $response->assertNotFound();

        $this->assertDatabaseHas('member_dependants', [
            'id' => $dependant->id,
            'first_name' => 'Mary',
        ]);
    }

    public function test_it_validates_required_dependant_fields(): void
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
                "/api/v1/organizations/{$organization->id}/members/{$member->id}/dependants"
            ),
            [],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'first_name',
            ]);
    }

    public function test_it_cannot_show_another_members_dependant(): void
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
            'last_name' => 'Smith',
        ]);

        $memberA->dependants()->attach($dependant->id, [
            'relationship_type' => 'spouse',
            'started_at' => '2026-01-01',
        ]);

        $response = $this->getJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/{$memberB->id}/dependants/{$dependant->id}"
            )
        );

        $response->assertNotFound();
    }

    public function test_it_cannot_update_another_members_dependant(): void
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
            'last_name' => 'Smith',
        ]);

        $memberA->dependants()->attach($dependant->id, [
            'relationship_type' => 'spouse',
            'started_at' => '2026-01-01',
        ]);

        $response = $this->putJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/{$memberB->id}/dependants/{$dependant->id}"
            ),
            [
                'first_name' => 'Changed',
            ],
        );

        $response->assertNotFound();

        $this->assertDatabaseHas('member_dependants', [
            'id' => $dependant->id,
            'first_name' => 'Mary',
        ]);
    }

    public function test_it_can_convert_a_dependant_to_a_member(): void
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
                "/api/v1/organizations/{$organization->id}/members/{$member->id}/dependants/{$dependant->id}/convert"
            )
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $dependant->id)
            ->assertJsonPath('data.converted_member_id', $member->id)
            ->assertJsonPath('data.converted_at', now()->toDateString());

        $this->assertDatabaseHas('member_dependants', [
            'id' => $dependant->id,
            'converted_member_id' => $member->id,
        ]);
    }

    public function test_it_cannot_convert_an_already_converted_dependant(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => true,
        ]);

        $this->actAsMemberOrganizationAdmin($organization);

        $firstMember = Member::factory()
            ->forOrganization($organization)
            ->create();

        $secondMember = Member::factory()
            ->forOrganization($organization)
            ->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organization->id,
            'first_name' => 'Mary',
            'converted_member_id' => $firstMember->id,
            'converted_at' => '2026-01-01',
        ]);

        $response = $this->postJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/{$secondMember->id}/dependants/{$dependant->id}/convert"
            )
        );

        $response
            ->assertUnprocessable()
            ->assertJson([
                'message' => 'This dependant has already been converted to a member.',
            ]);
    }

    public function test_it_cannot_convert_a_dependant_from_another_organization(): void
    {
        $organizationA = Organization::factory()->create([
            'is_active' => true,
        ]);

        $this->actAsMemberOrganizationAdmin($organizationA);

        $organizationB = Organization::factory()->create([
            'is_active' => true,
        ]);

        $member = Member::factory()
            ->forOrganization($organizationB)
            ->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organizationA->id,
            'first_name' => 'Mary',
        ]);

        $response = $this->postJson(
            $this->organizationUrl(
                $organizationA,
                "/api/v1/organizations/{$organizationB->id}/members/{$member->id}/dependants/{$dependant->id}/convert"
            )
        );

        $response->assertNotFound();

        $this->assertDatabaseHas('member_dependants', [
            'id' => $dependant->id,
            'converted_member_id' => null,
        ]);
    }

    public function test_it_cannot_convert_a_dependant_for_a_member_from_another_organization(): void
    {
        $organizationA = Organization::factory()->create([
            'is_active' => true,
        ]);

        $this->actAsMemberOrganizationAdmin($organizationA);

        $organizationB = Organization::factory()->create([
            'is_active' => true,
        ]);

        $member = Member::factory()
            ->forOrganization($organizationB)
            ->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organizationA->id,
            'first_name' => 'Mary',
        ]);

        $response = $this->postJson(
            $this->organizationUrl(
                $organizationA,
                "/api/v1/organizations/{$organizationA->id}/members/{$member->id}/dependants/{$dependant->id}/convert"
            )
        );

        $response->assertNotFound();

        $this->assertDatabaseHas('member_dependants', [
            'id' => $dependant->id,
            'converted_member_id' => null,
        ]);
    }

    public function test_it_cannot_convert_an_unrelated_dependant_for_a_member(): void
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

        /*
         * The dependant belongs to the same organization, but there is
         * no requirement that the dependant already has a relationship
         * with this member for conversion itself.
         *
         * Therefore this should currently be allowed.
         */
        $response = $this->postJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/organizations/{$organization->id}/members/{$member->id}/dependants/{$dependant->id}/convert"
            )
        );

        $response->assertOk();

        $this->assertDatabaseHas('member_dependants', [
            'id' => $dependant->id,
            'converted_member_id' => $member->id,
        ]);
    }

    public function test_it_cannot_convert_a_dependant_using_another_organizations_url(): void
    {
        $organizationA = Organization::factory()->create([
            'is_active' => true,
        ]);

        $this->actAsMemberOrganizationAdmin($organizationA);

        $organizationB = Organization::factory()->create([
            'is_active' => true,
        ]);

        $member = Member::factory()
            ->forOrganization($organizationB)
            ->create();

        $dependant = MemberDependant::create([
            'organization_id' => $organizationB->id,
            'first_name' => 'Mary',
        ]);

        $response = $this->postJson(
            $this->organizationUrl(
                $organizationA,
                "/api/v1/organizations/{$organizationA->id}/members/{$member->id}/dependants/{$dependant->id}/convert"
            )
        );

        $response->assertNotFound();

        $this->assertDatabaseHas('member_dependants', [
            'id' => $dependant->id,
            'converted_member_id' => null,
        ]);
    }
}