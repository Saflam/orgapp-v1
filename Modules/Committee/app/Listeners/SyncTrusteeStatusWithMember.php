<?php

namespace Modules\Committee\Listeners;

use Modules\Committee\Services\TrusteeService;
use Modules\Member\Events\MembershipStatusChanged;

class SyncTrusteeStatusWithMember
{
    public function handle(MembershipStatusChanged $event): void
    {
        app(TrusteeService::class)->syncMemberStatus($event->member);
    }
}
