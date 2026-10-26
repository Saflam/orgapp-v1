<?php

namespace Modules\Saradhi\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Member\Models\Member;
use Modules\Member\Enums\MembershipApplicationStatus;
use Modules\Member\Models\MembershipApplication;
use Modules\Member\Models\MembershipType;
use Modules\Member\Services\MembershipApplicationWorkflowService;
use Modules\Member\Models\MemberPaymentItem;
use Modules\Member\Models\MemberPaymentItemRate;
use Modules\Member\Services\MemberPaymentService;
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

        $introducerUser = \App\Models\User::factory()->create();

        \App\Models\OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $introducerUser->id,
            'status' => 'active',
        ]);

        \Modules\Member\Models\Member::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $introducerUser->id,
            'membership_number' => 'MID-1001',
            'joined_at' => '2026-10-01',
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

    public function test_saradhi_data_survives_the_complete_application_to_confirmation_workflow(): void
    {
        $organization = Organization::factory()->create();

        $organizationModuleService = app(
            \Modules\Core\Services\OrganizationModuleService::class
        );

        $organizationModuleService->enable($organization, 'Member');
        $organizationModuleService->enable($organization, 'Saradhi');

        $user = \App\Models\User::factory()->create();

        \App\Models\OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $membershipType = MembershipType::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Regular',
            'code' => 'REGULAR-' . uniqid(),
            'is_active' => true,
            'metadata' => [],
        ]);

        $item = MemberPaymentItem::query()->create([
            'organization_id' => $organization->id,
            'membership_type_id' => $membershipType->id,
            'title' => 'Registration Fee',
            'slug' => 'registration-fee-' . uniqid(),
            'is_mandatory' => true,
            'is_recurring' => false,
            'recurring_period' => null,
            'recurring_by' => null,
            'recurring_date' => null,
            'is_active' => true,
            'metadata' => [],
        ]);

        $rate = MemberPaymentItemRate::query()->create([
            'member_payment_item_id' => $item->id,
            'amount' => 1000,
            'currency' => 'INR',
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]);

        $rate->allocations()->create([
            'scope_type' => 'organization',
            'percentage' => 100,
        ]);

        $application = MembershipApplication::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_type_id' => $membershipType->id,
            'status' => MembershipApplicationStatus::SUBMITTED,
            'current_step' => 3,
            'completed_steps' => [1, 2, 3],
            'data' => [
                'member' => [
                    'first_name' => 'Saradhi',
                    'last_name' => 'Applicant',
                ],
                'address' => [
                    'address_type' => 'home',
                    'address_line_1' => '123 Main Street',
                ],
                'extension_data' => [
                    'Saradhi' => [
                        'governorate' => 'Ernakulam',
                        'sndp_branch' => 'Aluva',
                        'sndp_branch_number' => '123',
                        'sndp_union' => 'Aluva Union',
                        'introducer_name' => 'John Doe',
                        'introducer_calling_code' => '+91',
                        'introducer_phone' => '9876543210',
                        'introducer_mid' => null,
                        'introducer_unit_id' => null,
                    ],
                ],
                'identifications' => [],
            ],
            'submitted_at' => now(),
        ]);

        $actor = $user;
        $workflow = app(MembershipApplicationWorkflowService::class);

        foreach ([
            MembershipApplicationStatus::VERIFIED,
            MembershipApplicationStatus::REVIEWED,
            MembershipApplicationStatus::APPROVED,
            MembershipApplicationStatus::PENDING_PAYMENT,
        ] as $status) {
            $workflow->transition($application->refresh(), $status, $actor);
        }

        app(MemberPaymentService::class)->markApplicationPaid(
            $application->refresh(),
            $actor,
            'organization',
            $organization->id,
        );

        $confirmed = $workflow->confirm(
            application: $application->refresh(),
            actor: $actor,
            // Let the organization-scoped numbering service allocate a valid ID.
            membershipNumber: null,
            startsAt: '2026-10-05',
        );

        $this->assertSame(
            MembershipApplicationStatus::CONFIRMED,
            $confirmed->status
        );

        $member = Member::query()
            ->whereKey($confirmed->member_id)
            ->firstOrFail();

        $profile = SaradhiMemberProfile::query()
            ->where('member_id', $member->id)
            ->firstOrFail();

        $this->assertSame('Ernakulam', $profile->governorate);
        $this->assertSame('Aluva', $profile->sndp_branch);
        $this->assertSame('Aluva Union', $profile->sndp_union);
        $this->assertSame('John Doe', $profile->introducer_name);
        $this->assertSame($member->id, $confirmed->member_id);
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
    public function test_all_saradhi_fields_are_optional(): void
    {
        $organization = Organization::factory()->create();
        $organizationModuleService = app(\Modules\Core\Services\OrganizationModuleService::class);
        $organizationModuleService->enable($organization, 'Member');
        $organizationModuleService->enable($organization, 'Saradhi');

        $extension = app(MembershipApplicationExtensionRegistry::class)
            ->enabledFor($organization)['Saradhi'];

        $this->assertSame([], $extension->validate($organization, []));
    }

    public function test_introducer_mid_must_belong_to_same_organization(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $organizationModuleService = app(\Modules\Core\Services\OrganizationModuleService::class);
        $organizationModuleService->enable($organization, 'Member');
        $organizationModuleService->enable($organization, 'Saradhi');

        $user = \App\Models\User::factory()->create();
        \App\Models\OrganizationMembership::create([
            'organization_id' => $otherOrganization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $member = Member::factory()->create([
            'organization_id' => $otherOrganization->id,
            'user_id' => $user->id,
            'membership_number' => 'MID-OTHER-001',
        ]);

        $extension = app(MembershipApplicationExtensionRegistry::class)
            ->enabledFor($organization)['Saradhi'];

        $errors = $extension->validate($organization, [
            'introducer_mid' => $member->membership_number,
        ]);

        $this->assertArrayHasKey('introducer_mid', $errors);
    }

    public function test_introducer_must_belong_to_selected_unit_when_unit_is_provided(): void
    {
        $organization = Organization::factory()->create();
        $organizationModuleService = app(\Modules\Core\Services\OrganizationModuleService::class);
        $organizationModuleService->enable($organization, 'Member');
        $organizationModuleService->enable($organization, 'Saradhi');

        $unitA = Unit::factory()->create(['organization_id' => $organization->id]);
        $unitB = Unit::factory()->create(['organization_id' => $organization->id]);
        $user = \App\Models\User::factory()->create();
        \App\Models\OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);
        $member = Member::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'unit_id' => $unitA->id,
            'membership_number' => 'MID-UNIT-001',
        ]);

        $extension = app(MembershipApplicationExtensionRegistry::class)
            ->enabledFor($organization)['Saradhi'];

        $errors = $extension->validate($organization, [
            'introducer_mid' => $member->membership_number,
            'introducer_unit_id' => $unitB->id,
        ]);

        $this->assertArrayHasKey('introducer_unit_id', $errors);
    }

}