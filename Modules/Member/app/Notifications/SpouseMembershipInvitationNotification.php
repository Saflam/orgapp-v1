<?php

namespace Modules\Member\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Member\Models\SpouseMembershipInvitation;

class SpouseMembershipInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly SpouseMembershipInvitation $invitation,
        private readonly string $plainToken,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('organization.membership.apply.spouse-invitation', [
            'token' => $this->plainToken,
        ]);

        return (new MailMessage)
            ->subject('Invitation to complete your spouse membership application')
            ->greeting('Hello ' . $this->invitation->invitee_name . ',')
            ->line('You have been invited to complete a membership application as the spouse of ' . ($this->invitation->inviterMember?->user?->name ?? 'an organization member') . '.')
            ->line('Please sign in using this email address and complete your application yourself.')
            ->action('Complete spouse application', $url)
            ->line('This invitation expires on ' . $this->invitation->expires_at?->format('d M Y, h:i A') . '.')
            ->line('If you were not expecting this invitation, you can ignore this email.');
    }
}
