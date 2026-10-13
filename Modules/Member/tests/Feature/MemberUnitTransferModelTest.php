<?php

namespace Modules\Member\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Member\Enums\MemberRelationshipType;
use Modules\Member\Enums\MemberUnitTransferApprovalSide;
use Modules\Member\Enums\MemberUnitTransferApprovalStatus;
use Modules\Member\Enums\MemberUnitTransferStatus;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberRelationship;
use Modules\Member\Models\MemberUnitTransfer;
use Modules\Member\Models\MemberUnitTransferApproval;
use Tests\TestCase;

class MemberUnitTransferModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_captures_from_and_to_units_and_defaults_spouse_included(): void
    {
        $organization = Organization::factory()->create();
        $from = Unit::factory()->create(['organization_id' => $organization->id]);
        $to = Unit::factory()->create(['organization_id' => $organization->id]);
        $member = Member::factory()->forUnit($from)->create(['organization_id' => $organization->id]);

        $transfer = MemberUnitTransfer::create([
            'organization_id' => $organization->id,
            'member_id' => $member->id,
            'from_unit_id' => $from->id,
            'to_unit_id' => $to->id,
            'status' => MemberUnitTransferStatus::DRAFT,
        ]);

        $this->assertTrue($transfer->spouse_included);
        $this->assertSame($from->id, $transfer->fromUnit->id);
        $this->assertSame($to->id, $transfer->toUnit->id);
        $this->assertSame(MemberUnitTransferStatus::DRAFT, $transfer->status);
    }

    public function test_transfer_can_include_a_spouse_from_existing_member_relationship(): void
    {
        $organization = Organization::factory()->create();
        $from = Unit::factory()->create(['organization_id' => $organization->id]);
        $to = Unit::factory()->create(['organization_id' => $organization->id]);
        $member = Member::factory()->forUnit($from)->create(['organization_id' => $organization->id]);
        $spouse = Member::factory()->forUnit($from)->create(['organization_id' => $organization->id]);

        MemberRelationship::create([
            'member_id' => $member->id,
            'related_member_id' => $spouse->id,
            'relationship_type' => MemberRelationshipType::SPOUSE->value,
        ]);

        $transfer = MemberUnitTransfer::create([
            'organization_id' => $organization->id,
            'member_id' => $member->id,
            'from_unit_id' => $from->id,
            'to_unit_id' => $to->id,
            'spouse_included' => true,
            'spouse_member_id' => $spouse->id,
            'status' => MemberUnitTransferStatus::SUBMITTED,
            'submitted_at' => now(),
        ]);

        $this->assertTrue($transfer->spouse_included);
        $this->assertSame($spouse->id, $transfer->spouse->id);
        $this->assertSame(MemberRelationshipType::SPOUSE->value, $member->relationships()->first()->relationship_type);
    }

    public function test_transfer_has_exactly_one_outgoing_and_incoming_approval(): void
    {
        $organization = Organization::factory()->create();
        $from = Unit::factory()->create(['organization_id' => $organization->id]);
        $to = Unit::factory()->create(['organization_id' => $organization->id]);
        $member = Member::factory()->forUnit($from)->create(['organization_id' => $organization->id]);

        $transfer = MemberUnitTransfer::create([
            'organization_id' => $organization->id,
            'member_id' => $member->id,
            'from_unit_id' => $from->id,
            'to_unit_id' => $to->id,
            'status' => MemberUnitTransferStatus::SUBMITTED,
            'submitted_at' => now(),
        ]);

        $transfer->approvals()->createMany([
            [
                'organization_id' => $organization->id,
                'unit_id' => $from->id,
                'side' => MemberUnitTransferApprovalSide::OUTGOING,
                'status' => MemberUnitTransferApprovalStatus::PENDING,
            ],
            [
                'organization_id' => $organization->id,
                'unit_id' => $to->id,
                'side' => MemberUnitTransferApprovalSide::INCOMING,
                'status' => MemberUnitTransferApprovalStatus::PENDING,
            ],
        ]);

        $this->assertCount(2, $transfer->fresh()->approvals);
        $this->assertSame(1, $transfer->approvals()->where('side', MemberUnitTransferApprovalSide::OUTGOING->value)->count());
        $this->assertSame(1, $transfer->approvals()->where('side', MemberUnitTransferApprovalSide::INCOMING->value)->count());
    }
}
