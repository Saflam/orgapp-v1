<?php

namespace Modules\Member\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Core\Contracts\NotificationChannel;
use Modules\Core\Models\NotificationDelivery;
use Modules\Core\Models\Organization;
use Modules\Core\Services\NotificationChannelRegistry;
use Modules\Core\Services\NotificationService;
use Modules\Member\Events\MembershipApplication\MembershipApplicationApproved;
use Modules\Member\Events\MembershipApplication\MembershipApplicationReviewed;
use Modules\Member\Events\MembershipApplication\MembershipApplicationSubmitted;
use Modules\Member\Events\MembershipApplication\MembershipApplicationVerified;
use Modules\Member\Events\MembershipApplication\MembershipConfirmed;
use Modules\Member\Events\MembershipApplication\MembershipPaymentRequired;
use Modules\Member\Enums\MembershipApplicationStatus;
use Modules\Member\Models\MembershipApplication;
use Modules\Member\Models\MembershipType;
use Tests\TestCase;

class MembershipApplicationNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_application_lifecycle_events_create_notifications(): void
    {
        Queue::fake();

        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $membershipType = MembershipType::create([
            'organization_id' => $organization->id,
            'name' => 'Regular',
            'code' => 'REG-' . uniqid(),
            'is_active' => true,
            'metadata' => [],
        ]);

        $application = MembershipApplication::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_type_id' => $membershipType->id,
            'status' => MembershipApplicationStatus::SUBMITTED,
            'current_step' => 3,
            'completed_steps' => [1, 2, 3],
            'data' => [],
            'submitted_at' => now(),
        ]);

        app(NotificationChannelRegistry::class)->register(new FakeMemberNotificationChannel('email'));
        app(NotificationService::class)->configureChannel($organization, 'email', true);

        event(new MembershipApplicationSubmitted($application));
        event(new MembershipApplicationVerified($application));
        event(new MembershipApplicationReviewed($application));
        event(new MembershipApplicationApproved($application));
        event(new MembershipPaymentRequired($application));
        event(new MembershipConfirmed($application));

        $this->assertDatabaseCount('notifications', 6);
        $this->assertDatabaseHas('notifications', ['event' => 'membership.application.submitted']);
        $this->assertDatabaseHas('notifications', ['event' => 'membership.application.verified']);
        $this->assertDatabaseHas('notifications', ['event' => 'membership.application.reviewed']);
        $this->assertDatabaseHas('notifications', ['event' => 'membership.application.approved']);
        $this->assertDatabaseHas('notifications', ['event' => 'membership.payment.required']);
        $this->assertDatabaseHas('notifications', ['event' => 'membership.confirmed']);
        $this->assertDatabaseCount('notification_deliveries', 6);
    }
}

class FakeMemberNotificationChannel implements NotificationChannel
{
    public function __construct(private readonly string $channel) {}

    public function name(): string
    {
        return $this->channel;
    }

    public function send(NotificationDelivery $delivery, array $credentials): void {}
}
