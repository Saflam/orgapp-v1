<?php

namespace Modules\Member\Events;

use Modules\Member\Models\Member;
use Modules\Member\Models\Membership;

class MembershipStatusChanged
{
    public function __construct(
        public readonly Member $member,
        public readonly Membership $membership,
        public readonly mixed $oldStatus,
        public readonly mixed $newStatus,
    ) {}
}
