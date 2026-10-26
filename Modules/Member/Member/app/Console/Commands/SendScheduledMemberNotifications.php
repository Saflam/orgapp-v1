<?php

namespace Modules\Member\Console\Commands;

use Illuminate\Console\Command;
use Modules\Member\Services\MemberScheduledNotificationService;

class SendScheduledMemberNotifications extends Command
{
    protected $signature = 'member:send-scheduled-notifications';

    protected $description = 'Send scheduled membership and identification notifications.';

    public function handle(MemberScheduledNotificationService $service): int
    {
        $subscriptionCount = $service->sendSubscriptionReminders();
        $membershipExpiryCount = $service->sendMembershipExpiryReminders();
        $identificationCount = $service->sendIdentificationExpiryReminders();
        $dormantCount = $service->markExpiredMembershipsDormant();

        $this->info("Sent {$subscriptionCount} subscription reminder notification(s).");
        $this->info("Sent {$membershipExpiryCount} membership expiry reminder notification(s).");
        $this->info("Sent {$identificationCount} identification expiry notification(s).");
        $this->info("Moved {$dormantCount} expired membership(s) to dormant.");

        return self::SUCCESS;
    }
}
