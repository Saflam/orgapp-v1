<?php

namespace Modules\Events\Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Core\Services\RoleAssignmentService;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventParticipant;
use Modules\Events\Models\EventPass;
use Tests\TestCase;

class EventRateLimitTest extends TestCase
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

    public function test_attendance_scan_endpoint_is_rate_limited_per_organization_and_user(): void
    {
        [$organization, $user] = $this->authorizedScanner();
        $url = 'http://'.$organization->slug.'.example.test/api/v1/events/attendance/scan';
        $payload = [
            'token' => str_repeat('a', 64),
            'action' => 'entry',
        ];

        // A syntactically valid but unknown token reaches the service and returns 404.
        for ($i = 0; $i < 60; $i++) {
            $this->actingAs($user)->postJson($url, $payload)->assertNotFound();
        }

        $this->actingAs($user)
            ->postJson($url, $payload)
            ->assertTooManyRequests();
    }

    public function test_public_registration_endpoint_is_rate_limited(): void
    {
        $organization = Organization::factory()->create(['slug' => 'org-registration-rate-limit']);
        $modules = app(OrganizationModuleService::class);
        $modules->enable($organization, 'Core');
        $modules->enable($organization, 'Member');
        $modules->enable($organization, 'Events');

        $user = User::factory()->create();
        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'created_by' => $user->id,
            'title' => 'Rate Limit Public Registration',
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(2),
            'timezone' => 'Asia/Kolkata',
            'scope' => 'central',
            'visibility' => 'public',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $url = 'http://'.$organization->slug.'.example.test/api/v1/public/events/'.$event->id.'/registrations';
        for ($i = 0; $i < 10; $i++) {
            $this->postJson($url, [
                'name' => 'Guest '.$i,
                'email' => 'rate-limit-'.$i.'@example.test',
            ])->assertCreated();
        }

        $this->postJson($url, [
            'name' => 'Blocked Guest',
            'email' => 'rate-limit-blocked@example.test',
        ])->assertTooManyRequests();
    }

    public function test_pass_delivery_retry_endpoint_is_rate_limited(): void
    {
        [$organization, $user] = $this->authorizedInvitationSender();
        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'created_by' => $user->id,
            'title' => 'Rate Limit Retry Event',
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
            'name' => 'Retry Guest',
            'email' => 'retry-guest@example.test',
            'status' => 'invited',
            'invited_at' => now(),
        ]);
        $pass = EventPass::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_participant_id' => $participant->id,
            'token_hash' => hash('sha256', str_repeat('r', 64)),
            'status' => 'active',
            'capacity' => 1,
            'current_inside' => 0,
            'issued_at' => now(),
            'issued_by' => $user->id,
            'notification_delivery_status' => 'queued',
        ]);

        $url = 'http://'.$organization->slug.'.example.test/api/v1/events/'.$event->id.'/passes/'.$pass->id.'/retry-delivery';

        // The throttle middleware runs before controller validation, so requests
        // count toward the limit even when the pass is not eligible for retry.
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->postJson($url)->assertUnprocessable();
        }

        $this->actingAs($user)->postJson($url)->assertTooManyRequests();
    }


    public function test_attendance_scan_rate_limit_is_isolated_between_organizations(): void
    {
        [$organizationA, $userA] = $this->authorizedScanner();

        $organizationB = Organization::factory()->create(['slug' => 'org-rate-limit-b']);
        $modules = app(OrganizationModuleService::class);
        $modules->enable($organizationB, 'Core');
        $modules->enable($organizationB, 'Member');
        $modules->enable($organizationB, 'Events');

        $membership = OrganizationMembership::create([
            'organization_id' => $organizationB->id,
            'user_id' => $userA->id,
            'status' => 'active',
        ]);
        $permission = Permission::query()->firstOrCreate(
            ['code' => 'events.attendance.scan'],
            ['module' => 'events', 'name' => 'Scan Event Passes'],
        );
        $role = Role::query()->create([
            'organization_id' => $organizationB->id,
            'name' => 'Rate Limit Scanner Role B',
            'code' => 'rate_limit_scanner_b_'.uniqid(),
        ]);
        $role->permissions()->attach($permission);
        app(RoleAssignmentService::class)->assign($membership, $role, $organizationB);

        $payload = ['token' => str_repeat('a', 64), 'action' => 'entry'];
        $urlA = 'http://'.$organizationA->slug.'.example.test/api/v1/events/attendance/scan';
        $urlB = 'http://'.$organizationB->slug.'.example.test/api/v1/events/attendance/scan';

        for ($i = 0; $i < 60; $i++) {
            $this->actingAs($userA)->postJson($urlA, $payload)->assertNotFound();
        }
        $this->actingAs($userA)->postJson($urlA, $payload)->assertTooManyRequests();

        // Same user and IP, but a different organization host has an independent quota.
        $this->actingAs($userA)->postJson($urlB, $payload)->assertNotFound();
    }

    public function test_attendance_scan_rate_limit_is_isolated_between_users_in_same_organization(): void
    {
        [$organization, $userA] = $this->authorizedScanner();

        $userB = User::factory()->create();
        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $userB->id,
            'status' => 'active',
        ]);
        $permission = Permission::query()->firstOrCreate(
            ['code' => 'events.attendance.scan'],
            ['module' => 'events', 'name' => 'Scan Event Passes'],
        );
        $role = Role::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Rate Limit Scanner Role B',
            'code' => 'rate_limit_scanner_user_b_'.uniqid(),
        ]);
        $role->permissions()->attach($permission);
        app(RoleAssignmentService::class)->assign($membership, $role, $organization);

        $url = 'http://'.$organization->slug.'.example.test/api/v1/events/attendance/scan';
        $payload = ['token' => str_repeat('a', 64), 'action' => 'entry'];

        for ($i = 0; $i < 60; $i++) {
            $this->actingAs($userA)->postJson($url, $payload)->assertNotFound();
        }
        $this->actingAs($userA)->postJson($url, $payload)->assertTooManyRequests();

        // A different authenticated user in the same organization has a fresh quota.
        $this->actingAs($userB)->postJson($url, $payload)->assertNotFound();
    }

    public function test_public_registration_rate_limit_is_isolated_between_organizations(): void
    {
        $organizationA = Organization::factory()->create(['slug' => 'org-public-registration-a']);
        $organizationB = Organization::factory()->create(['slug' => 'org-public-registration-b']);
        $modules = app(OrganizationModuleService::class);
        foreach ([$organizationA, $organizationB] as $organization) {
            $modules->enable($organization, 'Core');
            $modules->enable($organization, 'Member');
            $modules->enable($organization, 'Events');
        }

        $creator = User::factory()->create();
        $events = [];
        foreach ([$organizationA, $organizationB] as $organization) {
            $events[] = Event::query()->create([
                'organization_id' => $organization->id,
                'created_by' => $creator->id,
                'title' => 'Public Registration Isolation',
                'starts_at' => now()->addDays(5),
                'ends_at' => now()->addDays(5)->addHours(2),
                'timezone' => 'Asia/Kolkata',
                'scope' => 'central',
                'visibility' => 'public',
                'status' => 'published',
                'published_at' => now(),
            ]);
        }

        $urlA = 'http://'.$organizationA->slug.'.example.test/api/v1/public/events/'.$events[0]->id.'/registrations';
        $urlB = 'http://'.$organizationB->slug.'.example.test/api/v1/public/events/'.$events[1]->id.'/registrations';
        for ($i = 0; $i < 10; $i++) {
            $this->postJson($urlA, ['name' => 'Guest '.$i, 'email' => 'isolation-a-'.$i.'@example.test'])->assertCreated();
        }
        $this->postJson($urlA, ['name' => 'Blocked Guest', 'email' => 'blocked-a@example.test'])->assertTooManyRequests();

        $this->postJson($urlB, ['name' => 'Guest B', 'email' => 'guest-b@example.test'])->assertCreated();
    }

    public function test_public_pass_validation_rate_limit_is_isolated_between_organizations(): void
    {
        $organizationA = Organization::factory()->create(['slug' => 'org-public-pass-a']);
        $organizationB = Organization::factory()->create(['slug' => 'org-public-pass-b']);
        $modules = app(OrganizationModuleService::class);
        foreach ([$organizationA, $organizationB] as $organization) {
            $modules->enable($organization, 'Core');
            $modules->enable($organization, 'Member');
            $modules->enable($organization, 'Events');
        }

        $urlA = 'http://'.$organizationA->slug.'.example.test/api/v1/public/event-passes/validate';
        $urlB = 'http://'.$organizationB->slug.'.example.test/api/v1/public/event-passes/validate';
        $payload = ['token' => str_repeat('z', 64)];
        for ($i = 0; $i < 30; $i++) {
            $this->postJson($urlA, $payload)->assertNotFound();
        }
        $this->postJson($urlA, $payload)->assertTooManyRequests();

        $this->postJson($urlB, $payload)->assertNotFound();
    }

    private function authorizedInvitationSender(): array
    {
        $organization = Organization::factory()->create(['slug' => 'org-retry-rate-limit']);
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
            ['code' => 'events.invitations.send'],
            ['module' => 'events', 'name' => 'Send Event Invitations'],
        );
        $role = Role::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Rate Limit Invitation Sender Role',
            'code' => 'rate_limit_sender_'.uniqid(),
        ]);
        $role->permissions()->attach($permission);
        app(RoleAssignmentService::class)->assign($membership, $role, $organization);

        return [$organization, $user];
    }

    private function authorizedScanner(): array
    {
        $organization = Organization::factory()->create(['slug' => 'org-rate-limit']);
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
            ['code' => 'events.attendance.scan'],
            ['module' => 'events', 'name' => 'Scan Event Passes'],
        );
        $role = Role::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Rate Limit Scanner Role',
            'code' => 'rate_limit_scanner_'.uniqid(),
        ]);
        $role->permissions()->attach($permission);
        app(RoleAssignmentService::class)->assign($membership, $role, $organization);

        return [$organization, $user];
    }

    public function test_public_pass_validation_is_rate_limited(): void
    {
        $organization = Organization::factory()->create([
            'slug' => 'org-pass-rate-limit',
        ]);

        $modules = app(OrganizationModuleService::class);
        $modules->enable($organization, 'Core');
        $modules->enable($organization, 'Member');
        $modules->enable($organization, 'Events');

        $url = 'http://'.$organization->slug.'.example.test/api/v1/public/event-passes/validate';
        $payload = ['token' => str_repeat('z', 64)];

        for ($i = 0; $i < 30; $i++) {
            $this->postJson($url, $payload)->assertNotFound();
        }

        $this->postJson($url, $payload)->assertTooManyRequests();
    }
}
