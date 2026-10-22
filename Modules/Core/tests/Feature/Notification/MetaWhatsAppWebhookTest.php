<?php

namespace Modules\Core\Tests\Feature\Notification;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Notification;
use Modules\Core\Models\NotificationDelivery;
use Modules\Core\Models\Organization;
use Modules\Core\Models\WhatsAppMessageUsage;
use Tests\TestCase;

class MetaWhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['core.whatsapp.app_secret' => 'test-meta-app-secret']);
        config(['core.whatsapp.webhook_verify_token' => 'test-webhook-verify-token']);
    }

    public function test_meta_get_verification_returns_challenge_for_matching_token(): void
    {
        $this->get('/api/v1/webhooks/meta/whatsapp?hub.mode=subscribe&hub.verify_token=test-webhook-verify-token&hub.challenge=challenge-123')
            ->assertOk()->assertSeeText('challenge-123');
    }

    public function test_meta_get_verification_rejects_wrong_token(): void
    {
        $this->get('/api/v1/webhooks/meta/whatsapp?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=challenge-123')
            ->assertForbidden();
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $body = json_encode(['object' => 'whatsapp_business_account', 'entry' => []], JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/webhooks/meta/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . str_repeat('a', 64),
        ], $body)->assertUnauthorized();
    }

    public function test_valid_delivery_status_updates_usage_and_delivery_and_is_idempotent(): void
    {
        $organization = Organization::factory()->create();
        $user = \App\Models\User::factory()->create();
        $notification = Notification::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'event' => 'test',
            'title' => 'Test notification',
            'body' => 'Test body',
            'data' => [],
        ]);
        $delivery = NotificationDelivery::query()->create([
            'notification_id' => $notification->id,
            'channel' => 'whatsapp',
            'channel_source' => 'system',
            'status' => 'sent',
            'attempts' => 1,
            'provider_message_id' => 'wamid.webhook-test-123',
        ]);
        $usage = WhatsAppMessageUsage::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'notification_delivery_id' => $delivery->id,
            'service_category' => 'utility',
            'channel_source' => 'system',
            'provider_message_id' => 'wamid.webhook-test-123',
            'status' => 'accepted',
            'metadata' => ['template_name' => 'test_template'],
            'accepted_at' => now(),
        ]);

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => ['statuses' => [[
                        'id' => 'wamid.webhook-test-123',
                        'status' => 'delivered',
                        'timestamp' => (string) now()->timestamp,
                        'pricing' => ['category' => 'utility', 'billable' => true],
                    ]]],
                ]],
            ]],
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = 'sha256=' . hash_hmac('sha256', $body, 'test-meta-app-secret');

        $this->call('POST', '/api/v1/webhooks/meta/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], $body)->assertOk()->assertJson(['received' => true, 'processed_statuses' => 1]);

        $this->assertSame('accepted', $usage->fresh()->status, 'API acceptance remains separate for monthly quota accounting.');
        $this->assertSame('delivered', $usage->fresh()->delivery_status);
        $this->assertNotNull($usage->fresh()->delivered_at);
        $this->assertTrue($usage->fresh()->meta_billable);
        $this->assertSame('utility', $usage->fresh()->meta_pricing_category);
        $this->assertSame('delivered', $delivery->fresh()->status);
        $this->assertDatabaseCount('whatsapp_webhook_events', 1);

        $this->call('POST', '/api/v1/webhooks/meta/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], $body)->assertOk()->assertJson(['received' => true, 'processed_statuses' => 0]);
        $this->assertDatabaseCount('whatsapp_webhook_events', 1);
    }

    public function test_late_sent_event_does_not_regress_read_status(): void
    {
        $organization = Organization::factory()->create();
        $user = \App\Models\User::factory()->create();
        $notification = Notification::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'event' => 'test',
            'title' => 'Test notification',
            'body' => 'Test body',
            'data' => [],
        ]);
        $delivery = NotificationDelivery::query()->create([
            'notification_id' => $notification->id,
            'channel' => 'whatsapp',
            'channel_source' => 'system',
            'status' => 'read',
            'attempts' => 1,
            'provider_message_id' => 'wamid.read-test-123',
        ]);
        WhatsAppMessageUsage::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'notification_delivery_id' => $delivery->id,
            'service_category' => 'utility',
            'channel_source' => 'system',
            'provider_message_id' => 'wamid.read-test-123',
            'status' => 'accepted',
            'delivery_status' => 'read',
            'metadata' => [],
        ]);

        $payload = ['object' => 'whatsapp_business_account', 'entry' => [['changes' => [['value' => ['statuses' => [[
            'id' => 'wamid.read-test-123', 'status' => 'sent', 'timestamp' => (string) (now()->timestamp - 60),
        ]]]]]]]];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/webhooks/meta/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $body, 'test-meta-app-secret'),
        ], $body)->assertOk();

        $this->assertSame('read', $delivery->fresh()->status);
        $this->assertSame('read', WhatsAppMessageUsage::query()->where('provider_message_id', 'wamid.read-test-123')->value('delivery_status'));
    }
}
