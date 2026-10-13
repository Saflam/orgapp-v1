<?php

namespace Modules\Events\Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Core\Services\RoleAssignmentService;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventParticipant;
use Modules\Events\Models\EventPass;
use Modules\Events\Models\EventPassAttendee;
use Modules\Events\Models\EventAttendanceScan;
use Modules\Events\Models\EventVolunteer;
use Tests\TestCase;

class EventAttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.deployment_mode' => 'multi', 'app.organization_base_domain' => 'example.test']);
    }

    public function test_scanner_can_record_entry_and_exit_and_reentry(): void
    {
        [$organization, $user] = $this->authorizedUser();
        [$event, $pass, $token] = $this->pass($organization, $user);
        $url = $this->url($organization, '/api/v1/events/attendance/scan');

        $this->actingAs($user)->postJson($url, ['token' => $token, 'action' => 'entry'])
            ->assertOk()->assertJsonPath('data.pass.current_inside', 1)->assertJsonPath('data.pass.remaining_capacity', 0);

        $this->actingAs($user)->postJson($url, ['token' => $token, 'action' => 'exit'])
            ->assertOk()->assertJsonPath('data.pass.current_inside', 0);

        $this->actingAs($user)->postJson($url, ['token' => $token, 'action' => 'entry'])
            ->assertOk()->assertJsonPath('data.pass.current_inside', 1);

        $this->assertDatabaseCount('event_attendance_scans', 3);
        $this->assertDatabaseHas('event_passes', [
            'id' => $pass->id,
            'current_inside' => 1,
        ]);
        $this->assertSame(1, (int) EventAttendanceScan::query()->where('event_pass_id', $pass->id)->where('action', 'entry')->sum('quantity')
            - (int) EventAttendanceScan::query()->where('event_pass_id', $pass->id)->where('action', 'exit')->sum('quantity'));
    }

    public function test_occupants_can_exit_after_event_cancellation_and_pass_is_then_revoked(): void
    {
        Notification::fake();
        [$organization, $user] = $this->authorizedUser();
        [$event, $pass, $token] = $this->pass($organization, $user);
        $scanUrl = $this->url($organization, '/api/v1/events/attendance/scan');

        $this->actingAs($user)->postJson($scanUrl, [
            'token' => $token,
            'action' => 'entry',
        ])->assertOk()->assertJsonPath('data.pass.current_inside', 1);

        $membership = OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->firstOrFail();
        $publishPermission = Permission::query()->firstOrCreate(
            ['code' => 'events.publish'],
            ['module' => 'events', 'name' => 'Publish Events'],
        );
        $publishRole = Role::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Event Publisher',
            'code' => 'event_publisher_' . uniqid(),
        ]);
        $publishRole->permissions()->attach($publishPermission->id);
        app(RoleAssignmentService::class)->assign($membership, $publishRole, $organization);

        $this->actingAs($user)
            ->postJson($this->url($organization, "/api/v1/events/{$event->id}/cancel"))
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('event_passes', [
            'id' => $pass->id,
            'status' => 'active',
            'current_inside' => 1,
        ]);

        $this->actingAs($user)->postJson($scanUrl, [
            'token' => $token,
            'action' => 'exit',
        ])->assertOk()->assertJsonPath('data.pass.current_inside', 0);

        $this->assertDatabaseHas('event_passes', [
            'id' => $pass->id,
            'status' => 'revoked',
            'current_inside' => 0,
        ]);
        $this->assertDatabaseCount('event_attendance_scans', 2);
    }

    public function test_entry_is_rejected_when_pass_capacity_is_exceeded(): void
    {
        [$organization, $user] = $this->authorizedUser();
        [, , $token] = $this->pass($organization, $user, 1);
        $url = $this->url($organization, '/api/v1/events/attendance/scan');

        $this->actingAs($user)->postJson($url, ['token' => $token, 'action' => 'entry'])->assertOk();
        $this->actingAs($user)->postJson($url, ['token' => $token, 'action' => 'entry'])
            ->assertUnprocessable()->assertJsonValidationErrors(['quantity']);
        $this->assertDatabaseCount('event_attendance_scans', 1);
    }

    public function test_entry_is_rejected_when_event_venue_capacity_would_be_exceeded(): void
    {
        [$organization, $user] = $this->authorizedUser();
        [$event, $pass, $token] = $this->pass($organization, $user, 2);
        $event->forceFill(['venue_capacity' => 1])->save();

        $this->actingAs($user)->postJson($this->url($organization, '/api/v1/events/attendance/scan'), [
            'token' => $token, 'action' => 'entry', 'quantity' => 2,
        ])->assertUnprocessable()->assertJsonValidationErrors(['event']);

        $this->assertDatabaseHas('event_passes', ['id' => $pass->id, 'current_inside' => 0]);
        $this->assertDatabaseCount('event_attendance_scans', 0);
    }

    public function test_authorized_correction_records_reason_and_preserves_original_scans(): void
    {
        [$organization, $user] = $this->authorizedUser('events.attendance.correct');
        [$event, $pass, $token] = $this->pass($organization, $user, 3);
        $scanUrl = $this->url($organization, '/api/v1/events/attendance/scan');
        $this->actingAs($user)->postJson($scanUrl, ['token' => $token, 'action' => 'entry', 'quantity' => 2])
            ->assertForbidden();

        // Seed a legitimate scan using the dedicated scanner permission, then correct the counter.
        $scanUser = User::factory()->create();
        $scanPermission = \Modules\Core\Models\Permission::query()->firstOrCreate(['code' => 'events.attendance.scan'], ['module' => 'events', 'name' => 'Scan Event Passes']);
        $scanRole = \Modules\Core\Models\Role::query()->create(['organization_id' => $organization->id, 'name' => 'Scan Role', 'code' => 'scan_role_'.uniqid()]);
        $scanRole->permissions()->attach($scanPermission->id);
        $membership = \App\Models\OrganizationMembership::query()->create(['organization_id' => $organization->id, 'user_id' => $scanUser->id, 'status' => 'active']);
        app(\Modules\Core\Services\RoleAssignmentService::class)->assign($membership, $scanRole, $organization);
        \Modules\Events\Models\EventVolunteer::query()->create(['organization_id' => $organization->id, 'event_id' => $event->id, 'user_id' => $scanUser->id, 'assigned_by' => $user->id, 'status' => 'active', 'assigned_at' => now()]);
        $this->actingAs($scanUser)->postJson($scanUrl, ['token' => $token, 'action' => 'entry', 'quantity' => 2])->assertOk();

        $this->actingAs($user)->postJson($this->url($organization, "/api/v1/events/{$event->id}/passes/{$pass->id}/attendance-corrections"), [
            'current_inside' => 1,
            'reason' => 'Manual headcount confirmed one attendee inside',
        ])->assertCreated()->assertJsonPath('data.before_inside', 2)->assertJsonPath('data.after_inside', 1);

        $this->assertDatabaseCount('event_attendance_scans', 1);
        $this->assertDatabaseHas('event_passes', ['id' => $pass->id, 'current_inside' => 1]);
        $this->assertDatabaseHas('event_attendance_corrections', ['event_pass_id' => $pass->id, 'occupancy_delta' => -1, 'reason' => 'Manual headcount confirmed one attendee inside']);
    }

    public function test_authorized_correction_can_correct_individual_attendee_without_rewriting_scans(): void
    {
        [$organization, $user] = $this->authorizedUser('events.attendance.correct');
        [$event, $pass] = $this->pass($organization, $user, 2);
        $attendee = EventPassAttendee::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_pass_id' => $pass->id,
            'name' => 'Guest One',
            'category' => 'guest',
            'status' => 'outside',
            'entry_count' => 0,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->postJson($this->url($organization, "/api/v1/events/{$event->id}/passes/{$pass->id}/attendance-corrections"), [
            'attendee_id' => $attendee->id,
            'status' => 'inside',
            'reason' => 'Manual headcount confirms this attendee is inside',
        ])->assertCreated()
            ->assertJsonPath('data.event_pass_attendee_id', $attendee->id)
            ->assertJsonPath('data.before_attendee_status', 'outside')
            ->assertJsonPath('data.after_attendee_status', 'inside')
            ->assertJsonPath('data.occupancy_delta', 1);

        $this->assertDatabaseHas('event_pass_attendees', ['id' => $attendee->id, 'status' => 'inside']);
        $this->assertDatabaseHas('event_passes', ['id' => $pass->id, 'current_inside' => 1]);
        $this->assertDatabaseHas('event_attendance_corrections', [
            'event_pass_id' => $pass->id,
            'event_pass_attendee_id' => $attendee->id,
            'before_attendee_status' => 'outside',
            'after_attendee_status' => 'inside',
            'reason' => 'Manual headcount confirms this attendee is inside',
        ]);
        $this->assertDatabaseCount('event_attendance_scans', 0);
    }

    public function test_exit_is_rejected_when_no_one_is_inside(): void
    {
        [$organization, $user] = $this->authorizedUser();
        [, , $token] = $this->pass($organization, $user);

        $this->actingAs($user)->postJson($this->url($organization, '/api/v1/events/attendance/scan'), [
            'token' => $token, 'action' => 'exit',
        ])->assertUnprocessable()->assertJsonValidationErrors(['quantity']);

        $this->assertDatabaseCount('event_attendance_scans', 0);
    }

    public function test_scan_requires_attendance_permission(): void
    {
        [$organization, $user] = $this->authorizedUser('events.view');
        [, , $token] = $this->pass($organization, $user);

        $this->actingAs($user)->postJson($this->url($organization, '/api/v1/events/attendance/scan'), [
            'token' => $token, 'action' => 'entry',
        ])->assertForbidden();
    }

    public function test_pass_from_another_organization_cannot_be_scanned(): void
    {
        [$organization, $user] = $this->authorizedUser();
        $otherOrganization = $this->organization('org-two');
        $otherUser = User::factory()->create();
        [, , $token] = $this->pass($otherOrganization, $otherUser);

        $this->actingAs($user)->postJson($this->url($organization, '/api/v1/events/attendance/scan'), [
            'token' => $token, 'action' => 'entry',
        ])->assertNotFound();
    }

    public function test_user_with_scan_permission_cannot_scan_an_event_without_assignment(): void
    {
        [$organization, $owner] = $this->authorizedUser();
        [, , $token] = $this->pass($organization, $owner);

        $volunteer = User::factory()->create();
        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $volunteer->id,
            'status' => 'active',
        ]);
        $permission = Permission::query()->firstOrCreate(
            ['code' => 'events.attendance.scan'],
            ['module' => 'events', 'name' => 'Scan Event Passes'],
        );
        $role = Role::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Unassigned Scanner Role',
            'code' => 'unassigned_scanner_'.uniqid(),
        ]);
        $role->permissions()->attach($permission);
        app(RoleAssignmentService::class)->assign($membership, $role, $organization);

        $this->actingAs($volunteer)->postJson($this->url($organization, '/api/v1/events/attendance/scan'), [
            'token' => $token,
            'action' => 'entry',
        ])->assertForbidden();

        $this->assertDatabaseCount('event_attendance_scans', 0);
    }

    public function test_scan_rejects_invalid_action_and_token(): void
    {
        [$organization, $user] = $this->authorizedUser();
        $this->actingAs($user)->postJson($this->url($organization, '/api/v1/events/attendance/scan'), [
            'token' => 'short', 'action' => 'in',
        ])->assertUnprocessable()->assertJsonValidationErrors(['token', 'action']);
    }

    public function test_quantity_scan_is_blocked_when_scan_history_and_occupancy_disagree(): void
    {
        [$organization, $user] = $this->authorizedUser();
        [, $pass, $token] = $this->pass($organization, $user, 2);
        $pass->forceFill(['current_inside' => 1])->save();

        $this->actingAs($user)->postJson($this->url($organization, '/api/v1/events/attendance/scan'), [
            'token' => $token,
            'action' => 'entry',
            'quantity' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors(['attendance']);

        $this->assertDatabaseCount('event_attendance_scans', 0);
        $this->assertDatabaseHas('event_passes', [
            'id' => $pass->id,
            'current_inside' => 1,
        ]);
    }

    public function test_individual_scan_is_blocked_when_roster_and_occupancy_disagree(): void
    {
        [$organization, $user] = $this->authorizedUser();
        [$event, $pass, $token] = $this->pass($organization, $user, 2);
        $roster = $this->actingAs($user)->postJson(
            $this->url($organization, "/api/v1/events/{$event->id}/passes/{$pass->id}/attendees"),
            ['attendees' => [['name' => 'Guest One'], ['name' => 'Guest Two']]]
        )->assertCreated()->json('data');

        $pass->forceFill(['current_inside' => 1])->save();

        $this->actingAs($user)->postJson($this->url($organization, '/api/v1/events/attendance/scan'), [
            'token' => $token,
            'action' => 'entry',
            'attendee_ids' => [$roster[0]['id']],
        ])->assertUnprocessable()->assertJsonValidationErrors(['attendance']);

        $this->assertDatabaseCount('event_attendance_scans', 0);
        $this->assertDatabaseHas('event_passes', [
            'id' => $pass->id,
            'current_inside' => 1,
        ]);
        $this->assertDatabaseHas('event_pass_attendees', [
            'id' => $roster[0]['id'],
            'status' => 'outside',
        ]);
    }

    public function test_invalid_batch_transition_does_not_partially_update_attendees(): void
    {
        [$organization, $user] = $this->authorizedUser();
        [$event, $pass, $token] = $this->pass($organization, $user, 2);
        $roster = $this->actingAs($user)->postJson(
            $this->url($organization, "/api/v1/events/{$event->id}/passes/{$pass->id}/attendees"),
            ['attendees' => [['name' => 'Guest One'], ['name' => 'Guest Two']]]
        )->assertCreated()->json('data');

        $insideAttendeeId = $roster[0]['id'];
        EventPassAttendee::query()->whereKey($insideAttendeeId)->update([
            'status' => 'inside',
            'first_entered_at' => now(),
            'last_entered_at' => now(),
            'entry_count' => 1,
        ]);
        $pass->forceFill(['current_inside' => 1])->save();
        EventAttendanceScan::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_pass_id' => $pass->id,
            'event_pass_attendee_id' => $insideAttendeeId,
            'scanned_by' => $user->id,
            'action' => 'entry',
            'quantity' => 1,
            'scanned_at' => now(),
        ]);

        $this->actingAs($user)->postJson($this->url($organization, '/api/v1/events/attendance/scan'), [
            'token' => $token,
            'action' => 'entry',
            'attendee_ids' => [$roster[1]['id'], $insideAttendeeId],
        ])->assertUnprocessable()->assertJsonValidationErrors(['attendee_ids']);

        $this->assertDatabaseCount('event_attendance_scans', 1);
        $this->assertDatabaseHas('event_pass_attendees', [
            'id' => $insideAttendeeId,
            'status' => 'inside',
            'entry_count' => 1,
        ]);
        $this->assertDatabaseHas('event_pass_attendees', [
            'id' => $roster[1]['id'],
            'status' => 'outside',
            'entry_count' => 0,
        ]);
        $this->assertDatabaseHas('event_passes', [
            'id' => $pass->id,
            'current_inside' => 1,
        ]);
    }

    public function test_batch_scan_rolls_back_all_writes_when_a_later_scan_insert_fails(): void
    {
        [$organization, $user] = $this->authorizedUser();
        [$event, $pass, $token] = $this->pass($organization, $user, 2);
        $roster = $this->actingAs($user)->postJson(
            $this->url($organization, "/api/v1/events/{$event->id}/passes/{$pass->id}/attendees"),
            ['attendees' => [['name' => 'Guest One'], ['name' => 'Guest Two']]]
        )->assertCreated()->json('data');

        $eventName = 'eloquent.creating: '.EventAttendanceScan::class;
        $created = 0;
        EventFacade::listen($eventName, function () use (&$created): void {
            $created++;
            if ($created === 2) {
                throw new \RuntimeException('Simulated scan persistence failure.');
            }
        });

        try {
            $this->withoutExceptionHandling();
            try {
                $this->actingAs($user)->postJson($this->url($organization, '/api/v1/events/attendance/scan'), [
                    'token' => $token,
                    'action' => 'entry',
                    'attendee_ids' => [$roster[0]['id'], $roster[1]['id']],
                ]);
                $this->fail('Expected the simulated scan persistence failure to escape the request.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('Simulated scan persistence failure.', $exception->getMessage());
            }
        } finally {
            EventFacade::forget($eventName);
        }

        $this->assertDatabaseCount('event_attendance_scans', 0);
        $this->assertDatabaseHas('event_passes', [
            'id' => $pass->id,
            'current_inside' => 0,
        ]);
        foreach ($roster as $attendee) {
            $this->assertDatabaseHas('event_pass_attendees', [
                'id' => $attendee['id'],
                'status' => 'outside',
                'entry_count' => 0,
            ]);
        }
    }

    private function authorizedUser(string $permissionCode = 'events.attendance.scan'): array
    {
        $organization = $this->organization('org-one');
        $user = User::factory()->create();
        $membership = OrganizationMembership::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'status' => 'active']);
        $permissionCodes = array_unique([$permissionCode, 'events.invitations.send']);
        $permissions = collect($permissionCodes)->map(fn (string $code) => Permission::query()->firstOrCreate(
            ['code' => $code],
            ['module' => 'events', 'name' => str($code)->headline()],
        ));
        $role = Role::query()->create(['organization_id' => $organization->id, 'name' => 'Attendance Test Role', 'code' => 'attendance_test_'.uniqid()]);
        $role->permissions()->attach($permissions->pluck('id')->all());
        app(RoleAssignmentService::class)->assign($membership, $role, $organization);
        return [$organization, $user];
    }

    private function organization(string $slug): Organization
    {
        $organization = Organization::factory()->create(['slug' => $slug]);
        $modules = app(OrganizationModuleService::class);
        $modules->enable($organization, 'Core');
        $modules->enable($organization, 'Member');
        $modules->enable($organization, 'Events');
        return $organization;
    }

    private function pass(Organization $organization, User $user, int $capacity = 1): array
    {
        $event = Event::query()->create([
            'organization_id' => $organization->id, 'created_by' => $user->id, 'title' => 'Test Event',
            'starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(5)->addHours(2),
            'timezone' => 'Asia/Kolkata', 'scope' => 'central', 'visibility' => 'restricted',
            'status' => 'published', 'published_at' => now(),
        ]);
        $participant = EventParticipant::query()->create([
            'organization_id' => $organization->id, 'event_id' => $event->id, 'participant_type' => 'guest',
            'name' => 'Guest', 'email' => 'guest'.uniqid().'@example.test', 'status' => 'invited', 'invited_at' => now(),
        ]);
        EventVolunteer::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'user_id' => $user->id,
            'assigned_by' => $user->id,
            'status' => 'active',
            'assigned_at' => now(),
        ]);
        $token = bin2hex(random_bytes(32));
        $pass = EventPass::query()->create([
            'organization_id' => $organization->id, 'event_id' => $event->id,
            'event_participant_id' => $participant->id, 'token_hash' => hash('sha256', $token),
            'status' => 'active', 'capacity' => $capacity, 'current_inside' => 0, 'issued_at' => now(), 'issued_by' => $user->id,
        ]);
        return [$event, $pass, $token];
    }

    private function url(Organization $organization, string $path): string
    {
        return 'http://'.$organization->slug.'.example.test'.$path;
    }
}
