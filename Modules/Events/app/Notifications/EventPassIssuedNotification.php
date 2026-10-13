<?php

namespace Modules\Events\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Events\Models\Event;

class EventPassIssuedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Event $event,
        private readonly string $qrSvg,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Your event pass: '.$this->event->title)
            ->view('events::emails.event-pass-issued', [
                'event' => $this->event,
                'qrSvg' => $this->qrSvg,
            ]);
    }
}
