<?php

namespace Modules\Member\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Modules\Core\Models\IdentificationType;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationIdentificationService;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Member\Http\Requests\StoreMembershipApplicationRequest;
use Modules\Member\Models\MembershipType;
use Tests\TestCase;

class StoreMembershipApplicationRequestTest extends TestCase
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
            'starts_at' => '2026-09-23',
            'membership_number' => 'M-0010',
            'first_name' => 'Test',
            'address' => [
                'address_type' => 'home',
                'address_line_1' => '123 Main Street',
            ],
        ];
    }

    public function test_base_membership_application_rules_are_defined(): void
    {
        $organization = Organization::factory()->create();

        $request = $this->makeRequest($organization);

        $rules = $request->rules();

        $this->assertArrayHasKey('user_id', $rules);
        $this->assertArrayHasKey('membership_type_id', $rules);
        $this->assertArrayHasKey('starts_at', $rules);
        $this->assertArrayHasKey('membership_number', $rules);
        $this->assertArrayHasKey('extension_data', $rules);
        $this->assertArrayHasKey('identifications', $rules);
    }

    public function test_personal_detail_rules_are_defined(): void
    {
        $organization = Organization::factory()->create();

        $request = $this->makeRequest($organization);

        $rules = $request->rules();

        $this->assertSame(
            [
                'required',
                'string',
                'max:255',
            ],
            $rules['first_name']
        );

        $this->assertSame(
            [
                'nullable',
                'string',
                'max:255',
            ],
            $rules['middle_name']
        );

        $this->assertSame(
            [
                'nullable',
                'string',
                'max:255',
            ],
            $rules['last_name']
        );

        $this->assertSame(
            [
                'nullable',
                'date',
            ],
            $rules['date_of_birth']
        );

        $this->assertSame(
            [
                'nullable',
                'string',
                'max:50',
            ],
            $rules['gender']
        );

        $this->assertArrayHasKey(
            'whatsapp',
            $rules
        );

        $this->assertArrayHasKey(
            'whatsapp_calling_code',
            $rules
        );

        $this->assertArrayHasKey(
            'home_contact',
            $rules
        );

        $this->assertArrayHasKey(
            'profession',
            $rules
        );

        $this->assertArrayHasKey(
            'company',
            $rules
        );
    }

    public function test_first_name_is_required(): void
    {
        $organization = Organization::factory()->create();

        $request = $this->makeRequest($organization);

        $validator = Validator::make(
            [
                'user_id' => 1,
                'membership_type_id' => 1,
                'starts_at' => '2026-09-23',
                'membership_number' => 'M-0010',
                'address' => [
                    'address_type' => 'home',
                    'address_line_1' => '123 Main Street',
                ],
            ],
            $request->rules()
        );

        $this->assertTrue($validator->fails());

        $this->assertArrayHasKey(
            'first_name',
            $validator->errors()->toArray()
        );
    }

    public function test_saradhi_rules_are_added_when_saradhi_is_enabled(): void
    {
        $organization = Organization::factory()->create();

        $moduleService = app(
            OrganizationModuleService::class
        );

        $moduleService->enable(
            $organization,
            'Member',
        );

        $moduleService->enable(
            $organization,
            'Saradhi',
        );

        $request = $this->makeRequest($organization);

        $rules = $request->rules();

        $this->assertArrayHasKey(
            'extension_data.Saradhi.governorate',
            $rules,
        );

        $this->assertArrayHasKey(
            'extension_data.Saradhi.sndp_branch',
            $rules,
        );

        $this->assertArrayHasKey(
            'extension_data.Saradhi.introducer_unit_id',
            $rules,
        );
    }

    public function test_saradhi_rules_are_not_added_when_saradhi_is_disabled(): void
    {
        $organization = Organization::factory()->create();

        $moduleService = app(
            OrganizationModuleService::class
        );

        $moduleService->enable(
            $organization,
            'Member',
        );

        $request = $this->makeRequest($organization);

        $rules = $request->rules();

        $this->assertArrayNotHasKey(
            'extension_data.Saradhi.governorate',
            $rules,
        );
    }

    public function test_address_rules_are_included(): void
    {
        $organization = Organization::factory()->create();

        $request = $this->makeRequest($organization);

        $rules = $request->rules();

        $this->assertSame(
            [
                'required',
                'array',
            ],
            $rules['address']
        );

        $this->assertSame(
            [
                'required',
                'string',
                'max:50',
            ],
            $rules['address.address_type']
        );

        $this->assertSame(
            [
                'required',
                'string',
                'max:255',
            ],
            $rules['address.address_line_1']
        );

        $this->assertSame(
            [
                'nullable',
                'string',
                'max:255',
            ],
            $rules['address.city']
        );
    }

    public function test_address_is_required(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create();

        $request = $this->makeRequest($organization);

        $validator = Validator::make(
            [
                'user_id' => $user->id,
                'membership_type_id' => 1,
                'starts_at' => '2026-09-23',
                'membership_number' => 'M-0010',
            ],
            $request->rules()
        );

        $this->assertTrue($validator->fails());

        $this->assertArrayHasKey(
            'address',
            $validator->errors()->toArray()
        );
    }

    public function test_required_identification_type_is_added_to_rules(): void
    {
        $organization = Organization::factory()->create();

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

        $request = $this->makeRequest($organization);

        $rules = $request->rules();

        $this->assertSame(
            [
                'required',
                'string',
                'max:255',
            ],
            $rules['identifications.civil_id']
        );
    }

    public function test_optional_identification_type_is_added_to_rules(): void
    {
        $organization = Organization::factory()->create();

        $passport = IdentificationType::create([
            'code' => 'passport',
            'name' => 'Passport',
        ]);

        app(OrganizationIdentificationService::class)->configure(
            organization: $organization,
            identificationType: $passport,
            isEnabled: true,
            isRequired: false,
        );

        $request = $this->makeRequest($organization);

        $rules = $request->rules();

        $this->assertSame(
            [
                'nullable',
                'string',
                'max:255',
            ],
            $rules['identifications.passport']
        );
    }

    public function test_required_identification_must_be_submitted(): void
    {
        $organization = Organization::factory()->create();

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

        $request = $this->makeRequest($organization);

        $validator = Validator::make(
            $this->validApplicationData($organization),
            $request->rules()
        );

        $this->assertTrue($validator->fails());

        $this->assertArrayHasKey(
            'identifications.civil_id',
            $validator->errors()->toArray()
        );
    }

    public function test_optional_identification_may_be_omitted(): void
    {
        $organization = Organization::factory()->create();

        $passport = IdentificationType::create([
            'code' => 'passport',
            'name' => 'Passport',
        ]);

        app(OrganizationIdentificationService::class)->configure(
            organization: $organization,
            identificationType: $passport,
            isEnabled: true,
            isRequired: false,
        );

        $request = $this->makeRequest($organization);

        $validator = Validator::make(
            $this->validApplicationData($organization),
            $request->rules()
        );

        $this->assertFalse($validator->fails());
    }

    public function test_disabled_identification_type_is_rejected(): void
    {
        $organization = Organization::factory()->create();

        $voterId = IdentificationType::create([
            'code' => 'voter_id',
            'name' => 'Voter ID',
        ]);

        app(OrganizationIdentificationService::class)->configure(
            organization: $organization,
            identificationType: $voterId,
            isEnabled: false,
            isRequired: false,
        );

        $request = $this->makeRequest($organization);

        $data = $this->validApplicationData($organization);

        $data['identifications'] = [
            'voter_id' => 'VOTER-123',
        ];

        $validator = Validator::make(
            $data,
            $request->rules()
        );

        $this->assertTrue($validator->fails());

        $errors = $validator->errors()->toArray();

        $this->assertArrayHasKey(
            'identifications',
            $errors
        );
    }

    public function test_unknown_identification_type_is_rejected(): void
    {
        $organization = Organization::factory()->create();

        $request = $this->makeRequest($organization);

        $data = $this->validApplicationData($organization);

        $data['identifications'] = [
            'unknown_id' => 'UNKNOWN-123',
        ];

        $validator = Validator::make(
            $data,
            $request->rules()
        );

        $this->assertTrue($validator->fails());

        $errors = $validator->errors()->toArray();

        $this->assertArrayHasKey(
            'identifications',
            $errors
        );
    }

    public function test_enabled_identification_type_is_accepted(): void
    {
        $organization = Organization::factory()->create();

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

        $request = $this->makeRequest($organization);

        $data = $this->validApplicationData($organization);

        $data['identifications'] = [
            'civil_id' => 'CIV-123456',
        ];

        $validator = Validator::make(
            $data,
            $request->rules()
        );

        $this->assertFalse($validator->fails());
    }
}