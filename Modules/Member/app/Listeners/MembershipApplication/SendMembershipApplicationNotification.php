<?php

namespace Modules\Member\Listeners\MembershipApplication;

use Modules\Core\Services\NotificationService;
use App\Models\OrganizationMembership;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Services\AuthorizationService;
use Modules\Member\Enums\MembershipApplicationStatus;
use Modules\Member\Events\MembershipApplication\MembershipApplicationApproved;
use Modules\Member\Events\MembershipApplication\MembershipApplicationReviewed;
use Modules\Member\Events\MembershipApplication\MembershipApplicationRejected;
use Modules\Member\Events\MembershipApplication\MembershipApplicationSubmitted;
use Modules\Member\Events\MembershipApplication\MembershipApplicationVerified;
use Modules\Member\Events\MembershipApplication\MembershipConfirmed;
use Modules\Member\Events\MembershipApplication\MembershipPaymentRequired;

class SendMembershipApplicationNotification
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly AuthorizationService $authorization,
    ) {}

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
            $event instanceof MembershipApplicationRejected => [
                'membership.application.rejected',
                'Membership application update',
                'Your membership application was rejected. Reason: ' . (string) ($application->statusHistories()->where('to_status', MembershipApplicationStatus::REJECTED->value)->latest('id')->value('notes') ?? 'Please contact the organization for details.'),
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

        $notificationData = [
            'application_id' => $application->getKey(),
            'status' => $application->status->value,
        ];

        $this->notifications->notifyOnce(
            organization: $organization,
            user: $user,
            event: $eventName,
            title: $title,
            body: $body,
            dedupeKey: "membership-application:{$application->id}:{$eventName}:user:{$user->id}",
            data: $notificationData,
            sourceModule: 'Member',
        );

        // Notify only staff who can actually process applications in the relevant scope.
        if ($event instanceof MembershipApplicationSubmitted) {
            $preferredUnitId = $application->unitId();
            $contexts = [new AuthorizationContext((int) $organization->id)];
            if ($preferredUnitId !== null) {
                $contexts[] = new AuthorizationContext((int) $organization->id, (int) $preferredUnitId);
            }

            $staff = OrganizationMembership::query()
                ->with('user')
                ->where('organization_id', $organization->id)
                ->where('status', 'active')
                ->get();

            foreach ($staff as $membership) {
                $recipient = $membership->user;
                if ($recipient === null || (int) $recipient->id === (int) $user->id) {
                    continue;
                }

                $canReview = false;
                foreach ($contexts as $context) {
                    foreach (['membership.application.verify'] as $permission) {
                        if ($this->authorization->can($recipient, $permission, $context)) {
                            $canReview = true;
                            break 2;
                        }
                    }
                }

                if (! $canReview) {
                    continue;
                }

                $this->notifications->notifyOnce(
                    organization: $organization,
                    user: $recipient,
                    event: 'membership.application.submitted.staff',
                    title: 'New membership application',
                    body: 'A new membership application requires staff review.',
                    dedupeKey: "membership-application:{$application->id}:submitted-staff:user:{$recipient->id}",
                    data: $notificationData + ['applicant_name' => $user->name],
                    sourceModule: 'Member',
                );
            }
        }

        // Route each completed stage to users authorized for the next action.
        $nextPermissions = match (true) {
            $event instanceof MembershipApplicationVerified => ['membership.application.review'],
            $event instanceof MembershipApplicationReviewed => ['membership.application.approve'],
            $event instanceof MembershipPaymentRequired => ['membership.payment.receive', 'membership.application.confirm'],
            default => [],
        };

        if ($nextPermissions !== []) {
            $unitId = $application->unitId();
            $context = new AuthorizationContext((int) $organization->id, $unitId);
            $staff = OrganizationMembership::query()
                ->with('user')
                ->where('organization_id', $organization->id)
                ->where('status', 'active')
                ->get();

            foreach ($staff as $membership) {
                $recipient = $membership->user;
                if ($recipient === null || (int) $recipient->id === (int) $user->id) {
                    continue;
                }

                $canTakeNextAction = false;
                foreach ($nextPermissions as $permission) {
                    if ($this->authorization->can($recipient, $permission, $context)) {
                        $canTakeNextAction = true;
                        break;
                    }
                }

                if (! $canTakeNextAction) {
                    continue;
                }

                $stage = str($application->status->value)->replace('_', ' ')->title()->toString();
                $this->notifications->notifyOnce(
                    organization: $organization,
                    user: $recipient,
                    event: 'membership.application.next_action',
                    title: 'Membership application requires action',
                    body: "Application #{$application->id} for {$user->name} is now {$stage} and requires your attention.",
                    dedupeKey: "membership-application:{$application->id}:next-action:{$application->status->value}:user:{$recipient->id}",
                    data: $notificationData + ['applicant_name' => $user->name, 'next_permissions' => $nextPermissions],
                    sourceModule: 'Member',
                );
            }
        }
    }
}
