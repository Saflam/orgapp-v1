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
use Modules\Events\Models\EventAttendanceScan;
use Modules\Events\Models\EventParticipant;
use Modules\Events\Models\EventPass;
use Tests\TestCase;

class EventAttendanceAuditApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.deployment_mode' => 'multi', 'app.organization_base_domain' => 'example.test']);
    }

    public function test_authorized_user_can_view_attendance_audit_history(): void
    {
        [$organization, $user] = $this->authorizedUser();
        [$event, $pass] = $this->eventWithPass($organization, $user);
        $this->scan($organization, $event, $pass, $user, 'entry', 2, now()->subMinutes(10));
        $this->scan($organization, $event, $pass, $user, 'exit', 1, now()->subMinutes(5));

        $this->actingAs($user)
            ->getJson($this->url($organization, "/api/v1/events/{$event->id}/attendance/scans"))
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.action', 'exit')
            ->assertJsonPath('data.0.quantity', 1)
            ->assertJsonPath('data.0.scanner.id', $user->id)
            ->assertJsonPath('data.0.pass.id', $pass->id)
            ->assertJsonMissingPath('data.0.scanner.email');
    }

    public function test_audit_history_can_be_filtered_and_paginated(): void
    {
        [$organization, $user] = $this->authorizedUser();
        [$event, $pass] = $this->eventWithPass($organization, $user);
        $this->scan($organization, $event, $pass, $user, 'entry', 1, now()->subMinutes(20));
        $this->scan($organization, $event, $pass, $user, 'exit', 1, now()->subMinutes(10));
        $this->scan($organization, $event, $pass, $user, 'entry', 1, now());

        $this->actingAs($user)
            ->getJson($this->url($organization, "/api/v1/events/{$event->id}/attendance/scans?action=entry&per_page=1&page=2"))
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('data.0.action', 'entry');
    }

    public function test_user_without_audit_permission_is_forbidden(): void
    {
        [$organization, $user] = $this->authorizedUser('events.view');
        [$event] = $this->eventWithPass($organization, $user);

        $this->actingAs($user)
            ->getJson($this->url($organization, "/api/v1/events/{$event->id}/attendance/scans"))
            ->assertForbidden();
    }

    public function test_audit_history_cannot_access_another_organizations_event(): void
    {
        [$organization, $user] = $this->authorizedUser();
        $otherOrganization = $this->organization('org-two');
        $otherUser = User::factory()->create();
        [$event] = $this->eventWithPass($otherOrganization, $otherUser);

        $this->actingAs($user)
            ->getJson($this->url($organization, "/api/v1/events/{$event->id}/attendance/scans"))
            ->assertNotFound();
    }

    public function test_audit_filters_are_validated(): void
    {
        [$organization, $user] = $this->authorizedUser();
        [$event] = $this->eventWithPass($organization, $user);

        $this->actingAs($user)
            ->getJson($this->url($organization, "/api/v1/events/{$event->id}/attendance/scans?action=invalid"))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['action']);
    }

    private function authorizedUser(string $permissionCode = 'events.attendance.audit.view'): array
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
            'name' => 'Attendance Audit Test Role',
            'code' => 'attendance_audit_test_'.uniqid(),
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

    private function eventWithPass(Organization $organization, User $user): array
    {
        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'created_by' => $user->id,
            'title' => 'Audit Test Event',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(2),
            'timezone' => 'Asia/Kolkata',
            'scope' => 'central',
            'visibility' => 'restricted',
            'status' => 'published',
            'published_at' => now()->subDays(1),
        ]);
        $participant = EventParticipant::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'participant_type' => 'guest',
            'name' => 'Audit Guest',
            'email' => 'audit-'.uniqid().'@example.test',
            'status' => 'invited',
            'invited_at' => now(),
        ]);
        $pass = EventPass::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_participant_id' => $participant->id,
            'token_hash' => hash('sha256', bin2hex(random_bytes(32))),
            'status' => 'active',
            'capacity' => 5,
            'current_inside' => 0,
            'issued_at' => now(),
            'issued_by' => $user->id,
        ]);

        return [$event, $pass];
    }

    private function scan(Organization $organization, Event $event, EventPass $pass, User $user, string $action, int $quantity, $scannedAt): void
    {
        EventAttendanceScan::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_pass_id' => $pass->id,
            'scanned_by' => $user->id,
            'action' => $action,
            'quantity' => $quantity,
            'scanned_at' => $scannedAt,
        ]);
    }

    private function url(Organization $organization, string $path): string
    {
        return 'http://'.$organization->slug.'.example.test'.$path;
    }
}
