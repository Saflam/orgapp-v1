<?php

namespace Modules\Member\Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Modules\Core\Contracts\NotificationChannel;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\NotificationDelivery;
use Modules\Core\Models\Organization;
use Modules\Core\Services\AuthorizationService;
use Modules\Core\Services\NotificationChannelRegistry;
use Modules\Core\Services\NotificationService;
use Modules\Member\Enums\MembershipApplicationStatus;
use Modules\Member\Events\MembershipApplication\MembershipApplicationSubmitted;
use Modules\Member\Models\MembershipApplication;
use Modules\Member\Models\MembershipType;
use Tests\TestCase;

class MembershipApplicationNotificationRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_submission_notifies_applicant_and_staff_authorized_to_verify_once(): void
    {
        Queue::fake();

        $organization = Organization::factory()->create();
        $applicant = User::factory()->create();
        $staff = User::factory()->create();

        OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $staff->id,
            'status' => 'active',
        ]);

        $type = MembershipType::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Standard',
            'code' => 'STANDARD-' . uniqid(),
            'is_active' => true,
            'metadata' => [],
        ]);

        $application = MembershipApplication::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $applicant->id,
            'membership_type_id' => $type->id,
            'status' => MembershipApplicationStatus::SUBMITTED,
            'current_step' => 3,
            'completed_steps' => [1, 2, 3],
            'data' => ['member' => ['first_name' => 'Applicant']],
            'submitted_at' => now(),
        ]);

        app(NotificationChannelRegistry::class)->register(new class implements NotificationChannel {
            public function name(): string { return 'email'; }
            public function send(NotificationDelivery $delivery, array $credentials): void {}
        });
        app(NotificationService::class)->configureChannel($organization, 'email', true);

        $authorization = Mockery::mock(AuthorizationService::class);
        // The listener reloads the recipient through OrganizationMembership::with('user'),
        // so the User instance is a different Eloquent object from the factory instance.
        // Match by primary key rather than Mockery object identity.
        $authorization->shouldReceive('can')
            ->atLeast()->once()
            ->with(
                Mockery::on(fn ($recipient): bool => $recipient instanceof User
                    && (int) $recipient->getKey() === (int) $staff->getKey()),
                'membership.application.verify',
                Mockery::on(fn ($context): bool => $context instanceof AuthorizationContext
                    && (int) $context->organizationId === (int) $organization->getKey()),
            )
            ->andReturn(true);
        app()->instance(AuthorizationService::class, $authorization);

        event(new MembershipApplicationSubmitted($application));
        event(new MembershipApplicationSubmitted($application));

        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseHas('notifications', [
            'organization_id' => $organization->id,
            'user_id' => $applicant->id,
            'event' => 'membership.application.submitted',
        ]);
        $this->assertDatabaseHas('notifications', [
            'organization_id' => $organization->id,
            'user_id' => $staff->id,
            'event' => 'membership.application.submitted.staff',
        ]);
        $this->assertDatabaseCount('notification_deliveries', 2);
    }
}
