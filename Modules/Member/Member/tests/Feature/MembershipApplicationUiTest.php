<?php

namespace Modules\Member\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\Unit;
use Modules\Core\Models\IdentificationType;
use Modules\Core\Services\OrganizationIdentificationService;
use Modules\Core\Models\Organization;
use Modules\Member\Models\MembershipType;
use Modules\Member\Models\MembershipApplication;
use Modules\Member\Models\MembershipApplicationStatusHistory;
use Modules\Member\Enums\MembershipApplicationStatus;
use Modules\Member\Tests\Concerns\ActsAsMemberOrganizationUser;
use Tests\TestCase;

class MembershipApplicationUiTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsMemberOrganizationUser;

    public function test_membership_application_page_renders_mobile_first_form_shell(): void
    {
        $organization = Organization::factory()->create([
            'slug' => 'apply-ui-' . uniqid(),
        ]);
        $this->actAsMemberOrganizationAdmin($organization);

        $this->get($this->organizationUrl($organization, '/membership/apply'))
            ->assertOk()
            ->assertSee('Become a member')
            ->assertSee('data-membership-application', false)
            ->assertSee('organization.webmanifest', false)
            ->assertSee('data-spouse-search-url', false)
            ->assertSee('data-spouse-registration-url', false);
    }

    public function test_browser_configuration_includes_phone_countries_and_active_membership_types(): void
    {
        $organization = Organization::factory()->create([
            'slug' => 'apply-config-' . uniqid(),
        ]);
        $this->actAsMemberOrganizationAdmin($organization);

        $membershipType = MembershipType::create([
            'organization_id' => $organization->id,
            'name' => 'Standard Membership',
            'code' => 'standard',
            'description' => 'Standard member.',
            'is_active' => true,
            'metadata' => [],
        ]);

        $this->getJson($this->organizationUrl($organization, '/membership/apply/configuration'))
            ->assertOk()
            ->assertJsonPath('membership_types.0.id', $membershipType->id)
            ->assertJsonFragment(['iso2' => 'IN', 'calling_code' => '91'])
            ->assertJsonStructure(['units', 'steps', 'personal', 'address', 'extensions', 'identifications', 'documents']);
    }


    public function test_submitted_applicant_is_shown_application_status_instead_of_a_new_form(): void
    {
        $organization = Organization::factory()->create([
            'slug' => 'apply-duplicate-' . uniqid(),
        ]);
        $this->actAsMemberOrganizationAdmin($organization);
        $user = auth()->user();

        MembershipApplication::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_type_id' => null,
            'status' => MembershipApplicationStatus::SUBMITTED,
            'current_step' => 5,
            'completed_steps' => [1, 2, 3, 4, 5],
            'data' => [],
            'submitted_at' => now(),
        ]);

        $this->get($this->organizationUrl($organization, '/membership/apply'))
            ->assertOk()
            ->assertSee('Your membership application is already on file')
            ->assertDontSee('data-membership-application', false);

        $this->get($this->organizationUrl($organization, '/home'))
            ->assertOk()
            ->assertSee('Application in Progress')
            ->assertDontSee('Apply Membership');
    }

    public function test_rejected_application_shows_reason_and_explicit_reapply_action_when_allowed(): void
    {
        $organization = Organization::factory()->create([
            'slug' => 'apply-rejected-reapply-' . uniqid(),
        ]);
        $this->actAsMemberOrganizationAdmin($organization);
        $user = auth()->user();

        $application = MembershipApplication::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_type_id' => null,
            'status' => MembershipApplicationStatus::REJECTED,
            'current_step' => 5,
            'completed_steps' => [1, 2, 3, 4, 5],
            'data' => ['member' => ['first_name' => 'Old', 'last_name' => 'Details']],
            'submitted_at' => now(),
        ]);
        $application->statusHistories()->create([
            'from_status' => MembershipApplicationStatus::SUBMITTED->value,
            'to_status' => MembershipApplicationStatus::REJECTED->value,
            'actor_user_id' => $user->id,
            'notes' => 'Please upload a clearer proof of address.',
            'allow_reapply' => true,
            'metadata' => [],
        ]);

        $this->get($this->organizationUrl($organization, '/membership/apply'))
            ->assertOk()
            ->assertSee('Your membership application was rejected')
            ->assertSee('Please upload a clearer proof of address.')
            ->assertSee('Re-apply for membership')
            ->assertSee('reapply=1', false)
            ->assertDontSee('data-membership-application', false);

        $this->get($this->organizationUrl($organization, '/membership/apply?reapply=1'))
            ->assertOk()
            ->assertSee('data-membership-application', false);
    }

    public function test_rejected_application_without_reapply_permission_shows_reason_without_reapply_button(): void
    {
        $organization = Organization::factory()->create([
            'slug' => 'apply-rejected-no-reapply-' . uniqid(),
        ]);
        $this->actAsMemberOrganizationAdmin($organization);
        $user = auth()->user();

        $application = MembershipApplication::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_type_id' => null,
            'status' => MembershipApplicationStatus::REJECTED,
            'current_step' => 5,
            'completed_steps' => [1, 2, 3, 4, 5],
            'data' => [],
            'submitted_at' => now(),
        ]);
        $application->statusHistories()->create([
            'from_status' => MembershipApplicationStatus::SUBMITTED->value,
            'to_status' => MembershipApplicationStatus::REJECTED->value,
            'actor_user_id' => $user->id,
            'notes' => 'This application does not meet the eligibility criteria.',
            'allow_reapply' => false,
            'metadata' => [],
        ]);

        $this->get($this->organizationUrl($organization, '/membership/apply'))
            ->assertOk()
            ->assertSee('Your membership application was rejected')
            ->assertSee('This application does not meet the eligibility criteria.')
            ->assertDontSee('Re-apply for membership')
            ->assertDontSee('data-membership-application', false);
    }

    public function test_browser_submission_rejects_a_future_date_of_birth(): void
    {
        $organization = Organization::factory()->create([
            'slug' => 'apply-future-dob-' . uniqid(),
        ]);
        $this->actAsMemberOrganizationAdmin($organization);

        $this->postJson($this->organizationUrl($organization, '/membership/apply'), [
            'date_of_birth' => now()->addDay()->toDateString(),
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_of_birth']);
    }

    public function test_browser_submission_reuses_profile_photo_saved_with_the_draft(): void
    {
        Storage::fake(config('media-library.disk_name'));

        $organization = Organization::factory()->create([
            'slug' => 'apply-saved-photo-' . uniqid(),
        ]);
        $this->actAsMemberOrganizationAdmin($organization);
        $user = auth()->user();
        $unit = Unit::query()->where('organization_id', $organization->id)->first()
            ?? Unit::factory()->create(['organization_id' => $organization->id]);

        $draftPayload = [
            'preferred_unit_id' => $unit->id,
            'first_name' => 'Taylor',
            'last_name' => 'Applicant',
            'blood_group' => 'O+',
            'primary_phone' => '9876543210',
            'primary_phone_country_iso2' => 'IN',
            'emergency_contacts' => [[
                'contact_name' => 'Emergency Person',
                'relationship' => 'Sibling',
                'phone' => '9876543211',
                'country_iso2' => 'IN',
            ]],
            'profile_photo' => UploadedFile::fake()->image('profile.png'),
        ];

        $this->post($this->organizationUrl($organization, '/membership/apply/draft'), $draftPayload, [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk()->assertJsonPath('saved', true);

        $draft = MembershipApplication::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->where('status', MembershipApplicationStatus::DRAFT)
            ->firstOrFail();
        $this->assertCount(1, $draft->getMedia('profile-photo'));

        $response = $this->post($this->organizationUrl($organization, '/membership/apply'), [
            'user_id' => $user->id,
            'first_name' => 'Taylor',
            'last_name' => 'Applicant',
            'blood_group' => 'O+',
            'primary_phone' => '9876543210',
            'primary_phone_country_iso2' => 'IN',
            'preferred_unit_id' => $unit->id,
            'addresses' => [
                ['address_type' => 'residential', 'address_line_1' => '10 Example Street', 'country' => 'India'],
                ['address_type' => 'permanent', 'address_line_1' => '12 Example Street', 'country' => 'India'],
            ],
            'emergency_contacts' => [[
                'contact_name' => 'Emergency Person',
                'relationship' => 'Sibling',
                'phone' => '9876543211',
                'country_iso2' => 'IN',
            ]],
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertCreated();
        $submitted = $draft->fresh();
        $this->assertSame(MembershipApplicationStatus::SUBMITTED, $submitted->status);
        $this->assertCount(1, $submitted->getMedia('profile-photo'));
    }

    public function test_self_service_configuration_does_not_require_an_admin_role(): void
    {
        $organization = Organization::factory()->create([
            'slug' => 'apply-self-service-' . uniqid(),
        ]);
        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'example.test',
        ]);
        app(\Modules\Core\Services\OrganizationModuleService::class)->enable($organization, 'Member');
        $applicant = User::factory()->create();
        $this->actingAs($applicant);

        MembershipType::create([
            'organization_id' => $organization->id,
            'name' => 'Standard Membership',
            'code' => 'standard',
            'is_active' => true,
            'metadata' => [],
        ]);

        $this->getJson($this->organizationUrl($organization, '/membership/apply/configuration'))
            ->assertOk()
            ->assertJsonPath('membership_types.0.code', 'standard');
    }

    public function test_later_application_step_draft_is_saved_and_restored_with_resume_position(): void
    {
        $organization = Organization::factory()->create([
            'slug' => 'apply-step-three-' . uniqid(),
        ]);
        $this->actAsMemberOrganizationAdmin($organization);
        $user = auth()->user();

        $application = MembershipApplication::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_type_id' => null,
            'status' => MembershipApplicationStatus::DRAFT,
            'current_step' => 3,
            'completed_steps' => [1, 2],
            'data' => [
                'draft' => [
                    'step_1' => ['first_name' => 'Taylor'],
                    'step_2' => ['addresses' => []],
                ],
            ],
        ]);

        $this->post($this->organizationUrl($organization, '/membership/apply/draft'), [
            'draft_step' => '3',
            'advance_step' => '1',
            'extension_data' => [
                'Committee' => ['designation' => 'Treasurer'],
            ],
        ], ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('saved', true)
            ->assertJsonPath('current_step', 4)
            ->assertJsonPath('step_data.extension_data.Committee.designation', 'Treasurer');

        $this->assertDatabaseHas('membership_applications', [
            'id' => $application->id,
            'current_step' => 4,
        ]);
        $this->assertSame(
            'Treasurer',
            data_get($application->fresh()->data, 'draft.step_3.extension_data.Committee.designation'),
        );
        $this->assertSame(
            'Treasurer',
            data_get($application->fresh()->data, 'draft.extension_data.Committee.designation'),
        );

        $this->getJson($this->organizationUrl($organization, '/membership/apply/configuration'))
            ->assertOk()
            ->assertJsonPath('draft.current_step', 4)
            ->assertJsonPath('draft.step_3.extension_data.Committee.designation', 'Treasurer')
            ->assertJsonPath('draft.extension_data.Committee.designation', 'Treasurer');
    }

    public function test_browser_submission_uses_authenticated_user_instead_of_submitted_user_id(): void
    {
        $organization = Organization::factory()->create([
            'slug' => 'apply-submit-' . uniqid(),
        ]);
        $this->actAsMemberOrganizationAdmin($organization);
        $authenticatedUser = auth()->user();
        $otherUser = User::factory()->create();

        $membershipType = MembershipType::create([
            'organization_id' => $organization->id,
            'name' => 'Standard Membership',
            'code' => 'standard',
            'is_active' => true,
            'metadata' => [],
        ]);

        $unit = \Modules\Core\Models\Unit::query()->where('organization_id', $organization->id)->first()
            ?? \Modules\Core\Models\Unit::factory()->create(['organization_id' => $organization->id]);

        $response = $this->post($this->organizationUrl($organization, '/membership/apply'), [
            'user_id' => $otherUser->id,
            'first_name' => 'Applicant',
            'last_name' => 'Example',
            'blood_group' => 'O+',
            'primary_phone' => '9876543210',
            'primary_phone_country_iso2' => 'IN',
            'preferred_unit_id' => $unit->id,
            'profile_photo' => \Illuminate\Http\UploadedFile::fake()->image('profile.png'),
            'addresses' => [
                ['address_type' => 'residential', 'address_line_1' => '10 Example Street', 'country' => 'India'],
                ['address_type' => 'permanent', 'address_line_1' => '12 Permanent Street', 'country' => 'India'],
            ],
            'emergency_contacts' => [[
                'contact_name' => 'Emergency Example',
                'relationship' => 'Parent',
                'phone' => '9876543211',
                'country_iso2' => 'IN',
            ]],
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertCreated()->assertJsonPath('user_id', $authenticatedUser->id);
        $this->assertDatabaseHas('membership_applications', [
            'organization_id' => $organization->id,
            'user_id' => $authenticatedUser->id,
        ]);
        $this->assertDatabaseMissing('membership_applications', [
            'organization_id' => $organization->id,
            'user_id' => $otherUser->id,
        ]);
    }
    public function test_step_one_is_saved_as_a_resumable_draft_and_sets_the_next_step(): void
    {
        Storage::fake(config('media-library.disk_name'));

        $organization = Organization::factory()->create([
            'slug' => 'apply-draft-' . uniqid(),
        ]);
        $this->actAsMemberOrganizationAdmin($organization);
        $user = auth()->user();
        $unit = Unit::query()->create([
            'organization_id' => $organization->id,
            'name' => 'North Unit',
            'code' => 'north-unit',
            'is_active' => true,
        ]);

        $response = $this->post($this->organizationUrl($organization, '/membership/apply/draft'), [
            'preferred_unit_id' => $unit->id,
            'first_name' => 'Taylor',
            'middle_name' => 'A',
            'last_name' => 'Applicant',
            'date_of_birth' => '1990-01-01',
            'blood_group' => 'O+',
            'primary_phone' => '9876543210',
            'primary_phone_country_iso2' => 'IN',
            'emergency_contacts' => [[
                'contact_name' => 'Emergency Person',
                'relationship' => 'Sibling',
                'phone' => '9876543211',
                'country_iso2' => 'IN',
            ]],
            'profile_photo' => UploadedFile::fake()->image('profile.png'),
        ], ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk()->assertJsonPath('saved', true)->assertJsonPath('current_step', 2);
        $application = MembershipApplication::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->firstOrFail();
        $this->assertSame(MembershipApplicationStatus::DRAFT, $application->status);
        $this->assertSame(2, $application->current_step);
        $this->assertSame('Taylor', data_get($application->data, 'draft.step_1.first_name'));
        $this->assertSame($unit->id, data_get($application->data, 'draft.step_1.preferred_unit_id'));
        $this->assertCount(1, $application->getMedia('profile-photo'));

        $configurationResponse = $this->getJson($this->organizationUrl($organization, '/membership/apply/configuration'))
            ->assertOk()
            ->assertJsonPath('draft.current_step', 2)
            ->assertJsonPath('draft.step_1.first_name', 'Taylor');

        $photoUrl = $configurationResponse->json('draft.profile_photo_url');
        $this->assertSame(route('organization.membership.apply.profile-photo'), $photoUrl);
        $photoResponse = $this->get($this->organizationUrl($organization, '/membership/apply/profile-photo'));
        $photoResponse->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $addressDraftResponse = $this->post($this->organizationUrl($organization, '/membership/apply/draft'), [
            'draft_step' => '2',
            'addresses' => [
                [
                    'address_type' => 'residential',
                    'address_line_1' => '10 Residential Street',
                    'address_line_2' => 'Near the park',
                    'locality' => 'North Ward',
                    'city' => 'Kochi',
                    'state' => 'Kerala',
                    'postal_code' => '682001',
                    'country' => 'India',
                    'is_primary' => '1',
                ],
                [
                    'address_type' => 'permanent',
                    'address_line_1' => '22 Permanent Road',
                    'address_line_2' => '',
                    'locality' => 'South Ward',
                    'city' => 'Thrissur',
                    'state' => 'Kerala',
                    'postal_code' => '680001',
                    'country' => 'India',
                    'is_primary' => '0',
                ],
            ],
            'home_contact' => '04841234567',
            'home_contact_country_iso2' => 'IN',
        ], ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest']);

        $addressDraftResponse->assertOk()
            ->assertJsonPath('saved', true)
            ->assertJsonPath('step_2.addresses.0.address_line_1', '10 Residential Street');

        $this->assertSame('10 Residential Street', data_get($application->fresh()->data, 'draft.step_2.addresses.0.address_line_1'));
        $this->getJson($this->organizationUrl($organization, '/membership/apply/configuration'))
            ->assertOk()
            ->assertJsonPath('draft.step_2.addresses.1.address_line_1', '22 Permanent Road')
            ->assertJsonPath('draft.step_2.home_contact', '04841234567');

        // Step 3 extension data must survive a fresh configuration request.
        $this->postJson($this->organizationUrl($organization, '/membership/apply/draft'), [
            'draft_step' => 3,
            'advance_step' => 1,
            'extension_data' => ['test_module' => ['sample_field' => 'Persisted step 3 value']],
        ])->assertOk()->assertJsonPath('step_data.extension_data.test_module.sample_field', 'Persisted step 3 value');

        $this->getJson($this->organizationUrl($organization, '/membership/apply/configuration'))
            ->assertOk()
            ->assertJsonPath('draft.current_step', 4)
            ->assertJsonPath('draft.step_3.extension_data.test_module.sample_field', 'Persisted step 3 value');

        // Step 4 proof files are stored privately and exposed only through the
        // authenticated draft-document route.
        $civilId = IdentificationType::create([
            'code' => 'civil_id',
            'name' => 'Civil ID',
        ]);
        app(OrganizationIdentificationService::class)->configure(
            organization: $organization,
            identificationType: $civilId,
            isEnabled: true,
            isRequired: true,
            requiresDocument: true,
            documentRequirements: ['front'],
        );

        $this->post($this->organizationUrl($organization, '/membership/apply/draft'), [
            'draft_step' => 4,
            'advance_step' => 1,
            'identifications' => ['civil_id' => 'CIV-123456'],
            'identification_documents' => [
                'civil_id' => ['front' => UploadedFile::fake()->create('civil-id-front.pdf', 100, 'application/pdf')],
            ],
        ], ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('saved', true);

        $documentConfiguration = $this->getJson($this->organizationUrl($organization, '/membership/apply/configuration'))
            ->assertOk()
            ->assertJsonPath('draft.step_4.identifications.civil_id', 'CIV-123456')
            ->assertJsonPath('draft.identification_documents.0.code', 'civil_id')
            ->assertJsonPath('draft.identification_documents.0.side', 'front');

        $this->get($documentConfiguration->json('draft.identification_documents.0.url'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        // Header directives are semantically unordered; middleware may normalize their order.
        $cacheDirectives = array_map(
            'trim',
            explode(',', (string) $photoResponse->headers->get('Cache-Control')),
        );
        $this->assertEqualsCanonicalizing(['private', 'no-store'], $cacheDirectives);

        $this->get($this->organizationUrl($organization, '/home'))
            ->assertOk()
            ->assertSee('Continue Application');
    }

    public function test_another_authenticated_user_cannot_retrieve_the_applicants_private_draft_photo(): void
    {
        Storage::fake(config('media-library.disk_name'));

        $organization = Organization::factory()->create([
            'slug' => 'apply-photo-private-' . uniqid(),
        ]);
        $this->actAsMemberOrganizationAdmin($organization);
        $owner = auth()->user();

        $draft = MembershipApplication::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'membership_type_id' => null,
            'status' => MembershipApplicationStatus::DRAFT,
            'current_step' => 2,
            'completed_steps' => [1],
            'data' => [],
        ]);
        $draft->addMedia(UploadedFile::fake()->image('private-profile.png'))
            ->toMediaCollection('profile-photo');

        $this->actingAs(User::factory()->create());

        $this->get($this->organizationUrl($organization, '/membership/apply/profile-photo'))
            ->assertNotFound();
    }

}
