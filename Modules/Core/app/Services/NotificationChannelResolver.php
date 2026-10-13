<?php

namespace Modules\Core\Services;

use Modules\Core\Models\Organization;
use Modules\Core\Models\OrganizationNotificationChannel;
use Modules\Core\Models\SystemNotificationChannel;

class NotificationChannelResolver
{
    /**
     * @return array{source: 'organization'|'system', credentials: array<string, mixed>}|null
     */
    public function resolve(
        Organization $organization,
        string $channel,
    ): ?array {
        $organizationChannel = OrganizationNotificationChannel::query()
            ->where('organization_id', $organization->getKey())
            ->where('channel', $channel)
            ->first();

        if ($organizationChannel?->is_enabled === true) {
            return [
                'source' => 'organization',
                'credentials' => $organizationChannel->credentials ?? [],
            ];
        }

        if ($organizationChannel?->use_system_default !== true) {
            return null;
        }

        $systemChannel = SystemNotificationChannel::query()
            ->where('channel', $channel)
            ->where('is_enabled', true)
            ->first();

        if ($systemChannel === null) {
            return null;
        }

        return [
            'source' => 'system',
            'credentials' => $systemChannel->credentials ?? [],
        ];
    }

    /**
     * @return array<string, array{source: 'organization'|'system', credentials: array<string, mixed>}>
     */
    public function resolveEnabledChannels(Organization $organization): array
    {
        $channelNames = OrganizationNotificationChannel::query()
            ->where('organization_id', $organization->getKey())
            ->pluck('channel')
            ->merge(
                SystemNotificationChannel::query()->pluck('channel')
            )
            ->unique()
            ->values();

        $resolved = [];

        foreach ($channelNames as $channel) {
            $configuration = $this->resolve($organization, $channel);

            if ($configuration !== null) {
                $resolved[$channel] = $configuration;
            }
        }

        return $resolved;
    }
}
