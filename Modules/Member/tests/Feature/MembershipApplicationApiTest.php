<?php

namespace Modules\Member\Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Modules\Core\Models\IdentificationType;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationIdentificationService;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Member\Enums\MembershipApplicationStatus;
use Modules\Member\Models\MembershipApplication;
use Modules\Member\Models\MembershipType;
use Modules\Member\Models\UserIdentification;
use Modules\Member\Tests\Concerns\ActsAsMemberOrganizationUser;
use Tests\TestCase;

class MembershipApplicationApiTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsMemberOrganizationUser;

    public function test_configuration_returns_base_fields_and_steps(): void
    {
        $organization = Organization::factory()->create();
        $this->actAsMemberOrganizationAdmin($organization);

        $response = $this->getJson(
            $this->organizationUrl($organization, '/api/v1/membership-application')
        );

        $response
            ->assertOk()
            ->assertJsonPath('personal.fields.first_name.type', 'string')
            ->assertJsonPath('personal.fields.first_name.required', true)
            ->assertJsonPath('personal.fields.blood_group.required', true)
            ->assertJsonPath('address.fields.address_line_1.required', true)
            ->assertJsonPath('steps.0.key', 'personal')
            ->assertJsonPath('steps.1.key', 'address')
            ->assertJsonPath('steps.2.key', 'documents')
            ->assertJsonPath('extensions', [])
            ->assertJsonPath('identifications', [])
            ->assertJsonPath('documents', []);
    }

    public function test_configuration_includes_enabled_module_fields_and_steps(): void
    {
        $organization = Organization::factory()->create();
        $this->actAsMemberOrganizationAdmin($organization);

        app(OrganizationModuleService::class)->enable($organization, 'Saradhi');

        $response = $this->getJson(
            $this->organizationUrl($organization, '/api/v1/membership-application')
        );

        $response
            ->assertOk()
            ->assertJsonPath('extensions.Saradhi.governorate.type', 'string')
            ->assertJsonPath('steps.2.key', 'extension.Saradhi')
            ->assertJsonPath('steps.3.key', 'documents');
    }

    public function test_submission_creates_only_a_submitted_application(): void
    {
        [$organization, $applicant, $membershipType] = $this->applicationContext();

        $response = $this->postJson(
            $this->organizationUrl($organization, '/api/v1/membership-application'),
            $this->validSubmission($applicant, $membershipType),
        );

        $response
            ->assertCreated()
            ->assertJsonPath('organization_id', $organization->id)
            ->assertJsonPath('user_id', $applicant->id)
            ->assertJsonPath('membership_type_id', $membershipType->id)
            ->assertJsonPath('status', 'submitted');

        $this->assertDatabaseHas('membership_applications', [
            'organization_id' => $organization->id,
            'user_id' => $applicant->id,
            'status' => MembershipApplicationStatus::SUBMITTED->value,
        ]);

        $this->assertDatabaseMissing('members', [
            'organization_id' => $organization->id,
            'user_id' => $applicant->id,
        ]);

        $this->assertDatabaseMissing('memberships', [
            'member_id' => 1,
        ]);

        $this->assertDatabaseMissing('user_details', [
            'user_id' => $applicant->id,
        ]);

        $this->assertDatabaseMissing('user_addresses', [
            'user_id' => $applicant->id,
        ]);
    }

    public function test_submission_stores_identification_and_application_documents_without_creating_user_identification(): void
    {
        [$organization, $applicant, $membershipType] = $this->applicationContext();

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
            documentRequirements: ['front', 'back'],
        );

        $response = $this->postJson(
            $this->organizationUrl($organization, '/api/v1/membership-application'),
            array_merge(
                $this->validSubmission($applicant, $membershipType),
                [
                    'identifications' => ['civil_id' => 'CIV-123456'],
                    'identification_documents' => [
                        'civil_id' => [
                            'front' => UploadedFile::fake()->create('front.pdf', 100, 'application/pdf'),
                            'back' => UploadedFile::fake()->create('back.pdf', 100, 'application/pdf'),
                        ],
                    ],
                ],
            ),
        );

        $response->assertCreated();

        $application = MembershipApplication::query()->firstOrFail();

        $this->assertSame(
            'CIV-123456',
            data_get($application->data, 'identifications.civil_id.identification_number')
        );

        $this->assertCount(2, $application->getMedia('application-documents'));

        $this->assertDatabaseMissing('user_identifications', [
            'user_id' => $applicant->id,
            'identification_type_id' => $civilId->id,
        ]);
    }

    public function test_rejecting_at_verification_can_allow_reapplication(): void
    {
        [$organization, $applicant, $membershipType] = $this->applicationContext();

        $this->postJson(
            $this->organizationUrl($organization, '/api/v1/membership-application'),
            $this->validSubmission($applicant, $membershipType),
        )->assertCreated();

        $application = MembershipApplication::query()->firstOrFail();

        $response = $this->postJson(
            $this->organizationUrl(
                $organization,
                "/api/v1/membership-applications/{$application->id}/verify"
            ),
            [
                'decision' => 'reject',
                'notes' => 'Please correct the address.',
                'allow_reapply' => true,
            ],
        );

        $response
            ->assertOk()
            ->assertJsonPath('status', 'rejected');

        $application->refresh();

        $this->assertTrue($application->latestRejectionAllowsReapply());

        $response = $this->postJson(
            $this->organizationUrl($organization, '/api/v1/membership-application'),
            $this->validSubmission($applicant, $membershipType, 'Updated'),
        );

        $response
            ->assertCreated()
            ->assertJsonPath('status', 'submitted');

        $this->assertDatabaseCount('membership_applications', 1);
    }

    public function test_workflow_reaches_confirmation_and_creates_active_membership(): void
    {
        [$organization, $applicant, $membershipType] = $this->applicationContext();

        $this->postJson(
            $this->organizationUrl($organization, '/api/v1/membership-application'),
            $this->validSubmission($applicant, $membershipType),
        )->assertCreated();

        $application = MembershipApplication::query()->firstOrFail();

        $this->postJson(
            $this->organizationUrl($organization, "/api/v1/membership-applications/{$application->id}/verify"),
            ['decision' => 'accept'],
        )->assertOk()->assertJsonPath('status', 'verified');

        $this->postJson(
            $this->organizationUrl($organization, "/api/v1/membership-applications/{$application->id}/review"),
            ['decision' => 'accept'],
        )->assertOk()->assertJsonPath('status', 'reviewed');

        $this->postJson(
            $this->organizationUrl($organization, "/api/v1/membership-applications/{$application->id}/approve"),
            ['decision' => 'accept'],
        )->assertOk()->assertJsonPath('status', 'approved');

        $this->postJson(
            $this->organizationUrl($organization, "/api/v1/membership-applications/{$application->id}/payment"),
        )->assertOk()->assertJsonPath('status', 'payment');

        $response = $this->postJson(
            $this->organizationUrl($organization, "/api/v1/membership-applications/{$application->id}/confirm"),
            [
                'membership_number' => 'MEM-0100',
                'starts_at' => '2026-10-05',
            ],
        );

        $response
            ->assertOk()
            ->assertJsonPath('status', 'confirmed');

        $this->assertDatabaseHas('members', [
            'organization_id' => $organization->id,
            'user_id' => $applicant->id,
            'membership_number' => 'MEM-0100',
        ]);

        $member = \Modules\Member\Models\Member::query()->firstOrFail();

        $this->assertDatabaseHas('memberships', [
            'member_id' => $member->id,
            'membership_type_id' => $membershipType->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('user_details', [
            'user_id' => $applicant->id,
            'first_name' => 'John',
            'blood_group' => 'O+',
        ]);

        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $applicant->id,
            'address_line_1' => '123 Main Street',
        ]);
    }

    public function test_application_cannot_skip_workflow_states(): void
    {
        [$organization, $applicant, $membershipType] = $this->applicationContext();

        $this->postJson(
            $this->organizationUrl($organization, '/api/v1/membership-application'),
            $this->validSubmission($applicant, $membershipType),
        )->assertCreated();

        $application = MembershipApplication::query()->firstOrFail();

        $this->postJson(
            $this->organizationUrl($organization, "/api/v1/membership-applications/{$application->id}/approve"),
            ['decision' => 'accept'],
        )->assertUnprocessable();

        $this->assertDatabaseMissing('members', [
            'organization_id' => $organization->id,
            'user_id' => $applicant->id,
        ]);
    }

    public function test_disabled_identification_type_is_rejected(): void
    {
        [$organization, $applicant, $membershipType] = $this->applicationContext();

        $passport = IdentificationType::create([
            'code' => 'passport',
            'name' => 'Passport',
        ]);

        app(OrganizationIdentificationService::class)->configure(
            organization: $organization,
            identificationType: $passport,
            isEnabled: false,
        );

        $response = $this->postJson(
            $this->organizationUrl($organization, '/api/v1/membership-application'),
            array_merge(
                $this->validSubmission($applicant, $membershipType),
                ['identifications' => ['passport' => 'P1234567']],
            ),
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['identifications']);

        $this->assertDatabaseMissing('members', [
            'organization_id' => $organization->id,
            'user_id' => $applicant->id,
        ]);
    }

    private function applicationContext(): array
    {
        $organization = Organization::factory()->create();
        $this->actAsMemberOrganizationAdmin($organization);

        $applicant = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $applicant->id,
            'status' => 'active',
        ]);

        $membershipType = MembershipType::create([
            'organization_id' => $organization->id,
            'name' => 'Regular Membership',
            'code' => 'REGULAR',
            'is_active' => true,
        ]);

        return [$organization, $applicant, $membershipType];
    }

    private function validSubmission(
        User $applicant,
        MembershipType $membershipType,
        string $firstName = 'John',
    ): array {
        return [
            'user_id' => $applicant->id,
            'membership_type_id' => $membershipType->id,
            'first_name' => $firstName,
            'middle_name' => 'Michael',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-15',
            'gender' => 'male',
            'blood_group' => 'O+',
            'whatsapp' => '771234567',
            'whatsapp_calling_code' => '+94',
            'home_contact' => '0112345678',
            'profession' => 'Software Engineer',
            'company' => 'Example Technologies',
            'address' => [
                'address_type' => 'home',
                'address_line_1' => '123 Main Street',
                'city' => 'Colombo',
                'country' => 'Sri Lanka',
                'is_primary' => true,
            ],
        ];
    }
}
