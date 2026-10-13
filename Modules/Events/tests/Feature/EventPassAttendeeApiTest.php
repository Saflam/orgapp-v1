<?php

namespace Modules\Events\Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Core\Services\RoleAssignmentService;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventParticipant;
use Modules\Events\Models\EventPass;
use Modules\Events\Models\EventVolunteer;
use Tests\TestCase;

class EventPassAttendeeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.deployment_mode' => 'multi', 'app.organization_base_domain' => 'example.test']);
    }

    public function test_authorized_user_can_register_individuals_under_a_group_pass(): void
    {
        [$organization, $user] = $this->authorizedUser();
        [$event, $pass] = $this->pass($organization, $user, 3);

        $this->actingAs($user)
            ->postJson($this->url($organization, "/api/v1/events/{$event->id}/passes/{$pass->id}/attendees"), [
                'attendees' => [
                    ['name' => 'Guest One', 'email' => 'one@example.test', 'category' => 'guest'],
                    ['name' => 'VIP Two', 'email' => 'two@example.test', 'category' => 'vip'],
                ],
            ])
            ->assertCreated()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.status', 'outside');

        $this->assertDatabaseCount('event_pass_attendees', 2);
    }

    public function test_attendee_roster_cannot_be_added_to_another_organizations_pass(): void
    {
        [$organizationA, $userA] = $this->authorizedUser();
        [$eventA] = $this->pass($organizationA, $userA, 2);

        $organizationB = Organization::factory()->create(['slug' => 'org-two']);
        $modules = app(OrganizationModuleService::class);
        $modules->enable($organizationB, 'Core');
        $modules->enable($organizationB, 'Member');
        $modules->enable($organizationB, 'Events');

        [$eventB, $passB] = $this->pass($organizationB, $userA, 2);

        $this->actingAs($userA)
            ->postJson($this->url($organizationA, "/api/v1/events/{$eventA->id}/passes/{$passB->id}/attendees"), [
                'attendees' => [
                    ['name' => 'Cross-organization attendee'],
                ],
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('event_pass_attendees', 0);
        $this->assertSame($organizationB->id, $passB->fresh()->organization_id);
        $this->assertSame($eventB->id, $passB->fresh()->event_id);
    }

    public function test_attendee_roster_cannot_exceed_pass_capacity(): void
    {
        [$organization, $user] = $this->authorizedUser();
        [$event, $pass] = $this->pass($organization, $user, 1);

        $this->actingAs($user)
            ->postJson($this->url($organization, "/api/v1/events/{$event->id}/passes/{$pass->id}/attendees"), [
                'attendees' => [
                    ['name' => 'One'],
                    ['name' => 'Two'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['attendees']);

        $this->assertDatabaseCount('event_pass_attendees', 0);
    }

    public function test_individual_attendee_can_enter_exit_and_reenter(): void
    {
        [$organization, $user] = $this->authorizedUser('events.attendance.scan');
        [$event, $pass, $token] = $this->pass($organization, $user, 2);

        $roster = $this->actingAs($user)
            ->postJson($this->url($organization, "/api/v1/events/{$event->id}/passes/{$pass->id}/attendees"), [
                'attendees' => [
                    ['name' => 'Guest One', 'category' => 'guest'],
                    ['name' => 'Guest Two', 'category' => 'vip'],
                ],
            ])->assertCreated()->json('data');

        $scanUrl = $this->url($organization, '/api/v1/events/attendance/scan');
        $attendeeId = $roster[0]['id'];

        $this->actingAs($user)->postJson($scanUrl, [
            'token' => $token, 'action' => 'entry', 'attendee_ids' => [$attendeeId],
        ])->assertOk()->assertJsonPath('data.pass.current_inside', 1);

        $this->actingAs($user)->postJson($scanUrl, [
            'token' => $token, 'action' => 'exit', 'attendee_ids' => [$attendeeId],
        ])->assertOk()->assertJsonPath('data.pass.current_inside', 0);

        $this->actingAs($user)->postJson($scanUrl, [
            'token' => $token, 'action' => 'entry', 'attendee_ids' => [$attendeeId],
        ])->assertOk()->assertJsonPath('data.pass.current_inside', 1);

        $this->assertDatabaseHas('event_pass_attendees', [
            'id' => $attendeeId,
            'status' => 'inside',
            'entry_count' => 2,
        ]);
        $this->assertDatabaseCount('event_attendance_scans', 3);
    }

    public function test_quantity_scanning_cannot_be_mixed_with_individual_roster_tracking(): void
    {
        [$organization, $user] = $this->authorizedUser('events.attendance.scan');
        [$event, $pass, $token] = $this->pass($organization, $user, 2);
        $this->actingAs($user)->postJson(
            $this->url($organization, "/api/v1/events/{$event->id}/passes/{$pass->id}/attendees"),
            ['attendees' => [['name' => 'Guest One']]]
        )->assertCreated();

        $this->actingAs($user)->postJson($this->url($organization, '/api/v1/events/attendance/scan'), [
            'token' => $token, 'action' => 'entry', 'quantity' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors(['quantity']);
    }

    private function authorizedUser(string $permissionCode = 'events.invitations.send'): array
    {
        $organization = Organization::factory()->create(['slug' => 'org-one']);
        $modules = app(OrganizationModuleService::class);
        $modules->enable($organization, 'Core');
        $modules->enable($organization, 'Member');
        $modules->enable($organization, 'Events');

        $user = User::factory()->create();
        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id, 'user_id' => $user->id, 'status' => 'active',
        ]);
        $permissionCodes = array_unique([$permissionCode, 'events.invitations.send', 'events.attendance.scan']);
        $permissions = collect($permissionCodes)->map(fn (string $code) => Permission::query()->firstOrCreate(
            ['code' => $code],
            ['module' => 'events', 'name' => str($code)->headline()]
        ));
        $role = Role::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Pass Attendee Test Role',
            'code' => 'pass_attendee_test_'.uniqid(),
        ]);
        $role->permissions()->attach($permissions->pluck('id')->all());
        app(RoleAssignmentService::class)->assign($membership, $role, $organization);

        return [$organization, $user];
    }

    private function pass(Organization $organization, User $user, int $capacity): array
    {
        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'created_by' => $user->id,
            'title' => 'Group Pass Event',
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(2),
            'timezone' => 'Asia/Kolkata',
            'scope' => 'central',
            'visibility' => 'restricted',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $participant = EventParticipant::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'participant_type' => 'guest',
            'name' => 'Group Host',
            'email' => 'host-'.uniqid().'@example.test',
            'status' => 'invited',
            'invited_at' => now(),
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
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_participant_id' => $participant->id,
            'token_hash' => hash('sha256', $token),
            'status' => 'active',
            'capacity' => $capacity,
            'current_inside' => 0,
            'issued_at' => now(),
            'issued_by' => $user->id,
        ]);

        return [$event, $pass, $token];
    }

    private function url(Organization $organization, string $path): string
    {
        return 'http://'.$organization->slug.'.example.test'.$path;
    }
}
