<?php

namespace Modules\Core\Tests\Feature\Notification;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Core\Models\Organization;
use Modules\Core\Models\OrganizationNotificationChannel;
use Modules\Core\Models\OtpChallenge;
use Modules\Core\Models\SystemNotificationChannel;
use Modules\Core\Services\MetaWhatsAppOtpSender;
use Tests\TestCase;

class MetaWhatsAppOtpSenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_otp_using_authentication_template_and_records_usage(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.otp123']]], 200)]);
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['phone' => '+919876543210']);
        $user->forceFill(['phone_verified_at' => now()])->save();
        $challenge = OtpChallenge::create(['user_id' => $user->id, 'purpose' => 'login', 'channel' => 'email', 'destination' => $user->email, 'code_hash' => bcrypt('123456'), 'expires_at' => now()->addMinutes(5), 'attempts' => 0, 'max_attempts' => 5]);
        OrganizationNotificationChannel::query()->create(['organization_id' => $organization->id, 'channel' => 'whatsapp', 'is_allowed' => true, 'is_enabled' => true, 'use_system_default' => false, 'credentials' => ['phone_number_id' => '123', 'access_token' => 'secret', 'otp_template_name' => 'saflivo_login_otp', 'otp_template_language' => 'en_US']]);

        $sent = app(MetaWhatsAppOtpSender::class)->send($user, $challenge, '123456', $organization);
        $this->assertTrue($sent);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/123/messages') && $request['template']['name'] === 'saflivo_login_otp' && $request['template']['components'][0]['parameters'][0]['text'] === '123456' && $request['template']['components'][1]['parameters'][0]['text'] === '123456');
        $this->assertDatabaseHas('whatsapp_message_usages', ['organization_id' => $organization->id, 'service_category' => 'otp', 'provider_message_id' => 'wamid.otp123', 'status' => 'accepted']);
    }

    public function test_otp_is_not_sent_when_whatsapp_feature_is_not_allowed(): void
    {
        Http::fake();
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['phone' => '+919876543210']);
        $user->forceFill(['phone_verified_at' => now()])->save();
        $challenge = OtpChallenge::create(['user_id' => $user->id, 'purpose' => 'login', 'channel' => 'email', 'destination' => $user->email, 'code_hash' => bcrypt('123456'), 'expires_at' => now()->addMinutes(5), 'attempts' => 0, 'max_attempts' => 5]);
        $this->assertFalse(app(MetaWhatsAppOtpSender::class)->send($user, $challenge, '123456', $organization));
        Http::assertNothingSent();
    }
}
