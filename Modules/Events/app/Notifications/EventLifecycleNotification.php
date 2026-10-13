<?php

namespace Modules\Events\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Events\Models\Event;

class EventLifecycleNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /** Maximum attempts made by Laravel's queue worker. */
    public int $tries = 4;

    /** Retry transient mail/transport failures with increasing delays. */
    public array $backoff = [30, 120, 300];

    /** Give mail transports up to 60 seconds per attempt. */
    public int $timeout = 60;

    public function __construct(
        private readonly Event $event,
        private readonly string $kind,
        private readonly string $participantName,
    ) {
        // Queue the notification only after the calling transaction has committed.
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = match ($this->kind) {
            'invited' => [
                'subject' => 'You are invited: '.$this->event->title,
                'intro' => 'You have been invited to attend this event.',
            ],
            'registered' => [
                'subject' => 'Registration confirmed: '.$this->event->title,
                'intro' => 'Your registration for this event is confirmed.',
            ],
            'participant_cancelled' => [
                'subject' => 'Registration cancelled: '.$this->event->title,
                'intro' => 'Your participation in this event has been cancelled.',
            ],
            'event_cancelled' => [
                'subject' => 'Event cancelled: '.$this->event->title,
                'intro' => 'This event has been cancelled by the organizer.',
            ],
            default => [
                'subject' => 'Event update: '.$this->event->title,
                'intro' => 'There is an update regarding this event.',
            ],
        };

        $mail = (new MailMessage())
            ->subject($message['subject'])
            ->greeting('Hello '.$this->participantName)
            ->line($message['intro'])
            ->line('Event: '.$this->event->title);

        if ($this->event->starts_at) {
            $mail->line('Starts: '.$this->event->starts_at->format('M j, Y g:i A').' ('.$this->event->timezone.')');
        }

        if ($this->event->venue_name) {
            $mail->line('Venue: '.$this->event->venue_name);
        }

        return $mail->line('This is an automated message from the event organizer.');
    }
}
