<?php

namespace Modules\Events\Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Core\Services\RoleAssignmentService;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventParticipant;
use Modules\Events\Models\EventPass;
use Modules\Events\Models\EventAttendanceScan;
use Modules\Events\Notifications\EventLifecycleNotification;
use Tests\TestCase;

class EventLifecycleApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.deployment_mode' => 'multi', 'app.organization_base_domain' => 'example.test']);
    }

    public function test_cancelling_an_event_revokes_active_passes_and_is_idempotent(): void
    {
        Notification::fake();
        [$organization, $user] = $this->authorizedUser('events.publish');
        $event = $this->event($organization, $user);
        $pass = $this->pass($organization, $event, $user);
        $endpoint = $this->url($organization, "/api/v1/events/{$event->id}/cancel");

        $this->actingAs($user)->postJson($endpoint)->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertDatabaseHas('event_passes', ['id' => $pass->id, 'status' => 'revoked']);
        $this->assertNotNull($pass->fresh()->revoked_at);

        $this->actingAs($user)->postJson($endpoint)->assertOk()->assertJsonPath('data.status', 'cancelled');
        Notification::assertSentOnDemand(EventLifecycleNotification::class);
    }

    public function test_cancelling_event_keeps_pass_active_when_people_are_inside(): void
    {
        [$organization, $user] = $this->authorizedUser('events.publish');
        $event = $this->event($organization, $user);
        $pass = $this->pass($organization, $event, $user, currentInside: 1);

        $this->actingAs($user)
            ->postJson($this->url($organization, "/api/v1/events/{$event->id}/cancel"))
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('event_passes', [
            'id' => $pass->id,
            'status' => 'active',
            'current_inside' => 1,
        ]);
        $this->assertNull($pass->fresh()->revoked_at);
    }

    public function test_repeated_event_cancellation_preserves_attendance_scan_history(): void
    {
        Notification::fake();
        [$organization, $user] = $this->authorizedUser('events.publish');
        $event = $this->event($organization, $user);
        $pass = $this->pass($organization, $event, $user, currentInside: 1);

        $scan = EventAttendanceScan::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_pass_id' => $pass->id,
            'event_pass_attendee_id' => null,
            'scanned_by' => $user->id,
            'action' => 'entry',
            'quantity' => 1,
            'scanned_at' => now()->subMinute(),
        ]);

        $endpoint = $this->url($organization, "/api/v1/events/{$event->id}/cancel");

        $this->actingAs($user)->postJson($endpoint)
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->actingAs($user)->postJson($endpoint)
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseCount('event_attendance_scans', 1);
        $this->assertDatabaseHas('event_attendance_scans', [
            'id' => $scan->id,
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_pass_id' => $pass->id,
            'action' => 'entry',
            'quantity' => 1,
        ]);
        $this->assertDatabaseHas('event_passes', [
            'id' => $pass->id,
            'status' => 'active',
            'current_inside' => 1,
        ]);
    }

    public function test_authorized_user_can_mark_eligible_participant_as_no_show_after_event_ends(): void
    {
        [$organization, $user] = $this->authorizedUser('events.participants.no_show');
        $event = $this->event($organization, $user);
        $event->forceFill([
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ])->save();
        $participant = $this->participant($organization, $event);

        $this->actingAs($user)
            ->postJson($this->url($organization, "/api/v1/events/{$event->id}/participants/{$participant->id}/no-show"))
            ->assertOk()
            ->assertJsonPath('data.status', 'no_show');

        $this->assertDatabaseHas('event_participants', [
            'id' => $participant->id,
            'status' => 'no_show',
        ]);
    }

    public function test_revoked_pass_cannot_be_validated(): void
    {
        [$organization, $user] = $this->authorizedUser('events.invitations.send');
        $event = $this->event($organization, $user);
        $pass = $this->pass($organization, $event, $user);
        $token = $pass->token_for_test;

        $this->actingAs($user)
            ->postJson($this->url($organization, "/api/v1/events/{$event->id}/passes/{$pass->id}/revoke"))
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked');

        $this->postJson($this->url($organization, '/api/v1/public/event-passes/validate'), ['token' => $token])
            ->assertNotFound();
    }

    public function test_pass_cannot_be_revoked_while_people_are_inside(): void
    {
        [$organization, $user] = $this->authorizedUser('events.invitations.send');
        $event = $this->event($organization, $user);
        $pass = $this->pass($organization, $event, $user, currentInside: 1);

        $this->actingAs($user)
            ->postJson($this->url($organization, "/api/v1/events/{$event->id}/passes/{$pass->id}/revoke"))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pass']);

        $this->assertDatabaseHas('event_passes', ['id' => $pass->id, 'status' => 'active']);
    }

    public function test_cancelling_participant_revokes_their_pass(): void
    {
        Notification::fake();
        [$organization, $user] = $this->authorizedUser('events.invitations.send');
        $event = $this->event($organization, $user);
        $participant = $this->participant($organization, $event);
        $pass = $this->pass($organization, $event, $user, participant: $participant);

        $this->actingAs($user)
            ->postJson($this->url($organization, "/api/v1/events/{$event->id}/participants/{$participant->id}/cancel"))
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('event_passes', ['id' => $pass->id, 'status' => 'revoked']);
        Notification::assertSentOnDemand(EventLifecycleNotification::class);
    }

    public function test_participant_cannot_be_cancelled_while_people_are_inside(): void
    {
        [$organization, $user] = $this->authorizedUser('events.invitations.send');
        $event = $this->event($organization, $user);
        $participant = $this->participant($organization, $event);
        $pass = $this->pass($organization, $event, $user, participant: $participant, currentInside: 1);

        $this->actingAs($user)
            ->postJson($this->url($organization, "/api/v1/events/{$event->id}/participants/{$participant->id}/cancel"))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['participant']);

        $this->assertDatabaseHas('event_participants', ['id' => $participant->id, 'status' => 'invited']);
        $this->assertDatabaseHas('event_passes', ['id' => $pass->id, 'status' => 'active']);
    }

    private function authorizedUser(string $permissionCode): array
    {
        $organization = Organization::factory()->create(['slug' => 'org-one']);
        $modules = app(OrganizationModuleService::class);
        $modules->enable($organization, 'Core');
        $modules->enable($organization, 'Member');
        $modules->enable($organization, 'Events');

        $user = User::factory()->create();
        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);
        $permission = Permission::query()->firstOrCreate(
            ['code' => $permissionCode],
            ['module' => 'events', 'name' => str($permissionCode)->headline()],
        );
        $role = Role::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Event Lifecycle Test Role',
            'code' => 'event_lifecycle_'.uniqid(),
        ]);
        $role->permissions()->attach($permission);
        app(RoleAssignmentService::class)->assign($membership, $role, $organization);

        return [$organization, $user];
    }

    private function event(Organization $organization, User $user): Event
    {
        return Event::query()->create([
            'organization_id' => $organization->id,
            'created_by' => $user->id,
            'title' => 'Lifecycle Test Event',
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(2),
            'timezone' => 'Asia/Kolkata',
            'scope' => 'central',
            'visibility' => 'restricted',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    private function participant(Organization $organization, Event $event): EventParticipant
    {
        return EventParticipant::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'participant_type' => 'guest',
            'name' => 'Lifecycle Guest',
            'email' => 'lifecycle@example.test',
            'status' => 'invited',
            'invited_at' => now(),
        ]);
    }

    private function pass(Organization $organization, Event $event, User $user, ?EventParticipant $participant = null, int $currentInside = 0): EventPass
    {
        $participant ??= $this->participant($organization, $event);
        $token = str_repeat('l', 64);
        $pass = EventPass::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_participant_id' => $participant->id,
            'token_hash' => hash('sha256', $token),
            'status' => 'active',
            'capacity' => 3,
            'current_inside' => $currentInside,
            'issued_at' => now(),
            'issued_by' => $user->id,
        ]);
        // Only used by this test class to validate revocation through the public endpoint.
        $pass->setAttribute('token_for_test', $token);
        return $pass;
    }

    private function url(Organization $organization, string $path): string
    {
        return 'http://'.$organization->slug.'.example.test'.$path;
    }
}
