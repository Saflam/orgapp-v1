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

    public function test_future_dated_committee_assignment_does_not_grant_permission_until_it_starts(): void
    {
        [$organization, $user, $membership, $permission] = $this->createAuthorizationFixture(
            permissionModule: 'events',
            membershipStart: today()->addDay()->toDateString(),
            membershipEnd: today()->addDays(30)->toDateString(),
            enablePermissionModule: true,
        );

        $authorization = app(AuthorizationService::class);
        $context = new AuthorizationContext($organization->id);

        $this->assertFalse($authorization->can($user, $permission->code, $context));

        $membership->update(['start_date' => today()->subDay()->toDateString()]);

        $this->assertTrue($authorization->can($user, $permission->code, $context));
    }

    public function test_expired_committee_assignment_does_not_grant_permission(): void
    {
        [$organization, $user, $membership, $permission] = $this->createAuthorizationFixture(
            permissionModule: 'events',
            membershipStart: today()->subDays(30)->toDateString(),
            membershipEnd: today()->subDay()->toDateString(),
            enablePermissionModule: true,
        );

        $this->assertFalse(
            app(AuthorizationService::class)->can(
                $user,
                $permission->code,
                new AuthorizationContext($organization->id),
            )
        );
    }

    public function test_designation_cannot_grant_permission_from_a_disabled_module(): void
    {
        [$organization, $user, $membership, $permission] = $this->createAuthorizationFixture(
            permissionModule: 'events',
            membershipStart: today()->subDay()->toDateString(),
            membershipEnd: today()->addDays(30)->toDateString(),
            enablePermissionModule: false,
        );

        $this->assertTrue($membership->exists);
        $this->assertFalse(
            app(AuthorizationService::class)->can(
                $user,
                $permission->code,
                new AuthorizationContext($organization->id),
            )
        );
    }

    /**
     * @return array{0: Organization, 1: User, 2: CommitteeMembership, 3: Permission}
     */
    private function createAuthorizationFixture(
        string $permissionModule,
        string $membershipStart,
        string $membershipEnd,
        bool $enablePermissionModule,
    ): array {
        $organization = Organization::factory()->create();
        $modules = app(OrganizationModuleService::class);

        // Required modules must be explicitly enabled in this test fixture.
        $modules->enable($organization, 'Core');
        $modules->enable($organization, 'Member');

        // Optional module used by these authorization tests.
        $modules->enable($organization, 'Committee');

        if ($enablePermissionModule) {
            $modules->enable($organization, 'Events');
        }

        $user = User::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $member = app(\Modules\Member\Services\MemberService::class)->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_number' => 'AUTH-' . uniqid(),
        ]);

        $type = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Authorization Committee',
            'code' => 'AUTH-' . strtoupper(substr(uniqid(), -6)),
            'is_active' => true,
        ]);

        $committee = Committee::create([
            'organization_id' => $organization->id,
            'committee_type_id' => $type->id,
            'unit_id' => null,
            'name' => 'Authorization Central Committee',
            'kind' => CommitteeKind::RECURRING,
            'status' => CommitteeStatus::ACTIVE,
        ]);

        $term = CommitteeTerm::create([
            'committee_id' => $committee->id,
            'start_date' => today()->subMonth()->toDateString(),
            'end_date' => today()->addMonth()->toDateString(),
            'status' => CommitteeTermStatus::ACTIVE,
        ]);

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Authorization Secretary',
            'code' => 'AUTH-SECRETARY-' . strtoupper(substr(uniqid(), -6)),
            'scope' => DesignationScope::CENTRAL,
            'is_active' => true,
        ]);

        $permission = Permission::create([
            'module' => $permissionModule,
            'name' => 'Authorization Test Permission',
            'code' => 'authorization.test.' . strtolower($permissionModule) . '.' . uniqid(),
        ]);
        $designation->permissions()->attach($permission->id);

        $committeeMembership = CommitteeMembership::create([
            'committee_term_id' => $term->id,
            'member_id' => $member->id,
            'designation_id' => $designation->id,
            'start_date' => $membershipStart,
            'end_date' => $membershipEnd,
            'status' => CommitteeMembershipStatus::ACTIVE,
        ]);

        return [$organization, $user, $committeeMembership, $permission];
    }

}
