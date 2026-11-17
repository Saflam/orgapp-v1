<?php

namespace Modules\Events\Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Core\Services\RoleAssignmentService;
use Modules\Events\Enums\EventStatus;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventAttendanceScan;
use Modules\Events\Models\EventPass;
use Modules\Events\Models\EventParticipant;
use Modules\Events\Models\EventVolunteer;
use Tests\TestCase;

class EventEndToEndJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'example.test',
        ]);

        Notification::fake();
        Queue::fake();
        Storage::fake('local');
    }

    public function test_event_can_complete_the_full_management_registration_pass_attendance_and_reporting_journey(): void
    {
        [$organization, $manager] = $this->organizationWithManager();
        $baseUrl = $this->url($organization, '/api/v1/events');

        // Create and publish the event.
        $created = $this->actingAs($manager)
            ->postJson($baseUrl, [
                'title' => 'Saradhi Community Gathering',
                'starts_at' => now()->addDays(5)->toDateTimeString(),
                'ends_at' => now()->addDays(5)->addHours(3)->toDateTimeString(),
                'timezone' => 'Asia/Kolkata',
                'scope' => 'central',
                'visibility' => 'restricted',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');

        $eventId = (int) $created->json('data.id');

        $this->actingAs($manager)
            ->postJson($baseUrl.'/'.$eventId.'/publish')
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        // Invite a guest and issue the guest's pass.
        $participantResponse = $this->actingAs($manager)
            ->postJson($baseUrl.'/'.$eventId.'/participants', [
                'participant_type' => 'guest',
                'name' => 'Integration Guest',
                'email' => 'integration-guest@example.test',
                'phone' => '+919876543210',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'invited');

        $participantId = (int) $participantResponse->json('data.id');

        $passResponse = $this->actingAs($manager)
            ->postJson($baseUrl.'/'.$eventId.'/participants/'.$participantId.'/pass')
            ->assertCreated()
            ->assertJsonPath('data.status', 'active');

        $token = $passResponse->json('data.token');
        $passId = (int) $passResponse->json('data.id');

        $this->assertIsString($token);
        $this->assertNotSame('', $token);

        // Assign the manager as a volunteer so the same authorized user can scan.
        $this->actingAs($manager)
            ->postJson($baseUrl.'/'.$eventId.'/volunteers', [
                'user_id' => $manager->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $manager->id)
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('event_volunteers', [
            'organization_id' => $organization->id,
            'event_id' => $eventId,
            'user_id' => $manager->id,
            'status' => 'active',
        ]);

        // Public token validation must not disclose the guest's email address.
        $this->postJson(
            $this->url($organization, '/api/v1/public/event-passes/validate'),
            ['token' => $token],
        )
            ->assertOk()
            ->assertJsonPath('data.pass.id', $passId)
            ->assertJsonMissing(['email' => 'integration-guest@example.test']);

        // Entry, exit, and re-entry are recorded as three immutable scan rows.
        $scanUrl = $this->url($organization, '/api/v1/events/attendance/scan');

        $this->actingAs($manager)->postJson($scanUrl, [
            'token' => $token,
            'action' => 'entry',
        ])->assertOk()->assertJsonPath('data.pass.current_inside', 1);

        $this->actingAs($manager)->postJson($scanUrl, [
            'token' => $token,
            'action' => 'exit',
        ])->assertOk()->assertJsonPath('data.pass.current_inside', 0);

        $this->actingAs($manager)->postJson($scanUrl, [
            'token' => $token,
            'action' => 'entry',
        ])->assertOk()->assertJsonPath('data.pass.current_inside', 1);

        $this->actingAs($manager)->postJson($scanUrl, [
            'token' => $token,
            'action' => 'exit',
        ])->assertOk()->assertJsonPath('data.pass.current_inside', 0);

        $this->assertDatabaseCount('event_attendance_scans', 4);
        $this->assertSame(2, EventAttendanceScan::query()->where('event_id', $eventId)->where('action', 'entry')->count());
        $this->assertSame(2, EventAttendanceScan::query()->where('event_id', $eventId)->where('action', 'exit')->count());

        // Reporting must reconcile the participant, pass, and attendance history.
        $this->actingAs($manager)
            ->getJson($baseUrl.'/'.$eventId.'/report')
            ->assertOk()
            ->assertJsonPath('data.event.id', $eventId)
            ->assertJsonPath('data.participants.total', 1)
            ->assertJsonPath('data.passes.total', 1)
            ->assertJsonPath('data.passes.currently_inside', 0)
            ->assertJsonPath('data.attendance.entry_movements', 2)
            ->assertJsonPath('data.attendance.exit_movements', 2)
            ->assertJsonPath('data.data_quality.occupancy_reconciliation', 'consistent');

        $this->actingAs($manager)
            ->getJson($baseUrl.'/'.$eventId.'/reports/attendance?action=entry')
            ->assertOk()
            ->assertJsonPath('data.total', 2);

        // Cancellation after everyone has exited revokes the pass but preserves scans.
        $this->actingAs($manager)
            ->postJson($baseUrl.'/'.$eventId.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('event_passes', [
            'id' => $passId,
            'status' => 'revoked',
            'current_inside' => 0,
        ]);
        $this->assertDatabaseCount('event_attendance_scans', 4);

        $this->postJson(
            $this->url($organization, '/api/v1/public/event-passes/validate'),
            ['token' => $token],
        )->assertNotFound();

        $this->actingAs($manager)
            ->getJson($baseUrl.'/'.$eventId.'/reports/passes?status=revoked')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $passId);

        // Repeating cancellation is idempotent and does not alter attendance history.
        $this->actingAs($manager)
            ->postJson($baseUrl.'/'.$eventId.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseCount('event_attendance_scans', 4);
        $this->assertDatabaseHas('event_participants', [
            'id' => $participantId,
            'status' => 'invited',
        ]);

        $event = Event::query()->findOrFail($eventId);
        $pass = EventPass::query()->findOrFail($passId);
        $participant = EventParticipant::query()->findOrFail($participantId);
        $volunteer = EventVolunteer::query()->where('event_id', $eventId)->firstOrFail();

        $this->assertSame(EventStatus::Cancelled, $event->status);
        $this->assertSame('revoked', $pass->status);
        $this->assertSame('invited', $participant->status);
        $this->assertSame('active', $volunteer->status);
    }

    private function organizationWithManager(): array
    {
        $organization = Organization::factory()->create(['slug' => 'org-one']);

        $modules = app(OrganizationModuleService::class);
        $modules->enable($organization, 'Core');
        $modules->enable($organization, 'Member');
        $modules->enable($organization, 'Events');

        $manager = User::factory()->create();
        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $manager->id,
            'status' => 'active',
        ]);

        $permissionCodes = [
            'events.manage',
            'events.publish',
            'events.invitations.send',
            'events.attendance.scan',
            'events.volunteers.manage',
            'events.reports.view',
            'events.view',
        ];

        $permissions = collect($permissionCodes)->map(fn (string $code) => Permission::query()->firstOrCreate(
            ['code' => $code],
            ['module' => 'events', 'name' => str($code)->headline()],
        ));

        $role = Role::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Events End-to-End Test Role',
            'code' => 'events_e2e_'.uniqid(),
        ]);
        $role->permissions()->attach($permissions->pluck('id')->all());
        app(RoleAssignmentService::class)->assign($membership, $role, $organization);

        return [$organization, $manager];
    }

    private function url(Organization $organization, string $path): string
    {
        return 'http://'.$organization->slug.'.example.test'.$path;
    }
}
