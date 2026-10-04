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
use Modules\Member\Models\MembershipType;
use Modules\Member\Models\UserIdentification;
use Modules\Member\Tests\Concerns\ActsAsMemberOrganizationUser;
use Tests\TestCase;

class MembershipApplicationApiTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsMemberOrganizationUser;

    public function test_membership_application_configuration_returns_base_fields(): void
    {
        $organization = Organization::factory()->create();

        $this->actAsMemberOrganizationAdmin($organization);

        $response = $this->getJson(
            $this->organizationUrl(
                $organization,
                '/api/v1/membership-application'
            ),
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'personal.fields.first_name.type',
                'string'
            )
            ->assertJsonPath(
                'personal.fields.first_name.required',
                true
            )
            ->assertJsonPath(
                'personal.fields.date_of_birth.type',
                'date'
            )
            ->assertJsonPath(
                'address.fields.address_line_1.type',
                'string'
            )
            ->assertJsonPath(
                'address.fields.address_line_1.required',
                true
            )
            ->assertJsonPath(
                'extensions',
                []
            )
            ->assertJsonPath(
                'identifications',
                []
            )
            ->assertJsonPath(
                'documents',
                []
            );
    }

    public function test_membership_application_configuration_returns_dynamic_base_steps(): void
    {
        $organization = Organization::factory()->create();

        $this->actAsMemberOrganizationAdmin($organization);

        $response = $this->getJson(
            $this->organizationUrl(
                $organization,
                '/api/v1/membership-application'
            ),
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'steps.0.key',
                'personal'
            )
            ->assertJsonPath(
                'steps.0.type',
                'core'
            )
            ->assertJsonPath(
                'steps.1.key',
                'address'
            )
            ->assertJsonPath(
                'steps.1.type',
                'core'
            )
            ->assertJsonPath(
                'steps.2.key',
                'documents'
            )
            ->assertJsonPath(
                'steps.2.type',
                'documents'
            );
    }

    public function test_membership_application_configuration_returns_enabled_module_fields(): void
    {
        $organization = Organization::factory()->create();

        $this->actAsMemberOrganizationAdmin($organization);

        $moduleService = app(OrganizationModuleService::class);

        $moduleService->enable(
            $organization,
            'Saradhi'
        );

        $response = $this->getJson(
            $this->organizationUrl(
                $organization,
                '/api/v1/membership-application'
            ),
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'extensions.Saradhi.governorate.type',
                'string'
            )
            ->assertJsonPath(
                'extensions.Saradhi.governorate.label',
                'Governorate'
            )
            ->assertJsonPath(
                'extensions.Saradhi.governorate.required',
                false
            )
            ->assertJsonPath(
                'extensions.Saradhi.introducer_name.type',
                'string'
            );
    }

    public function test_membership_application_configuration_inserts_enabled_module_steps(): void
    {
        $organization = Organization::factory()->create();

        $this->actAsMemberOrganizationAdmin($organization);

        app(OrganizationModuleService::class)->enable(
            $organization,
            'Saradhi'
        );

        $response = $this->getJson(
            $this->organizationUrl(
                $organization,
                '/api/v1/membership-application'
            ),
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'steps.0.key',
                'personal'
            )
            ->assertJsonPath(
                'steps.1.key',
                'address'
            )
            ->assertJsonPath(
                'steps.2.key',
                'extension.Saradhi'
            )
            ->assertJsonPath(
                'steps.2.type',
                'extension'
            )
            ->assertJsonPath(
                'steps.2.module',
                'Saradhi'
            )
            ->assertJsonPath(
                'steps.2.label',
                'Saradhi Details'
            )
            ->assertJsonPath(
                'steps.3.key',
                'documents'
            );
    }

    public function test_membership_application_configuration_excludes_disabled_module_fields(): void
    {
        $organization = Organization::factory()->create();

        $this->actAsMemberOrganizationAdmin($organization);

        $moduleService = app(OrganizationModuleService::class);

        $moduleService->enable(
            $organization,
            'Member'
        );

        $response = $this->getJson(
            $this->organizationUrl(
                $organization,
                '/api/v1/membership-application'
            ),
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'extensions',
                []
            );
    }

    public function test_membership_application_configuration_does_not_create_steps_for_disabled_modules(): void
    {
        $organization = Organization::factory()->create();

        $this->actAsMemberOrganizationAdmin($organization);

        app(OrganizationModuleService::class)->enable(
            $organization,
            'Member'
        );

        $response = $this->getJson(
            $this->organizationUrl(
                $organization,
                '/api/v1/membership-application'
            ),
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'steps.0.key',
                'personal'
            )
            ->assertJsonPath(
                'steps.1.key',
                'address'
            )
            ->assertJsonPath(
                'steps.2.key',
                'documents'
            )
            ->assertJsonMissingPath(
                'steps.2.module'
            );
    }

    public function test_membership_application_configuration_returns_enabled_identification_fields(): void
    {
        $organization = Organization::factory()->create();

        $this->actAsMemberOrganizationAdmin($organization);

        $civilId = IdentificationType::create([
            'code' => 'civil_id',
            'name' => 'Civil ID',
        ]);

        $passport = IdentificationType::create([
            'code' => 'passport',
            'name' => 'Passport',
        ]);

        app(OrganizationIdentificationService::class)->configure(
            organization: $organization,
            identificationType: $civilId,
            isEnabled: true,
            isRequired: true,
            requiresDocument: true,
        );

        app(OrganizationIdentificationService::class)->configure(
            organization: $organization,
            identificationType: $passport,
            isEnabled: false,
            isRequired: false,
            requiresDocument: false,
        );

        $response = $this->getJson(
            $this->organizationUrl(
                $organization,
                '/api/v1/membership-application'
            ),
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'identifications.civil_id.type',
                'string'
            )
            ->assertJsonPath(
                'identifications.civil_id.label',
                'Civil ID'
            )
            ->assertJsonPath(
                'identifications.civil_id.required',
                true
            )
            ->assertJsonPath(
                'identifications.civil_id.requires_document',
                true
            )
            ->assertJsonMissingPath(
                'identifications.passport'
            );
    }

    public function test_membership_application_configuration_returns_document_requirements(): void
    {
        $organization = Organization::factory()->create();

        $this->actAsMemberOrganizationAdmin($organization);

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
            documentRequirements: [
                'front',
                'back',
            ],
        );

        $response = $this->getJson(
            $this->organizationUrl(
                $organization,
                '/api/v1/membership-application'
            ),
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'documents.civil_id.label',
                'Civil ID'
            )
            ->assertJsonPath(
                'documents.civil_id.required',
                true
            )
            ->assertJsonPath(
                'documents.civil_id.requirements.0',
                'front'
            )
            ->assertJsonPath(
                'documents.civil_id.requirements.1',
                'back'
            );
    }

    public function test_membership_application_submission_persists_personal_address_and_identification_data(): void
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
        ]);

        $civilId = IdentificationType::create([
            'code' => 'civil_id',
            'name' => 'Civil ID',
        ]);

        app(OrganizationIdentificationService::class)->configure(
            organization: $organization,
            identificationType: $civilId,
            isEnabled: true,
            isRequired: true,
        );

        $response = $this->postJson(
            $this->organizationUrl(
                $organization,
                '/api/v1/membership-application'
            ),
            [
                'user_id' => $applicant->id,
                'membership_type_id' => $membershipType->id,
                'starts_at' => '2026-10-04',
                'membership_number' => 'MEM-0100',
                'first_name' => 'John',
                'middle_name' => 'Michael',
                'last_name' => 'Doe',
                'date_of_birth' => '1990-01-15',
                'gender' => 'male',
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
                'identifications' => [
                    'civil_id' => 'CIV-123456',
                ],
            ],
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'organization_id',
                $organization->id
            )
            ->assertJsonPath(
                'user_id',
                $applicant->id
            )
            ->assertJsonPath(
                'membership_number',
                'MEM-0100'
            );

        $this->assertDatabaseHas('members', [
            'organization_id' => $organization->id,
            'user_id' => $applicant->id,
            'membership_number' => 'MEM-0100',
        ]);

        $this->assertDatabaseHas('user_details', [
            'user_id' => $applicant->id,
            'first_name' => 'John',
            'middle_name' => 'Michael',
            'last_name' => 'Doe',
            'gender' => 'male',
            'profession' => 'Software Engineer',
            'company' => 'Example Technologies',
        ]);

        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $applicant->id,
            'address_type' => 'home',
            'address_line_1' => '123 Main Street',
            'city' => 'Colombo',
            'country' => 'Sri Lanka',
        ]);

        $this->assertDatabaseHas('user_identifications', [
            'user_id' => $applicant->id,
            'identification_type_id' => $civilId->id,
            'identification_number' => 'CIV-123456',
        ]);
    }

    public function test_membership_application_submission_persists_identification_document(): void
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
        ]);

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
        );

        $file = UploadedFile::fake()->create(
            'civil-id.pdf',
            100,
            'application/pdf'
        );

        $response = $this->post(
            $this->organizationUrl(
                $organization,
                '/api/v1/membership-application'
            ),
            [
                'user_id' => $applicant->id,
                'membership_type_id' => $membershipType->id,
                'starts_at' => '2026-10-04',
                'membership_number' => 'MEM-0102',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'address' => [
                    'address_type' => 'home',
                    'address_line_1' => '123 Main Street',
                    'city' => 'Colombo',
                    'country' => 'Sri Lanka',
                ],
                'identifications' => [
                    'civil_id' => 'CIV-123456',
                ],
                'identification_documents' => [
                    'civil_id' => [
                        'document' => $file,
                    ],
                ],
            ],
        );

        $response->assertCreated();

        $identification = UserIdentification::query()
            ->where('user_id', $applicant->id)
            ->where('identification_type_id', $civilId->id)
            ->firstOrFail();

        $document = $identification->document('document');

        $this->assertNotNull($document);

        $this->assertSame(
            'document',
            $document->getCustomProperty('document_side')
        );

        $this->assertNotEmpty(
            $document->file_name
        );
    }

    public function test_membership_application_submission_persists_front_and_back_identification_documents(): void
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
        ]);

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
            documentRequirements: [
                'front',
                'back',
            ],
        );

        $frontFile = UploadedFile::fake()->create(
            'civil-id-front.jpg',
            100,
            'image/jpeg'
        );

        $backFile = UploadedFile::fake()->create(
            'civil-id-back.jpg',
            100,
            'image/jpeg'
        );

        $response = $this->post(
            $this->organizationUrl(
                $organization,
                '/api/v1/membership-application'
            ),
            [
                'user_id' => $applicant->id,
                'membership_type_id' => $membershipType->id,
                'starts_at' => '2026-10-04',
                'membership_number' => 'MEM-0103',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'address' => [
                    'address_type' => 'home',
                    'address_line_1' => '123 Main Street',
                    'city' => 'Colombo',
                    'country' => 'Sri Lanka',
                ],
                'identifications' => [
                    'civil_id' => 'CIV-123456',
                ],
                'identification_documents' => [
                    'civil_id' => [
                        'front' => $frontFile,
                        'back' => $backFile,
                    ],
                ],
            ],
        );

        $response->assertCreated();

        $identification = UserIdentification::query()
            ->where('user_id', $applicant->id)
            ->where('identification_type_id', $civilId->id)
            ->firstOrFail();

        $frontDocument = $identification->document('front');
        $backDocument = $identification->document('back');

        $this->assertNotNull($frontDocument);
        $this->assertNotNull($backDocument);

        $this->assertSame(
            'front',
            $frontDocument->getCustomProperty('document_side')
        );

        $this->assertSame(
            'back',
            $backDocument->getCustomProperty('document_side')
        );

        $this->assertNotSame(
            $frontDocument->id,
            $backDocument->id
        );

        $this->assertNotEmpty(
            $frontDocument->file_name
        );

        $this->assertNotEmpty(
            $backDocument->file_name
        );
    }

    public function test_membership_application_rejects_an_identification_type_that_is_not_enabled(): void
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
        ]);

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
            $this->organizationUrl(
                $organization,
                '/api/v1/membership-application'
            ),
            [
                'user_id' => $applicant->id,
                'membership_type_id' => $membershipType->id,
                'starts_at' => '2026-10-04',
                'membership_number' => 'MEM-0101',
                'first_name' => 'Jane',
                'address' => [
                    'address_type' => 'home',
                    'address_line_1' => '123 Main Street',
                ],
                'identifications' => [
                    'passport' => 'P1234567',
                ],
            ],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['identifications']);

        $this->assertDatabaseMissing('members', [
            'organization_id' => $organization->id,
            'membership_number' => 'MEM-0101',
        ]);
    }
}