<?php

namespace Modules\Member\Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Member\Enums\MembershipApplicationStatus;
use Modules\Member\Models\MembershipApplication;
use Modules\Member\Models\MembershipType;
use Modules\Member\Services\MembershipApplicationService;
use Tests\TestCase;

class MembershipApplicationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_starts_a_new_application(): void
    {
        [$organization, $user, $membershipType] = $this->context();

        $application = app(MembershipApplicationService::class)->startOrResume(
            organization: $organization,
            userId: $user->id,
            membershipTypeId: $membershipType->id,
        );

        $this->assertSame($organization->id, $application->organization_id);
        $this->assertSame($user->id, $application->user_id);
        $this->assertSame($membershipType->id, $application->membership_type_id);
        $this->assertSame(MembershipApplicationStatus::DRAFT, $application->status);
        $this->assertSame(1, $application->current_step);
        $this->assertSame([], $application->completed_steps);
        $this->assertSame([], $application->data);
    }

    public function test_it_resumes_an_existing_draft(): void
    {
        [$organization, $user, $membershipType] = $this->context();

        $existingApplication = MembershipApplication::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_type_id' => $membershipType->id,
            'status' => MembershipApplicationStatus::DRAFT,
            'current_step' => 2,
            'completed_steps' => [1],
            'data' => ['personal' => ['gender' => 'male']],
        ]);

        $application = app(MembershipApplicationService::class)->startOrResume(
            organization: $organization,
            userId: $user->id,
            membershipTypeId: $membershipType->id,
        );

        $this->assertTrue($application->is($existingApplication));
        $this->assertSame(2, $application->current_step);
        $this->assertSame([1], $application->completed_steps);
        $this->assertSame(['personal' => ['gender' => 'male']], $application->data);
        $this->assertDatabaseCount('membership_applications', 1);
    }

    public function test_applications_are_scoped_to_the_organization(): void
    {
        [$organization, $user, $membershipType] = $this->context();
        $otherOrganization = Organization::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $otherOrganization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        MembershipApplication::create([
            'organization_id' => $otherOrganization->id,
            'user_id' => $user->id,
            'membership_type_id' => $this->createMembershipType($otherOrganization)->id,
            'status' => MembershipApplicationStatus::DRAFT,
            'current_step' => 2,
            'completed_steps' => [1],
            'data' => [],
        ]);

        $application = app(MembershipApplicationService::class)->startOrResume(
            organization: $organization,
            userId: $user->id,
            membershipTypeId: $membershipType->id,
        );

        $this->assertSame($organization->id, $application->organization_id);
        $this->assertDatabaseCount('membership_applications', 2);
    }

    public function test_it_does_not_create_another_draft_while_an_application_is_in_progress(): void
    {
        [$organization, $user, $membershipType] = $this->context();

        MembershipApplication::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_type_id' => $membershipType->id,
            'status' => MembershipApplicationStatus::SUBMITTED,
            'current_step' => 4,
            'completed_steps' => [1, 2, 3, 4],
            'data' => [],
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(MembershipApplicationService::class)->startOrResume(
            organization: $organization,
            userId: $user->id,
            membershipTypeId: $membershipType->id,
        );
    }

    public function test_rejected_application_with_reapply_permission_reopens_as_draft(): void
    {
        [$organization, $user, $membershipType] = $this->context();

        $application = MembershipApplication::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_type_id' => $membershipType->id,
            'status' => MembershipApplicationStatus::REJECTED,
            'current_step' => 4,
            'completed_steps' => [1, 2, 3, 4],
            'data' => ['old' => true],
        ]);

        $application->statusHistories()->create([
            'from_status' => MembershipApplicationStatus::SUBMITTED->value,
            'to_status' => MembershipApplicationStatus::REJECTED->value,
            'actor_user_id' => $user->id,
            'notes' => 'Please revise.',
            'allow_reapply' => true,
            'metadata' => [],
        ]);

        $application = app(MembershipApplicationService::class)->startOrResume(
            organization: $organization,
            userId: $user->id,
            membershipTypeId: $membershipType->id,
        );

        $this->assertSame(MembershipApplicationStatus::DRAFT, $application->status);
        $this->assertSame([], $application->data);
        $this->assertSame([], $application->completed_steps);
    }

    private function context(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        return [
            $organization,
            $user,
            $this->createMembershipType($organization),
        ];
    }

    private function createMembershipType(Organization $organization): MembershipType
    {
        return MembershipType::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Primary Member',
            'code' => 'PRIMARY-' . $organization->id . '-' . uniqid(),
            'description' => null,
            'is_active' => true,
            'metadata' => [],
        ]);
    }
}
