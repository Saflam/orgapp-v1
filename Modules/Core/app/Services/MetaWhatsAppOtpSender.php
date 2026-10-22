<?php

namespace Modules\Core\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Core\Models\Organization;
use Modules\Core\Models\OtpChallenge;
use Throwable;

class MetaWhatsAppOtpSender
{
    public function __construct(
        private readonly NotificationChannelResolver $resolver,
        private readonly WhatsAppUsageRecorder $usageRecorder,
        private readonly WhatsAppUsageLimitService $usageLimits,
    ) {}

    public function send(User $user, OtpChallenge $challenge, string $code, Organization $organization): bool
    {
        $configuration = $this->resolver->resolve($organization, 'whatsapp', 'otp');
        if ($configuration === null) return false;

        $source = $configuration['source'];
        $credentials = $configuration['credentials'];
        $phone = $user->phone;
        // Send only to a stored international-format number. Saflivo should collect WhatsApp opt-in in its login/profile UX before production use.
        if (! is_string($phone) || ! str_starts_with(trim($phone), '+')) return false;
        $recipient = preg_replace('/\D+/', '', $phone);
        if (! is_string($recipient) || strlen($recipient) < 7 || strlen($recipient) > 15) return false;

        try {
            $reserved = $this->usageLimits->reserve($organization);
        } catch (WhatsAppUsageLimitExceeded) {
            return false;
        }

        try {
            $apiVersion = $credentials['api_version'] ?? 'v23.0';
            $templateName = $credentials['otp_template_name'];
            $language = $credentials['otp_template_language'] ?? 'en_US';
            $baseUrl = rtrim((string) ($credentials['base_url'] ?? 'https://graph.facebook.com'), '/');
            $response = Http::acceptJson()->asJson()->withToken($credentials['access_token'])
                ->connectTimeout(5)->timeout(20)
                ->post("{$baseUrl}/{$apiVersion}/{$credentials['phone_number_id']}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $recipient,
                    'type' => 'template',
                    'template' => [
                        'name' => $templateName,
                        'language' => ['code' => $language],
                        'components' => [
                            ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $code]]],
                            ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $code]]],
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                $this->usageLimits->release($organization, $reserved);
                $this->usageRecorder->recordFailed($organization->id, $user->id, 'otp', $source, null, $challenge->id, (string) ($response->json('error.code') ?? 'meta_api_error'));
                return false;
            }
            $messageId = $response->json('messages.0.id');
            if (! is_string($messageId) || $messageId === '') {
                $this->usageLimits->release($organization, $reserved);
                $this->usageRecorder->recordFailed($organization->id, $user->id, 'otp', $source, null, $challenge->id, 'missing_message_id');
                return false;
            }
            $this->usageRecorder->recordAccepted($organization->id, $user->id, 'otp', $source, $messageId, null, $challenge->id, ['template_name' => $templateName]);
            return true;
        } catch (Throwable $exception) {
            if (isset($reserved)) $this->usageLimits->release($organization, $reserved);
            $this->usageRecorder->recordFailed($organization->id, $user->id, 'otp', $source, null, $challenge->id, 'request_exception');
            return false;
        }
    }
}
