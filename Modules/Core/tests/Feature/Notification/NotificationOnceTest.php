<?php

namespace Modules\Core\Tests\Feature\Notification;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Notification;
use Modules\Core\Models\Organization;
use Modules\Core\Services\NotificationService;
use Tests\TestCase;
use App\Models\User;

class NotificationOnceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_notification_dedupe_key_prevents_duplicate_notifications(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        $service = app(NotificationService::class);

        $first = $service->notifyOnce(
            organization: $organization,
            user: $user,
            event: 'member.test.reminder',
            title: 'Reminder',
            body: 'Test reminder.',
            dedupeKey: 'test:1',
        );

        $second = $service->notifyOnce(
            organization: $organization,
            user: $user,
            event: 'member.test.reminder',
            title: 'Reminder',
            body: 'Test reminder.',
            dedupeKey: 'test:1',
        );

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertDatabaseCount('notifications', 1);
    }
}
