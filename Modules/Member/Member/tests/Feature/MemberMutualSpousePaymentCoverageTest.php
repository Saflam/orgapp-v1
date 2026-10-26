<?php

namespace Modules\Member\Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Member\Enums\MemberPaymentObligationStatus;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberPaymentItem;
use Modules\Member\Models\MemberPaymentItemRate;
use Modules\Member\Models\MemberRelationship;
use Modules\Member\Models\Membership;
use Modules\Member\Models\MembershipType;
use Modules\Member\Services\MemberPaymentService;
use Tests\TestCase;

class MemberMutualSpousePaymentCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_spouse_added_after_first_payment_is_covered_when_their_obligations_are_created(): void
    {
        [$organization, $type, $husband, $husbandMembership, $wife, $wifeMembership] = $this->coupleContext();
        $item = $this->welfareFund($organization, $type);
        $service = app(MemberPaymentService::class);

        // The husband is already a member and pays before the wife joins / the relationship is recorded.
        $husbandObligations = $service->syncOutstandingObligations($husbandMembership, '2026-12-31');
        $husband2025 = $husbandObligations->firstWhere('occurrence_key', 'yearly:2025-01-01');
        $service->recordPayment([$husband2025], User::factory()->create(), 'organization', $organization->id, 'cash', 'H-2025');

        MemberRelationship::create([
            'member_id' => $husband->id,
            'related_member_id' => $wife->id,
            'relationship_type' => 'spouse',
            'started_at' => null,
            'ended_at' => null,
            'metadata' => [],
        ]);

        // Creating the wife's obligations later must find the already-confirmed matching payment.
        $wifeObligations = $service->syncOutstandingObligations($wifeMembership, '2026-12-31');
        $wife2025 = $wifeObligations->firstWhere('occurrence_key', 'yearly:2025-01-01');
        $wife2026 = $wifeObligations->firstWhere('occurrence_key', 'yearly:2026-01-01');

        $this->assertSame(MemberPaymentObligationStatus::COVERED_BY_FAMILY, $wife2025->refresh()->status);
        $this->assertSame($husband2025->id, $wife2025->metadata['family_coverage']['covered_by_obligation_id']);
        $this->assertSame(MemberPaymentObligationStatus::PENDING, $wife2026->refresh()->status);
    }

    public function test_spouse_payments_cover_only_the_matching_annual_period_in_either_direction(): void
    {
        [$organization, $type, $husband, $husbandMembership, $wife, $wifeMembership] = $this->coupleContext();
        $this->welfareFund($organization, $type);
        MemberRelationship::create([
            'member_id' => $husband->id,
            'related_member_id' => $wife->id,
            'relationship_type' => 'spouse',
            'started_at' => null,
            'ended_at' => null,
            'metadata' => [],
        ]);

        $service = app(MemberPaymentService::class);
        $husbandObligations = $service->syncOutstandingObligations($husbandMembership, '2026-12-31');
        $wifeObligations = $service->syncOutstandingObligations($wifeMembership, '2026-12-31');
        $husband2025 = $husbandObligations->firstWhere('occurrence_key', 'yearly:2025-01-01');
        $husband2026 = $husbandObligations->firstWhere('occurrence_key', 'yearly:2026-01-01');
        $wife2025 = $wifeObligations->firstWhere('occurrence_key', 'yearly:2025-01-01');
        $wife2026 = $wifeObligations->firstWhere('occurrence_key', 'yearly:2026-01-01');

        // Wife pays 2025 first; husband's 2025 obligation becomes covered, but 2026 remains due.
        $service->recordPayment([$wife2025], User::factory()->create(), 'organization', $organization->id, 'cash', 'W-2025');
        $this->assertSame(MemberPaymentObligationStatus::COVERED_BY_FAMILY, $husband2025->refresh()->status);
        $this->assertSame(MemberPaymentObligationStatus::PENDING, $husband2026->refresh()->status);

        // Husband pays 2026; wife is now covered for 2026, while her 2025 paid record remains paid.
        $service->recordPayment([$husband2026], User::factory()->create(), 'organization', $organization->id, 'cash', 'H-2026');
        $this->assertSame(MemberPaymentObligationStatus::PAID, $wife2025->refresh()->status);
        $this->assertSame(MemberPaymentObligationStatus::COVERED_BY_FAMILY, $wife2026->refresh()->status);
        $this->assertSame($husband2026->id, $wife2026->metadata['family_coverage']['covered_by_obligation_id']);
        $this->assertSame(0.0, (float) $wife2026->refresh()->paid_amount);
    }

    private function coupleContext(): array
    {
        $organization = Organization::factory()->create(['slug' => 'org-' . uniqid()]);
        $unit = Unit::factory()->create(['organization_id' => $organization->id]);
        $type = MembershipType::create([
            'organization_id' => $organization->id,
            'name' => 'Regular',
            'code' => 'REG-' . uniqid(),
            'is_active' => true,
            'metadata' => [],
        ]);

        $createMember = function (string $prefix) use ($organization, $unit, $type): array {
            $user = User::factory()->create();
            OrganizationMembership::create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'status' => 'active',
            ]);
            $member = Member::create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'unit_id' => $unit->id,
                'membership_number' => $prefix . '-' . uniqid(),
                'joined_at' => '2025-01-01',
                'metadata' => [],
            ]);
            $membership = Membership::create([
                'member_id' => $member->id,
                'membership_type_id' => $type->id,
                'starts_at' => '2025-01-01',
                'status' => 'active',
                'metadata' => [],
            ]);

            return [$member, $membership];
        };

        [$husband, $husbandMembership] = $createMember('H');
        [$wife, $wifeMembership] = $createMember('W');

        return [$organization, $type, $husband, $husbandMembership, $wife, $wifeMembership];
    }

    private function welfareFund(Organization $organization, MembershipType $type): MemberPaymentItem
    {
        $item = MemberPaymentItem::create([
            'organization_id' => $organization->id,
            'membership_type_id' => $type->id,
            'title' => 'Welfare Fund',
            'slug' => 'welfare-fund-' . uniqid(),
            'family_payment_policy' => 'mutual_spouse',
            'is_mandatory' => true,
            'is_recurring' => true,
            'recurring_period' => 'yearly',
            'recurring_by' => 'join_date',
            'recurring_date' => null,
            'is_active' => true,
            'metadata' => [],
        ]);

        $rate = MemberPaymentItemRate::create([
            'member_payment_item_id' => $item->id,
            'amount' => 1000,
            'currency' => 'INR',
            'effective_from' => '2025-01-01',
        ]);
        $rate->allocations()->create(['scope_type' => 'organization', 'percentage' => 100]);

        return $item;
    }
}
