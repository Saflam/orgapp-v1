<?php

namespace Modules\Member\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Modules\Core\Models\IdentificationType;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationIdentificationService;
use Modules\Member\Http\Requests\StoreMembershipApplicationRequest;
use Modules\Member\Models\MembershipType;
use Tests\TestCase;

class StoreMembershipApplicationDocumentValidationTest extends TestCase
{
    use RefreshDatabase;

    private function makeRequest(
        Organization $organization,
    ): StoreMembershipApplicationRequest {
        $uri = '/api/v1/organizations/'
            . $organization->id
            . '/membership-application';

        $request = StoreMembershipApplicationRequest::create(
            $uri,
            'POST',
        );

        $route = new Route(
            'POST',
            '/api/v1/organizations/{organization}/membership-application',
            [],
        );

        $route->bind($request);

        $route->setParameter(
            'organization',
            $organization,
        );

        $request->setRouteResolver(
            fn () => $route
        );

        return $request;
    }

    private function validApplicationData(
        Organization $organization,
    ): array {
        $user = User::factory()->create();

        $membershipType = MembershipType::create([
            'organization_id' => $organization->id,
            'code' => 'regular',
            'name' => 'Regular Membership',
        ]);

        return [
            'user_id' => $user->id,
            'membership_type_id' => $membershipType->id,
            'blood_group' => 'O+',
            'first_name' => 'Test',
            'address' => [
                'address_type' => 'home',
                'address_line_1' => '123 Main Street',
            ],
        ];
    }

    private function identificationType(
        string $code = 'civil_id',
        string $name = 'Civil ID',
    ): IdentificationType {
        return IdentificationType::create([
            'code' => $code,
            'name' => $name,
        ]);
    }

    private function configureIdentification(
        Organization $organization,
        IdentificationType $identificationType,
        bool $isRequired = false,
        bool $requiresDocument = false,
        array $documentRequirements = [],
    ): void {
        app(OrganizationIdentificationService::class)->configure(
            organization: $organization,
            identificationType: $identificationType,
            isEnabled: true,
            isRequired: $isRequired,
            requiresDocument: $requiresDocument,
            documentRequirements: $documentRequirements,
        );
    }

    public function test_document_is_required_when_document_requirement_is_configured(): void
    {
        $organization = Organization::factory()->create();

        $civilId = $this->identificationType();

        $this->configureIdentification(
            organization: $organization,
            identificationType: $civilId,
            isRequired: true,
            requiresDocument: true,
            documentRequirements: ['document'],
        );

        $request = $this->makeRequest($organization);

        $data = $this->validApplicationData($organization);

        $data['identifications'] = [
            'civil_id' => 'CIV-123456',
        ];

        $validator = Validator::make(
            $data,
            $request->rules(),
        );

        $this->assertTrue($validator->fails());

        $this->assertArrayHasKey(
            'identification_documents.civil_id.document',
            $validator->errors()->toArray(),
        );
    }

    public function test_single_document_is_accepted_when_document_is_required(): void
    {
        $organization = Organization::factory()->create();

        $civilId = $this->identificationType();

        $this->configureIdentification(
            organization: $organization,
            identificationType: $civilId,
            isRequired: true,
            requiresDocument: true,
            documentRequirements: ['document'],
        );

        $request = $this->makeRequest($organization);

        $data = $this->validApplicationData($organization);

        $data['identifications'] = [
            'civil_id' => 'CIV-123456',
        ];

        $data['identification_documents'] = [
            'civil_id' => [
                'document' => UploadedFile::fake()->create(
                    'civil-id.pdf',
                    500,
                    'application/pdf',
                ),
            ],
        ];

        $validator = Validator::make(
            $data,
            $request->rules(),
        );

        $this->assertFalse($validator->fails());
    }

    public function test_front_document_is_required_when_front_is_configured(): void
    {
        $organization = Organization::factory()->create();

        $civilId = $this->identificationType();

        $this->configureIdentification(
            organization: $organization,
            identificationType: $civilId,
            isRequired: true,
            requiresDocument: true,
            documentRequirements: ['front'],
        );

        $request = $this->makeRequest($organization);

        $data = $this->validApplicationData($organization);

        $data['identifications'] = [
            'civil_id' => 'CIV-123456',
        ];

        $validator = Validator::make(
            $data,
            $request->rules(),
        );

        $this->assertTrue($validator->fails());

        $this->assertArrayHasKey(
            'identification_documents.civil_id.front',
            $validator->errors()->toArray(),
        );
    }

