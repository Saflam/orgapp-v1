<?php

namespace Modules\Member\Services;

use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberUnitHistory;

class MemberUnitHistoryService
{
    public function ensureInitial(Member $member, ?CarbonInterface $startedAt = null): ?MemberUnitHistory
    {
        if ($member->unit_id === null) {
            return null;
        }

        if ($member->unitHistories()->exists()) {
            return $member->unitHistories()->latest('started_at')->first();
        }

        $startedAt ??= $member->joined_at?->startOfDay() ?? now();

        return MemberUnitHistory::create([
            'organization_id' => $member->organization_id,
            'member_id' => $member->id,
            'unit_id' => $member->unit_id,
            'started_at' => $startedAt,
            'ended_at' => null,
            'source' => 'initial',
            'actor_user_id' => null,
            'member_unit_transfer_id' => null,
            'reason' => null,
        ]);
    }

    public function changeUnit(
        Member $member,
        ?int $newUnitId,
        ?int $actorUserId = null,
        ?int $transferId = null,
        string $source = 'manual',
        ?string $reason = null,
        ?CarbonInterface $changedAt = null,
    ): void {
        $changedAt ??= now();

        if ($member->unit_id === $newUnitId) {
            return;
        }

        $this->ensureInitial($member);

        $currentHistory = $member->unitHistories()
            ->whereNull('ended_at')
            ->lockForUpdate()
            ->first();

        if ($currentHistory !== null) {
            if ($changedAt->lessThanOrEqualTo($currentHistory->started_at)) {
                throw new DomainException('The unit change timestamp must be after the current unit history start.');
            }

            $currentHistory->forceFill([
                'ended_at' => $changedAt,
            ])->save();
        }

        if ($newUnitId === null) {
            return;
        }

        MemberUnitHistory::create([
            'organization_id' => $member->organization_id,
            'member_id' => $member->id,
            'unit_id' => $newUnitId,
            'started_at' => $changedAt,
            'ended_at' => null,
            'source' => $source,
            'actor_user_id' => $actorUserId,
            'member_unit_transfer_id' => $transferId,
            'reason' => $reason,
        ]);
    }
}
