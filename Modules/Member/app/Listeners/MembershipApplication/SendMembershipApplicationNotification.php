<?php

namespace Modules\Member\Listeners\MembershipApplication;

use Modules\Core\Services\NotificationService;
use Modules\Member\Events\MembershipApplication\MembershipApplicationApproved;
use Modules\Member\Events\MembershipApplication\MembershipApplicationReviewed;
use Modules\Member\Events\MembershipApplication\MembershipApplicationSubmitted;
use Modules\Member\Events\MembershipApplication\MembershipApplicationVerified;
use Modules\Member\Events\MembershipApplication\MembershipConfirmed;
use Modules\Member\Events\MembershipApplication\MembershipPaymentRequired;

class SendMembershipApplicationNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(object $event): void
    {
        $application = $event->application;

        [$eventName, $title, $body] = match (true) {
            $event instanceof MembershipApplicationSubmitted => [
                'membership.application.submitted',
                'Membership application submitted',
                'Your membership application has been submitted successfully.',
            ],
            $event instanceof MembershipApplicationVerified => [
                'membership.application.verified',
                'Membership application verified',
                'Your membership application has been verified and is moving to review.',
            ],
            $event instanceof MembershipApplicationReviewed => [
                'membership.application.reviewed',
                'Membership application reviewed',
                'Your membership application has been reviewed.',
            ],
            $event instanceof MembershipApplicationApproved => [
                'membership.application.approved',
                'Membership application approved',
                'Your membership application has been approved. Payment is required before confirmation.',
            ],
            $event instanceof MembershipPaymentRequired => [
                'membership.payment.required',
                'Membership payment required',
                'Your membership application has been approved and payment is now required.',
            ],
            $event instanceof MembershipConfirmed => [
                'membership.confirmed',
                'Membership confirmed',
                'Your membership has been confirmed successfully.',
            ],
            default => [null, null, null],
        };

        if ($eventName === null) {
            return;
        }

        $organization = $application->organization;
        $user = $application->user;

        $this->notifications->notify(
            organization: $organization,
            user: $user,
            event: $eventName,
            title: $title,
            body: $body,
            data: [
                'application_id' => $application->getKey(),
                'status' => $application->status->value,
            ],
            sourceModule: 'Member',
        );
    }
}
