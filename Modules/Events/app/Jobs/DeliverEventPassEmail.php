<?php

namespace Modules\Events\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Modules\Events\Enums\EventStatus;
use Modules\Events\Models\EventPass;
use Modules\Events\Notifications\EventPassIssuedNotification;
use Throwable;

/**
 * Delivers a pass email without serializing the bearer token or QR contents
 * into the queue payload. The QR SVG is encrypted in private storage; the job
 * carries only its path.
 */
class DeliverEventPassEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 4;

    public array $backoff = [30, 120, 300];

    public int $timeout = 60;

    public function __construct(
        public readonly int $passId,
        public readonly string $encryptedQrPath,
    ) {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $pass = EventPass::query()->with(['event', 'participant'])->find($this->passId);

        if (! $pass) {
            $this->deleteQrArtifact();
            return;
        }

        // Duplicate jobs skip a delivery already recorded as successful.
        if ($pass->notification_delivery_status === 'sent') {
            $this->deleteQrArtifact();
            return;
        }

        // A pass must still be usable when the queued email is actually sent.
        if ($pass->status !== 'active'
            || $pass->event?->status === EventStatus::Cancelled
            || in_array($pass->participant?->status, ['cancelled', 'declined'], true)) {
            $pass->forceFill(['notification_delivery_status' => 'cancelled'])->save();
            $this->deleteQrArtifact();
            return;
        }

        $claimed = DB::transaction(function () use ($pass): bool {
            $locked = EventPass::query()->lockForUpdate()->find($pass->id);
            if (! $locked || $locked->notification_delivery_status === 'sent') {
                return false;
            }

            // A second first-attempt job should not send while another job owns delivery.
            // A retry of this same job can resume after an interrupted attempt.
            if ($locked->notification_delivery_status === 'sending' && $this->attempts() <= 1) {
                return false;
            }

            $locked->notification_delivery_status = 'sending';
            $locked->save();

            return true;
        });

        if (! $claimed) {
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
            EventPass::query()->whereKey($this->passId)->update([
                'notification_delivery_status' => 'queued',
            ]);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        EventPass::query()->whereKey($this->passId)->update([
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
