<?php

namespace Modules\Member\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Member\Events\MembershipApplication\MembershipApplicationApproved;
use Modules\Member\Events\MembershipApplication\MembershipApplicationReviewed;
use Modules\Member\Events\MembershipApplication\MembershipApplicationRejected;
use Modules\Member\Events\MembershipApplication\MembershipApplicationSubmitted;
use Modules\Member\Events\MembershipApplication\MembershipApplicationVerified;
use Modules\Member\Events\MembershipApplication\MembershipConfirmed;
use Modules\Member\Events\MembershipApplication\MembershipPaymentRequired;
use Modules\Member\Events\MembershipPaymentReceived;
use Modules\Member\Listeners\MembershipApplication\SendMembershipApplicationNotification;
use Modules\Member\Listeners\SendMembershipPaymentReceipt;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        MembershipPaymentReceived::class => [
            SendMembershipPaymentReceipt::class,
        ],
        MembershipApplicationSubmitted::class => [
            SendMembershipApplicationNotification::class,
        ],
        MembershipApplicationVerified::class => [
            SendMembershipApplicationNotification::class,
        ],
        MembershipApplicationReviewed::class => [
            SendMembershipApplicationNotification::class,
        ],
        MembershipApplicationRejected::class => [
            SendMembershipApplicationNotification::class,
        ],
        MembershipApplicationApproved::class => [
            SendMembershipApplicationNotification::class,
        ],
        MembershipPaymentRequired::class => [
            SendMembershipApplicationNotification::class,
        ],
        MembershipConfirmed::class => [
            SendMembershipApplicationNotification::class,
        ],
    ];

    protected static $shouldDiscoverEvents = true;

    protected function configureEmailVerification(): void {}
}
