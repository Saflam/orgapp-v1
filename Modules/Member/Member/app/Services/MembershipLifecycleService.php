<?php

namespace Modules\Member\Services;

use DomainException;
use Modules\Member\Enums\MembershipStatus;
use Modules\Member\Models\Membership;

class MembershipLifecycleService
{
    public function activate(Membership $membership): Membership
    {
        $this->ensureStatus(
            $membership,
            MembershipStatus::IN_REVIEW,
            MembershipStatus::ACTIVE
        );

        $membership->status = MembershipStatus::ACTIVE;
        $membership->save();

        return $membership->refresh();
    }

    public function markDormant(Membership $membership): Membership
    {
        $this->ensureStatus(
            $membership,
            MembershipStatus::ACTIVE,
            MembershipStatus::DORMANT
        );

        $membership->status = MembershipStatus::DORMANT;
        $membership->save();

        return $membership->refresh();
    }

    public function expire(Membership $membership): Membership
    {
        $this->ensureStatus(
            $membership,
            MembershipStatus::DORMANT,
            MembershipStatus::EXPIRED
        );

        $membership->status = MembershipStatus::EXPIRED;
        $membership->save();

        return $membership->refresh();
    }

    public function cancel(Membership $membership): Membership
    {
        $allowedStatuses = [
            MembershipStatus::ACTIVE,
            MembershipStatus::DORMANT,
        ];

        if (! in_array($membership->status, $allowedStatuses, true)) {
            throw new DomainException(
                "Membership cannot be cancelled from status [{$membership->status->value}]."
            );
        }

        $membership->status = MembershipStatus::CANCELLED;
        $membership->save();

        return $membership->refresh();
    }

    private function ensureStatus(
        Membership $membership,
        MembershipStatus $expected,
        MembershipStatus $new
    ): void {
        if ($membership->status !== $expected) {
            throw new DomainException(
                "Membership cannot transition from "
                . "[{$membership->status->value}] to "
                . "[{$new->value}]."
            );
        }
    }
}