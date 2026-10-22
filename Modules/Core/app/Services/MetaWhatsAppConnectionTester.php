<?php

namespace Modules\Core\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class MetaWhatsAppConnectionTester
{
    /**
     * Verify credentials against the Phone Number object. This is a read-only
     * Graph API request and never sends a WhatsApp message.
     *
     * @return array{ok:bool,message:string,phone_number:?string,verified_name:?string,code:?string}
     */
    public function test(array $credentials): array
    {
        $phoneNumberId = trim((string) ($credentials['phone_number_id'] ?? ''));
        $accessToken = trim((string) ($credentials['access_token'] ?? ''));
        $apiVersion = (string) ($credentials['api_version'] ?? 'v23.0');
        $baseUrl = rtrim((string) ($credentials['base_url'] ?? 'https://graph.facebook.com'), '/');

        if ($phoneNumberId === '' || $accessToken === '') {
            return $this->failure('Enter a Phone Number ID and access token before testing.', 'missing_credentials');
        }
        if (! preg_match('/^v\d+\.\d+$/', $apiVersion)) {
            return $this->failure('The Graph API version must use a format such as v23.0.', 'invalid_api_version');
        }
        if (! filter_var($baseUrl, FILTER_VALIDATE_URL) || ! str_starts_with(strtolower($baseUrl), 'https://')) {
            return $this->failure('The Graph API base URL must be a valid HTTPS URL.', 'invalid_base_url');
        }

        try {
            $response = Http::acceptJson()->withToken($accessToken)
                ->connectTimeout(5)->timeout(15)
                ->get("{$baseUrl}/{$apiVersion}/{$phoneNumberId}", [
                    'fields' => 'display_phone_number,verified_name',
                ]);
        } catch (Throwable) {
            return $this->failure('Could not reach Meta. Check the server network and try again.', 'network_error');
        }

        if (! $response->successful()) {
            $providerCode = $response->json('error.code');
            $providerSubcode = $response->json('error.error_subcode');
            $code = is_numeric($providerCode) ? (string) $providerCode : 'meta_api_error';
            $message = match ((int) $providerCode) {
                190 => 'Meta rejected the access token. Replace it with a valid token and try again.',
                10, 200 => 'The token does not have the required permissions to read this WhatsApp phone number.',
                100 => 'Meta could not find this Phone Number ID or the supplied configuration is invalid.',
                4, 17, 32, 613 => 'Meta rate-limited this connection check. Wait briefly and try again.',
                default => $response->serverError()
                    ? 'Meta is temporarily unavailable. Try again later.'
                    : 'Meta could not validate this configuration. Check the Phone Number ID, token and permissions.',
            };
            if ($providerSubcode !== null) {
                $code .= ':' . (string) $providerSubcode;
            }
            return $this->failure($message . ' (Meta code ' . $code . ').', $code);
        }

        $displayPhone = $response->json('display_phone_number');
        $verifiedName = $response->json('verified_name');
        return [
            'ok' => true,
            'message' => 'Connection successful. Meta accepted the read-only check for this Phone Number ID.',
            'phone_number' => is_string($displayPhone) ? $displayPhone : null,
            'verified_name' => is_string($verifiedName) ? $verifiedName : null,
            'code' => null,
        ];
    }

    private function failure(string $message, string $code): array
    {
        return ['ok' => false, 'message' => $message, 'phone_number' => null, 'verified_name' => null, 'code' => $code];
    }
}
