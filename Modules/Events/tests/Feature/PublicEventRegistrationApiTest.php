<?php

namespace Modules\Events\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Events\Models\Event;
use Modules\Events\Notifications\EventLifecycleNotification;
use Tests\TestCase;

class PublicEventRegistrationApiTest extends TestCase
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

    public function test_guest_can_register_for_a_published_public_event_without_authentication(): void
    {
        Notification::fake();
        [$organization, $event] = $this->publicEvent();

        $this->postJson($this->url($organization, $event), [
            'name' => 'Public Guest',
            'email' => 'Guest@Example.test',
            'phone' => '+919876543210',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Public Guest')
            ->assertJsonPath('data.status', 'registered');

        $this->assertDatabaseHas('event_participants', [
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'email' => 'guest@example.test',
            'status' => 'registered',
        ]);

        Notification::assertSentOnDemand(EventLifecycleNotification::class);
    }

    public function test_public_registration_validates_required_custom_fields(): void
    {
        [$organization, $event] = $this->publicEvent([
            ['key' => 'dietary_preference', 'label' => 'Dietary preference', 'type' => 'select', 'required' => true, 'options' => ['Vegetarian', 'No preference']],
        ]);

        $this->postJson($this->url($organization, $event), [
            'name' => 'Public Guest',
            'email' => 'guest@example.test',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['custom_fields.dietary_preference']);
    }

    public function test_custom_field_values_must_match_configured_options(): void
    {
        [$organization, $event] = $this->publicEvent([
            ['key' => 'dietary_preference', 'label' => 'Dietary preference', 'type' => 'select', 'required' => true, 'options' => ['Vegetarian', 'No preference']],
        ]);

        $this->postJson($this->url($organization, $event), [
            'name' => 'Public Guest',
            'email' => 'guest@example.test',
            'custom_fields' => ['dietary_preference' => 'Not an option'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['custom_fields.dietary_preference']);
    }

    public function test_duplicate_email_registration_is_rejected(): void
    {
        [$organization, $event] = $this->publicEvent();
        $endpoint = $this->url($organization, $event);
        $payload = ['name' => 'Public Guest', 'email' => 'guest@example.test'];

        $this->postJson($endpoint, $payload)->assertCreated();
        $this->postJson($endpoint, $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_is_rejected_for_restricted_or_draft_events(): void
    {
        [$organization, $publicEvent] = $this->publicEvent();
        $restricted = $this->makeEvent($organization, 'restricted', 'published');
        $draft = $this->makeEvent($organization, 'public', 'draft');

        foreach ([$restricted, $draft] as $event) {
            $this->postJson($this->url($organization, $event), [
                'name' => 'Public Guest',
                'email' => 'guest-'.$event->id.'@example.test',
            ])->assertUnprocessable()
                ->assertJsonValidationErrors(['event']);
        }
    }

    public function test_event_from_another_organization_is_not_exposed(): void
    {
        [$organization] = $this->organization();
        [$otherOrganization, $otherEvent] = $this->publicEvent();

        $this->postJson($this->url($organization, $otherEvent), [
            'name' => 'Public Guest',
            'email' => 'guest@example.test',
        ])->assertNotFound();
    }

    public function test_registration_cutoff_rejects_public_registration(): void
    {
        [$organization, $event] = $this->publicEvent();
        $event->forceFill(['registration_closes_at' => now()->subMinute()])->save();

        $this->postJson($this->url($organization, $event), [
            'name' => 'Late Guest', 'email' => 'late@example.test',
        ])->assertUnprocessable()->assertJsonValidationErrors(['event']);
    }

    public function test_registration_capacity_rejects_registration_after_limit_is_reached(): void
    {
        [$organization, $event] = $this->publicEvent();
        $event->forceFill(['registration_capacity' => 1])->save();
        $endpoint = $this->url($organization, $event);

        $this->postJson($endpoint, ['name' => 'First Guest', 'email' => 'first@example.test'])->assertCreated();
        $this->postJson($endpoint, ['name' => 'Second Guest', 'email' => 'second@example.test'])
            ->assertUnprocessable()->assertJsonValidationErrors(['event']);
        $this->assertSame(1, $event->participants()->whereNotIn('status', ['cancelled', 'declined'])->count());
    }

    private function publicEvent(array $fields = []): array
    {
        [$organization, $user] = $this->organization();
        $event = $this->makeEvent($organization, 'public', 'published', $fields, $user);

        return [$organization, $event];
    }

    private function organization(): array
    {
        $organization = Organization::factory()->create(['slug' => 'org-'.uniqid()]);
        $modules = app(OrganizationModuleService::class);
        $modules->enable($organization, 'Core');
        $modules->enable($organization, 'Member');
        $modules->enable($organization, 'Events');

        return [$organization, User::factory()->create()];
    }

    private function makeEvent(Organization $organization, string $visibility, string $status, array $fields = [], ?User $user = null): Event
    {
        $user ??= User::factory()->create();

        return Event::query()->create([
            'organization_id' => $organization->id,
            'created_by' => $user->id,
            'title' => 'Public Registration Test Event',
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(2),
            'timezone' => 'Asia/Kolkata',
            'scope' => 'central',
            'visibility' => $visibility,
            'status' => $status,
            'published_at' => $status === 'published' ? now() : null,
            'registration_fields' => $fields,
        ]);
    }

    private function url(Organization $organization, Event $event): string
    {
        return 'http://'.$organization->slug.'.example.test/api/v1/public/events/'.$event->id.'/registrations';
    }
}
