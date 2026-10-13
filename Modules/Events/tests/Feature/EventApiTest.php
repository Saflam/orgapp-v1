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
use Modules\Events\Services\EventManagementService;
use Tests\TestCase;

class EventApiTest extends TestCase
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

    public function test_authorized_user_can_list_only_events_for_the_current_organization(): void
    {
        [$organization, $user] = $this->authorizedUser('events.view');
        $event = $this->createEvent($organization, $user, ['title' => 'Local Event']);

        $otherOrganization = Organization::factory()->create(['slug' => 'org-two']);
        $otherUser = User::factory()->create();
        $otherEvent = $this->createEvent($otherOrganization, $otherUser, ['title' => 'Other Event']);

        $this->actingAs($user)
            ->getJson($this->url($organization, '/api/v1/events'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $event->id)
            ->assertJsonMissing(['title' => 'Other Event']);

        $this->assertDatabaseHas('events', ['id' => $otherEvent->id]);
    }

    public function test_user_without_view_permission_cannot_list_events(): void
    {
        [$organization, $user] = $this->authorizedUser('events.manage');

        $this->actingAs($user)
            ->getJson($this->url($organization, '/api/v1/events'))
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_list_events(): void
    {
        $organization = Organization::factory()->create(['slug' => 'org-one']);

        $this->getJson($this->url($organization, '/api/v1/events'))
            ->assertUnauthorized();
    }

    public function test_authorized_user_can_create_draft_event_and_default_category(): void
    {
        [$organization, $user] = $this->authorizedUser('events.manage');

        $response = $this->actingAs($user)
            ->postJson($this->url($organization, '/api/v1/events'), $this->validPayload());

        $response
            ->assertCreated()
            ->assertJsonPath('data.title', 'Community Gathering')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.scope', 'central')
            ->assertJsonPath('data.visibility', 'restricted')
            ->assertJsonPath('data.category.name', 'Uncategorized');

        $this->assertDatabaseHas('events', [
            'organization_id' => $organization->id,
            'title' => 'Community Gathering',
            'status' => 'draft',
            'unit_id' => null,
        ]);

        $this->assertDatabaseHas('event_categories', [
            'organization_id' => $organization->id,
            'slug' => 'uncategorized',
            'is_system' => true,
        ]);
    }

    public function test_event_creation_validates_required_fields_and_schedule(): void
    {
        [$organization, $user] = $this->authorizedUser('events.manage');

        $this->actingAs($user)
            ->postJson($this->url($organization, '/api/v1/events'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'title',
                'starts_at',
                'ends_at',
                'scope',
                'visibility',
            ]);

        $payload = $this->validPayload([
            'ends_at' => now()->addDays(1)->toDateTimeString(),
            'starts_at' => now()->addDays(2)->toDateTimeString(),
        ]);

        $this->actingAs($user)
            ->postJson($this->url($organization, '/api/v1/events'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ends_at']);
    }

    public function test_user_without_manage_permission_cannot_create_event(): void
    {
        [$organization, $user] = $this->authorizedUser('events.view');

        $this->actingAs($user)
            ->postJson($this->url($organization, '/api/v1/events'), $this->validPayload())
            ->assertForbidden();
    }

    public function test_central_event_discards_unit_id(): void
    {
        [$organization, $user] = $this->authorizedUser('events.manage');

        $this->actingAs($user)
            ->postJson(
                $this->url($organization, '/api/v1/events'),
                $this->validPayload(['scope' => 'central', 'unit_id' => 999999])
            )
            ->assertCreated()
            ->assertJsonPath('data.unit_id', null);

        $this->assertDatabaseHas('events', [
            'organization_id' => $organization->id,
            'title' => 'Community Gathering',
            'unit_id' => null,
        ]);
    }

    public function test_authorized_user_can_view_event_in_current_organization(): void
    {
        [$organization, $user] = $this->authorizedUser('events.view');
        $event = $this->createEvent($organization, $user);

        $this->actingAs($user)
            ->getJson($this->url($organization, "/api/v1/events/{$event->id}"))
            ->assertOk()
            ->assertJsonPath('data.id', $event->id)
            ->assertJsonPath('data.title', 'Community Gathering');
    }

    public function test_event_from_another_organization_is_not_accessible(): void
    {
        [$organization, $user] = $this->authorizedUser('events.view');
        $otherOrganization = Organization::factory()->create(['slug' => 'org-two']);
        $otherUser = User::factory()->create();
        $event = $this->createEvent($otherOrganization, $otherUser);

        $this->actingAs($user)
            ->getJson($this->url($organization, "/api/v1/events/{$event->id}"))
            ->assertNotFound();
    }

    public function test_authorized_user_can_update_draft_event(): void
    {
        [$organization, $user] = $this->authorizedUser('events.manage');
        $event = $this->createEvent($organization, $user);

        $this->actingAs($user)
            ->putJson($this->url($organization, "/api/v1/events/{$event->id}"), [
                'title' => 'Updated Gathering',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated Gathering')
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'title' => 'Updated Gathering',
        ]);
    }

    public function test_event_from_another_organization_cannot_be_updated(): void
    {
        [$organization, $user] = $this->authorizedUser('events.manage');
        $otherOrganization = Organization::factory()->create(['slug' => 'org-two']);
        $otherUser = User::factory()->create();
        $event = $this->createEvent($otherOrganization, $otherUser);

        $this->actingAs($user)
            ->putJson($this->url($organization, "/api/v1/events/{$event->id}"), [
                'title' => 'Tampered',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('events', [
            'id' => $event->id,
            'title' => 'Tampered',
        ]);
    }

    public function test_authorized_user_can_publish_draft_event(): void
    {
        [$organization, $user] = $this->authorizedUser('events.publish');
        $event = $this->createEvent($organization, $user);

        $response = $this->actingAs($user)
            ->postJson($this->url($organization, "/api/v1/events/{$event->id}/publish"));

        $response->assertOk()->assertJsonPath('data.status', 'published');
        $this->assertNotEmpty($response->json('data.published_at'));

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => 'published',
        ]);
    }

    public function test_event_cannot_be_published_twice(): void
    {
        [$organization, $user] = $this->authorizedUser('events.publish');
        $event = $this->createEvent($organization, $user, ['status' => 'published']);

        $this->actingAs($user)
            ->postJson($this->url($organization, "/api/v1/events/{$event->id}/publish"))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['event']);
    }

    public function test_user_without_publish_permission_cannot_publish_event(): void
    {
        [$organization, $user] = $this->authorizedUser('events.manage');
        $event = $this->createEvent($organization, $user);

        $this->actingAs($user)
            ->postJson($this->url($organization, "/api/v1/events/{$event->id}/publish"))
            ->assertForbidden();
    }

    public function test_authorized_user_can_cancel_event_and_repeat_cancel_is_idempotent(): void
    {
        [$organization, $user] = $this->authorizedUser('events.publish');
        $event = $this->createEvent($organization, $user);
        $endpoint = $this->url($organization, "/api/v1/events/{$event->id}/cancel");

        $response = $this->actingAs($user)->postJson($endpoint);
        $response->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertNotEmpty($response->json('data.cancelled_at'));

        $this->actingAs($user)
            ->postJson($endpoint)
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_event_from_another_organization_cannot_be_cancelled(): void
    {
        [$organization, $user] = $this->authorizedUser('events.publish');
        $otherOrganization = Organization::factory()->create(['slug' => 'org-two']);
        $otherUser = User::factory()->create();
        $event = $this->createEvent($otherOrganization, $otherUser);

        $this->actingAs($user)
            ->postJson($this->url($organization, "/api/v1/events/{$event->id}/cancel"))
            ->assertNotFound();

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => 'draft',
        ]);
    }

    public function test_unit_scoped_permissions_only_allow_the_assigned_unit_event(): void
    {
        $organization = Organization::factory()->create(['slug' => 'org-one']);
        $modules = app(OrganizationModuleService::class);
        $modules->enable($organization, 'Core');
        $modules->enable($organization, 'Member');
        $modules->enable($organization, 'Events');

        $unitA = Unit::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Unit A',
            'code' => 'unit-a',
            'is_active' => true,
        ]);
        $unitB = Unit::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Unit B',
            'code' => 'unit-b',
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);
        $permission = Permission::query()->firstOrCreate(
            ['code' => 'events.view'],
            ['module' => 'events', 'name' => 'View Events'],
        );
        $role = Role::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Unit Events Viewer',
            'code' => 'unit_events_viewer_' . uniqid(),
        ]);
        $role->permissions()->attach($permission);
        $assignmentService = app(RoleAssignmentService::class);
        $assignmentService->assign($membership, $role, $unitA);

        $eventA = $this->createEvent($organization, $user, [
            'scope' => 'unit',
            'unit_id' => $unitA->id,
        ]);
        $eventB = $this->createEvent($organization, $user, [
            'scope' => 'unit',
            'unit_id' => $unitB->id,
            'title' => 'Unit B Event',
        ]);

        $this->actingAs($user)
            ->getJson($this->url($organization, "/api/v1/events/{$eventA->id}"))
            ->assertOk();

        $this->actingAs($user)
            ->getJson($this->url($organization, "/api/v1/events/{$eventB->id}"))
            ->assertForbidden();

        $list = $this->actingAs($user)
            ->getJson($this->url($organization, '/api/v1/events'))
            ->assertOk();

        $this->assertSame(
            [$eventA->id],
            collect($list->json('data'))->pluck('id')->all(),
        );
    }

    private function authorizedUser(string $permissionCode): array
    {
        $organization = Organization::factory()->create(['slug' => 'org-one']);

        $moduleService = app(OrganizationModuleService::class);
        $moduleService->enable($organization, 'Core');
        $moduleService->enable($organization, 'Member');
        $moduleService->enable($organization, 'Events');

        $user = User::factory()->create();

        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $permission = Permission::query()->firstOrCreate(
            ['code' => $permissionCode],
            [
                'module' => 'events',
                'name' => str($permissionCode)->headline(),
            ],
        );

        $role = Role::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Events Test Role',
            'code' => 'events_test_' . uniqid(),
        ]);

        $role->permissions()->attach($permission);

        app(RoleAssignmentService::class)->assign($membership, $role, $organization);

        return [$organization, $user];
    }

    private function createEvent(
        Organization $organization,
        User $user,
        array $overrides = [],
    ): Event {
        $data = array_merge($this->validPayload(), [
            'organization_id' => $organization->id,
            'created_by' => $user->id,
            'status' => 'draft',
        ], $overrides);

        if (! isset($data['category_id'])) {
            $data['category_id'] = app(EventManagementService::class)
                ->ensureDefaultCategory($organization, $user->id)
                ->id;
        }

        return Event::query()->create($data);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Community Gathering',
            'starts_at' => now()->addDays(10)->toDateTimeString(),
            'ends_at' => now()->addDays(10)->addHours(2)->toDateTimeString(),
            'timezone' => 'Asia/Kolkata',
            'scope' => 'central',
            'visibility' => 'restricted',
        ], $overrides);
    }

    private function url(Organization $organization, string $path): string
    {
        return 'http://' . $organization->slug . '.example.test' . $path;
    }
}
