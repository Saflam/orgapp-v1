<?php

namespace Modules\Core\Tests\Feature\Notification;

use Illuminate\Support\Facades\Http;
use Modules\Core\Services\MetaWhatsAppConnectionTester;
use Tests\TestCase;

class MetaWhatsAppConnectionTesterTest extends TestCase
{
    public function test_valid_credentials_are_checked_using_a_read_only_phone_number_request(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response([
            'display_phone_number' => '+1 555-0100',
            'verified_name' => 'Example Organization',
        ], 200)]);

        $result = app(MetaWhatsAppConnectionTester::class)->test([
            'phone_number_id' => '123456789',
            'access_token' => 'private-test-token',
            'api_version' => 'v23.0',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('+1 555-0100', $result['phone_number']);
        $this->assertSame('Example Organization', $result['verified_name']);
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && $request->url() === 'https://graph.facebook.com/v23.0/123456789?fields=display_phone_number%2Cverified_name'
            && $request->hasHeader('Authorization', 'Bearer private-test-token'));
        Http::assertSentCount(1);
    }

    public function test_missing_credentials_fail_without_making_a_request(): void
    {
        Http::fake();
        $result = app(MetaWhatsAppConnectionTester::class)->test(['phone_number_id' => '123']);
        $this->assertFalse($result['ok']);
        $this->assertSame('missing_credentials', $result['code']);
        Http::assertNothingSent();
    }

    public function test_invalid_access_token_returns_actionable_safe_error_without_echoing_token(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response([
            'error' => ['message' => 'Invalid OAuth access token.', 'code' => 190],
        ], 401)]);
        $secret = 'do-not-leak-this-token';
        $result = app(MetaWhatsAppConnectionTester::class)->test([
            'phone_number_id' => '123456789', 'access_token' => $secret, 'api_version' => 'v23.0',
        ]);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('valid token', $result['message']);
        $this->assertStringNotContainsString($secret, $result['message']);
    }

    public function test_invalid_api_version_is_rejected_before_network_call(): void
    {
        Http::fake();
        $result = app(MetaWhatsAppConnectionTester::class)->test([
            'phone_number_id' => '123456789', 'access_token' => 'token', 'api_version' => 'latest',
        ]);
        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_api_version', $result['code']);
        Http::assertNothingSent();
    }
}
