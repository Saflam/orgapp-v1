<?php

namespace Modules\Core\Tests\Feature\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Core\Contracts\NotificationChannel;
use Modules\Core\Models\NotificationDelivery;
use Modules\Core\Models\SystemNotificationChannel;
use Modules\Core\Models\Organization;
use Modules\Core\Services\NotificationChannelRegistry;
use Modules\Core\Services\NotificationService;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_creates_delivery_for_enabled_channel(): void
    {
        Queue::fake();
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $channel = new FakeNotificationChannel('email');

        app(NotificationChannelRegistry::class)->register($channel);
        $service = app(NotificationService::class);
        $service->configureChannel($organization, 'email', true);

        $result = $service->notify(
            organization: $organization,
            user: $user,
            event: 'member.application.approved',
            title: 'Application approved',
            body: 'Your membership application has been approved.',
            sourceModule: 'Member',
        );

        $this->assertCount(1, $result['deliveries']);
        $this->assertDatabaseHas('notifications', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'event' => 'member.application.approved',
        ]);
        $this->assertDatabaseHas('notification_deliveries', [
            'notification_id' => $result['notification']->id,
            'channel' => 'email',
            'status' => 'queued',
        ]);
        Queue::assertPushed(\Modules\Core\Jobs\DeliverNotification::class, 1);
    }

    public function test_failed_delivery_can_be_retried_without_creating_another_notification(): void
    {
        Queue::fake();
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        app(NotificationChannelRegistry::class)->register(new FakeNotificationChannel('email'));
        $service = app(NotificationService::class);
        $service->configureChannel($organization, 'email', true);

        $result = $service->notifyOnce(
            organization: $organization,
            user: $user,
            event: 'member.payment.receipt.issued',
            title: 'Payment receipt',
            body: 'Your payment receipt is ready.',
            dedupeKey: 'member-payment:100',
            sourceModule: 'Member',
        );

        $this->assertNotNull($result);
        $delivery = $result['deliveries'][0];
        $delivery->forceFill([
            'status' => 'failed',
            'attempts' => 3,
            'error' => 'Temporary provider failure.',
        ])->save();

        Queue::assertPushed(\Modules\Core\Jobs\DeliverNotification::class, 1);

        $retried = $service->retryFailedDelivery($delivery);

        $this->assertSame($delivery->id, $retried->id);
        $this->assertSame('queued', $retried->status);
        $this->assertSame(3, $retried->attempts);
        $this->assertNull($retried->error);
        $this->assertNull($retried->sent_at);
        $this->assertSame(1, \Modules\Core\Models\Notification::query()->count());
        $this->assertSame(1, NotificationDelivery::query()->count());
        Queue::assertPushed(\Modules\Core\Jobs\DeliverNotification::class, 2);
    }

    public function test_system_default_channel_is_used_when_organization_allows_it(): void
    {
        Queue::fake();
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        app(NotificationChannelRegistry::class)->register(new FakeNotificationChannel('whatsapp'));
        $service = app(NotificationService::class);

        $service->configureSystemChannel('whatsapp', true, ['api_key' => 'system-secret']);
        $service->setSystemDefaultAccess($organization, 'whatsapp', true);

        $result = $service->notify($organization, $user, 'member.application.approved', 'Approved', 'Approved.');

        $this->assertCount(1, $result['deliveries']);
        $this->assertDatabaseHas('notification_deliveries', [
            'notification_id' => $result['notification']->id,
            'channel' => 'whatsapp',
            'channel_source' => 'system',
        ]);
    }

    public function test_organization_channel_overrides_system_default(): void
    {
        Queue::fake();
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        app(NotificationChannelRegistry::class)->register(new FakeNotificationChannel('sms'));
        $service = app(NotificationService::class);

        $service->configureSystemChannel('sms', true, ['api_key' => 'system-secret']);
        $service->setSystemDefaultAccess($organization, 'sms', true);
        $service->configureOrganizationChannel($organization, 'sms', true, ['api_key' => 'org-secret']);

        $result = $service->notify($organization, $user, 'member.application.approved', 'Approved', 'Approved.');

        $this->assertCount(1, $result['deliveries']);
        $this->assertDatabaseHas('notification_deliveries', [
            'notification_id' => $result['notification']->id,
            'channel' => 'sms',
            'channel_source' => 'organization',
        ]);
    }

    public function test_system_default_is_not_used_without_organization_permission(): void
    {
        Queue::fake();
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        app(NotificationChannelRegistry::class)->register(new FakeNotificationChannel('whatsapp'));
        $service = app(NotificationService::class);

        $service->configureSystemChannel('whatsapp', true, ['api_key' => 'system-secret']);

        $result = $service->notify($organization, $user, 'member.application.approved', 'Approved', 'Approved.');

        $this->assertCount(0, $result['deliveries']);
    }

    public function test_system_default_fallback_returns_when_organization_channel_is_disabled(): void
    {
        Queue::fake();
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        app(NotificationChannelRegistry::class)->register(new FakeNotificationChannel('sms'));
        $service = app(NotificationService::class);

        $service->configureSystemChannel('sms', true, ['api_key' => 'system-secret']);
        $service->setSystemDefaultAccess($organization, 'sms', true);
        $service->configureOrganizationChannel($organization, 'sms', false, ['api_key' => 'org-secret']);

        $result = $service->notify($organization, $user, 'member.application.approved', 'Approved', 'Approved.');

        $this->assertCount(1, $result['deliveries']);
        $this->assertSame('system', $result['deliveries'][0]->channel_source);
    }

    public function test_disabled_organization_channel_creates_no_delivery(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        app(NotificationChannelRegistry::class)->register(new FakeNotificationChannel('email'));
        app(NotificationService::class)->configureChannel($organization, 'email', false);

        $result = app(NotificationService::class)->notify(
            $organization,
            $user,
            'member.application.approved',
            'Application approved',
            'Approved.',
        );

        $this->assertCount(0, $result['deliveries']);
    }

    public function test_user_can_disable_an_enabled_channel(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        app(NotificationChannelRegistry::class)->register(new FakeNotificationChannel('email'));
        $service = app(NotificationService::class);
        $service->configureChannel($organization, 'email', true);
        $service->setUserPreference($organization, $user, 'email', false);

        $result = $service->notify(
            $organization,
            $user,
            'member.application.approved',
            'Application approved',
            'Approved.',
        );

        $this->assertCount(0, $result['deliveries']);
    }

    public function test_unregistered_provider_is_not_queued(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        app(NotificationService::class)->configureChannel($organization, 'whatsapp', true);

        $result = app(NotificationService::class)->notify(
            $organization,
            $user,
            'member.application.approved',
            'Application approved',
            'Approved.',
        );

        $this->assertCount(0, $result['deliveries']);
    }
}

class FakeNotificationChannel implements NotificationChannel
{
    public function __construct(private readonly string $channel) {}

    public function name(): string
    {
        return $this->channel;
    }

    public function send(NotificationDelivery $delivery, array $credentials): void
    {
        // Test double.
    }
}
