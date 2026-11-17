<?php

namespace Modules\Events\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Modules\Events\Enums\EventStatus;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventPass;
use Modules\Events\Notifications\EventPassIssuedNotification;
use Throwable;

/**
 * Delivers a pass email without serializing the bearer token or QR contents
 * into the queue payload. The QR SVG is encrypted in private storage; the job
 * carries only its path.
 */
class DeliverEventPassEmail implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 4;

    public array $backoff = [30, 120, 300];

    public int $timeout = 60;

    /** Prevent duplicate dispatches for the same pass across application nodes. */
    public int $uniqueFor = 900;

    public function __construct(
        public readonly int $passId,
        public readonly string $encryptedQrPath,
    ) {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return 'event-pass-delivery:'.$this->passId;
    }

    public function handle(): void
    {
        $pass = EventPass::query()->with(['event', 'participant'])->find($this->passId);

        if (! $pass) {
            $this->deleteQrArtifact();
            return;
        }

        // Claim in the same lock order used by attendance and pass expiry:
        // event first, then pass. This prevents a queued email from being sent
        // for a pass whose event has already ended or been cancelled.
        $claimState = DB::transaction(function () use ($pass): string {
            $event = Event::query()->lockForUpdate()->find($pass->event_id);
            $locked = EventPass::query()
                ->where('event_id', $pass->event_id)
                ->lockForUpdate()
                ->find($pass->id);

            if (! $event || ! $locked) {
                return 'terminal';
            }

            if ($locked->notification_delivery_status === 'sent'
                || $locked->notification_delivery_status === 'cancelled'
                || $locked->notification_delivery_status === 'failed') {
                return 'terminal';
            }

            if ($event->status !== EventStatus::Published) {
                $locked->notification_delivery_status = 'cancelled';
                $locked->save();

                return 'terminal';
            }

            if ($event->ends_at !== null && $event->ends_at->lessThanOrEqualTo(now())) {
                if ($locked->status === 'active') {
                    $locked->status = 'expired';
                }
                $locked->notification_delivery_status = 'cancelled';
                $locked->save();

                return 'terminal';
            }

            $participant = $locked->participant()->first();
            if ($locked->status !== 'active'
                || in_array($participant?->status, ['cancelled', 'declined'], true)) {
                $locked->notification_delivery_status = 'cancelled';
                $locked->save();

                return 'terminal';
            }

            // A duplicate first-attempt job must not send while the original
            // job owns delivery. A retry of this same queued job can resume
            // after an interrupted attempt; unique dispatch locking prevents
            // a second independently-dispatched job for this pass.
            if ($locked->notification_delivery_status === 'sending' && $this->attempts() <= 1) {
                return 'busy';
            }

            if (! in_array($locked->notification_delivery_status, ['queued', 'sending'], true)) {
                return 'terminal';
            }

            $locked->notification_delivery_status = 'sending';
            $locked->save();

            return 'claimed';
        });

        if ($claimState !== 'claimed') {
            if ($claimState === 'terminal') {
                $this->deleteQrArtifact();
            }
            return;
        }

        try {
            $encryptedSvg = Storage::disk('local')->get($this->encryptedQrPath);
            $qrSvg = Crypt::decryptString($encryptedSvg);

            $recipient = $pass->participant?->email;
            if (! $recipient && $pass->participant?->member_id) {
                $member = \Modules\Member\Models\Member::query()->with('user')->find($pass->participant->member_id);
                $recipient = $member?->user?->email;
            }

            if (! $recipient) {
                $pass->forceFill(['notification_delivery_status' => 'not_requested'])->save();
                $this->deleteQrArtifact();
                return;
            }

            Notification::route('mail', $recipient)->notify(
                new \Modules\Events\Notifications\EventPassIssuedNotification($pass->event, $qrSvg)
            );

            $pass->forceFill([
                'notification_delivery_status' => 'sent',
                'notification_sent_at' => now(),
                'notification_failed_at' => null,
            ])->save();

            $this->deleteQrArtifact();
        } catch (Throwable $exception) {
            EventPass::query()
                ->whereKey($this->passId)
                ->where('notification_delivery_status', 'sending')
                ->update(['notification_delivery_status' => 'queued']);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        EventPass::query()
            ->whereKey($this->passId)
            ->whereIn('notification_delivery_status', ['queued', 'sending'])
            ->update([
                'notification_delivery_status' => 'failed',
                'notification_failed_at' => now(),
            ]);

        $this->deleteQrArtifact();
    }

    private function deleteQrArtifact(): void
    {
        Storage::disk('local')->delete($this->encryptedQrPath);
    }
}
