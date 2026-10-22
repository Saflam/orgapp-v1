<?php

namespace Modules\Core\Tests\Feature\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Core\Jobs\DeliverNotification;
use Modules\Core\Models\NotificationDelivery;
use Modules\Core\Models\Organization;
use Modules\Core\Services\NotificationChannelRegistry;
use Modules\Core\Services\NotificationChannelResolver;
use Modules\Core\Services\NotificationService;
use Modules\Core\Services\WhatsAppUsageRecorder;
use Tests\TestCase;

class DatabaseQueueExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_queue_worker_executes_notification_job(): void
    {
        config(['queue.default' => 'database']);

        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        app(NotificationChannelRegistry::class)
            ->register(new FakeNotificationChannel('email'));

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

        $delivery = $result['deliveries'][0];

        $this->assertDatabaseHas('jobs', [
            'queue' => 'default',
        ]);

        // Process one job using Laravel's database queue connection.
        $this->artisan('queue:work', [
            'connection' => 'database',
            '--once' => true,
            '--tries' => 1,
        ])->assertExitCode(0);

        $delivery->refresh();

        $this->assertSame('sent', $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertNotNull($delivery->sent_at);
        $this->assertNull($delivery->error);

        $this->assertDatabaseCount('jobs', 0);
    }
}