    public function test_front_document_is_accepted_when_front_is_configured(): void
    {
        $organization = Organization::factory()->create();

        $civilId = $this->identificationType();

        $this->configureIdentification(
            organization: $organization,
            identificationType: $civilId,
            isRequired: true,
            requiresDocument: true,
            documentRequirements: ['front'],
        );

        $request = $this->makeRequest($organization);

        $data = $this->validApplicationData($organization);

        $data['identifications'] = [
            'civil_id' => 'CIV-123456',
        ];

        $data['identification_documents'] = [
            'civil_id' => [
                'front' => UploadedFile::fake()->create(
                    'civil-id-front.jpg',
                    500,
                    'image/jpeg',
                ),
            ],
        ];

        $validator = Validator::make(
            $data,
            $request->rules(),
        );

        $this->assertFalse($validator->fails());
    }

    public function test_both_front_and_back_documents_are_required(): void
    {
        $organization = Organization::factory()->create();

        $civilId = $this->identificationType();

        $this->configureIdentification(
            organization: $organization,
            identificationType: $civilId,
            isRequired: true,
            requiresDocument: true,
            documentRequirements: ['front', 'back'],
        );

        $request = $this->makeRequest($organization);

        $data = $this->validApplicationData($organization);

        $data['identifications'] = [
            'civil_id' => 'CIV-123456',
        ];

        $data['identification_documents'] = [
            'civil_id' => [
                'front' => UploadedFile::fake()->create(
                    'civil-id-front.jpg',
                    500,
                    'image/jpeg',
                ),
            ],
        ];

        $validator = Validator::make(
            $data,
            $request->rules(),
        );

        $this->assertTrue($validator->fails());

        $this->assertArrayHasKey(
            'identification_documents.civil_id.back',
            $validator->errors()->toArray(),
        );
    }

    public function test_front_and_back_documents_are_accepted_when_both_are_configured(): void
    {
        $organization = Organization::factory()->create();

        $civilId = $this->identificationType();

        $this->configureIdentification(
            organization: $organization,
            identificationType: $civilId,
            isRequired: true,
            requiresDocument: true,
            documentRequirements: ['front', 'back'],
        );

        $request = $this->makeRequest($organization);

        $data = $this->validApplicationData($organization);

        $data['identifications'] = [
            'civil_id' => 'CIV-123456',
        ];

        $data['identification_documents'] = [
            'civil_id' => [
                'front' => UploadedFile::fake()->create(
                    'civil-id-front.jpg',
                    500,
                    'image/jpeg',
                ),
                'back' => UploadedFile::fake()->create(
                    'civil-id-back.jpg',
                    500,
                    'image/jpeg',
                ),
            ],
        ];

        $validator = Validator::make(
            $data,
            $request->rules(),
        );

        $this->assertFalse($validator->fails());
    }

    public function test_missing_required_front_document_is_rejected(): void
    {
        $organization = Organization::factory()->create();

        $civilId = $this->identificationType();

        $this->configureIdentification(
            organization: $organization,
            identificationType: $civilId,
            requiresDocument: true,
            documentRequirements: ['front', 'back'],
        );

        $request = $this->makeRequest($organization);

        $data = $this->validApplicationData($organization);

        $data['identifications'] = [
            'civil_id' => 'CIV-123456',
        ];

        $data['identification_documents'] = [
            'civil_id' => [
                'back' => UploadedFile::fake()->create(
                    'civil-id-back.jpg',
                    500,
                    'image/jpeg',
                ),
            ],
        ];

        $validator = Validator::make(
            $data,
            $request->rules(),
        );

        $this->assertTrue($validator->fails());

        $this->assertArrayHasKey(
            'identification_documents.civil_id.front',
            $validator->errors()->toArray(),
        );
    }

    public function test_wrong_document_side_is_rejected(): void
    {
        $organization = Organization::factory()->create();

        $civilId = $this->identificationType();

        $this->configureIdentification(
            organization: $organization,
            identificationType: $civilId,
            requiresDocument: true,
            documentRequirements: ['front'],
        );

        $request = $this->makeRequest($organization);

        $data = $this->validApplicationData($organization);

        $data['identifications'] = [
            'civil_id' => 'CIV-123456',
        ];

        $data['identification_documents'] = [
            'civil_id' => [
                'back' => UploadedFile::fake()->create(
                    'civil-id-back.jpg',
                    500,
                    'image/jpeg',
                ),
            ],
        ];

        $validator = Validator::make(
            $data,
            $request->rules(),
        );

        $this->assertTrue($validator->fails());

        $errors = $validator->errors()->toArray();

        $this->assertArrayHasKey(
            'identification_documents',
            $errors,
        );
    }

