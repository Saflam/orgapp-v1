<?php

namespace Modules\Member\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Member\Enums\MemberRelationshipType;
use Modules\Member\Enums\MemberUnitTransferApprovalSide;
use Modules\Member\Enums\MemberUnitTransferApprovalStatus;
use Modules\Member\Enums\MemberUnitTransferStatus;
use App\Models\User;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberUnitTransferApproval;
use Modules\Member\Models\MemberChange;
use Modules\Member\Models\MemberRelationship;
use Modules\Member\Models\MemberUnitTransfer;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Services\AuthorizationService;

class MemberUnitTransferService
{
    public function submit(
        Member $member,
        int $targetUnitId,
        ?bool $spouseIncluded = null,
    ): MemberUnitTransfer {
        $member->loadMissing('unit');

        if ($member->unit_id === null) {
            throw new DomainException('A member without a current unit cannot request a unit transfer.');
        }

        if ($member->unit_id === $targetUnitId) {
            throw new DomainException('A member cannot request a transfer to the current unit.');
        }

        $this->ensureTargetUnitBelongsToOrganization($member, $targetUnitId);
        $this->ensureNoActiveTransfer($member);

        $spouseIncluded ??= true;
        $spouse = null;

        if ($spouseIncluded) {
            $spouse = $this->resolveEligibleSpouse($member);

            if ($spouse !== null) {
                $this->ensureNoActiveTransfer($spouse);
            }
        }

        return DB::transaction(function () use ($member, $targetUnitId, $spouseIncluded, $spouse): MemberUnitTransfer {
            $transfer = MemberUnitTransfer::create([
                'organization_id' => $member->organization_id,
                'member_id' => $member->id,
                'from_unit_id' => $member->unit_id,
                'to_unit_id' => $targetUnitId,
                'spouse_included' => $spouseIncluded,
                'spouse_member_id' => $spouse?->id,
                'status' => MemberUnitTransferStatus::SUBMITTED,
                'submitted_at' => now(),
            ]);

            $transfer->approvals()->createMany([
                [
                    'organization_id' => $member->organization_id,
                    'unit_id' => $member->unit_id,
                    'side' => MemberUnitTransferApprovalSide::OUTGOING,
                    'status' => MemberUnitTransferApprovalStatus::PENDING,
                ],
                [
                    'organization_id' => $member->organization_id,
                    'unit_id' => $targetUnitId,
                    'side' => MemberUnitTransferApprovalSide::INCOMING,
                    'status' => MemberUnitTransferApprovalStatus::PENDING,
                ],
            ]);

            $transfer->load('approvals');
            app(MemberUnitTransferNotificationService::class)->submitted($transfer);

            return $transfer;
        });
    }


