<?php

namespace Modules\Core\Tests\Feature\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Core\Models\Notification;
use Modules\Core\Models\NotificationDelivery;
use Modules\Core\Models\Organization;
use Modules\Core\Services\MetaWhatsAppCloudApiChannel;
use Modules\Core\Services\RetryableWhatsAppDeliveryException;
use Modules\Core\Models\WhatsAppMessageUsage;
use RuntimeException;
use Tests\TestCase;

class MetaWhatsAppCloudApiChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_an_approved_template_through_meta_and_stores_message_id(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.test-message-123']],
            ], 200),
        ]);

        $organization = Organization::factory()->create();
        $user = User::factory()->create(['phone' => '+1 (555) 123-4567']);
        $notification = Notification::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'event' => 'membership.application.submitted',
            'title' => 'Application submitted',
            'body' => 'Your application is under review.',
            'data' => [],
            'source_module' => 'Member',
        ]);
        $delivery = NotificationDelivery::query()->create([
            'notification_id' => $notification->id,
            'channel' => 'whatsapp',
            'channel_source' => 'system',
            'status' => 'queued',
            'attempts' => 1,
            'queued_at' => now(),
        ]);

        app(MetaWhatsAppCloudApiChannel::class)->send($delivery, [
            'phone_number_id' => '123456789',
            'access_token' => 'test-access-token',
            'template_name' => 'saflivo_notification',
            'template_language' => 'en_US',
            'api_version' => 'v23.0',
        ]);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://graph.facebook.com/v23.0/123456789/messages'
                && $request->hasHeader('Authorization', 'Bearer test-access-token')
                && $request['messaging_product'] === 'whatsapp'
                && $request['to'] === '15551234567'
                && $request['type'] === 'template'
                && $request['template']['name'] === 'saflivo_notification'
                && $request['template']['components'][0]['parameters'][0]['text'] === 'Application submitted'
                && $request['template']['components'][0]['parameters'][1]['text'] === 'Your application is under review.';
        });

        $this->assertSame('wamid.test-message-123', $delivery->fresh()->provider_message_id);
    }

    public function test_provider_api_error_is_reported_without_disclosing_access_token(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['message' => 'Invalid OAuth access token.', 'code' => 190],
            ], 401),
        ]);

        $organization = Organization::factory()->create();
        $user = User::factory()->create(['phone' => '+15551234567']);
        $notification = Notification::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'event' => 'test',
            'title' => 'Test',
            'body' => 'Test body',
            'data' => [],
        ]);
        $delivery = NotificationDelivery::query()->create([
            'notification_id' => $notification->id,
            'channel' => 'whatsapp',
            'channel_source' => 'system',
            'status' => 'queued',
            'attempts' => 1,
        ]);

        try {
            app(MetaWhatsAppCloudApiChannel::class)->send($delivery, [
                'phone_number_id' => '123456789',
                'access_token' => 'super-secret-token',
                'template_name' => 'saflivo_notification',
            ]);
            $this->fail('Expected a provider error.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('code 190', $exception->getMessage());
            $this->assertStringNotContainsString('super-secret-token', $exception->getMessage());
        }
    }
    public function test_rate_limit_response_is_classified_as_retryable_without_recording_a_terminal_failure(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['message' => 'Too many requests.', 'code' => 4],
            ], 429, ['Retry-After' => '15']),
        ]);

        $organization = Organization::factory()->create();
        $user = User::factory()->create(['phone' => '+15551234567']);
        $notification = Notification::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'event' => 'test',
            'title' => 'Test',
            'body' => 'Test body',
            'data' => [],
        ]);
        $delivery = NotificationDelivery::query()->create([
            'notification_id' => $notification->id,
            'channel' => 'whatsapp',
            'channel_source' => 'system',
            'status' => 'queued',
            'attempts' => 1,
        ]);

        try {
            app(MetaWhatsAppCloudApiChannel::class)->send($delivery, [
                'phone_number_id' => '123456789',
                'access_token' => 'test-access-token',
                'template_name' => 'saflivo_notification',
            ]);
            $this->fail('Expected a retryable provider error.');
        } catch (RetryableWhatsAppDeliveryException $exception) {
            $this->assertSame(15, $exception->retryAfterSeconds);
            $this->assertSame('4', $exception->errorCode);
            $this->assertStringNotContainsString('test-access-token', $exception->getMessage());
        }

        $this->assertSame(0, WhatsAppMessageUsage::query()->where('notification_delivery_id', $delivery->id)->count());
    }

}
