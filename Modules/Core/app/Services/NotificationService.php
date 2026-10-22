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

    public function setChannelAllowed(Organization $organization, string $channel, bool $allowed): OrganizationNotificationChannel
    {
        $existing = OrganizationNotificationChannel::query()
            ->where('organization_id', $organization->getKey())
            ->where('channel', $channel)
            ->first();
        return $this->updateWhatsAppPermissions(
            $organization,
            $channel,
            $allowed,
            (bool) ($existing?->use_system_default ?? false),
            $allowed && ($existing?->utility_allowed ?? false),
            $allowed && ($existing?->marketing_allowed ?? false),
        );
    }

    public function updateWhatsAppPermissions(
        Organization $organization,
        string $channel,
        bool $allowed,
        bool $useSystemDefault,
        bool $utilityAllowed = false,
        bool $marketingAllowed = false,
    ): OrganizationNotificationChannel {
        if (! in_array($channel, ['whatsapp', 'sms'], true)) {
            throw new \InvalidArgumentException('Only WhatsApp and SMS have organization-level channel permissions.');
        }
        if (! $allowed) {
            // Keep default-channel access as an independent System Admin setting.
            // The resolver still requires is_allowed before any channel can be used.
            $utilityAllowed = false;
            $marketingAllowed = false;
        }
        if ($channel !== 'whatsapp') {
            $utilityAllowed = false;
            $marketingAllowed = false;
        }
        return OrganizationNotificationChannel::query()->updateOrCreate(
            ['organization_id' => $organization->getKey(), 'channel' => $channel],
            [
                'is_allowed' => $allowed,
                'use_system_default' => $useSystemDefault,
                'utility_allowed' => $utilityAllowed,
                'marketing_allowed' => $marketingAllowed,
            ],
        );
    }

    public function setSystemDefaultAccess(
        Organization $organization,
        string $channel,
        bool $allowed,
    ): OrganizationNotificationChannel {
        $existing = OrganizationNotificationChannel::query()
            ->where('organization_id', $organization->getKey())
            ->where('channel', $channel)
            ->first();
        // Store this permission independently from the feature flag. The channel
        // resolver checks is_allowed as a separate prerequisite before delivery.
        return OrganizationNotificationChannel::query()->updateOrCreate(
            ['organization_id' => $organization->getKey(), 'channel' => $channel],
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
        string $serviceCategory = 'utility',
    ): ?array {
        return DB::transaction(function () use ($organization, $user, $event, $title, $body, $dedupeKey, $data, $sourceModule, $serviceCategory): ?array {
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
                serviceCategory: $serviceCategory,
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
        string $serviceCategory = 'utility',
    ): array {
        if (! in_array($serviceCategory, ['utility', 'marketing'], true)) {
            throw new \InvalidArgumentException('Notification service category must be utility or marketing.');
        }
        return DB::transaction(function () use ($organization, $user, $event, $title, $body, $data, $sourceModule, $serviceCategory): array {
            $notification = Notification::query()->create([
                'organization_id' => $organization->getKey(),
                'user_id' => $user->getKey(),
                'event' => $event,
                'title' => $title,
                'body' => $body,
                'data' => array_merge($data, ['service_category' => $serviceCategory]),
                'source_module' => $sourceModule,
            ]);

            $deliveries = [];

            foreach ($this->resolver->resolveEnabledChannels($organization, $serviceCategory) as $channel => $configuration) {
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
