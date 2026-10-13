<?php

namespace Modules\Events\Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Contracts\AuthorizationContext as AuthorizationContextContract;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\Unit;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Core\Services\RoleAssignmentService;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventParticipant;
use Modules\Events\Models\EventPass;
use Modules\Events\Models\EventVolunteer;
use Tests\TestCase;

class EventUnitScopedScanAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'example.test',
        ]);
    }

    public function test_unit_scoped_scanner_can_scan_pass_for_assigned_unit_event(): void
    {
        $organization = Organization::factory()->create([
            'slug' => 'org-unit-scan',
        ]);

        $modules = app(OrganizationModuleService::class);
        $modules->enable($organization, 'Core');
        $modules->enable($organization, 'Member');
        $modules->enable($organization, 'Events');

        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $user = User::factory()->create();

        $membership = OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $permission = Permission::query()->firstOrCreate(
            ['code' => 'events.attendance.scan'],
            [
                'module' => 'events',
                'name' => 'Scan Event Passes',
            ],
        );

        $role = Role::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Unit Event Scanner',
            'code' => 'unit_event_scanner_' . uniqid(),
        ]);

        $role->permissions()->attach($permission->id);

        $context = new class(
            (int) $organization->id,
            (int) $unit->id,
        ) implements AuthorizationContextContract {
            public function __construct(
                private readonly int $organizationId,
                private readonly int $unitId,
            ) {}

            public function contextType(): string
            {
                return 'unit';
            }

            public function contextId(): int|string
            {
                return $this->unitId;
            }

            public function organizationId(): int
            {
                return $this->organizationId;
            }
        };

        app(RoleAssignmentService::class)->assign(
            $membership,
            $role,
            $context,
        );

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'created_by' => $user->id,
            'title' => 'Unit Event',
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(2),
            'timezone' => 'Asia/Kolkata',
            'scope' => 'unit',
            'visibility' => 'restricted',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $participant = EventParticipant::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'participant_type' => 'guest',
            'name' => 'Guest',
            'email' => 'guest-' . uniqid() . '@example.test',
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

        EventPass::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_participant_id' => $participant->id,
            'token_hash' => hash('sha256', $token),
            'status' => 'active',
            'capacity' => 1,
            'current_inside' => 0,
            'issued_at' => now(),
            'issued_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson(
                'http://' . $organization->slug
                    . '.example.test/api/v1/events/attendance/scan',
                [
                    'token' => $token,
                    'action' => 'entry',
                ],
            );

        $response->assertOk()
            ->assertJsonPath('data.event.id', $event->id)
            ->assertJsonPath('data.pass.current_inside', 1);

        $otherUnit = Unit::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $otherEvent = Event::query()->create([
            'organization_id' => $organization->id,
            'unit_id' => $otherUnit->id,
            'created_by' => $user->id,
            'title' => 'Unassigned Unit Event',
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(2),
            'timezone' => 'Asia/Kolkata',
            'scope' => 'unit',
            'visibility' => 'restricted',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $otherParticipant = EventParticipant::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $otherEvent->id,
            'participant_type' => 'guest',
            'name' => 'Other Unit Guest',
            'email' => 'other-unit-' . uniqid() . '@example.test',
            'status' => 'invited',
            'invited_at' => now(),
        ]);
        $otherToken = bin2hex(random_bytes(32));
        EventPass::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $otherEvent->id,
            'event_participant_id' => $otherParticipant->id,
            'token_hash' => hash('sha256', $otherToken),
            'status' => 'active',
            'capacity' => 1,
            'current_inside' => 0,
            'issued_at' => now(),
            'issued_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->postJson(
                'http://' . $organization->slug . '.example.test/api/v1/events/attendance/scan',
                ['token' => $otherToken, 'action' => 'entry'],
            )
            ->assertForbidden();
    }

    public function test_unit_scoped_permissions_are_enforced_across_event_resource_routes(): void
    {
        Notification::fake();

        $organization = Organization::factory()->create(['slug' => 'org-unit-routes']);
        $modules = app(OrganizationModuleService::class);
        $modules->enable($organization, 'Core');
        $modules->enable($organization, 'Member');
        $modules->enable($organization, 'Events');

        $unitA = Unit::factory()->create(['organization_id' => $organization->id]);
        $unitB = Unit::factory()->create(['organization_id' => $organization->id]);
        $user = User::factory()->create();
        $membership = OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $permissionCodes = [
            'events.view',
            'events.manage',
            'events.publish',
            'events.invitations.send',
            'events.participants.no_show',
            'events.attendance.correct',
            'events.attendance.audit.view',
            'events.volunteers.manage',
            'events.reports.view',
        ];

        $permissions = collect($permissionCodes)->map(function (string $code) {
            return Permission::query()->firstOrCreate(
                ['code' => $code],
                ['module' => 'events', 'name' => str($code)->headline()],
            );
        });

        $role = Role::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Unit-Scoped Event Route Auditor',
            'code' => 'unit_event_route_auditor_' . uniqid(),
        ]);
        $role->permissions()->attach($permissions->pluck('id')->all());
        app(RoleAssignmentService::class)->assign($membership, $role, $unitA);

        $eventA = $this->createUnitEvent($organization, $user, $unitA, 'Unit A Event');
        $eventB = $this->createUnitEvent($organization, $user, $unitB, 'Unit B Event');

        $baseA = 'http://' . $organization->slug . '.example.test/api/v1/events/' . $eventA->id;
        $baseB = 'http://' . $organization->slug . '.example.test/api/v1/events/' . $eventB->id;

        // A unit-scoped user can list and invite participants for the assigned unit.
        $this->actingAs($user)
            ->getJson($baseA . '/participants')
            ->assertOk();

        $this->actingAs($user)
            ->postJson($baseA . '/participants', [
                'participant_type' => 'guest',
                'name' => 'Assigned Unit Guest',
                'email' => 'assigned-unit-guest@example.test',
            ])
            ->assertCreated();

        // The same permissions must not authorize access to another unit's event.
        $crossUnitRequests = [
            fn () => $this->getJson($baseB . '/participants'),
            fn () => $this->postJson($baseB . '/participants', [
                'participant_type' => 'guest',
                'name' => 'Cross Unit Guest',
                'email' => 'cross-unit-guest@example.test',
            ]),
            fn () => $this->postJson($baseB . '/participants/1/pass'),
            fn () => $this->getJson($baseB . '/pass-deliveries'),
            fn () => $this->postJson($baseB . '/passes/1/retry-delivery'),
            fn () => $this->postJson($baseB . '/participants/1/cancel'),
            fn () => $this->postJson($baseB . '/participants/1/no-show'),
            fn () => $this->postJson($baseB . '/passes/1/revoke'),
            fn () => $this->postJson($baseB . '/passes/1/attendees', ['name' => 'Attendee']),
            fn () => $this->getJson($baseB . '/attendance/corrections'),
            fn () => $this->postJson($baseB . '/passes/1/attendance-corrections', [
                'action' => 'entry', 'quantity' => 1, 'reason' => 'Unit scope test',
            ]),
            fn () => $this->getJson($baseB . '/attendance/scans'),
            fn () => $this->getJson($baseB . '/volunteers'),
            fn () => $this->postJson($baseB . '/volunteers', ['user_id' => $user->id]),
            fn () => $this->deleteJson($baseB . '/volunteers/' . $user->id),
            fn () => $this->putJson($baseB, ['title' => 'Unauthorized Update']),
            fn () => $this->postJson($baseB . '/publish'),
            fn () => $this->postJson($baseB . '/cancel'),
        ];

        foreach ($crossUnitRequests as $request) {
            $request()->assertForbidden();
        }

        // Report endpoints deliberately return 404 for an event outside the
        // user's visible unit scope, avoiding disclosure of inaccessible events.
        $this->actingAs($user)
            ->getJson($baseB . '/report')
            ->assertNotFound();

        // Organization-level permission assignments intentionally cover all units.
        $organizationUser = User::factory()->create();
        $organizationMembership = OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $organizationUser->id,
            'status' => 'active',
        ]);
        $organizationViewerRole = Role::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Organization Event Viewer',
            'code' => 'organization_event_viewer_' . uniqid(),
        ]);
        $organizationViewerRole->permissions()->attach(
            Permission::query()->where('code', 'events.view')->value('id'),
        );
        app(RoleAssignmentService::class)->assign(
            $organizationMembership,
            $organizationViewerRole,
            $organization,
        );

        $this->actingAs($organizationUser)
            ->getJson($baseB . '/participants')
            ->assertOk();
    }

    private function createUnitEvent(Organization $organization, User $user, Unit $unit, string $title): Event
    {
        return Event::query()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'created_by' => $user->id,
            'title' => $title,
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(2),
            'timezone' => 'Asia/Kolkata',
            'scope' => 'unit',
            'visibility' => 'restricted',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

}