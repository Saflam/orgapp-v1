<?php

namespace Modules\Events\Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Core\Services\RoleAssignmentService;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventCategory;
use Modules\Events\Models\EventAttendanceScan;
use Modules\Events\Models\EventParticipant;
use Modules\Events\Models\EventPass;
use Modules\Events\Models\EventPassAttendee;
use Tests\TestCase;

class EventReportApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.deployment_mode' => 'multi', 'app.organization_base_domain' => 'example.test']);
    }

    public function test_authorized_user_can_view_event_report_summary(): void
    {
        [$organization, $user] = $this->authorizedUser();
        [$event, $pass] = $this->eventWithPass($organization, $user, 'guest', 'active', 3, 1);

        EventAttendanceScan::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_pass_id' => $pass->id,
            'scanned_by' => $user->id,
            'action' => 'entry',
            'quantity' => 2,
            'scanned_at' => now()->subHour(),
        ]);
        EventAttendanceScan::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_pass_id' => $pass->id,
            'scanned_by' => $user->id,
            'action' => 'exit',
            'quantity' => 1,
            'scanned_at' => now()->subMinutes(30),
        ]);

        $this->actingAs($user)
            ->getJson($this->url($organization, "/api/v1/events/{$event->id}/report"))
            ->assertOk()
            ->assertJsonPath('data.event.id', $event->id)
            ->assertJsonPath('data.participants.total', 1)
            ->assertJsonPath('data.participants.by_type.guest', 1)
            ->assertJsonPath('data.passes.total', 1)
            ->assertJsonPath('data.passes.active_capacity', 3)
            ->assertJsonPath('data.passes.currently_inside', 1)
            ->assertJsonPath('data.passes.remaining_capacity', 2)
            ->assertJsonPath('data.passes.distinct_passes_scanned', 1)
            ->assertJsonPath('data.passes.registered_individual_attendees', 0)
            ->assertJsonPath('data.passes.unique_individual_attendees_scanned', 0)
            ->assertJsonPath('data.attendance.entry_movements', 1)
            ->assertJsonPath('data.attendance.exit_movements', 1)
            ->assertJsonPath('data.attendance.entry_quantity_total', 2)
            ->assertJsonPath('data.attendance.exit_quantity_total', 1)
            ->assertJsonPath('data.attendance.quantity_tracking.entry_quantity_total', 2)
            ->assertJsonPath('data.attendance.quantity_tracking.exit_quantity_total', 1)
            ->assertJsonPath('data.attendance.identity_tracking.scan_records', 0)
            ->assertJsonPath('data.data_quality.occupancy_reconciliation', 'consistent');
    }

    public function test_report_separates_identity_tracking_and_reconciles_rostered_occupancy(): void
    {
        [$organization, $user] = $this->authorizedUser();
        [$event, $pass] = $this->eventWithPass($organization, $user, 'guest', 'active', 3, 1);

        $inside = EventPassAttendee::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_pass_id' => $pass->id,
            'name' => 'Inside Guest',
            'email' => 'inside-'.uniqid().'@example.test',
            'category' => 'guest',
            'status' => 'inside',
            'entry_count' => 1,
            'created_by' => $user->id,
        ]);
        EventPassAttendee::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_pass_id' => $pass->id,
            'name' => 'Outside VIP',
            'email' => 'outside-'.uniqid().'@example.test',
            'category' => 'vip',
            'status' => 'outside',
            'entry_count' => 0,
            'created_by' => $user->id,
        ]);

        EventAttendanceScan::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_pass_id' => $pass->id,
            'event_pass_attendee_id' => $inside->id,
            'scanned_by' => $user->id,
            'action' => 'entry',
            'quantity' => 1,
            'scanned_at' => now()->subMinutes(10),
        ]);

        $this->actingAs($user)
            ->getJson($this->url($organization, "/api/v1/events/{$event->id}/report"))
            ->assertOk()
            ->assertJsonPath('data.passes.registered_individual_attendees', 2)
            ->assertJsonPath('data.passes.individual_attendees_currently_inside', 1)
            ->assertJsonPath('data.passes.individual_attendees_outside', 1)
            ->assertJsonPath('data.passes.unique_individual_attendees_scanned', 1)
            ->assertJsonPath('data.passes.individual_attendees_by_category.guest.registered', 1)
            ->assertJsonPath('data.passes.individual_attendees_by_category.guest.inside', 1)
            ->assertJsonPath('data.attendance.identity_tracking.scan_records', 1)
            ->assertJsonPath('data.attendance.quantity_tracking.scan_records', 0)
            ->assertJsonPath('data.data_quality.passes_with_individual_rosters', 1)
            ->assertJsonPath('data.data_quality.passes_with_occupancy_mismatch', 0)
            ->assertJsonPath('data.data_quality.occupancy_reconciliation', 'consistent');
    }

    public function test_report_flags_mismatch_between_pass_occupancy_and_roster(): void
    {
        [$organization, $user] = $this->authorizedUser();
        [$event, $pass] = $this->eventWithPass($organization, $user, 'guest', 'active', 3, 2);

        EventPassAttendee::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_pass_id' => $pass->id,
            'name' => 'Only Rostered Guest',
            'email' => 'rostered-'.uniqid().'@example.test',
            'category' => 'guest',
            'status' => 'inside',
            'entry_count' => 1,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->getJson($this->url($organization, "/api/v1/events/{$event->id}/report"))
            ->assertOk()
            ->assertJsonPath('data.data_quality.passes_with_individual_rosters', 1)
            ->assertJsonPath('data.data_quality.passes_with_occupancy_mismatch', 1)
            ->assertJsonPath('data.data_quality.occupancy_reconciliation', 'review_required');
    }

    public function test_user_without_report_permission_is_forbidden(): void
    {
        [$organization, $user] = $this->authorizedUser('events.view');
        [$event] = $this->eventWithPass($organization, $user);

        $this->actingAs($user)
            ->getJson($this->url($organization, "/api/v1/events/{$event->id}/report"))
            ->assertForbidden();
    }

    public function test_event_report_cannot_access_another_organizations_event(): void
    {
        [$organization, $user] = $this->authorizedUser();
        $otherOrganization = $this->organization('org-two');
        $otherUser = User::factory()->create();
        [$event] = $this->eventWithPass($otherOrganization, $otherUser);

        $this->actingAs($user)
            ->getJson($this->url($organization, "/api/v1/events/{$event->id}/report"))
            ->assertNotFound();
    }

    public function test_unauthenticated_user_cannot_view_event_report(): void
    {
        $organization = $this->organization('org-one');
        $user = User::factory()->create();
        [$event] = $this->eventWithPass($organization, $user);

        $this->getJson($this->url($organization, "/api/v1/events/{$event->id}/report"))
            ->assertUnauthorized();
    }

    public function test_organization_summary_is_scoped_and_supports_date_and_status_filters(): void
    {
        [$organization, $user] = $this->authorizedUser();
        [$event] = $this->eventWithPass($organization, $user);
        $event->update(['starts_at' => now()->startOfDay(), 'status' => 'published']);

        $otherOrganization = $this->organization('org-two');
        $otherUser = User::factory()->create();
        $this->eventWithPass($otherOrganization, $otherUser);

        $this->actingAs($user)
            ->getJson($this->url($organization, '/api/v1/events/reports/summary?from='.now()->toDateString().'&to='.now()->toDateString().'&status=published'))
            ->assertOk()
            ->assertJsonPath('data.events.total', 1)
            ->assertJsonPath('data.events.by_status.published', 1)
            ->assertJsonPath('data.registrations.total', 1)
            ->assertJsonPath('data.passes.total', 1)
            ->assertJsonPath('data.filters.status', 'published');
    }

    public function test_category_report_groups_event_registration_and_attendance_metrics(): void
    {
        [$organization, $user] = $this->authorizedUser();
        $category = EventCategory::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Community',
            'slug' => 'community-report-'.uniqid(),
            'is_system' => false,
            'is_active' => true,
            'created_by' => $user->id,
        ]);
        [$event, $pass] = $this->eventWithPass($organization, $user);
        $event->update(['category_id' => $category->id]);
        EventAttendanceScan::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_pass_id' => $pass->id,
            'scanned_by' => $user->id,
            'action' => 'entry',
            'quantity' => 2,
            'scanned_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson($this->url($organization, '/api/v1/events/reports/categories?category_id='.$category->id))
            ->assertOk()
            ->assertJsonPath('data.categories.0.category_id', $category->id)
            ->assertJsonPath('data.categories.0.category_name', 'Community')
            ->assertJsonPath('data.categories.0.event_count', 1)
            ->assertJsonPath('data.categories.0.registrations_total', 1)
            ->assertJsonPath('data.categories.0.passes_total', 1)
            ->assertJsonPath('data.categories.0.entry_quantity_total', 2)
            ->assertJsonPath('data.filters.category_id', (string) $category->id);
    }

    public function test_organization_and_category_reports_count_cancelled_registrations_and_no_shows(): void
    {
        [$organization, $user] = $this->authorizedUser();
        $category = EventCategory::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Community Metrics',
            'slug' => 'community-metrics-'.uniqid(),
            'is_system' => false,
            'is_active' => true,
            'created_by' => $user->id,
        ]);
        [$event] = $this->eventWithPass($organization, $user);
        $event->update(['category_id' => $category->id]);

        // The helper creates one invited participant; convert it to a cancellation.
        EventParticipant::query()
            ->where('event_id', $event->id)
            ->where('organization_id', $organization->id)
            ->firstOrFail()
            ->update(['status' => 'cancelled']);

        EventParticipant::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'participant_type' => 'guest',
            'name' => 'No-show Guest',
            'email' => 'no-show-'.uniqid().'@example.test',
            'status' => 'no_show',
            'invited_at' => now()->subDay(),
        ]);
        EventParticipant::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'participant_type' => 'guest',
            'name' => 'Registered Guest',
            'email' => 'registered-'.uniqid().'@example.test',
            'status' => 'registered',
            'registered_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson($this->url($organization, '/api/v1/events/reports/summary'))
            ->assertOk()
            ->assertJsonPath('data.registrations.total', 3)
            ->assertJsonPath('data.registrations.cancelled', 1)
            ->assertJsonPath('data.registrations.no_shows', 1)
            ->assertJsonPath('data.registrations.by_status.cancelled', 1)
            ->assertJsonPath('data.registrations.by_status.no_show', 1)
            ->assertJsonPath('data.registrations.by_status.registered', 1);

        $this->actingAs($user)
            ->getJson($this->url($organization, '/api/v1/events/reports/categories?category_id='.$category->id))
            ->assertOk()
            ->assertJsonPath('data.categories.0.registrations_total', 3)
            ->assertJsonPath('data.categories.0.registrations_cancelled', 1)
            ->assertJsonPath('data.categories.0.no_shows', 1);
    }

    public function test_organization_report_rejects_invalid_date_range(): void
    {
        [$organization, $user] = $this->authorizedUser();

        $this->actingAs($user)
            ->getJson($this->url($organization, '/api/v1/events/reports/summary?from=2026-10-20&to=2026-10-01'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to']);
    }

    public function test_user_without_report_permission_cannot_view_organization_summary(): void
    {
        [$organization, $user] = $this->authorizedUser('events.view');

        $this->actingAs($user)
            ->getJson($this->url($organization, '/api/v1/events/reports/summary'))
            ->assertForbidden();
    }


    public function test_registration_report_supports_status_search_and_pagination(): void
    {
        [$organization, $user] = $this->authorizedUser();
        [$event] = $this->eventWithPass($organization, $user);
        EventParticipant::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'participant_type' => 'guest',
            'name' => 'Filtered Guest',
            'email' => 'filtered-guest@example.test',
            'status' => 'registered',
            'registered_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson($this->url($organization, "/api/v1/events/{$event->id}/reports/registrations?status=registered&search=Filtered&per_page=1"))
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.per_page', 1)
            ->assertJsonPath('data.data.0.name', 'Filtered Guest');
    }

    public function test_attendance_report_filters_scan_action_and_is_event_scoped(): void
    {
        [$organization, $user] = $this->authorizedUser();
        [$event, $pass] = $this->eventWithPass($organization, $user);
        EventAttendanceScan::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_pass_id' => $pass->id,
            'scanned_by' => $user->id,
            'action' => 'entry',
            'quantity' => 1,
            'scanned_at' => now(),
        ]);
        EventAttendanceScan::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_pass_id' => $pass->id,
            'scanned_by' => $user->id,
            'action' => 'exit',
            'quantity' => 1,
            'scanned_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson($this->url($organization, "/api/v1/events/{$event->id}/reports/attendance?action=entry"))
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.action', 'entry');
    }

    public function test_pass_report_filters_status_and_does_not_expose_token_hash(): void
    {
        [$organization, $user] = $this->authorizedUser();
        [$event, $pass] = $this->eventWithPass($organization, $user, 'guest', 'active');

        $response = $this->actingAs($user)
            ->getJson($this->url($organization, "/api/v1/events/{$event->id}/reports/passes?status=active"))
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $pass->id);

        $this->assertStringNotContainsString($pass->token_hash, $response->getContent());
    }

    public function test_detail_report_endpoints_require_report_permission(): void
    {
        [$organization, $user] = $this->authorizedUser('events.view');
        [$event] = $this->eventWithPass($organization, $user);

        foreach (['registrations', 'attendance', 'passes'] as $report) {
            $this->actingAs($user)
                ->getJson($this->url($organization, "/api/v1/events/{$event->id}/reports/{$report}"))
                ->assertForbidden();
        }
    }

    public function test_unit_scoped_report_permission_limits_event_and_organization_reports(): void
    {
        $organization = $this->organization('org-report-unit-scope');
        $assignedUnit = Unit::factory()->create(['organization_id' => $organization->id]);
        $otherUnit = Unit::factory()->create(['organization_id' => $organization->id]);
        $user = User::factory()->create();
        $membership = OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $permission = Permission::query()->firstOrCreate(
            ['code' => 'events.reports.view'],
            ['module' => 'events', 'name' => 'View Event Reports'],
        );
        $role = Role::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Unit Report Viewer',
            'code' => 'unit_report_viewer_'.uniqid(),
        ]);
        $role->permissions()->attach($permission->id);
        app(RoleAssignmentService::class)->assign(
            $membership,
            $role,
            new AuthorizationContext((int) $organization->id, (int) $assignedUnit->id),
        );

        $assignedEvent = $this->createUnitScopedReportEvent($organization, $user, $assignedUnit, 'Assigned Unit Report Event');
        $otherEvent = $this->createUnitScopedReportEvent($organization, $user, $otherUnit, 'Other Unit Report Event');

        $baseUrl = 'http://'.$organization->slug.'.example.test/api/v1/events';

        $this->actingAs($user)
            ->getJson($baseUrl.'/reports/summary')
            ->assertOk()
            ->assertJsonPath('data.events.total', 1);

        $this->actingAs($user)
            ->getJson($baseUrl.'/'.$assignedEvent->id.'/report')
            ->assertOk()
            ->assertJsonPath('data.event.id', $assignedEvent->id);

        $this->actingAs($user)
            ->getJson($baseUrl.'/'.$otherEvent->id.'/report')
            ->assertNotFound();

        $this->actingAs($user)
            ->getJson($baseUrl.'/reports/categories')
            ->assertOk()
            ->assertJsonPath('data.categories.0.event_count', 1);
    }

    private function createUnitScopedReportEvent(Organization $organization, User $user, \Modules\Core\Models\Unit $unit, string $title): Event
    {
        return Event::query()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'created_by' => $user->id,
            'title' => $title,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHours(2),
            'timezone' => 'Asia/Kolkata',
            'scope' => 'unit',
            'visibility' => 'restricted',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    private function authorizedUser(string $permissionCode = 'events.reports.view'): array
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
            'name' => 'Event Report Test Role',
            'code' => 'event_report_test_'.uniqid(),
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

    private function eventWithPass(
        Organization $organization,
        User $user,
        string $participantType = 'guest',
        string $passStatus = 'active',
        int $capacity = 1,
        int $currentInside = 0,
    ): array {
        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'created_by' => $user->id,
            'title' => 'Report Test Event',
            'starts_at' => now()->subDays(1),
            'ends_at' => now()->addHours(2),
            'timezone' => 'Asia/Kolkata',
            'scope' => 'central',
            'visibility' => 'restricted',
            'status' => 'published',
            'published_at' => now()->subDays(2),
        ]);
        $participant = EventParticipant::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'participant_type' => $participantType,
            'name' => 'Report Guest',
            'email' => 'report-'.uniqid().'@example.test',
            'status' => 'invited',
            'invited_at' => now(),
        ]);
        $pass = EventPass::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_participant_id' => $participant->id,
            'token_hash' => hash('sha256', bin2hex(random_bytes(32))),
            'status' => $passStatus,
            'capacity' => $capacity,
            'current_inside' => $currentInside,
            'issued_at' => now(),
            'issued_by' => $user->id,
        ]);

        return [$event, $pass];
    }

    private function url(Organization $organization, string $path): string
    {
        return 'http://'.$organization->slug.'.example.test'.$path;
    }
}
