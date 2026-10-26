<?php

namespace Modules\Member\Services;

use App\Models\User;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Core\Services\NotificationService;
use Modules\Member\Enums\MemberUnitTransferApprovalSide;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberUnitTransfer;

class MemberUnitTransferNotificationService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function submitted(MemberUnitTransfer $transfer): void
    {
        $transfer->loadMissing(['organization', 'member.user', 'spouse.user', 'fromUnit', 'toUnit']);
        $personLabel = $this->personLabel($transfer);
        $body = "A unit transfer request for {$personLabel} was submitted from {$transfer->fromUnit->name} to {$transfer->toUnit->name}.";

        foreach ([$transfer->from_unit_id, $transfer->to_unit_id] as $unitId) {
            $userIds = Member::query()
                ->where('organization_id', $transfer->organization_id)
                ->where('unit_id', $unitId)
                ->whereNotNull('user_id')
                ->pluck('user_id')
                ->unique();

            foreach (User::query()->whereIn('id', $userIds)->get() as $user) {
                $side = $unitId === $transfer->from_unit_id ? 'outgoing' : 'incoming';
                $this->notify($transfer, $user, 'submitted', "Unit transfer request ({$side})", $body,
                    "member-unit-transfer:{$transfer->id}:submitted:{$side}:user:{$user->id}", ['side' => $side]);
            }
        }
    }

    public function approvalDecided(MemberUnitTransfer $transfer, MemberUnitTransferApprovalSide $side): void
    {
        $transfer->loadMissing(['organization', 'member.user', 'spouse.user', 'fromUnit', 'toUnit', 'approvals']);
        $otherSide = $side === MemberUnitTransferApprovalSide::OUTGOING
            ? MemberUnitTransferApprovalSide::INCOMING
            : MemberUnitTransferApprovalSide::OUTGOING;
        $other = $transfer->approvals->firstWhere('side', $otherSide->value);

        if ($transfer->status->value === 'approved') {
            $body = "Both units approved your transfer from {$transfer->fromUnit->name} to {$transfer->toUnit->name}. The transfer is awaiting completion.";
        } else {
            $otherUnit = $otherSide === MemberUnitTransferApprovalSide::OUTGOING ? $transfer->fromUnit : $transfer->toUnit;
            $body = "Your unit transfer has been approved by one unit and is awaiting a decision from {$otherUnit->name}.";
        }

        foreach ($this->transferPeople($transfer) as $member) {
            if ($member->user !== null) {
                $this->notify($transfer, $member->user, 'approval', 'Unit transfer approval update', $this->spouseAwareBody($transfer, $body),
                    "member-unit-transfer:{$transfer->id}:approval:{$side->value}:user:{$member->user->id}", ['side' => $side->value]);
            }
        }
    }

    public function rejected(MemberUnitTransfer $transfer, MemberUnitTransferApprovalSide $side, string $reason): void
    {
        $transfer->loadMissing(['organization', 'member.user', 'spouse.user', 'fromUnit', 'toUnit']);
        $body = "Your unit transfer request from {$transfer->fromUnit->name} to {$transfer->toUnit->name} was rejected. Reason: {$reason}";

        foreach ($this->transferPeople($transfer) as $member) {
            if ($member->user !== null) {
                $this->notify($transfer, $member->user, 'rejected', 'Unit transfer rejected', $this->spouseAwareBody($transfer, $body),
                    "member-unit-transfer:{$transfer->id}:rejected:{$side->value}:user:{$member->user->id}", ['side' => $side->value, 'reason' => $reason]);
            }
        }
    }

    public function completed(MemberUnitTransfer $transfer): void
    {
        $transfer->loadMissing(['organization', 'member.user', 'spouse.user', 'fromUnit', 'toUnit']);
        $subject = $transfer->spouse_included && $transfer->spouse_member_id !== null ? 'Your joint unit transfer' : 'Your unit transfer';
        $body = "{$subject} from {$transfer->fromUnit->name} to {$transfer->toUnit->name} is complete.";

        foreach ($this->transferPeople($transfer) as $member) {
            if ($member->user !== null) {
                $this->notify($transfer, $member->user, 'completed', 'Unit transfer completed', $body,
                    "member-unit-transfer:{$transfer->id}:completed:user:{$member->user->id}", ['member_id' => $member->id]);
            }
        }
    }

    private function notify(MemberUnitTransfer $transfer, User $user, string $stage, string $title, string $body, string $dedupeKey, array $data = []): void
    {
        $this->notifications->notifyOnce(
            organization: $transfer->organization,
            user: $user,
            event: "member.unit_transfer.{$stage}",
            title: $title,
            body: $body,
            dedupeKey: $dedupeKey,
            data: array_merge(['unit_transfer_id' => $transfer->id], $data),
            sourceModule: 'Member',
        );
    }

    /** @return array<int, Member> */
    private function transferPeople(MemberUnitTransfer $transfer): array
    {
        $people = [$transfer->member];
        if ($transfer->spouse_included && $transfer->spouse_member_id !== null && $transfer->spouse !== null) {
            $people[] = $transfer->spouse;
        }
        return $people;
    }

    private function personLabel(MemberUnitTransfer $transfer): string
    {
        $name = $transfer->member->full_name ?: 'member #' . $transfer->member_id;
        if ($transfer->spouse_included && $transfer->spouse_member_id !== null) {
            $spouseName = $transfer->spouse?->full_name ?: 'spouse';
            return "{$name} and {$spouseName}";
        }
        return $name;
    }

    private function spouseAwareBody(MemberUnitTransfer $transfer, string $body): string
    {
        return $transfer->spouse_included && $transfer->spouse_member_id !== null
            ? str_replace('Your unit transfer', 'Your joint unit transfer', $body)
            : $body;
    }
}
