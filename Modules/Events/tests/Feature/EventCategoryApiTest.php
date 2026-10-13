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
use Modules\Events\Models\EventCategory;
use Tests\TestCase;

class EventCategoryApiTest extends TestCase
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

    public function test_authorized_user_can_list_categories_for_the_current_organization(): void
    {
        [$organization, $user] = $this->authorizedUser('events.view');

        EventCategory::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Community',
            'slug' => 'community',
            'is_active' => true,
        ]);

        $otherOrganization = Organization::factory()->create([
            'slug' => 'org-two',
        ]);

        EventCategory::query()->create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Other',
            'slug' => 'other',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->getJson(
            $this->url($organization, '/api/v1/events/categories')
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.data.0.name', 'Community')
            ->assertJsonMissing(['name' => 'Other']);
    }

    public function test_user_without_view_permission_cannot_list_categories(): void
    {
        [$organization, $user] = $this->authorizedUser('events.manage');

        $this->actingAs($user)
            ->getJson(
                $this->url($organization, '/api/v1/events/categories')
            )
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_list_categories(): void
    {
        $organization = Organization::factory()->create([
            'slug' => 'org-one',
        ]);

        $this->getJson(
            $this->url($organization, '/api/v1/events/categories')
        )->assertUnauthorized();
    }

    public function test_authorized_user_can_create_category_and_slug_is_generated_uniquely(): void
    {
        [$organization, $user] = $this->authorizedUser('events.manage');

        $endpoint = $this->url(
            $organization,
            '/api/v1/events/categories'
        );

        $this->actingAs($user)
            ->postJson($endpoint, ['name' => 'Annual Meeting'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'annual-meeting');

        $this->actingAs($user)
            ->postJson($endpoint, ['name' => 'Annual Meeting'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'annual-meeting-2');

        $this->assertDatabaseHas('event_categories', [
            'organization_id' => $organization->id,
            'name' => 'Annual Meeting',
            'slug' => 'annual-meeting-2',
        ]);
    }

    public function test_category_creation_validates_required_name(): void
    {
        [$organization, $user] = $this->authorizedUser('events.manage');

        $this->actingAs($user)
            ->postJson(
                $this->url($organization, '/api/v1/events/categories'),
                []
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_authorized_user_can_update_category(): void
    {
        [$organization, $user] = $this->authorizedUser('events.manage');

        $category = $this->category(
            $organization,
            'Community',
            'community'
        );

        $this->actingAs($user)
            ->putJson(
                $this->url(
                    $organization,
                    "/api/v1/events/categories/{$category->id}"
                ),
                ['name' => 'Community Events']
            )
            ->assertOk()
            ->assertJsonPath('data.name', 'Community Events')
            ->assertJsonPath('data.slug', 'community-events');
    }

    public function test_category_from_another_organization_is_not_accessible(): void
    {
        [$organization, $user] = $this->authorizedUser('events.view');

        $otherOrganization = Organization::factory()->create([
            'slug' => 'org-two',
        ]);

        $category = $this->category(
            $otherOrganization,
            'Private',
            'private'
        );

        $this->actingAs($user)
            ->getJson(
                $this->url(
                    $organization,
                    "/api/v1/events/categories/{$category->id}"
                )
            )
            ->assertNotFound();
    }

    public function test_authorized_user_can_delete_unused_category(): void
    {
        [$organization, $user] = $this->authorizedUser('events.manage');

        $category = $this->category(
            $organization,
            'Temporary',
            'temporary'
        );

        $this->actingAs($user)
            ->deleteJson(
                $this->url(
                    $organization,
                    "/api/v1/events/categories/{$category->id}"
                )
            )
            ->assertOk();

        $this->assertDatabaseMissing('event_categories', [
            'id' => $category->id,
        ]);
    }

    public function test_category_assigned_to_an_event_cannot_be_deleted(): void
    {
        [$organization, $user] = $this->authorizedUser('events.manage');

        $category = $this->category(
            $organization,
            'Conference',
            'conference'
        );

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'category_id' => $category->id,
            'created_by' => $user->id,
            'title' => 'Annual Conference',
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(2),
            'timezone' => 'Asia/Kolkata',
            'scope' => 'central',
            'visibility' => 'restricted',
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->deleteJson(
                $this->url(
                    $organization,
                    "/api/v1/events/categories/{$category->id}"
                )
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
        ]);

        $this->assertDatabaseHas('event_categories', [
            'id' => $category->id,
        ]);
    }

    public function test_system_category_cannot_be_updated_or_deleted(): void
    {
        [$organization, $user] = $this->authorizedUser('events.manage');

        $category = EventCategory::query()->create([
            'organization_id' => $organization->id,
            'name' => 'System',
            'slug' => 'system',
            'is_system' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->putJson(
                $this->url(
                    $organization,
                    "/api/v1/events/categories/{$category->id}"
                ),
                ['name' => 'Changed']
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);

        $this->actingAs($user)
            ->deleteJson(
                $this->url(
                    $organization,
                    "/api/v1/events/categories/{$category->id}"
                )
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);

        $this->assertDatabaseHas('event_categories', [
            'id' => $category->id,
            'name' => 'System',
        ]);
    }

    /**
     * Create an organization, enable required modules, and assign
     * the requested permission to an active organization membership.
     */
    private function authorizedUser(string $permissionCode): array
    {
        $organization = Organization::factory()->create([
            'slug' => 'org-one',
        ]);

        $moduleService = app(OrganizationModuleService::class);

        // Enable dependencies before enabling Events.
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
            ]
        );

        $role = Role::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Events Test Role',
            'code' => 'events_test_' . uniqid(),
        ]);

        $role->permissions()->attach($permission);

        app(RoleAssignmentService::class)->assign(
            $membership,
            $role,
            $organization
        );

        return [$organization, $user];
    }

    private function category(
        Organization $organization,
        string $name,
        string $slug
    ): EventCategory {
        return EventCategory::query()->create([
            'organization_id' => $organization->id,
            'name' => $name,
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    private function url(
        Organization $organization,
        string $path
    ): string {
        return 'http://'
            . $organization->slug
            . '.example.test'
            . $path;
    }
}