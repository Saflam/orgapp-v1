<?php

namespace Modules\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Models\NotificationDelivery;
use Modules\Core\Models\Organization;
use Modules\Core\Services\NotificationChannelRegistry;
use Modules\Core\Services\NotificationChannelResolver;
use Modules\Core\Services\RetryableWhatsAppDeliveryException;
use Modules\Core\Services\WhatsAppUsageRecorder;
use Throwable;

class DeliverNotification implements ShouldQueue
{
    use Dispatchable;
    use Queueable;
    use InteractsWithQueue;
    use SerializesModels;

    public int $tries = 3;

    /** Backoff for retryable provider responses, in seconds. */
    public function backoff(): array
    {
        return [10, 30];
    }

    public function __construct(public readonly int $deliveryId) {}

    public function handle(
        NotificationChannelRegistry $channels,
        NotificationChannelResolver $resolver,
        WhatsAppUsageRecorder $usageRecorder,
    ): void {
        $delivery = NotificationDelivery::query()->with('notification')->findOrFail($this->deliveryId);

        // If the provider message ID was persisted, the provider accepted the send.
        // Never send it again merely because a later local operation failed.
        if ($delivery->provider_message_id) {
            return;
        }

        $delivery->increment('attempts');
        $delivery->refresh();

        try {
            $organization = Organization::query()->findOrFail($delivery->notification->organization_id);
            $serviceCategory = (string) data_get($delivery->notification->data, 'service_category', 'utility');
            $configuration = $resolver->resolve($organization, $delivery->channel, $serviceCategory);

            if ($configuration === null || $configuration['source'] !== $delivery->channel_source) {
                throw new \RuntimeException(
                    "Notification channel [{$delivery->channel}] is no longer available from the queued source [{$delivery->channel_source}]."
                );
            }

            $channels->get($delivery->channel)->send($delivery, $configuration['credentials']);

            // A webhook may arrive before this worker finishes. Preserve provider states.
            $delivery->refresh();
            if (! in_array($delivery->status, ['delivered', 'read', 'failed'], true)) {
                $delivery->forceFill(['status' => 'sent', 'sent_at' => now(), 'error' => null])->save();
            }
        } catch (Throwable $exception) {
            $delivery->refresh();

            // Do not re-send if Meta accepted the message but a later local step failed.
            if ($delivery->provider_message_id) {
                if (! in_array($delivery->status, ['delivered', 'read', 'failed'], true)) {
                    $delivery->forceFill(['status' => 'sent', 'sent_at' => now(), 'error' => null])->save();
                }
                return;
            }

            if ($exception instanceof RetryableWhatsAppDeliveryException && $this->attempts() < $this->tries) {
                $backoff = $this->backoff();
                $defaultDelay = $backoff[min(max($this->attempts() - 1, 0), count($backoff) - 1)];
                $delay = $exception->retryAfterSeconds ?? $defaultDelay;
                $delivery->forceFill([
                    'status' => 'queued',
                    'error' => 'Temporary Meta API error; retry scheduled. ' . $exception->getMessage(),
                ])->save();
                $this->release($delay);
                return;
            }

            $delivery->forceFill(['status' => 'failed', 'error' => $exception->getMessage()])->save();

            if ($exception instanceof RetryableWhatsAppDeliveryException && $this->attempts() >= $this->tries) {
                $notification = $delivery->notification;
                $usageRecorder->recordFailed(
                    (int) $notification->organization_id,
                    $notification->user_id ? (int) $notification->user_id : null,
                    (string) data_get($notification->data, 'service_category', 'utility'),
                    $delivery->channel_source,
                    $delivery->id,
                    null,
                    $exception->errorCode,
                );
            }

            // Permanent errors should not consume all queue attempts. Retryable errors
            // are failed by the queue only after the configured retry budget is spent.
            if (! $exception instanceof RetryableWhatsAppDeliveryException) {
                $this->fail($exception);
                return;
            }

            throw $exception;
        }
    }
}
