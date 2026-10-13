<?php

namespace Modules\Events\Tests\Feature;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Events\Notifications\EventLifecycleNotification;
use Modules\Events\Jobs\DeliverEventPassEmail;
use Modules\Events\Notifications\EventPassIssuedNotification;
use Modules\Events\Models\Event;
use Tests\TestCase;

class EventNotificationQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_lifecycle_notifications_are_queued_with_bounded_retries_and_after_commit(): void
    {
        $event = new Event(['title' => 'Queue Test']);
        $notification = new EventLifecycleNotification($event, 'registered', 'Test Participant');

        $this->assertInstanceOf(ShouldQueue::class, $notification);
        $this->assertSame(4, $notification->tries);
        $this->assertSame([30, 120, 300], $notification->backoff);
        $this->assertTrue($notification->afterCommit ?? false);
    }

    public function test_pass_delivery_job_is_queued_without_bearer_token_or_email_in_its_payload(): void
    {
        $token = str_repeat('x', 64);
        $job = new DeliverEventPassEmail(123, 'events/pass-delivery/example.enc');
        $serialized = serialize($job);

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertSame(4, $job->tries);
        $this->assertSame([30, 120, 300], $job->backoff);
        $this->assertTrue($job->afterCommit ?? false);
        $this->assertStringNotContainsString($token, $serialized);
        $this->assertStringNotContainsString('@', $serialized);

        // The notification itself is rendered synchronously inside the queued job;
        // it is not independently queued with the bearer QR contents.
        $notification = new EventPassIssuedNotification(
            $event = new Event(['title' => 'Pass Queue Test']),
            '<svg></svg>'
        );
        $this->assertNotInstanceOf(ShouldQueue::class, $notification);
    }
}
