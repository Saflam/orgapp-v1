<?php

namespace Modules\Core\Services;

use Modules\Core\Models\Organization;
use Modules\Core\Models\OrganizationNotificationChannel;
use Modules\Core\Models\SystemNotificationChannel;

class NotificationChannelResolver
{
    /** @return array{source: 'organization'|'system', credentials: array<string, mixed>}|null */
    public function resolve(Organization $organization, string $channel, string $serviceCategory = 'utility'): ?array
    {
        $organizationChannel = OrganizationNotificationChannel::query()
            ->where('organization_id', $organization->getKey())
            ->where('channel', $channel)
            ->first();

        if (in_array($channel, ['whatsapp', 'sms'], true) && $organizationChannel?->is_allowed !== true) {
            return null;
        }

        if ($channel === 'whatsapp' && ! $this->serviceAllowed($organizationChannel, $serviceCategory)) {
            return null;
        }

        if ($organizationChannel?->is_enabled === true
            && $this->hasUsableCredentials($channel, $organizationChannel->credentials ?? [], $serviceCategory)) {
            return ['source' => 'organization', 'credentials' => $organizationChannel->credentials ?? []];
        }

        if ($organizationChannel?->use_system_default !== true) {
            return null;
        }

        $systemChannel = SystemNotificationChannel::query()
            ->where('channel', $channel)
            ->where('is_enabled', true)
            ->first();

        if ($systemChannel === null || ! $this->hasUsableCredentials($channel, $systemChannel->credentials ?? [], $serviceCategory)) {
            return null;
        }

        return ['source' => 'system', 'credentials' => $systemChannel->credentials ?? []];
    }

    /** @return array<string, array{source: 'organization'|'system', credentials: array<string, mixed>}> */
    public function resolveEnabledChannels(Organization $organization, string $serviceCategory = 'utility'): array
    {
        $channelNames = OrganizationNotificationChannel::query()
            ->where('organization_id', $organization->getKey())
            ->pluck('channel')
            ->merge(SystemNotificationChannel::query()->pluck('channel'))
            ->unique()->values();

        $resolved = [];
        foreach ($channelNames as $channel) {
            $configuration = $this->resolve($organization, $channel, $serviceCategory);
            if ($configuration !== null) $resolved[$channel] = $configuration;
        }
        return $resolved;
    }

    private function serviceAllowed(?OrganizationNotificationChannel $channel, string $serviceCategory): bool
    {
        if ($channel?->is_allowed !== true) return false;
        return match ($serviceCategory) {
            'otp' => true,
            'utility' => $channel->utility_allowed === true,
            'marketing' => $channel->marketing_allowed === true,
            default => false,
        };
    }

    private function hasUsableCredentials(string $channel, array $credentials, string $serviceCategory): bool
    {
        if ($channel !== 'whatsapp') {
            return $credentials !== [] || ! in_array($channel, ['sms'], true);
        }

        $templateKey = match ($serviceCategory) {
            'otp' => 'otp_template_name',
            'marketing' => 'marketing_template_name',
            default => 'template_name',
        };

        foreach (['phone_number_id', 'access_token', $templateKey] as $key) {
            if (! isset($credentials[$key]) || ! is_string($credentials[$key]) || trim($credentials[$key]) === '') return false;
        }
        return true;
    }
}
