<?php

namespace Modules\Core\Services;

use Modules\Core\Models\WhatsAppMessageUsage;

class WhatsAppUsageRecorder
{
    public function recordAccepted(
        ?int $organizationId,
        ?int $userId,
        string $serviceCategory,
        string $source,
        string $messageId,
        ?int $deliveryId = null,
        ?int $otpChallengeId = null,
        array $metadata = [],
    ): WhatsAppMessageUsage {
        return WhatsAppMessageUsage::query()->create([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'notification_delivery_id' => $deliveryId,
            'otp_challenge_id' => $otpChallengeId,
            'service_category' => $serviceCategory,
            'channel_source' => $source,
            'provider_message_id' => $messageId,
            'status' => 'accepted',
            'metadata' => $metadata,
            'accepted_at' => now(),
        ]);
    }

    public function recordFailed(
        ?int $organizationId,
        ?int $userId,
        string $serviceCategory,
        string $source,
        ?int $deliveryId = null,
        ?int $otpChallengeId = null,
        string $errorCode = 'provider_error',
    ): WhatsAppMessageUsage {
        return WhatsAppMessageUsage::query()->create([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'notification_delivery_id' => $deliveryId,
            'otp_challenge_id' => $otpChallengeId,
            'service_category' => $serviceCategory,
            'channel_source' => $source,
            'status' => 'failed',
            'metadata' => ['error_code' => $errorCode],
        ]);
    }
}