    public function approve(
        MemberUnitTransfer $transfer,
        User $actor,
        MemberUnitTransferApprovalSide $side,
    ): MemberUnitTransfer {
        return DB::transaction(function () use ($transfer, $actor, $side): MemberUnitTransfer {
            $transfer = MemberUnitTransfer::query()
                ->whereKey($transfer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($transfer->status !== MemberUnitTransferStatus::SUBMITTED) {
                throw new DomainException('Only submitted unit transfer requests can be approved.');
            }

            $approval = $transfer->approvals()
                ->where('side', $side->value)
                ->lockForUpdate()
                ->first();

            if ($approval === null) {
                throw new DomainException('The requested approval side does not exist.');
            }

            if ($approval->status !== MemberUnitTransferApprovalStatus::PENDING) {
                throw new DomainException('This unit transfer approval has already been decided.');
            }

            $this->ensureActorCanDecideApproval($transfer, $approval, $actor, 'member.unit-transfer.approve');

            $approval->forceFill([
                'status' => MemberUnitTransferApprovalStatus::APPROVED,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'reason' => null,
            ])->save();

            $approvals = $transfer->approvals()->lockForUpdate()->get();

            if ($approvals->every(
                fn (MemberUnitTransferApproval $item): bool =>
                    $item->status === MemberUnitTransferApprovalStatus::APPROVED
            )) {
                $transfer->forceFill([
                    'status' => MemberUnitTransferStatus::APPROVED,
                ])->save();
            }

            $transfer = $transfer->fresh(['approvals']);
            app(MemberUnitTransferNotificationService::class)->approvalDecided($transfer, $side);

            return $transfer;
        });
    }

    public function complete(
        MemberUnitTransfer $transfer,
        User $actor,
    ): MemberUnitTransfer {
        return DB::transaction(function () use ($transfer, $actor): MemberUnitTransfer {
            $transfer = MemberUnitTransfer::query()
                ->whereKey($transfer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($transfer->status !== MemberUnitTransferStatus::APPROVED) {
                throw new DomainException('Only approved unit transfer requests can be completed.');
            }

            $approvals = $transfer->approvals()
                ->lockForUpdate()
                ->get();

            if ($approvals->count() !== 2 || ! $approvals->every(
                fn (MemberUnitTransferApproval $approval): bool =>
                    $approval->status === MemberUnitTransferApprovalStatus::APPROVED
            )) {
                throw new DomainException('Both unit transfer approvals are required before completion.');
            }

            $memberIds = [$transfer->member_id];

            if ($transfer->spouse_included && $transfer->spouse_member_id !== null) {
                $memberIds[] = $transfer->spouse_member_id;
            }

            sort($memberIds);

            $members = Member::query()
                ->where('organization_id', $transfer->organization_id)
                ->whereIn('id', $memberIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($memberIds as $memberId) {
                if (! $members->has($memberId)) {
                    throw new DomainException('A member included in this transfer could not be found.');
                }
            }

            foreach ($members as $member) {
                if ($member->unit_id !== $transfer->from_unit_id) {
                    throw new DomainException('A member included in this transfer is no longer in the original unit.');
                }
            }

            $authorization = app(AuthorizationService::class);
            $canCompleteFromSource = $authorization->can(
                $actor,
                'member.unit-transfer.complete',
                new AuthorizationContext($transfer->organization_id, $transfer->from_unit_id),
            );
            $canCompleteFromTarget = $authorization->can(
                $actor,
                'member.unit-transfer.complete',
                new AuthorizationContext($transfer->organization_id, $transfer->to_unit_id),
            );

            if (! $canCompleteFromSource && ! $canCompleteFromTarget) {
                throw new DomainException('The actor is not authorized to complete this unit transfer.');
            }

            foreach ($members as $member) {
                $beforeData = [
                    'unit_id' => $member->unit_id,
                ];

                app(MemberUnitHistoryService::class)->changeUnit(
                    member: $member,
                    newUnitId: $transfer->to_unit_id,
                    actorUserId: $actor->id,
                    transferId: $transfer->id,
                    source: 'transfer',
                    reason: 'Unit transfer completed.',
                );

                $member->forceFill([
                    'unit_id' => $transfer->to_unit_id,
                ])->save();

                MemberChange::create([
                    'member_id' => $member->id,
                    'actor_user_id' => $actor->id,
                    'reason' => 'Unit transfer completed.',
                    'before_data' => $beforeData,
                    'after_data' => [
                        'unit_id' => $member->unit_id,
                        'unit_transfer_id' => $transfer->id,
                    ],
                ]);
            }

            $transfer->forceFill([
                'status' => MemberUnitTransferStatus::COMPLETED,
                'completed_at' => now(),
            ])->save();

            $transfer = $transfer->fresh(['approvals']);
            app(MemberUnitTransferNotificationService::class)->completed($transfer);

            return $transfer;
        });
    }

    public function reject(
        MemberUnitTransfer $transfer,
        User $actor,
        MemberUnitTransferApprovalSide $side,
        string $reason,
    ): MemberUnitTransfer {
        $reason = trim($reason);

        if ($reason === '') {
            throw new DomainException('A rejection reason is required.');
        }

        return DB::transaction(function () use ($transfer, $actor, $side, $reason): MemberUnitTransfer {
            $transfer = MemberUnitTransfer::query()
                ->whereKey($transfer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($transfer->status !== MemberUnitTransferStatus::SUBMITTED) {
                throw new DomainException('Only submitted unit transfer requests can be rejected.');
            }

            $approval = $transfer->approvals()
                ->where('side', $side->value)
                ->lockForUpdate()
                ->first();

            if ($approval === null) {
                throw new DomainException('The requested approval side does not exist.');
            }

            if ($approval->status !== MemberUnitTransferApprovalStatus::PENDING) {
                throw new DomainException('This unit transfer approval has already been decided.');
            }

            $this->ensureActorCanDecideApproval($transfer, $approval, $actor, 'member.unit-transfer.reject');

            $now = now();

            $approval->forceFill([
                'status' => MemberUnitTransferApprovalStatus::REJECTED,
                'decided_by' => $actor->id,
                'decided_at' => $now,
                'reason' => $reason,
            ])->save();

            $transfer->forceFill([
                'status' => MemberUnitTransferStatus::REJECTED,
                'rejected_at' => $now,
                'rejected_by' => $actor->id,
                'rejection_reason' => $reason,
            ])->save();

            $transfer = $transfer->fresh(['approvals']);
            app(MemberUnitTransferNotificationService::class)->rejected($transfer, $side, $reason);

            return $transfer;
        });
    }

    private function ensureActorCanDecideApproval(
        MemberUnitTransfer $transfer,
        MemberUnitTransferApproval $approval,
        User $actor,
        string $permissionCode,
    ): void {
        $allowed = app(AuthorizationService::class)->can(
            $actor,
            $permissionCode,
            new AuthorizationContext($transfer->organization_id, $approval->unit_id),
        );

        if (! $allowed) {
            throw new DomainException('The actor is not authorized to decide this transfer approval because the required unit-scoped permission is missing.');
        }
    }

    private function ensureTargetUnitBelongsToOrganization(Member $member, int $targetUnitId): void
    {
        $exists = DB::table('units')
            ->where('id', $targetUnitId)
            ->where('organization_id', $member->organization_id)
            ->exists();

        if (! $exists) {
            throw new DomainException('The target unit must belong to the member\'s organization.');
        }
    }

    private function ensureNoActiveTransfer(Member $member): void
    {
        $activeStatuses = [
            MemberUnitTransferStatus::DRAFT->value,
            MemberUnitTransferStatus::SUBMITTED->value,
            MemberUnitTransferStatus::APPROVED->value,
        ];

        $exists = MemberUnitTransfer::query()
            ->where('organization_id', $member->organization_id)
            ->where(function ($query) use ($member): void {
                $query->where('member_id', $member->id)
                    ->orWhere('spouse_member_id', $member->id);
            })
            ->whereIn('status', $activeStatuses)
            ->exists();

        if ($exists) {
            throw new DomainException('This member already has an active unit transfer request.');
        }
    }

    private function resolveEligibleSpouse(Member $member): ?Member
    {
        $relationship = MemberRelationship::query()
            ->where('relationship_type', MemberRelationshipType::SPOUSE->value)
            ->whereNull('ended_at')
            ->where(function ($query) use ($member): void {
                $query->where('member_id', $member->id)
                    ->orWhere('related_member_id', $member->id);
            })
            ->first();

        if ($relationship === null) {
            return null;
        }

        $spouseId = $relationship->member_id === $member->id
            ? $relationship->related_member_id
            : $relationship->member_id;

        $spouse = Member::query()->find($spouseId);

        if ($spouse === null || $spouse->organization_id !== $member->organization_id) {
            throw new DomainException('The spouse must belong to the same organization.');
        }

        if ($spouse->unit_id !== $member->unit_id) {
            throw new DomainException('The spouse must belong to the member\'s current unit for a joint transfer.');
        }

        return $spouse;
    }
}
