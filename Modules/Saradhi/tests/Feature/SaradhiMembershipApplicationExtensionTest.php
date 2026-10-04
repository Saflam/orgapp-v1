<?php

namespace Modules\Saradhi\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Member\Models\Member;
use Modules\Member\Models\MembershipType;
use Modules\Member\Services\MembershipApplicationExtensionRegistry;
use Modules\Member\Services\MemberRegistrationService;
use Modules\Saradhi\Models\SaradhiMemberProfile;
use Tests\TestCase;

class SaradhiMembershipApplicationExtensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_saradhi_extension_fields_are_available_when_saradhi_is_enabled(): void
    {
        $organization = Organization::factory()->create();

        $organizationModuleService = app(
            \Modules\Core\Services\OrganizationModuleService::class
        );

        $organizationModuleService->enable(
            $organization,
            'Member'
        );

        $organizationModuleService->enable(
            $organization,
            'Saradhi'
        );

        $registry = app(
            MembershipApplicationExtensionRegistry::class
        );

        $fields = $registry->fields($organization);

        $this->assertArrayHasKey(
            'Saradhi',
            $fields
        );

        $this->assertArrayHasKey(
            'governorate',
            $fields['Saradhi']
        );

        $this->assertArrayHasKey(
            'sndp_branch',
            $fields['Saradhi']
        );

        $this->assertArrayHasKey(
            'introducer_name',
            $fields['Saradhi']
        );
    }

    public function test_saradhi_extension_is_only_enabled_when_saradhi_module_is_enabled(): void
    {
        $organization = Organization::factory()->create();

        $registry = app(
            MembershipApplicationExtensionRegistry::class
        );

        $this->assertArrayNotHasKey(
            'Saradhi',
            $registry->enabledFor($organization)
        );

        $organizationModuleService = app(
            \Modules\Core\Services\OrganizationModuleService::class
        );

        $organizationModuleService->enable(
            $organization,
            'Member'
        );

        $this->assertArrayNotHasKey(
            'Saradhi',
            $registry->enabledFor($organization)
        );

        $organizationModuleService->enable(
            $organization,
            'Saradhi'
        );

        $this->assertArrayHasKey(
            'Saradhi',
            $registry->enabledFor($organization)
        );
    }

    public function test_saradhi_application_data_is_persisted(): void
    {
        $organization = Organization::factory()->create();

        $organizationModuleService = app(
            \Modules\Core\Services\OrganizationModuleService::class
        );

        $organizationModuleService->enable(
            $organization,
            'Member'
        );

        $organizationModuleService->enable(
            $organization,
            'Saradhi'
        );

        $user = \App\Models\User::factory()->create();

        \App\Models\OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $membershipType = MembershipType::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Primary Member',
            'code' => 'PRIMARY',
            'description' => null,
            'is_active' => true,
            'metadata' => [],
        ]);

        $service = app(MemberRegistrationService::class);

        $member = $service->register(
            [
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'membership_number' => 'M-SARADHI-001',
            ],
            $membershipType->id,
            '2026-10-03',
            [
                'Saradhi' => [
                    'governorate' => 'Ernakulam',
                    'sndp_branch' => 'Aluva',
                    'sndp_branch_number' => '123',
                    'sndp_union' => 'Aluva Union',
                    'introducer_name' => 'John Doe',
                    'introducer_calling_code' => '+91',
                    'introducer_phone' => '9876543210',
                    'introducer_mid' => 'MID-1001',
                ],
            ],
        );

        $profile = SaradhiMemberProfile::query()
            ->where('member_id', $member->id)
            ->first();

        $this->assertNotNull($profile);

        $this->assertSame(
            'Ernakulam',
            $profile->governorate
        );

        $this->assertSame(
            'Aluva',
            $profile->sndp_branch
        );

        $this->assertSame(
            '123',
            $profile->sndp_branch_number
        );

        $this->assertSame(
            'Aluva Union',
            $profile->sndp_union
        );

        $this->assertSame(
            'John Doe',
            $profile->introducer_name
        );

        $this->assertSame(
            'MID-1001',
            $profile->introducer_mid
        );
    }

    public function test_saradhi_data_is_not_persisted_when_saradhi_is_disabled(): void
    {
        $organization = Organization::factory()->create();

        $organizationModuleService = app(
            \Modules\Core\Services\OrganizationModuleService::class
        );

        $organizationModuleService->enable(
            $organization,
            'Member'
        );

        $user = \App\Models\User::factory()->create();

        \App\Models\OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $membershipType = MembershipType::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Primary Member',
            'code' => 'PRIMARY',
            'description' => null,
            'is_active' => true,
            'metadata' => [],
        ]);

        $service = app(MemberRegistrationService::class);

        $member = $service->register(
            [
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'membership_number' => 'M-NO-SARADHI-001',
            ],
            $membershipType->id,
            '2026-10-03',
            [
                'Saradhi' => [
                    'governorate' => 'Ernakulam',
                ],
            ],
        );

        $this->assertInstanceOf(
            Member::class,
            $member
        );

        $this->assertDatabaseMissing(
            'saradhi_member_profiles',
            [
                'member_id' => $member->id,
            ]
        );
    }

    public function test_introducer_unit_must_belong_to_the_member_organization(): void
    {
        $organization = Organization::factory()->create();

        $otherOrganization = Organization::factory()->create();

        $otherUnit = Unit::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);

        $organizationModuleService = app(
            \Modules\Core\Services\OrganizationModuleService::class
        );

        $organizationModuleService->enable(
            $organization,
            'Member'
        );

        $organizationModuleService->enable(
            $organization,
            'Saradhi'
        );

        $user = \App\Models\User::factory()->create();

        \App\Models\OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $membershipType = MembershipType::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Primary Member',
            'code' => 'PRIMARY',
            'description' => null,
            'is_active' => true,
            'metadata' => [],
        ]);

        $service = app(MemberRegistrationService::class);

        $this->expectException(ValidationException::class);

        $service->register(
            [
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'membership_number' => 'M-SARADHI-INVALID-UNIT',
            ],
            $membershipType->id,
            '2026-10-03',
            [
                'Saradhi' => [
                    'introducer_unit_id' => $otherUnit->id,
                ],
            ],
        );

        $this->assertDatabaseMissing(
            'members',
            [
                'organization_id' => $organization->id,
                'membership_number' => 'M-SARADHI-INVALID-UNIT',
            ]
        );
    }
}