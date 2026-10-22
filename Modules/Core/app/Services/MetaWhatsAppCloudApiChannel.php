<?php

namespace Modules\Core\Services;

use Illuminate\Support\Facades\Http;
use Modules\Core\Contracts\NotificationChannel;
use Modules\Core\Models\NotificationDelivery;
use RuntimeException;

class MetaWhatsAppCloudApiChannel implements NotificationChannel
{
    public function __construct(
        private readonly WhatsAppUsageRecorder $usageRecorder,
        private readonly WhatsAppUsageLimitService $usageLimits,
    ) {}

    public function name(): string { return 'whatsapp'; }

    public function send(NotificationDelivery $delivery, array $credentials): void
    {
        $notification = $delivery->notification;
        $user = $notification->user;
        $organizationId = (int) $notification->organization_id;
        $service = (string) data_get($notification->data, 'service_category', 'utility');
        $templateKey = $service === 'marketing' ? 'marketing_template_name' : 'template_name';
        $languageKey = $service === 'marketing' ? 'marketing_template_language' : 'template_language';
        $phone = $user?->phone;

        if (! is_string($phone) || trim($phone) === '' || ! str_starts_with(trim($phone), '+')) {
            throw new RuntimeException('WhatsApp delivery requires an international E.164 recipient phone number.');
        }
        $recipient = preg_replace('/\D+/', '', $phone);
        if (! is_string($recipient) || strlen($recipient) < 7 || strlen($recipient) > 15) {
            throw new RuntimeException('The recipient phone number is not a valid international phone number.');
        }
        foreach (['phone_number_id', 'access_token', $templateKey] as $key) {
            if (! isset($credentials[$key]) || ! is_string($credentials[$key]) || trim($credentials[$key]) === '') {
                throw new RuntimeException("WhatsApp Cloud API configuration is missing [{$key}].");
            }
        }
        $apiVersion = $credentials['api_version'] ?? 'v23.0';
        if (! is_string($apiVersion) || ! preg_match('/^v\d+\.\d+$/', $apiVersion)) {
            throw new RuntimeException('WhatsApp Cloud API api_version must look like v23.0.');
        }
        $language = $credentials[$languageKey] ?? 'en_US';
        if (! is_string($language) || ! preg_match('/^[a-z]{2,3}_[A-Z]{2,4}$/', $language)) {
            throw new RuntimeException('WhatsApp template language must be a locale such as en_US.');
        }

        $payload = [
            'messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $recipient, 'type' => 'template',
            'template' => [
                'name' => $credentials[$templateKey], 'language' => ['code' => $language],
                'components' => [[ 'type' => 'body', 'parameters' => [
                    ['type' => 'text', 'text' => mb_substr((string) $notification->title, 0, 1024)],
                    ['type' => 'text', 'text' => mb_substr((string) $notification->body, 0, 1024)],
                ]]],
            ],
        ];
        $baseUrl = rtrim((string) ($credentials['base_url'] ?? 'https://graph.facebook.com'), '/');
        if (! filter_var($baseUrl, FILTER_VALIDATE_URL) || ! str_starts_with($baseUrl, 'https://')) {
            throw new RuntimeException('WhatsApp Cloud API base_url must be a valid HTTPS URL.');
        }

        $reserved = $this->usageLimits->reserve($organizationId);
        try {
            $response = Http::acceptJson()->asJson()->withToken($credentials['access_token'])
                ->connectTimeout(5)->timeout(20)
                ->post("{$baseUrl}/{$apiVersion}/{$credentials['phone_number_id']}/messages", $payload);
        } catch (\Throwable $exception) {
            $this->usageLimits->release($organizationId, $reserved);
            $this->usageRecorder->recordFailed($organizationId, $user?->id, $service, $delivery->channel_source, $delivery->id, null, 'request_exception');
            throw new RuntimeException('Meta WhatsApp Cloud API request failed before a response was received.', 0, $exception);
        }

        if (! $response->successful()) {
            $this->usageLimits->release($organizationId, $reserved);
            $providerMessage = $response->json('error.message');
            $providerCode = $response->json('error.code');
            $detail = is_string($providerMessage) ? $providerMessage : 'Meta returned an unsuccessful response.';
            $code = $providerCode !== null ? " (code {$providerCode})" : '';
            $message = "Meta WhatsApp Cloud API request failed{$code}: {$detail}";

            // Only retry when Meta explicitly returned a transient HTTP response.
            // Network timeouts are intentionally not retried automatically because
            // the provider may have accepted the message before the connection failed.
            if ($response->status() === 429 || $response->serverError()) {
                $retryAfter = $response->header('Retry-After');
                $retryAfterSeconds = is_string($retryAfter) && ctype_digit($retryAfter)
                    ? max(1, min(300, (int) $retryAfter))
                    : null;
                throw new RetryableWhatsAppDeliveryException($message, $retryAfterSeconds, (string) ($providerCode ?? 'meta_transient_error'));
            }

            $this->usageRecorder->recordFailed($organizationId, $user?->id, $service, $delivery->channel_source, $delivery->id, null, (string) ($providerCode ?? 'meta_api_error'));
            throw new RuntimeException($message);
        }
        $messageId = $response->json('messages.0.id');
        if (! is_string($messageId) || $messageId === '') {
            $this->usageLimits->release($organizationId, $reserved);
            $this->usageRecorder->recordFailed($organizationId, $user?->id, $service, $delivery->channel_source, $delivery->id, null, 'missing_message_id');
            throw new RuntimeException('Meta accepted the request but did not return a WhatsApp message ID.');
        }

        $delivery->forceFill(['provider_message_id' => $messageId])->save();
        $this->usageRecorder->recordAccepted(
            $organizationId, $user?->id, $service, $delivery->channel_source, $messageId,
            $delivery->id, null, ['template_name' => $credentials[$templateKey]],
        );
    }
}
