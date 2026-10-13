<?php

namespace Modules\Events\Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Core\Services\RoleAssignmentService;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventParticipant;
use Modules\Events\Models\EventPass;
use Modules\Events\Jobs\DeliverEventPassEmail;
use Modules\Events\Notifications\EventPassIssuedNotification;
use Tests\TestCase;

class EventPassApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Queue::fake();
        Storage::fake('local');
        config(['app.deployment_mode' => 'multi', 'app.organization_base_domain' => 'example.test']);
    }

    public function test_authorized_user_can_issue_pass_and_token_is_not_stored_in_plain_text(): void
    {
        [$organization, $user] = $this->authorizedUser();
        $event = $this->event($organization, $user);
        $participant = $this->participant($organization, $event, 'guest@example.test');

        $response = $this->actingAs($user)->postJson($this->url($organization, "/api/v1/events/{$event->id}/participants/{$participant->id}/pass"));
        $response->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.notification_status', 'queued');
        $token = $response->json('data.token');
        $this->assertIsString($token);
        $this->assertSame(hash('sha256', $token), EventPass::query()->firstOrFail()->token_hash);
        $this->assertDatabaseMissing('event_passes', ['token_hash' => $token]);
        Queue::assertPushed(DeliverEventPassEmail::class, function (DeliverEventPassEmail $job) use ($token): bool {
            $serializedJob = serialize($job);
            $this->assertStringNotContainsString($token, $serializedJob);
            $this->assertStringNotContainsString('@example.test', $serializedJob);

            $this->assertTrue(Storage::disk('local')->exists($job->encryptedQrPath));
            $encryptedSvg = Storage::disk('local')->get($job->encryptedQrPath);
            $this->assertStringNotContainsString($token, $encryptedSvg);
            $this->assertStringContainsString('<svg', Crypt::decryptString($encryptedSvg));

            return $job->passId === EventPass::query()->firstOrFail()->id;
        });
        $this->assertDatabaseHas('event_passes', [
            'id' => EventPass::query()->firstOrFail()->id,
            'notification_delivery_status' => 'queued',
        ]);
    }

    public function test_delivery_job_sends_the_qr_email_and_records_success_without_queueing_the_token(): void
    {
        [$organization, $user] = $this->authorizedUser();
        $event = $this->event($organization, $user);
        $participant = $this->participant($organization, $event, 'guest@example.test');
        $token = str_repeat('b', 64);
        $pass = EventPass::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_participant_id' => $participant->id,
            'token_hash' => hash('sha256', $token),
            'status' => 'active',
            'capacity' => 1,
            'current_inside' => 0,
            'issued_at' => now(),
            'issued_by' => $user->id,
            'notification_delivery_status' => 'queued',
        ]);
        $path = 'events/pass-delivery/test-artifact.enc';
        Storage::disk('local')->put($path, Crypt::encryptString('<svg xmlns="http://www.w3.org/2000/svg"></svg>'));

        $job = new DeliverEventPassEmail($pass->id, $path);
        $job->handle();

        $this->assertDatabaseHas('event_passes', [
            'id' => $pass->id,
            'notification_delivery_status' => 'sent',
        ]);
        $this->assertNotNull($pass->fresh()->notification_sent_at);
        $this->assertFalse(Storage::disk('local')->exists($path));
        Notification::assertSentOnDemand(EventPassIssuedNotification::class);

        // Reprocessing the same job after success must not send a second email.
        $job->handle();
        Notification::assertSentOnDemand(EventPassIssuedNotification::class);
    }

    public function test_terminal_pass_delivery_failure_is_recorded_and_encrypted_artifact_is_removed(): void
    {
        [$organization, $user] = $this->authorizedUser();
        $event = $this->event($organization, $user);
        $participant = $this->participant($organization, $event, 'failed@example.test');
        $pass = EventPass::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_participant_id' => $participant->id,
            'token_hash' => hash('sha256', str_repeat('f', 64)),
            'status' => 'active',
            'capacity' => 1,
            'current_inside' => 0,
            'issued_at' => now(),
            'issued_by' => $user->id,
            'notification_delivery_status' => 'queued',
        ]);
        $path = 'events/pass-delivery/terminal-failure.enc';
        Storage::disk('local')->put($path, Crypt::encryptString('<svg></svg>'));

        $job = new DeliverEventPassEmail($pass->id, $path);
        $job->failed(new \RuntimeException('Simulated terminal queue failure'));

        $this->assertDatabaseHas('event_passes', [
            'id' => $pass->id,
            'notification_delivery_status' => 'failed',
        ]);
        $this->assertNotNull($pass->fresh()->notification_failed_at);
        $this->assertFalse(Storage::disk('local')->exists($path));
    }

    public function test_failed_delivery_can_be_retried_with_a_rotated_token(): void
    {
        [$organization, $user] = $this->authorizedUser();
        $event = $this->event($organization, $user);
        $participant = $this->participant($organization, $event, 'guest@example.test');
        $oldToken = str_repeat('c', 64);
        $pass = EventPass::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_participant_id' => $participant->id,
            'token_hash' => hash('sha256', $oldToken),
            'status' => 'active',
            'capacity' => 1,
            'current_inside' => 0,
            'issued_at' => now(),
            'issued_by' => $user->id,
            'notification_delivery_status' => 'failed',
            'notification_failed_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson(
            $this->url($organization, "/api/v1/events/{$event->id}/passes/{$pass->id}/retry-delivery")
        );

        $response->assertAccepted()->assertJsonPath('data.notification_status', 'queued');
        $this->assertNotNull($pass->fresh()->token_hash);
        $this->assertNotSame(hash('sha256', $oldToken), $pass->fresh()->token_hash);
        $this->postJson($this->url($organization, '/api/v1/public/event-passes/validate'), ['token' => $oldToken])
            ->assertNotFound();
        Queue::assertPushed(DeliverEventPassEmail::class);
    }

    public function test_delivery_status_endpoint_reports_counts_without_exposing_tokens_or_emails(): void
    {
        [$organization, $user] = $this->authorizedUser();
        $event = $this->event($organization, $user);
        $participant = $this->participant($organization, $event, 'private@example.test');
        EventPass::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_participant_id' => $participant->id,
            'token_hash' => hash('sha256', str_repeat('d', 64)),
            'status' => 'active',
            'capacity' => 1,
            'current_inside' => 0,
            'issued_at' => now(),
            'issued_by' => $user->id,
            'notification_delivery_status' => 'failed',
            'notification_failed_at' => now(),
        ]);

        $this->actingAs($user)->getJson(
            $this->url($organization, "/api/v1/events/{$event->id}/pass-deliveries")
        )->assertOk()
            ->assertJsonPath('data.counts.failed', 1)
            ->assertJsonPath('data.failed.data.0.status', 'failed')
            ->assertJsonMissing(['email' => 'private@example.test'])
            ->assertJsonMissing(['token_hash' => hash('sha256', str_repeat('d', 64))]);
    }

    public function test_only_failed_delivery_can_be_retried(): void
    {
        [$organization, $user] = $this->authorizedUser();
        $event = $this->event($organization, $user);
        $participant = $this->participant($organization, $event, 'guest@example.test');
        $pass = EventPass::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_participant_id' => $participant->id,
            'token_hash' => hash('sha256', str_repeat('e', 64)),
            'status' => 'active',
            'capacity' => 1,
            'current_inside' => 0,
            'issued_at' => now(),
            'issued_by' => $user->id,
            'notification_delivery_status' => 'queued',
        ]);

        $this->actingAs($user)->postJson(
            $this->url($organization, "/api/v1/events/{$event->id}/passes/{$pass->id}/retry-delivery")
        )->assertUnprocessable()->assertJsonValidationErrors(['notification']);
    }

    public function test_duplicate_pass_issuance_is_rejected(): void
    {
        [$organization, $user] = $this->authorizedUser();
        $event = $this->event($organization, $user);
        $participant = $this->participant($organization, $event, 'guest@example.test');
        $endpoint = $this->url($organization, "/api/v1/events/{$event->id}/participants/{$participant->id}/pass");

        $this->actingAs($user)->postJson($endpoint)->assertCreated();
        $this->actingAs($user)->postJson($endpoint)->assertUnprocessable();
    }

    public function test_pass_token_can_be_validated_without_exposing_email(): void
    {
        [$organization, $user] = $this->authorizedUser();
        $event = $this->event($organization, $user);
        $participant = $this->participant($organization, $event, 'private@example.test');
        $issue = $this->actingAs($user)->postJson($this->url($organization, "/api/v1/events/{$event->id}/participants/{$participant->id}/pass"));
        $token = $issue->json('data.token');

        $this->postJson($this->url($organization, '/api/v1/public/event-passes/validate'), ['token' => $token])
            ->assertOk()
            ->assertJsonPath('data.participant.name', 'Guest')
            ->assertJsonMissing(['email' => 'private@example.test']);
    }

    public function test_pass_token_cannot_be_validated_through_another_organizations_endpoint(): void
    {
        [$organizationA, $userA] = $this->authorizedUser();

        $organizationB = Organization::factory()->create(['slug' => 'org-two']);
        $modules = app(OrganizationModuleService::class);
        $modules->enable($organizationB, 'Core');
        $modules->enable($organizationB, 'Member');
        $modules->enable($organizationB, 'Events');

        $eventB = $this->event($organizationB, $userA);
        $participantB = $this->participant($organizationB, $eventB, 'other-org@example.test');
        $tokenB = str_repeat('f', 64);

        EventPass::query()->create([
            'organization_id' => $organizationB->id,
            'event_id' => $eventB->id,
            'event_participant_id' => $participantB->id,
            'token_hash' => hash('sha256', $tokenB),
            'status' => 'active',
            'capacity' => 1,
            'current_inside' => 0,
            'issued_at' => now(),
            'issued_by' => $userA->id,
            'notification_delivery_status' => 'not_requested',
        ]);

        $this->postJson(
            $this->url($organizationA, '/api/v1/public/event-passes/validate'),
            ['token' => $tokenB]
        )->assertNotFound();
    }

    public function test_pass_management_cannot_use_resources_from_another_organization(): void
    {
        [$organizationA, $userA] = $this->authorizedUser();
        $eventA = $this->event($organizationA, $userA);
        $participantA = $this->participant($organizationA, $eventA, 'a@example.test');

        $organizationB = Organization::factory()->create(['slug' => 'org-two']);
        $modules = app(OrganizationModuleService::class);
        $modules->enable($organizationB, 'Core');
        $modules->enable($organizationB, 'Member');
        $modules->enable($organizationB, 'Events');

        $eventB = $this->event($organizationB, $userA);
        $participantB = $this->participant($organizationB, $eventB, 'b@example.test');
        $passB = EventPass::query()->create([
            'organization_id' => $organizationB->id,
            'event_id' => $eventB->id,
            'event_participant_id' => $participantB->id,
            'token_hash' => hash('sha256', str_repeat('g', 64)),
            'status' => 'active',
            'capacity' => 1,
            'current_inside' => 0,
            'issued_at' => now(),
            'issued_by' => $userA->id,
            'notification_delivery_status' => 'failed',
            'notification_failed_at' => now(),
        ]);

        $baseA = $this->url($organizationA, "/api/v1/events/{$eventA->id}");

        $this->actingAs($userA)
            ->postJson($baseA . "/participants/{$participantB->id}/pass")
            ->assertNotFound();

        $this->actingAs($userA)
            ->postJson($baseA . "/passes/{$passB->id}/revoke")
            ->assertNotFound();

        $this->actingAs($userA)
            ->postJson($baseA . "/passes/{$passB->id}/retry-delivery")
            ->assertNotFound();

        $this->assertDatabaseHas('event_passes', [
            'id' => $passB->id,
            'organization_id' => $organizationB->id,
            'status' => 'active',
        ]);
    }

    public function test_pass_email_view_renders_qr_code_without_printing_the_raw_token(): void
    {
        $token = str_repeat('a', 64);
        $event = new Event(['title' => 'QR Rendering Test']);

        $qrSvg = '<svg xmlns="http://www.w3.org/2000/svg"><text>QR</text></svg>';
        $html = view('events::emails.event-pass-issued', [
            'event' => $event,
            'qrSvg' => $qrSvg,
        ])->render();

        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('QR Rendering Test', $html);
        $this->assertStringNotContainsString($token, $html);
    }

    public function test_invalid_token_is_not_found(): void
    {
        [$organization] = $this->authorizedUser();
        $this->postJson($this->url($organization, '/api/v1/public/event-passes/validate'), ['token' => str_repeat('x', 64)])
            ->assertNotFound();
    }

    public function test_user_without_invitation_permission_cannot_issue_pass(): void
    {
        [$organization, $user] = $this->authorizedUser('events.view');
        $event = $this->event($organization, $user);
        $participant = $this->participant($organization, $event, 'guest@example.test');

        $this->actingAs($user)->postJson($this->url($organization, "/api/v1/events/{$event->id}/participants/{$participant->id}/pass"))
            ->assertForbidden();
    }

    private function authorizedUser(string $permissionCode = 'events.invitations.send'): array
    {
        $organization = Organization::factory()->create(['slug' => 'org-one']);
        $modules = app(OrganizationModuleService::class);
        $modules->enable($organization, 'Core');
        $modules->enable($organization, 'Member');
        $modules->enable($organization, 'Events');
        $user = User::factory()->create();
        $membership = OrganizationMembership::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'status' => 'active']);
        $permission = Permission::query()->firstOrCreate(['code' => $permissionCode], ['module' => 'events', 'name' => str($permissionCode)->headline()]);
        $role = Role::query()->create(['organization_id' => $organization->id, 'name' => 'Pass Test Role', 'code' => 'pass_test_'.uniqid()]);
        $role->permissions()->attach($permission);
        app(RoleAssignmentService::class)->assign($membership, $role, $organization);
        return [$organization, $user];
    }

    private function event(Organization $organization, User $user): Event
    {
        return Event::query()->create([
            'organization_id' => $organization->id, 'created_by' => $user->id, 'title' => 'Test Event',
            'starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(5)->addHours(2),
            'timezone' => 'Asia/Kolkata', 'scope' => 'central', 'visibility' => 'restricted',
            'status' => 'published', 'published_at' => now(),
        ]);
    }

    private function participant(Organization $organization, Event $event, string $email): EventParticipant
    {
        return EventParticipant::query()->create([
            'organization_id' => $organization->id, 'event_id' => $event->id, 'participant_type' => 'guest',
            'name' => 'Guest', 'email' => $email, 'status' => 'invited', 'invited_at' => now(),
        ]);
    }

    private function url(Organization $organization, string $path): string
    {
        return 'http://'.$organization->slug.'.example.test'.$path;
    }
}
