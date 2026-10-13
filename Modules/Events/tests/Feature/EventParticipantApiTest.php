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
use Modules\Events\Notifications\EventLifecycleNotification;
use Modules\Member\Models\Member;
use Tests\TestCase;

class EventParticipantApiTest extends TestCase
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

    public function test_authorized_user_can_invite_an_organization_member(): void
    {
        [$organization, $user] = $this->authorizedUser('events.invitations.send');
        $event = $this->publishedRestrictedEvent($organization, $user);
        $member = Member::factory()->forOrganization($organization)->create();

        $this->actingAs($user)
            ->postJson($this->url($organization, "/api/v1/events/{$event->id}/participants"), [
                'participant_type' => 'member',
                'member_id' => $member->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.participant_type', 'member')
            ->assertJsonPath('data.member_id', $member->id)
            ->assertJsonPath('data.status', 'invited');

        $this->assertDatabaseHas('event_participants', [
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'member_id' => $member->id,
            'participant_type' => 'member',
            'status' => 'invited',
        ]);
    }

    public function test_authorized_user_can_invite_a_guest(): void
    {
        Notification::fake();
        [$organization, $user] = $this->authorizedUser('events.invitations.send');
        $event = $this->publishedRestrictedEvent($organization, $user);

        $this->actingAs($user)
            ->postJson($this->url($organization, "/api/v1/events/{$event->id}/participants"), [
                'participant_type' => 'guest',
                'name' => 'Guest Person',
                'email' => 'guest@example.test',
                'phone' => '+919876543210',
            ])
            ->assertCreated()
            ->assertJsonPath('data.participant_type', 'guest')
            ->assertJsonPath('data.name', 'Guest Person')
            ->assertJsonPath('data.email', 'guest@example.test')
            ->assertJsonPath('data.status', 'invited');

        Notification::assertSentOnDemand(EventLifecycleNotification::class);
    }

    public function test_member_from_another_organization_cannot_be_invited(): void
    {
        [$organization, $user] = $this->authorizedUser('events.invitations.send');
        $event = $this->publishedRestrictedEvent($organization, $user);
        $otherOrganization = Organization::factory()->create(['slug' => 'org-two']);
        $member = Member::factory()->forOrganization($otherOrganization)->create();

        $this->actingAs($user)
            ->postJson($this->url($organization, "/api/v1/events/{$event->id}/participants"), [
                'participant_type' => 'member',
                'member_id' => $member->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['member_id']);

        $this->assertDatabaseMissing('event_participants', [
            'event_id' => $event->id,
            'member_id' => $member->id,
        ]);
    }

    public function test_duplicate_member_invitation_is_rejected(): void
    {
        [$organization, $user] = $this->authorizedUser('events.invitations.send');
        $event = $this->publishedRestrictedEvent($organization, $user);
        $member = Member::factory()->forOrganization($organization)->create();
        $endpoint = $this->url($organization, "/api/v1/events/{$event->id}/participants");
        $payload = ['participant_type' => 'member', 'member_id' => $member->id];

        $this->actingAs($user)->postJson($endpoint, $payload)->assertCreated();
        $this->actingAs($user)->postJson($endpoint, $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['member_id']);
    }

    public function test_user_without_invitation_permission_cannot_invite_participants(): void
    {
        [$organization, $user] = $this->authorizedUser('events.view');
        $event = $this->publishedRestrictedEvent($organization, $user);

        $this->actingAs($user)
            ->postJson($this->url($organization, "/api/v1/events/{$event->id}/participants"), [
                'participant_type' => 'guest',
                'name' => 'Guest Person',
                'email' => 'guest@example.test',
            ])
            ->assertForbidden();
    }

    public function test_participants_cannot_be_invited_to_draft_or_public_events(): void
    {
        [$organization, $user] = $this->authorizedUser('events.invitations.send');
        $draft = $this->event($organization, $user, 'draft', 'restricted');
        $public = $this->event($organization, $user, 'published', 'public');

        foreach ([$draft, $public] as $event) {
            $this->actingAs($user)
                ->postJson($this->url($organization, "/api/v1/events/{$event->id}/participants"), [
                    'participant_type' => 'guest',
                    'name' => 'Guest Person',
                    'email' => 'guest-'. $event->id .'@example.test',
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['event']);
        }
    }

    public function test_participant_listing_is_scoped_to_the_current_organization(): void
    {
        [$organization, $user] = $this->authorizedUser('events.view');
        $event = $this->publishedRestrictedEvent($organization, $user);
        $otherOrganization = Organization::factory()->create(['slug' => 'org-two']);
        $otherUser = User::factory()->create();
        $otherEvent = $this->publishedRestrictedEvent($otherOrganization, $otherUser);

        $this->actingAs($user)
            ->getJson($this->url($organization, "/api/v1/events/{$otherEvent->id}/participants"))
            ->assertNotFound();

        $this->actingAs($user)
            ->getJson($this->url($organization, "/api/v1/events/{$event->id}/participants"))
            ->assertOk()
            ->assertJsonPath('data', []);
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
            'name' => 'Events Participant Test Role',
            'code' => 'events_participant_test_'.uniqid(),
        ]);
        $role->permissions()->attach($permission);
        app(RoleAssignmentService::class)->assign($membership, $role, $organization);

        return [$organization, $user];
    }

    private function publishedRestrictedEvent(Organization $organization, User $user): Event
    {
        return $this->event($organization, $user, 'published', 'restricted');
    }

    private function event(Organization $organization, User $user, string $status, string $visibility): Event
    {
        return Event::query()->create([
            'organization_id' => $organization->id,
            'created_by' => $user->id,
            'title' => 'Participant Test Event',
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(2),
            'timezone' => 'Asia/Kolkata',
            'scope' => 'central',
            'visibility' => $visibility,
            'status' => $status,
            'published_at' => $status === 'published' ? now() : null,
        ]);
    }

    private function url(Organization $organization, string $path): string
    {
        return 'http://'.$organization->slug.'.example.test'.$path;
    }
}
