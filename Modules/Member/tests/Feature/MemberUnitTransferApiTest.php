<?php

namespace Modules\Member\Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberUnitTransfer;
use Modules\Member\Tests\Concerns\ActsAsMemberOrganizationUser;
use Tests\TestCase;

class MemberUnitTransferApiTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsMemberOrganizationUser;

    public function test_authorized_organization_user_can_submit_a_unit_transfer(): void
    {
        $organization = Organization::factory()->create(['slug' => 'transfer-' . uniqid()]);
        $this->actAsMemberOrganizationAdmin($organization);
        $from = Unit::factory()->create(['organization_id' => $organization->id]);
        $to = Unit::factory()->create(['organization_id' => $organization->id]);
        $member = Member::factory()->forUnit($from)->create(['organization_id' => $organization->id]);

        $this->postJson($this->organizationUrl(
            $organization,
            '/api/v1/organizations/' . $organization->id . '/member-unit-transfers',
        ), [
            'member_id' => $member->id,
            'to_unit_id' => $to->id,
            'spouse_included' => false,
        ])->assertCreated()
            ->assertJsonPath('data.member_id', $member->id)
            ->assertJsonPath('data.from_unit_id', $from->id)
            ->assertJsonPath('data.to_unit_id', $to->id)
            ->assertJsonPath('data.status', 'submitted');

        $this->assertDatabaseHas('member_unit_transfers', [
            'organization_id' => $organization->id,
            'member_id' => $member->id,
            'from_unit_id' => $from->id,
            'to_unit_id' => $to->id,
        ]);
    }

    public function test_transfer_endpoint_does_not_expose_another_organizations_transfer(): void
    {
        $organization = Organization::factory()->create(['slug' => 'transfer-a-' . uniqid()]);
        $otherOrganization = Organization::factory()->create(['slug' => 'transfer-b-' . uniqid()]);
        $this->actAsMemberOrganizationAdmin($organization);

        $from = Unit::factory()->create(['organization_id' => $otherOrganization->id]);
        $to = Unit::factory()->create(['organization_id' => $otherOrganization->id]);
        $member = Member::factory()->forUnit($from)->create(['organization_id' => $otherOrganization->id]);
        $transfer = MemberUnitTransfer::create([
            'organization_id' => $otherOrganization->id,
            'member_id' => $member->id,
            'from_unit_id' => $from->id,
            'to_unit_id' => $to->id,
            'spouse_included' => false,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->getJson($this->organizationUrl(
            $organization,
            '/api/v1/organizations/' . $organization->id . '/member-unit-transfers/' . $transfer->id,
        ))->assertNotFound();
    }

    public function test_submission_rejects_member_from_another_organization(): void
    {
        $organization = Organization::factory()->create(['slug' => 'transfer-c-' . uniqid()]);
        $otherOrganization = Organization::factory()->create(['slug' => 'transfer-d-' . uniqid()]);
        $this->actAsMemberOrganizationAdmin($organization);

        $foreignUnit = Unit::factory()->create(['organization_id' => $otherOrganization->id]);
        $foreignMember = Member::factory()->forUnit($foreignUnit)->create(['organization_id' => $otherOrganization->id]);
        $target = Unit::factory()->create(['organization_id' => $organization->id]);

        $this->postJson($this->organizationUrl(
            $organization,
            '/api/v1/organizations/' . $organization->id . '/member-unit-transfers',
        ), [
            'member_id' => $foreignMember->id,
            'to_unit_id' => $target->id,
            'spouse_included' => false,
        ])->assertNotFound();
    }

    public function test_unauthenticated_user_cannot_list_unit_transfers(): void
    {
        $organization = Organization::factory()->create(['slug' => 'transfer-unauth-' . uniqid()]);

        $this->getJson($this->organizationUrl(
            $organization,
            '/api/v1/organizations/' . $organization->id . '/member-unit-transfers',
        ))->assertUnauthorized();
    }

    public function test_organization_member_without_transfer_permissions_cannot_list_transfers(): void
    {
        // Match the multi-tenant host configuration used by the organization URL helper.
        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'example.test',
        ]);

        $organization = Organization::factory()->create(['slug' => 'transfer-no-perm-' . uniqid()]);
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

        $this->actingAs($user)->getJson($this->organizationUrl(
            $organization,
            '/api/v1/organizations/' . $organization->id . '/member-unit-transfers',
        ))->assertForbidden();
    }

    public function test_rejection_request_requires_a_valid_side_and_reason(): void
    {
        $organization = Organization::factory()->create(['slug' => 'transfer-reject-validation-' . uniqid()]);
        $this->actAsMemberOrganizationAdmin($organization);
        $from = Unit::factory()->create(['organization_id' => $organization->id]);
        $to = Unit::factory()->create(['organization_id' => $organization->id]);
        $member = Member::factory()->forUnit($from)->create(['organization_id' => $organization->id]);
        $transfer = MemberUnitTransfer::create([
            'organization_id' => $organization->id,
            'member_id' => $member->id,
            'from_unit_id' => $from->id,
            'to_unit_id' => $to->id,
            'spouse_included' => false,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->postJson($this->organizationUrl(
            $organization,
            '/api/v1/organizations/' . $organization->id . '/member-unit-transfers/' . $transfer->id . '/reject',
        ), [
            'side' => 'unrecognized',
            'reason' => '',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['side', 'reason']);
    }

}
