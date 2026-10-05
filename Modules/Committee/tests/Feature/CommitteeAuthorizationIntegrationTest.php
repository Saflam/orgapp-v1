<?php

namespace Modules\Committee\Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Committee\Enums\CommitteeKind;
use Modules\Committee\Enums\CommitteeMembershipStatus;
use Modules\Committee\Enums\CommitteeStatus;
use Modules\Committee\Enums\CommitteeTermStatus;
use Modules\Committee\Enums\DesignationScope;
use Modules\Committee\Models\Committee;
use Modules\Committee\Models\CommitteeMembership;
use Modules\Committee\Models\CommitteeTerm;
use Modules\Committee\Models\CommitteeType;
use Modules\Committee\Models\Designation;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Unit;
use Modules\Core\Services\AuthorizationService;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Member\Models\Member;
use Tests\TestCase;

class CommitteeAuthorizationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_central_designation_permission_is_available_through_core_authorization(): void
    {
        $organization = Organization::factory()->create();
        app(OrganizationModuleService::class)->enable($organization, 'Committee');

        $user = User::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $member = app(\Modules\Member\Services\MemberService::class)->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_number' => 'TEST-0001',
        ]);

        $type = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Central',
            'code' => 'CENTRAL',
            'is_active' => true,
        ]);

        $committee = Committee::create([
            'organization_id' => $organization->id,
            'committee_type_id' => $type->id,
            'unit_id' => null,
            'name' => 'Central Committee',
            'kind' => CommitteeKind::RECURRING,
            'status' => CommitteeStatus::ACTIVE,
        ]);

        $term = CommitteeTerm::create([
            'committee_id' => $committee->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => CommitteeTermStatus::ACTIVE,
        ]);

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Central Secretary',
            'code' => 'CENTRAL_SECRETARY',
            'scope' => DesignationScope::CENTRAL,
            'is_active' => true,
        ]);

        $permission = Permission::create([
            'module' => 'member',
            'name' => 'Verify Applications',
            'code' => 'membership.application.verify',
        ]);

        $designation->permissions()->attach($permission);

        CommitteeMembership::create([
            'committee_term_id' => $term->id,
            'member_id' => $member->id,
            'designation_id' => $designation->id,
            'status' => CommitteeMembershipStatus::ACTIVE,
        ]);

        $this->assertTrue(
            app(AuthorizationService::class)->can(
                $user,
                'membership.application.verify',
                new AuthorizationContext($organization->id),
            )
        );
    }

    public function test_unit_designation_permission_is_limited_to_its_unit_context(): void
    {
        $organization = Organization::factory()->create();
        app(OrganizationModuleService::class)->enable($organization, 'Committee');

        $unitA = Unit::factory()->create(['organization_id' => $organization->id]);
        $unitB = Unit::factory()->create(['organization_id' => $organization->id]);

        $user = User::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $member = app(\Modules\Member\Services\MemberService::class)->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_number' => 'TEST-0002',
            'unit_id' => $unitA->id,
        ]);

        $type = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Unit',
            'code' => 'UNIT',
            'is_active' => true,
        ]);

        $committee = Committee::create([
            'organization_id' => $organization->id,
            'committee_type_id' => $type->id,
            'unit_id' => $unitA->id,
            'name' => 'Aluva Committee',
            'kind' => CommitteeKind::RECURRING,
            'status' => CommitteeStatus::ACTIVE,
        ]);

        $term = CommitteeTerm::create([
            'committee_id' => $committee->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => CommitteeTermStatus::ACTIVE,
        ]);

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Unit Secretary',
            'code' => 'UNIT_SECRETARY',
            'scope' => DesignationScope::UNIT,
            'is_active' => true,
        ]);

        $permission = Permission::create([
            'module' => 'member',
            'name' => 'View Members',
            'code' => 'members.view',
        ]);
        $designation->permissions()->attach($permission);

        CommitteeMembership::create([
            'committee_term_id' => $term->id,
            'member_id' => $member->id,
            'designation_id' => $designation->id,
            'status' => CommitteeMembershipStatus::ACTIVE,
        ]);

        $authorization = app(AuthorizationService::class);

        $this->assertTrue(
            $authorization->can(
                $user,
                'members.view',
                new AuthorizationContext($organization->id, $unitA->id),
            )
        );

        $this->assertFalse(
            $authorization->can(
                $user,
                'members.view',
                new AuthorizationContext($organization->id, $unitB->id),
            )
        );

        $this->assertFalse(
            $authorization->can(
                $user,
                'members.view',
                new AuthorizationContext($organization->id),
            )
        );
    }
}
