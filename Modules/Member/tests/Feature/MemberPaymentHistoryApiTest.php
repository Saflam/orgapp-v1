<?php

namespace Modules\Member\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberPayment;
use Modules\Member\Tests\Concerns\ActsAsMemberOrganizationUser;
use Tests\TestCase;

class MemberPaymentHistoryApiTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsMemberOrganizationUser;

    public function test_organization_admin_can_list_member_payment_history(): void
    {
        $organization = Organization::factory()->create(['slug' => 'org-' . uniqid()]);
        $this->actAsMemberOrganizationAdmin($organization);

        $member = Member::factory()->create(['organization_id' => $organization->id]);
        $payment = $this->payment($organization->id, $member->id, 'MP-HISTORY-001');

        $this->getJson($this->organizationUrl(
            $organization,
            "/api/v1/organizations/{$organization->id}/members/{$member->id}/payments"
        ))
            ->assertOk()
            ->assertJsonPath('data.0.id', $payment->id)
            ->assertJsonPath('data.0.receipt_number', 'MP-HISTORY-001')
            ->assertJsonPath('data.0.total_amount', '125.00');
    }

    public function test_organization_admin_can_view_a_payment_in_member_history(): void
    {
        $organization = Organization::factory()->create(['slug' => 'org-' . uniqid()]);
        $this->actAsMemberOrganizationAdmin($organization);

        $member = Member::factory()->create(['organization_id' => $organization->id]);
        $payment = $this->payment($organization->id, $member->id, 'MP-HISTORY-002');

        $this->getJson($this->organizationUrl(
            $organization,
            "/api/v1/organizations/{$organization->id}/members/{$member->id}/payments/{$payment->id}"
        ))
            ->assertOk()
            ->assertJsonPath('data.id', $payment->id)
            ->assertJsonPath('data.receipt_number', 'MP-HISTORY-002');
    }

    public function test_payment_from_another_member_cannot_be_viewed_through_member_history(): void
    {
        $organization = Organization::factory()->create(['slug' => 'org-' . uniqid()]);
        $this->actAsMemberOrganizationAdmin($organization);

        $member = Member::factory()->create(['organization_id' => $organization->id]);
        $otherMember = Member::factory()->create(['organization_id' => $organization->id]);
        $payment = $this->payment($organization->id, $otherMember->id, 'MP-HISTORY-003');

        $this->getJson($this->organizationUrl(
            $organization,
            "/api/v1/organizations/{$organization->id}/members/{$member->id}/payments/{$payment->id}"
        ))->assertUnprocessable();
    }

    public function test_member_payment_history_is_scoped_to_current_organization(): void
    {
        $organization = Organization::factory()->create(['slug' => 'org-' . uniqid()]);
        $otherOrganization = Organization::factory()->create();
        $this->actAsMemberOrganizationAdmin($organization);

        $otherMember = Member::factory()->create(['organization_id' => $otherOrganization->id]);

        // The organization.access middleware rejects a URL targeting another
        // organization before the controller is dispatched. A 404 is expected
        // here and avoids disclosing that the other organization exists.
        $this->getJson($this->organizationUrl(
            $organization,
            "/api/v1/organizations/{$otherOrganization->id}/members/{$otherMember->id}/payments"
        ))->assertNotFound();
    }

    private function payment(int $organizationId, int $memberId, string $receipt): MemberPayment
    {
        return MemberPayment::create([
            'organization_id' => $organizationId,
            'member_id' => $memberId,
            'receipt_number' => $receipt,
            'total_amount' => 125,
            'currency' => 'INR',
            'payment_method' => 'cash',
            'collector_scope_type' => 'organization',
            'collector_scope_id' => $organizationId,
            'received_at' => now(),
            'received_by' => User::factory()->create()->id,
            'metadata' => [],
        ]);
    }
}