    public function test_document_for_identification_without_document_requirement_is_rejected(): void
    {
        $organization = Organization::factory()->create();

        $passport = $this->identificationType(
            code: 'passport',
            name: 'Passport',
        );

        $this->configureIdentification(
            organization: $organization,
            identificationType: $passport,
            isRequired: false,
            requiresDocument: false,
            documentRequirements: [],
        );

        $request = $this->makeRequest($organization);

        $data = $this->validApplicationData($organization);

        $data['identifications'] = [
            'passport' => 'P-123456',
        ];

        $data['identification_documents'] = [
            'passport' => [
                'document' => UploadedFile::fake()->create(
                    'passport.pdf',
                    500,
                    'application/pdf',
                ),
            ],
        ];

        $validator = Validator::make(
            $data,
            $request->rules(),
        );

        $this->assertTrue($validator->fails());

        $this->assertArrayHasKey(
            'identification_documents',
            $validator->errors()->toArray(),
        );
    }

    public function test_document_for_disabled_identification_type_is_rejected(): void
    {
        $organization = Organization::factory()->create();

        $voterId = $this->identificationType(
            code: 'voter_id',
            name: 'Voter ID',
        );

        app(OrganizationIdentificationService::class)->configure(
            organization: $organization,
            identificationType: $voterId,
            isEnabled: false,
            isRequired: false,
            requiresDocument: false,
            documentRequirements: [],
        );

        $request = $this->makeRequest($organization);

        $data = $this->validApplicationData($organization);

        $data['identification_documents'] = [
            'voter_id' => [
                'document' => UploadedFile::fake()->create(
                    'voter-id.pdf',
                    500,
                    'application/pdf',
                ),
            ],
        ];

        $validator = Validator::make(
            $data,
            $request->rules(),
        );

        $this->assertTrue($validator->fails());

        $this->assertArrayHasKey(
            'identification_documents',
            $validator->errors()->toArray(),
        );
    }

    public function test_document_for_unknown_identification_type_is_rejected(): void
    {
        $organization = Organization::factory()->create();

        $request = $this->makeRequest($organization);

        $data = $this->validApplicationData($organization);

        $data['identification_documents'] = [
            'unknown_id' => [
                'document' => UploadedFile::fake()->create(
                    'unknown.pdf',
                    500,
                    'application/pdf',
                ),
            ],
        ];

        $validator = Validator::make(
            $data,
            $request->rules(),
        );

        $this->assertTrue($validator->fails());

        $this->assertArrayHasKey(
            'identification_documents',
            $validator->errors()->toArray(),
        );
    }

    public function test_unsupported_document_type_is_rejected(): void
    {
        $organization = Organization::factory()->create();

        $civilId = $this->identificationType();

        $this->configureIdentification(
            organization: $organization,
            identificationType: $civilId,
            requiresDocument: true,
            documentRequirements: ['document'],
        );

        $request = $this->makeRequest($organization);

        $data = $this->validApplicationData($organization);

        $data['identifications'] = [
            'civil_id' => 'CIV-123456',
        ];

        $data['identification_documents'] = [
            'civil_id' => [
                'document' => UploadedFile::fake()->create(
                    'civil-id.txt',
                    500,
                    'text/plain',
                ),
            ],
        ];

        $validator = Validator::make(
            $data,
            $request->rules(),
        );

        $this->assertTrue($validator->fails());

        $this->assertArrayHasKey(
            'identification_documents.civil_id.document',
            $validator->errors()->toArray(),
        );
    }

    public function test_document_larger_than_10_mb_is_rejected(): void
    {
        $organization = Organization::factory()->create();

        $civilId = $this->identificationType();

        $this->configureIdentification(
            organization: $organization,
            identificationType: $civilId,
            requiresDocument: true,
            documentRequirements: ['document'],
        );

        $request = $this->makeRequest($organization);

        $data = $this->validApplicationData($organization);

        $data['identifications'] = [
            'civil_id' => 'CIV-123456',
        ];

        $data['identification_documents'] = [
            'civil_id' => [
                'document' => UploadedFile::fake()->create(
                    'large-civil-id.pdf',
                    10241,
                    'application/pdf',
                ),
            ],
        ];

        $validator = Validator::make(
            $data,
            $request->rules(),
        );

        $this->assertTrue($validator->fails());

        $this->assertArrayHasKey(
            'identification_documents.civil_id.document',
            $validator->errors()->toArray(),
        );
    }
}