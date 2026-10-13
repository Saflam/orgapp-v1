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
use Modules\Events\Models\EventVolunteer;
use Tests\TestCase;

class EventVolunteerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.deployment_mode' => 'multi', 'app.organization_base_domain' => 'example.test']);
    }

    public function test_authorized_manager_can_assign_an_active_organization_member_as_volunteer(): void
    {
        [$organization, $manager] = $this->authorizedUser();
        $event = $this->event($organization, $manager);
        $volunteer = User::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $volunteer->id,
            'status' => 'active',
        ]);

        $this->actingAs($manager)
            ->postJson($this->url($organization, "/api/v1/events/{$event->id}/volunteers"), [
                'user_id' => $volunteer->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $volunteer->id)
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('event_volunteers', [
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'user_id' => $volunteer->id,
            'status' => 'active',
        ]);
    }

    public function test_user_without_active_membership_cannot_be_assigned(): void
    {
        [$organization, $manager] = $this->authorizedUser();
        $event = $this->event($organization, $manager);
        $outsideUser = User::factory()->create();

        $this->actingAs($manager)
            ->postJson($this->url($organization, "/api/v1/events/{$event->id}/volunteers"), [
                'user_id' => $outsideUser->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);

        $this->assertDatabaseCount('event_volunteers', 0);
    }

    public function test_manager_can_revoke_volunteer_and_assignment_history_is_preserved(): void
    {
        [$organization, $manager] = $this->authorizedUser();
        $event = $this->event($organization, $manager);
        $volunteer = User::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $volunteer->id,
            'status' => 'active',
        ]);
        EventVolunteer::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'user_id' => $volunteer->id,
            'assigned_by' => $manager->id,
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        $this->actingAs($manager)
            ->deleteJson($this->url($organization, "/api/v1/events/{$event->id}/volunteers/{$volunteer->id}"))
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked')
            ->assertJsonPath('data.revoked_by', $manager->id);

        $this->assertDatabaseHas('event_volunteers', [
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'user_id' => $volunteer->id,
            'status' => 'revoked',
        ]);
    }

    public function test_volunteer_routes_require_management_permission(): void
    {
        [$organization, $user] = $this->authorizedUser('events.view');
        $event = $this->event($organization, $user);

        $this->actingAs($user)
            ->getJson($this->url($organization, "/api/v1/events/{$event->id}/volunteers"))
            ->assertForbidden();
    }

    public function test_event_from_another_organization_cannot_be_used_for_assignment(): void
    {
        [$organization, $manager] = $this->authorizedUser();
        $otherOrganization = $this->organization('org-two');
        $otherManager = User::factory()->create();
        $otherEvent = $this->event($otherOrganization, $otherManager);
        $volunteer = User::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $volunteer->id,
            'status' => 'active',
        ]);

        $this->actingAs($manager)
            ->postJson($this->url($organization, "/api/v1/events/{$otherEvent->id}/volunteers"), [
                'user_id' => $volunteer->id,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('event_volunteers', 0);
    }

    private function authorizedUser(string $permissionCode = 'events.volunteers.manage'): array
    {
        $organization = $this->organization('org-one');
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
            'name' => 'Event Volunteer Test Role',
            'code' => 'event_volunteer_test_'.uniqid(),
        ]);
        $role->permissions()->attach($permission);
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

    private function event(Organization $organization, User $creator): Event
    {
        return Event::query()->create([
            'organization_id' => $organization->id,
            'created_by' => $creator->id,
            'title' => 'Volunteer Test Event',
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(2),
            'timezone' => 'Asia/Kolkata',
            'scope' => 'central',
            'visibility' => 'restricted',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    private function url(Organization $organization, string $path): string
    {
        return 'http://'.$organization->slug.'.example.test'.$path;
    }
}
