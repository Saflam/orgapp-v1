<?php

namespace Modules\Events\Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\Unit;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Core\Services\RoleAssignmentService;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventParticipant;
use Tests\TestCase;

class EventUnitScopedPassManagementTest extends TestCase
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

    public function test_unit_scoped_manager_can_manage_pass_attendees_and_volunteers_for_assigned_unit(): void
    {
        $organization = Organization::factory()->create(['slug' => 'org-unit-pass-management']);

        $modules = app(OrganizationModuleService::class);
        $modules->enable($organization, 'Core');
        $modules->enable($organization, 'Member');
        $modules->enable($organization, 'Events');

        $unit = Unit::factory()->create(['organization_id' => $organization->id]);
        $user = User::factory()->create();

        $membership = OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $permissions = collect([
            'events.invitations.send',
            'events.volunteers.manage',
        ])->map(fn (string $code) => Permission::query()->firstOrCreate(
            ['code' => $code],
            ['module' => 'events', 'name' => str($code)->headline()],
        ));

        $role = Role::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Unit Pass and Volunteer Manager',
            'code' => 'unit_pass_volunteer_manager_' . uniqid(),
        ]);
        $role->permissions()->attach($permissions->pluck('id')->all());

        app(RoleAssignmentService::class)->assign($membership, $role, $unit);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'created_by' => $user->id,
            'title' => 'Unit Pass Management Event',
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(2),
            'timezone' => 'Asia/Kolkata',
            'scope' => 'unit',
            'visibility' => 'restricted',
            'status' => 'published',
            'published_at' => now(),
        ]);

        // No email is assigned so this test exercises pass authorization without
        // dispatching a delivery job; delivery behavior has separate feature tests.
        $participant = EventParticipant::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'participant_type' => 'guest',
            'name' => 'Unit Pass Guest',
            'status' => 'invited',
            'invited_at' => now(),
        ]);

        $baseUrl = 'http://' . $organization->slug . '.example.test/api/v1/events/' . $event->id;

        $passResponse = $this->actingAs($user)
            ->postJson($baseUrl . '/participants/' . $participant->id . '/pass')
            ->assertCreated()
            ->assertJsonPath('data.status', 'active');

        $passId = (int) $passResponse->json('data.id');

        $this->actingAs($user)
            ->postJson($baseUrl . '/passes/' . $passId . '/attendees', [
                'attendees' => [
                    ['name' => 'Unit A Attendee', 'category' => 'guest'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.0.name', 'Unit A Attendee')
            ->assertJsonPath('data.0.status', 'outside');

        $this->assertDatabaseHas('event_pass_attendees', [
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_pass_id' => $passId,
            'name' => 'Unit A Attendee',
            'status' => 'outside',
        ]);

        $this->actingAs($user)
            ->postJson($baseUrl . '/passes/' . $passId . '/revoke')
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked');

        $this->actingAs($user)
            ->postJson($baseUrl . '/volunteers', ['user_id' => $user->id])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.status', 'active');

        $this->actingAs($user)
            ->getJson($baseUrl . '/volunteers')
            ->assertOk()
            ->assertJsonPath('data.0.user_id', $user->id)
            ->assertJsonPath('data.0.status', 'active');

        $this->actingAs($user)
            ->deleteJson($baseUrl . '/volunteers/' . $user->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked');

        $this->assertDatabaseHas('event_volunteers', [
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => 'revoked',
        ]);
    }
}
