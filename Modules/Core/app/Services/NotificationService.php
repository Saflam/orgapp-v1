<?php

namespace Modules\Core\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Core\Jobs\DeliverNotification;
use Modules\Core\Models\Notification;
use Modules\Core\Models\NotificationDelivery;
use Modules\Core\Models\Organization;
use Modules\Core\Models\OrganizationNotificationChannel;
use Modules\Core\Models\SystemNotificationChannel;
use Modules\Core\Models\UserNotificationPreference;

class NotificationService
{
    public function __construct(
        private readonly NotificationChannelRegistry $channels,
        private readonly NotificationChannelResolver $resolver,
    ) {}

    public function configureSystemChannel(
        string $channel,
        bool $enabled,
        array $credentials = [],
    ): SystemNotificationChannel {
        return SystemNotificationChannel::query()->updateOrCreate(
            ['channel' => $channel],
            [
                'is_enabled' => $enabled,
                'credentials' => $credentials,
            ],
        );
    }

    /**
     * Backward-compatible helper for simple organization channel configuration.
     *
     * This enables an organization-owned channel without credentials, which is
     * useful for provider implementations whose credentials are configured
     * outside this service.
     */
    public function configureChannel(
        Organization $organization,
        string $channel,
        bool $enabled,
    ): OrganizationNotificationChannel {
        return $this->configureOrganizationChannel(
            organization: $organization,
            channel: $channel,
            enabled: $enabled,
        );
    }

    public function configureOrganizationChannel(
        Organization $organization,
        string $channel,
        bool $enabled,
        array $credentials = [],
    ): OrganizationNotificationChannel {
        return OrganizationNotificationChannel::query()->updateOrCreate(
            [
                'organization_id' => $organization->getKey(),
                'channel' => $channel,
            ],
            [
                'is_enabled' => $enabled,
                'credentials' => $credentials,
            ],
        );
    }

    public function setSystemDefaultAccess(
        Organization $organization,
        string $channel,
        bool $allowed,
    ): OrganizationNotificationChannel {
        return OrganizationNotificationChannel::query()->updateOrCreate(
            [
                'organization_id' => $organization->getKey(),
                'channel' => $channel,
            ],
            ['use_system_default' => $allowed],
        );
    }

    public function setUserPreference(
        Organization $organization,
        User $user,
        string $channel,
        bool $enabled,
    ): UserNotificationPreference {
        return UserNotificationPreference::query()->updateOrCreate(
            [
                'organization_id' => $organization->getKey(),
                'user_id' => $user->getKey(),
                'channel' => $channel,
            ],
            ['is_enabled' => $enabled],
        );
    }

    /**
     * Create one notification and queue deliveries for its effective channels.
     * Organization credentials override the system default when enabled.
     * Otherwise an organization may fall back to the system default only when
     * use_system_default is enabled for that channel.
     *
     * @return array{notification: Notification, deliveries: array<int, NotificationDelivery>}
     */
    public function notifyOnce(
        Organization $organization,
        User $user,
        string $event,
        string $title,
        string $body,
        string $dedupeKey,
        array $data = [],
        ?string $sourceModule = null,
    ): ?array {
        return DB::transaction(function () use ($organization, $user, $event, $title, $body, $dedupeKey, $data, $sourceModule): ?array {
            $existing = Notification::query()
                ->where('organization_id', $organization->getKey())
                ->where('user_id', $user->getKey())
                ->where('event', $event)
                ->where('data->dedupe_key', $dedupeKey)
                ->first();

            if ($existing !== null) {
                return null;
            }

            return $this->notify(
                organization: $organization,
                user: $user,
                event: $event,
                title: $title,
                body: $body,
                data: array_merge($data, ['dedupe_key' => $dedupeKey]),
                sourceModule: $sourceModule,
            );
        });
    }

    /**
     * Retry an existing failed delivery without creating a new notification
     * or delivery record.
     *
     * The delivery attempt counter is intentionally preserved and will be
     * incremented again by DeliverNotification when the queued job runs.
     */
    public function retryFailedDelivery(NotificationDelivery $delivery): NotificationDelivery
    {
        if ($delivery->status !== 'failed') {
            throw new \InvalidArgumentException(
                "Only failed notification deliveries can be retried."
            );
        }

        return DB::transaction(function () use ($delivery): NotificationDelivery {
            $delivery->refresh();

            if ($delivery->status !== 'failed') {
                throw new \InvalidArgumentException(
                    "Only failed notification deliveries can be retried."
                );
            }

            $delivery->forceFill([
                'status' => 'queued',
                'error' => null,
                'queued_at' => now(),
                'sent_at' => null,
            ])->save();

            DeliverNotification::dispatch($delivery->getKey())->afterCommit();

            return $delivery->fresh();
        });
    }

    public function notify(
        Organization $organization,
        User $user,
        string $event,
        string $title,
        string $body,
        array $data = [],
        ?string $sourceModule = null,
    ): array {
        return DB::transaction(function () use ($organization, $user, $event, $title, $body, $data, $sourceModule): array {
            $notification = Notification::query()->create([
                'organization_id' => $organization->getKey(),
                'user_id' => $user->getKey(),
                'event' => $event,
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'source_module' => $sourceModule,
            ]);

            $deliveries = [];

            foreach ($this->resolver->resolveEnabledChannels($organization) as $channel => $configuration) {
                if (! $this->channels->has($channel)) {
                    continue;
                }

                $preference = UserNotificationPreference::query()
                    ->where('organization_id', $organization->getKey())
                    ->where('user_id', $user->getKey())
                    ->where('channel', $channel)
                    ->first();

                if ($preference?->is_enabled === false) {
                    continue;
                }

                $delivery = NotificationDelivery::query()->create([
                    'notification_id' => $notification->getKey(),
                    'channel' => $channel,
                    'channel_source' => $configuration['source'],
                    'status' => 'queued',
                    'attempts' => 0,
                    'queued_at' => now(),
                ]);

                DeliverNotification::dispatch($delivery->getKey())->afterCommit();
                $deliveries[] = $delivery;
            }

            return [
                'notification' => $notification,
                'deliveries' => $deliveries,
            ];
        });
    }
}
