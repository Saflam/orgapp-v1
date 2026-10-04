<?php

namespace Modules\Committee\Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Committee\Enums\CommitteeKind;
use Modules\Committee\Enums\CommitteeMembershipStatus;
use Modules\Committee\Enums\CommitteeStatus;
use Modules\Committee\Enums\CommitteeTermStatus;
use Modules\Committee\Models\Committee;
use Modules\Committee\Models\CommitteeMembership;
use Modules\Committee\Models\CommitteeTerm;
use Modules\Committee\Models\CommitteeType;
use Modules\Committee\Models\Designation;
use Modules\Core\Models\Permission;
use Modules\Committee\Services\CommitteeAuthorizationService;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Member\Models\Member;
use Tests\TestCase;

class CommitteeAuthorizationServiceTest extends TestCase
{
    use RefreshDatabase;

    private int $membershipNumber = 1;

    private function createMember(Organization $organization): Member
    {
        $number = str_pad((string) $this->membershipNumber++, 4, '0', STR_PAD_LEFT);

        $user = User::factory()->create([
            'name' => 'Test Member',
        ]);

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        return app(\Modules\Member\Services\MemberService::class)->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_number' => 'TEST-' . $number,
            'joined_at' => '2026-09-23',
            'metadata' => [],
        ]);
    }

    private function createCommitteeType(
        Organization $organization,
        string $code = 'REGULAR',
    ): CommitteeType {
        return CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Regular Committee',
            'code' => $code,
            'description' => null,
            'is_active' => true,
        ]);
    }

    private function createCommittee(
        Organization $organization,
        CommitteeType $committeeType,
        CommitteeStatus $status = CommitteeStatus::ACTIVE,
    ): Committee {
        return Committee::create([
            'organization_id' => $organization->id,
            'committee_type_id' => $committeeType->id,
            'unit_id' => null,
            'parent_committee_id' => null,
            'name' => 'Test Committee',
            'kind' => CommitteeKind::RECURRING,
            'status' => $status,
        ]);
    }

    private function createActiveTerm(
        Committee $committee,
    ): CommitteeTerm {
        return CommitteeTerm::create([
            'committee_id' => $committee->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => CommitteeTermStatus::ACTIVE,
        ]);
    }

    private function createDesignation(
        Organization $organization,
        bool $isActive = true,
    ): Designation {
        return Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => $isActive,
        ]);
    }

    private function createPermission(
        string $code = 'finance.transactions.create',
    ): Permission {
        return Permission::create([
            'module' => 'finance',
            'name' => 'Create Transactions',
            'code' => $code,
        ]);
    }

    private function createMembership(
        Member $member,
        CommitteeTerm $term,
        Designation $designation,
        CommitteeMembershipStatus $status = CommitteeMembershipStatus::ACTIVE,
    ): CommitteeMembership {
        return CommitteeMembership::create([
            'committee_term_id' => $term->id,
            'member_id' => $member->id,
            'designation_id' => $designation->id,
            'status' => $status,
        ]);
    }

    private function grantPermission(
        Designation $designation,
        Permission $permission,
    ): void {
        $designation->permissions()->attach($permission->id);
    }

    private function createOrganizationWithCommitteeModule(): Organization
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            $organization,
            'Committee',
        );

        return $organization;
    }

    public function test_member_has_permission_through_active_committee_membership(): void
    {
        $organization = $this->createOrganizationWithCommitteeModule();
        $committeeType = $this->createCommitteeType($organization);
        $member = $this->createMember($organization);

        $committee = $this->createCommittee($organization, $committeeType,);

        $term = $this->createActiveTerm($committee);

        $designation = $this->createDesignation($organization);

        $permission = $this->createPermission();

        $this->grantPermission($designation, $permission);

        $this->createMembership(
            $member,
            $term,
            $designation,
        );

        $service = app(CommitteeAuthorizationService::class);

        $this->assertTrue(
            $service->hasPermission(
                $member,
                'finance.transactions.create'
            )
        );
    }

    public function test_ended_membership_does_not_grant_permission(): void
    {
        $organization = $this->createOrganizationWithCommitteeModule();

        $member = $this->createMember($organization);

        $committeeType = $this->createCommitteeType($organization);
        $committee = $this->createCommittee($organization, $committeeType,);

        $term = $this->createActiveTerm($committee);

        $designation = $this->createDesignation($organization);

        $permission = $this->createPermission();

        $this->grantPermission($designation, $permission);

        $this->createMembership(
            $member,
            $term,
            $designation,
            CommitteeMembershipStatus::ENDED,
        );

        $service = app(CommitteeAuthorizationService::class);

        $this->assertFalse(
            $service->hasPermission(
                $member,
                'finance.transactions.create'
            )
        );
    }

    public function test_completed_term_does_not_grant_permission(): void
    {
        $organization = $this->createOrganizationWithCommitteeModule();

        $member = $this->createMember($organization);

        $committeeType = $this->createCommitteeType($organization);
        $committee = $this->createCommittee($organization, $committeeType,);

        $term = CommitteeTerm::create([
            'committee_id' => $committee->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
            'status' => CommitteeTermStatus::COMPLETED,
        ]);

        $designation = $this->createDesignation($organization);

        $permission = $this->createPermission();

        $this->grantPermission($designation, $permission);

        $this->createMembership(
            $member,
            $term,
            $designation,
        );

        $service = app(CommitteeAuthorizationService::class);

        $this->assertFalse(
            $service->hasPermission(
                $member,
                'finance.transactions.create'
            )
        );
    }

    public function test_archived_committee_does_not_grant_permission(): void
    {
        $organization = $this->createOrganizationWithCommitteeModule();

        $member = $this->createMember($organization);

        $committeeType = $this->createCommitteeType($organization);
        $committee = $this->createCommittee($organization, $committeeType, CommitteeStatus::ARCHIVED,);

        $term = $this->createActiveTerm($committee);

        $designation = $this->createDesignation($organization);

        $permission = $this->createPermission();

        $this->grantPermission($designation, $permission);

        $this->createMembership(
            $member,
            $term,
            $designation,
        );

        $service = app(CommitteeAuthorizationService::class);

        $this->assertFalse(
            $service->hasPermission(
                $member,
                'finance.transactions.create'
            )
        );
    }

    public function test_inactive_designation_does_not_grant_permission(): void
    {
        $organization = $this->createOrganizationWithCommitteeModule();

        $member = $this->createMember($organization);

        $committeeType = $this->createCommitteeType($organization);
        $committee = $this->createCommittee($organization, $committeeType);

        $term = $this->createActiveTerm($committee);

        $designation = $this->createDesignation(
            $organization,
            false,
        );

        $permission = $this->createPermission();

        $this->grantPermission($designation, $permission);

        $this->createMembership(
            $member,
            $term,
            $designation,
        );

        $service = app(CommitteeAuthorizationService::class);

        $this->assertFalse(
            $service->hasPermission(
                $member,
                'finance.transactions.create'
            )
        );
    }

    public function test_designation_without_requested_permission_does_not_grant_permission(): void
    {
        $organization = $this->createOrganizationWithCommitteeModule();

        $member = $this->createMember($organization);

        $committeeType = $this->createCommitteeType($organization);
        $committee = $this->createCommittee($organization, $committeeType);

        $term = $this->createActiveTerm($committee);

        $designation = $this->createDesignation($organization);

        $otherPermission = $this->createPermission(
            'finance.accounts.view',
        );

        $this->grantPermission(
            $designation,
            $otherPermission,
        );

        $this->createMembership(
            $member,
            $term,
            $designation,
        );

        $service = app(CommitteeAuthorizationService::class);

        $this->assertFalse(
            $service->hasPermission(
                $member,
                'finance.transactions.create'
            )
        );
    }

    public function test_member_can_retain_permission_from_another_active_committee(): void
    {
        $organization = $this->createOrganizationWithCommitteeModule();

        $member = $this->createMember($organization);

        $committeeType = $this->createCommitteeType($organization);

        // First committee has ended.
        $expiredCommittee = $this->createCommittee($organization, $committeeType,);


        $expiredTerm = CommitteeTerm::create([
            'committee_id' => $expiredCommittee->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
            'status' => CommitteeTermStatus::COMPLETED,
        ]);

        $expiredDesignation = $this->createDesignation($organization);

        $permission = $this->createPermission();

        $this->grantPermission(
            $expiredDesignation,
            $permission,
        );

        $this->createMembership(
            $member,
            $expiredTerm,
            $expiredDesignation,
        );

        // Second committee is still active.
        $activeCommittee = $this->createCommittee($organization, $committeeType,);

        $activeTerm = $this->createActiveTerm(
            $activeCommittee,
        );

        $activeDesignation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Secretary',
            'code' => 'SECRETARY',
            'is_active' => true,
        ]);

        $this->grantPermission(
            $activeDesignation,
            $permission,
        );

        $this->createMembership(
            $member,
            $activeTerm,
            $activeDesignation,
        );

        $service = app(CommitteeAuthorizationService::class);

        $this->assertTrue(
            $service->hasPermission(
                $member,
                'finance.transactions.create'
            )
        );
    }

    public function test_different_member_does_not_get_another_members_permission(): void
    {
        $organization = $this->createOrganizationWithCommitteeModule();

        $memberA = $this->createMember($organization);
        $memberB = $this->createMember($organization);

        $committeeType = $this->createCommitteeType($organization);
        $committee = $this->createCommittee($organization, $committeeType);

        $term = $this->createActiveTerm($committee);

        $designation = $this->createDesignation($organization);

        $permission = $this->createPermission();

        $this->grantPermission($designation, $permission);

        $this->createMembership(
            $memberA,
            $term,
            $designation,
        );

        $service = app(CommitteeAuthorizationService::class);

        $this->assertTrue(
            $service->hasPermission(
                $memberA,
                'finance.transactions.create'
            )
        );

        $this->assertFalse(
            $service->hasPermission(
                $memberB,
                'finance.transactions.create'
            )
        );
    }

    public function test_has_any_permission_returns_true_when_one_permission_matches(): void
    {
        $organization = $this->createOrganizationWithCommitteeModule();

        $member = $this->createMember($organization);

        $committeeType = $this->createCommitteeType($organization);
        $committee = $this->createCommittee($organization, $committeeType);

        $term = $this->createActiveTerm($committee);

        $designation = $this->createDesignation($organization);

        $permission = $this->createPermission(
            'finance.accounts.view',
        );

        $this->grantPermission(
            $designation,
            $permission,
        );

        $this->createMembership(
            $member,
            $term,
            $designation,
        );

        $service = app(CommitteeAuthorizationService::class);

        $this->assertTrue(
            $service->hasAnyPermission(
                $member,
                [
                    'finance.accounts.view',
                    'finance.transactions.create',
                ]
            )
        );
    }

    public function test_has_any_permission_returns_false_when_no_permission_matches(): void
    {
        $organization = $this->createOrganizationWithCommitteeModule();

        $member = $this->createMember($organization);

        $committeeType = $this->createCommitteeType($organization);
        $committee = $this->createCommittee($organization, $committeeType);

        $term = $this->createActiveTerm($committee);

        $designation = $this->createDesignation($organization);

        $permission = $this->createPermission(
            'finance.accounts.view',
        );

        $this->grantPermission(
            $designation,
            $permission,
        );

        $this->createMembership(
            $member,
            $term,
            $designation,
        );

        $service = app(CommitteeAuthorizationService::class);

        $this->assertFalse(
            $service->hasAnyPermission(
                $member,
                [
                    'finance.transactions.create',
                    'finance.transactions.approve',
                ]
            )
        );
    }

    public function test_has_all_permissions_returns_true_when_all_permissions_are_effective(): void
    {
        $organization = $this->createOrganizationWithCommitteeModule();

        $member = $this->createMember($organization);

        $committeeType = $this->createCommitteeType($organization);
        $committee = $this->createCommittee($organization, $committeeType);

        $term = $this->createActiveTerm($committee);

        $designation = $this->createDesignation($organization);

        $createPermission = $this->createPermission(
            'finance.transactions.create',
        );

        $approvePermission = $this->createPermission(
            'finance.transactions.approve',
        );

        $this->grantPermission(
            $designation,
            $createPermission,
        );

        $this->grantPermission(
            $designation,
            $approvePermission,
        );

        $this->createMembership(
            $member,
            $term,
            $designation,
        );

        $service = app(CommitteeAuthorizationService::class);

        $this->assertTrue(
            $service->hasAllPermissions(
                $member,
                [
                    'finance.transactions.create',
                    'finance.transactions.approve',
                ]
            )
        );
    }

    public function test_has_all_permissions_returns_false_when_one_permission_is_missing(): void
    {
        $organization = $this->createOrganizationWithCommitteeModule();

        $member = $this->createMember($organization);

        $committeeType = $this->createCommitteeType($organization);
        $committee = $this->createCommittee($organization, $committeeType);

        $term = $this->createActiveTerm($committee);

        $designation = $this->createDesignation($organization);

        $permission = $this->createPermission(
            'finance.transactions.create',
        );

        $this->grantPermission(
            $designation,
            $permission,
        );

        $this->createMembership(
            $member,
            $term,
            $designation,
        );

        $service = app(CommitteeAuthorizationService::class);

        $this->assertFalse(
            $service->hasAllPermissions(
                $member,
                [
                    'finance.transactions.create',
                    'finance.transactions.approve',
                ]
            )
        );
    }

    public function test_has_any_permission_with_empty_list_returns_false(): void
    {
        $organization = $this->createOrganizationWithCommitteeModule();
        $member = $this->createMember($organization);

        $service = app(CommitteeAuthorizationService::class);

        $this->assertFalse(
            $service->hasAnyPermission(
                $member,
                []
            )
        );
    }

    public function test_has_all_permissions_with_empty_list_returns_true(): void
    {
        $organization = $this->createOrganizationWithCommitteeModule();
        $member = $this->createMember($organization);

        $service = app(CommitteeAuthorizationService::class);

        $this->assertTrue(
            $service->hasAllPermissions(
                $member,
                []
            )
        );
    }

    public function test_disabled_committee_module_does_not_grant_permission(): void
    {
        $organization = Organization::factory()->create();

        $committeeType = $this->createCommitteeType($organization);

        $member = $this->createMember($organization);

        $committee = $this->createCommittee(
            $organization,
            $committeeType,
        );

        $term = $this->createActiveTerm($committee);

        $designation = $this->createDesignation($organization);

        $permission = $this->createPermission();

        $this->grantPermission(
            $designation,
            $permission,
        );

        $this->createMembership(
            $member,
            $term,
            $designation,
        );

        $service = app(CommitteeAuthorizationService::class);

        $this->assertFalse(
            $service->hasPermission(
                $member,
                'finance.transactions.create',
            )
        );
    }
}