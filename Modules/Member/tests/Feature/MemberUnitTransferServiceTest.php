<?php

namespace Modules\Member\Tests\Feature;

use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\RoleAssignment;
use App\Models\OrganizationMembership;
use Modules\Core\Models\Unit;
use Modules\Member\Enums\MemberRelationshipType;
use Modules\Member\Enums\MemberUnitTransferApprovalSide;
use Modules\Member\Enums\MemberUnitTransferApprovalStatus;
use Modules\Member\Enums\MemberUnitTransferStatus;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberRelationship;
use Modules\Member\Models\MemberUnitTransfer;
use Modules\Member\Services\MemberUnitTransferService;
use Tests\TestCase;

class MemberUnitTransferServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_creates_transfer_and_both_pending_approvals_without_changing_member_unit(): void
    {
        [$organization, $from, $to, $member] = $this->memberInTwoUnits();

        $transfer = app(MemberUnitTransferService::class)->submit($member, $to->id);

        $this->assertSame(MemberUnitTransferStatus::SUBMITTED, $transfer->status);
        $this->assertSame($from->id, $member->fresh()->unit_id);
        $this->assertSame($from->id, $transfer->from_unit_id);
        $this->assertSame($to->id, $transfer->to_unit_id);
        $this->assertTrue($transfer->spouse_included);
        $this->assertCount(2, $transfer->approvals);
        $this->assertSame(1, $transfer->approvals->where('side', MemberUnitTransferApprovalSide::OUTGOING)->where('status', MemberUnitTransferApprovalStatus::PENDING)->count());
        $this->assertSame(1, $transfer->approvals->where('side', MemberUnitTransferApprovalSide::INCOMING)->where('status', MemberUnitTransferApprovalStatus::PENDING)->count());
    }

    public function test_submit_can_exclude_spouse(): void
    {
        [, , $to, $member] = $this->memberInTwoUnits();

        $transfer = app(MemberUnitTransferService::class)->submit($member, $to->id, false);

        $this->assertFalse($transfer->spouse_included);
        $this->assertNull($transfer->spouse_member_id);
    }

    public function test_submit_includes_spouse_when_active_spouse_relationship_exists_in_same_unit(): void
    {
        [$organization, $from, $to, $member] = $this->memberInTwoUnits();
        $spouse = Member::factory()->forUnit($from)->create(['organization_id' => $organization->id]);

        MemberRelationship::create([
            'member_id' => $member->id,
            'related_member_id' => $spouse->id,
            'relationship_type' => MemberRelationshipType::SPOUSE->value,
        ]);

        $transfer = app(MemberUnitTransferService::class)->submit($member, $to->id);

        $this->assertSame($spouse->id, $transfer->spouse_member_id);
        $this->assertTrue($transfer->spouse_included);
    }

    public function test_submit_rejects_same_unit(): void
    {
        [, $from, , $member] = $this->memberInTwoUnits();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('current unit');

        app(MemberUnitTransferService::class)->submit($member, $from->id);
    }

    public function test_submit_rejects_target_unit_from_another_organization(): void
    {
        [, , , $member] = $this->memberInTwoUnits();
        $otherOrganization = Organization::factory()->create();
        $foreignUnit = Unit::factory()->create(['organization_id' => $otherOrganization->id]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('member\'s organization');

        app(MemberUnitTransferService::class)->submit($member, $foreignUnit->id);
    }

    public function test_submit_rejects_second_active_transfer_for_member(): void
    {
        [, , $to, $member] = $this->memberInTwoUnits();
        $service = app(MemberUnitTransferService::class);

        $service->submit($member, $to->id, false);

        $anotherUnit = Unit::factory()->create(['organization_id' => $member->organization_id]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('active unit transfer');

        $service->submit($member, $anotherUnit->id, false);
    }

    public function test_submit_rejects_joint_transfer_when_spouse_is_in_another_unit(): void
    {
        [$organization, $from, $to, $member] = $this->memberInTwoUnits();
        $otherUnit = Unit::factory()->create(['organization_id' => $organization->id]);
        $spouse = Member::factory()->forUnit($otherUnit)->create(['organization_id' => $organization->id]);

        MemberRelationship::create([
            'member_id' => $member->id,
            'related_member_id' => $spouse->id,
            'relationship_type' => MemberRelationshipType::SPOUSE->value,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('current unit');

        app(MemberUnitTransferService::class)->submit($member, $to->id);
    }


    public function test_outgoing_unit_can_approve_without_completing_transfer(): void
    {
        [$organization, $from, $to, $member] = $this->memberInTwoUnits();
        $approver = User::factory()->create();
        Member::factory()->forUnit($from)->create([
            'organization_id' => $organization->id,
            'user_id' => $approver->id,
        ]);
        $this->grantUnitTransferPermissions($approver, $organization, $from);

        $transfer = app(MemberUnitTransferService::class)->submit($member, $to->id, false);

        $result = app(MemberUnitTransferService::class)->approve(
            $transfer,
            $approver,
            MemberUnitTransferApprovalSide::OUTGOING,
        );

        $this->assertSame(MemberUnitTransferStatus::SUBMITTED, $result->status);
        $this->assertSame(MemberUnitTransferApprovalStatus::APPROVED, $result->approvals->where('side', MemberUnitTransferApprovalSide::OUTGOING)->first()->status);
        $this->assertSame($from->id, $member->fresh()->unit_id);
    }

    public function test_incoming_unit_can_approve_and_both_approvals_mark_transfer_approved(): void
    {
        [$organization, $from, $to, $member] = $this->memberInTwoUnits();
        $outgoingApprover = User::factory()->create();
        $incomingApprover = User::factory()->create();

        Member::factory()->forUnit($from)->create([
            'organization_id' => $organization->id,
            'user_id' => $outgoingApprover->id,
        ]);
        $this->grantUnitTransferPermissions($outgoingApprover, $organization, $from);
        Member::factory()->forUnit($to)->create([
            'organization_id' => $organization->id,
            'user_id' => $incomingApprover->id,
        ]);
        $this->grantUnitTransferPermissions($incomingApprover, $organization, $to);

        $service = app(MemberUnitTransferService::class);
        $transfer = $service->submit($member, $to->id, false);

        $service->approve($transfer, $outgoingApprover, MemberUnitTransferApprovalSide::OUTGOING);
        $result = $service->approve($transfer, $incomingApprover, MemberUnitTransferApprovalSide::INCOMING);

        $this->assertSame(MemberUnitTransferStatus::APPROVED, $result->status);
        $this->assertSame($from->id, $member->fresh()->unit_id);
    }

    public function test_rejection_requires_reason_and_rejects_transfer(): void
    {
        [$organization, $from, $to, $member] = $this->memberInTwoUnits();
        $approver = User::factory()->create();
        Member::factory()->forUnit($from)->create([
            'organization_id' => $organization->id,
            'user_id' => $approver->id,
        ]);
        $this->grantUnitTransferPermissions($approver, $organization, $from);

        $service = app(MemberUnitTransferService::class);
        $transfer = $service->submit($member, $to->id, false);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('rejection reason');

        $service->reject($transfer, $approver, MemberUnitTransferApprovalSide::OUTGOING, '  ');
    }

    public function test_rejection_records_reason_and_does_not_change_member_unit(): void
    {
        [$organization, $from, $to, $member] = $this->memberInTwoUnits();
        $approver = User::factory()->create();
        Member::factory()->forUnit($from)->create([
            'organization_id' => $organization->id,
            'user_id' => $approver->id,
        ]);
        $this->grantUnitTransferPermissions($approver, $organization, $from);

        $service = app(MemberUnitTransferService::class);
        $transfer = $service->submit($member, $to->id, false);

        $result = $service->reject(
            $transfer,
            $approver,
            MemberUnitTransferApprovalSide::OUTGOING,
            'Membership records require review.',
        );

        $this->assertSame(MemberUnitTransferStatus::REJECTED, $result->status);
        $this->assertSame('Membership records require review.', $result->rejection_reason);
        $rejectedApproval = $result->approvals
            ->firstWhere('side', MemberUnitTransferApprovalSide::OUTGOING);

        $this->assertNotNull($rejectedApproval);
        $this->assertSame(
            MemberUnitTransferApprovalStatus::REJECTED,
            $rejectedApproval->status,
        );

        $pendingApproval = $result->approvals
            ->firstWhere('side', MemberUnitTransferApprovalSide::INCOMING);

        $this->assertNotNull($pendingApproval);
        $this->assertSame(
            MemberUnitTransferApprovalStatus::PENDING,
            $pendingApproval->status,
        );
        $this->assertSame($from->id, $member->fresh()->unit_id);
    }

    public function test_user_from_wrong_unit_cannot_decide_approval(): void
    {
        [$organization, $from, $to, $member] = $this->memberInTwoUnits();
        $wrongApprover = User::factory()->create();
        $otherUnit = Unit::factory()->create(['organization_id' => $organization->id]);
        Member::factory()->forUnit($otherUnit)->create([
            'organization_id' => $organization->id,
            'user_id' => $wrongApprover->id,
        ]);
        $this->grantUnitTransferPermissions($wrongApprover, $organization, $otherUnit);

        $service = app(MemberUnitTransferService::class);
        $transfer = $service->submit($member, $to->id, false);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('authorized to decide');

        $service->approve($transfer, $wrongApprover, MemberUnitTransferApprovalSide::OUTGOING);
    }

    public function test_approval_cannot_be_decided_twice(): void
    {
        [$organization, $from, $to, $member] = $this->memberInTwoUnits();
        $approver = User::factory()->create();
        Member::factory()->forUnit($from)->create([
            'organization_id' => $organization->id,
            'user_id' => $approver->id,
        ]);
        $this->grantUnitTransferPermissions($approver, $organization, $from);

        $service = app(MemberUnitTransferService::class);
        $transfer = $service->submit($member, $to->id, false);

        $service->approve($transfer, $approver, MemberUnitTransferApprovalSide::OUTGOING);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('already been decided');

        $service->approve($transfer, $approver, MemberUnitTransferApprovalSide::OUTGOING);
    }

    public function test_completion_requires_both_approvals(): void
    {
        [$organization, $from, $to, $member] = $this->memberInTwoUnits();
        $outgoingApprover = User::factory()->create();
        $incomingApprover = User::factory()->create();

        Member::factory()->forUnit($from)->create([
            'organization_id' => $organization->id,
            'user_id' => $outgoingApprover->id,
        ]);
        $this->grantUnitTransferPermissions($outgoingApprover, $organization, $from);
        Member::factory()->forUnit($to)->create([
            'organization_id' => $organization->id,
            'user_id' => $incomingApprover->id,
        ]);
        $this->grantUnitTransferPermissions($incomingApprover, $organization, $to);

        $service = app(MemberUnitTransferService::class);
        $transfer = $service->submit($member, $to->id, false);

        $service->approve($transfer, $outgoingApprover, MemberUnitTransferApprovalSide::OUTGOING);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('approved unit transfer requests');

        $service->complete($transfer, $outgoingApprover);
    }

    public function test_completion_moves_member_and_records_change(): void
    {
        [$organization, $from, $to, $member] = $this->memberInTwoUnits();
        $outgoingApprover = User::factory()->create();
        $incomingApprover = User::factory()->create();

        Member::factory()->forUnit($from)->create([
            'organization_id' => $organization->id,
            'user_id' => $outgoingApprover->id,
        ]);
        $this->grantUnitTransferPermissions($outgoingApprover, $organization, $from);
        Member::factory()->forUnit($to)->create([
            'organization_id' => $organization->id,
            'user_id' => $incomingApprover->id,
        ]);
        $this->grantUnitTransferPermissions($incomingApprover, $organization, $to);

        $service = app(MemberUnitTransferService::class);
        $transfer = $service->submit($member, $to->id, false);
        $service->approve($transfer, $outgoingApprover, MemberUnitTransferApprovalSide::OUTGOING);
        $service->approve($transfer, $incomingApprover, MemberUnitTransferApprovalSide::INCOMING);

        $result = $service->complete($transfer, $incomingApprover);

        $this->assertSame(MemberUnitTransferStatus::COMPLETED, $result->status);
        $this->assertNotNull($result->completed_at);
        $this->assertSame($from->id, $result->from_unit_id);
        $this->assertSame($to->id, $member->fresh()->unit_id);

        $change = $member->changes()->first();

        $this->assertNotNull($change);
        $this->assertSame($from->id, $change->before_data['unit_id']);
        $this->assertSame($to->id, $change->after_data['unit_id']);
        $this->assertSame($transfer->id, $change->after_data['unit_transfer_id']);
        $this->assertSame($incomingApprover->id, $change->actor_user_id);

        $history = $member->unitHistories()->orderBy('started_at')->get();

        $this->assertCount(2, $history);
        $this->assertSame($from->id, $history[0]->unit_id);
        $this->assertNotNull($history[0]->ended_at);
        $this->assertSame($to->id, $history[1]->unit_id);
        $this->assertNull($history[1]->ended_at);
        $this->assertSame('transfer', $history[1]->source);
        $this->assertSame($transfer->id, $history[1]->member_unit_transfer_id);
    }

    public function test_joint_completion_moves_member_and_spouse_atomically(): void
    {
        [$organization, $from, $to, $member] = $this->memberInTwoUnits();
        $spouse = Member::factory()->forUnit($from)->create(['organization_id' => $organization->id]);

        MemberRelationship::create([
            'member_id' => $member->id,
            'related_member_id' => $spouse->id,
            'relationship_type' => MemberRelationshipType::SPOUSE->value,
        ]);

        $outgoingApprover = User::factory()->create();
        $incomingApprover = User::factory()->create();

        Member::factory()->forUnit($from)->create([
            'organization_id' => $organization->id,
            'user_id' => $outgoingApprover->id,
        ]);
        $this->grantUnitTransferPermissions($outgoingApprover, $organization, $from);
        Member::factory()->forUnit($to)->create([
            'organization_id' => $organization->id,
            'user_id' => $incomingApprover->id,
        ]);
        $this->grantUnitTransferPermissions($incomingApprover, $organization, $to);

        $service = app(MemberUnitTransferService::class);
        $transfer = $service->submit($member, $to->id);
        $service->approve($transfer, $outgoingApprover, MemberUnitTransferApprovalSide::OUTGOING);
        $service->approve($transfer, $incomingApprover, MemberUnitTransferApprovalSide::INCOMING);

        $service->complete($transfer, $incomingApprover);

        $this->assertSame($to->id, $member->fresh()->unit_id);
        $this->assertSame($to->id, $spouse->fresh()->unit_id);
        $this->assertSame(1, $member->changes()->count());
        $this->assertSame(1, $spouse->changes()->count());
        $this->assertCount(2, $member->unitHistories()->get());
        $this->assertCount(2, $spouse->unitHistories()->get());
        $this->assertSame($to->id, $member->unitHistories()->whereNull('ended_at')->first()->unit_id);
        $this->assertSame($to->id, $spouse->unitHistories()->whereNull('ended_at')->first()->unit_id);
    }

    public function test_completion_rejects_stale_source_unit_without_changing_transfer_members(): void
    {
        [$organization, $from, $to, $member] = $this->memberInTwoUnits();
        $outgoingApprover = User::factory()->create();
        $incomingApprover = User::factory()->create();

        Member::factory()->forUnit($from)->create([
            'organization_id' => $organization->id,
            'user_id' => $outgoingApprover->id,
        ]);
        $this->grantUnitTransferPermissions($outgoingApprover, $organization, $from);
        Member::factory()->forUnit($to)->create([
            'organization_id' => $organization->id,
            'user_id' => $incomingApprover->id,
        ]);
        $this->grantUnitTransferPermissions($incomingApprover, $organization, $to);

        $service = app(MemberUnitTransferService::class);
        $transfer = $service->submit($member, $to->id, false);
        $service->approve($transfer, $outgoingApprover, MemberUnitTransferApprovalSide::OUTGOING);
        $service->approve($transfer, $incomingApprover, MemberUnitTransferApprovalSide::INCOMING);

        $otherUnit = Unit::factory()->create(['organization_id' => $organization->id]);
        $member->forceFill(['unit_id' => $otherUnit->id])->save();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('original unit');

        $service->complete($transfer, $incomingApprover);

        $this->assertSame($otherUnit->id, $member->fresh()->unit_id);
        $this->assertDatabaseMissing('member_changes', [
            'member_id' => $member->id,
        ]);
    }

    public function test_completion_cannot_be_performed_twice(): void
    {
        [$organization, $from, $to, $member] = $this->memberInTwoUnits();
        $outgoingApprover = User::factory()->create();
        $incomingApprover = User::factory()->create();

        Member::factory()->forUnit($from)->create([
            'organization_id' => $organization->id,
            'user_id' => $outgoingApprover->id,
        ]);
        $this->grantUnitTransferPermissions($outgoingApprover, $organization, $from);
        Member::factory()->forUnit($to)->create([
            'organization_id' => $organization->id,
            'user_id' => $incomingApprover->id,
        ]);
        $this->grantUnitTransferPermissions($incomingApprover, $organization, $to);

        $service = app(MemberUnitTransferService::class);
        $transfer = $service->submit($member, $to->id, false);
        $service->approve($transfer, $outgoingApprover, MemberUnitTransferApprovalSide::OUTGOING);
        $service->approve($transfer, $incomingApprover, MemberUnitTransferApprovalSide::INCOMING);
        $service->complete($transfer, $incomingApprover);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('approved unit transfer requests');

        $service->complete($transfer, $incomingApprover);
    }


    private function grantUnitTransferPermissions(User $user, Organization $organization, Unit $unit): void
    {
        $codes = [
            'member.unit-transfer.view',
            'member.unit-transfer.approve',
            'member.unit-transfer.reject',
            'member.unit-transfer.complete',
        ];

        $permissions = collect($codes)->map(function (string $code): Permission {
            return Permission::query()->updateOrCreate(
                ['code' => $code],
                [
                    'module' => 'member',
                    'name' => $code,
                    'description' => 'Test unit-transfer permission.',
                ],
            );
        });

        $role = Role::query()->firstOrCreate(
            ['organization_id' => $organization->id, 'code' => 'test_unit_transfer_approver'],
            ['name' => 'Test Unit Transfer Approver', 'description' => 'Test role.'],
        );
        $role->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());

        $membership = OrganizationMembership::query()->firstOrCreate(
            ['organization_id' => $organization->id, 'user_id' => $user->id],
            ['status' => 'active'],
        );

        RoleAssignment::query()->firstOrCreate([
            'organization_membership_id' => $membership->id,
            'role_id' => $role->id,
            'context_type' => 'unit',
            'context_id' => $unit->id,
        ]);
    }

    private function memberInTwoUnits(): array
    {
        $organization = Organization::factory()->create();
        $from = Unit::factory()->create(['organization_id' => $organization->id]);
        $to = Unit::factory()->create(['organization_id' => $organization->id]);
        $member = Member::factory()->forUnit($from)->create(['organization_id' => $organization->id]);

        return [$organization, $from, $to, $member];
    }
}
