<?php

namespace Modules\Member\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Member\Models\SpouseRelationshipRequest;

class SpouseRelationshipRequested extends Notification
{
    use Queueable;

    public function __construct(public SpouseRelationshipRequest $spouseRequest)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $application = $this->spouseRequest->application;
        $requesterMember = $this->spouseRequest->requesterMember;
        $memberData = data_get($application?->data, 'member', []);
        $requesterName = trim(implode(' ', array_filter([
            data_get($memberData, 'first_name'),
            data_get($memberData, 'middle_name'),
            data_get($memberData, 'last_name'),
        ])));

        return [
            'type' => 'spouse_relationship_request',
            'spouse_relationship_request_id' => $this->spouseRequest->id,
            'organization_id' => $this->spouseRequest->organization_id,
            'requester_name' => $requesterName ?: ($requesterMember?->user?->name ?? $application?->user?->name ?? 'A member applicant'),
            'message' => 'A membership applicant has requested to identify you as their spouse. Review the request in your member profile.',
            'url' => route('organization.member.profile'),
        ];
    }
}
