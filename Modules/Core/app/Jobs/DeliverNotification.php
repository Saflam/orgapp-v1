<?php

namespace Modules\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Models\NotificationDelivery;
use Modules\Core\Services\NotificationChannelRegistry;
use Modules\Core\Services\NotificationChannelResolver;
use Modules\Core\Models\Organization;

class DeliverNotification implements ShouldQueue
{
    use Dispatchable;
    use Queueable;
    use InteractsWithQueue;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $deliveryId,
    ) {}

    public function handle(
        NotificationChannelRegistry $channels,
        NotificationChannelResolver $resolver,
    ): void {
        $delivery = NotificationDelivery::query()
            ->with('notification')
            ->findOrFail($this->deliveryId);

        $delivery->increment('attempts');
        $delivery->refresh();

        try {
            $organization = Organization::query()->findOrFail(
                $delivery->notification->organization_id
            );

            $configuration = $resolver->resolve(
                $organization,
                $delivery->channel,
            );

            if ($configuration === null || $configuration['source'] !== $delivery->channel_source) {
                throw new \RuntimeException(
                    "Notification channel [{$delivery->channel}] is no longer available from the queued source [{$delivery->channel_source}]."
                );
            }

            $channels->get($delivery->channel)->send(
                $delivery,
                $configuration['credentials'],
            );

            $delivery->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
                'error' => null,
            ])->save();
        } catch (\Throwable $exception) {
            $delivery->forceFill([
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }
}
