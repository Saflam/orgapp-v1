<?php

namespace Modules\Member\Tests\Feature;

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
        $organization = Organization::factory()->create();

        $user = User::factory()->create();

        $membershipType = $this->createMembershipType(
            $organization
        );

        $service = app(MembershipApplicationService::class);

        $application = $service->startOrResume(
            organization: $organization,
            userId: $user->id,
            membershipTypeId: $membershipType->id,
        );

        $this->assertInstanceOf(
            MembershipApplication::class,
            $application
        );

        $this->assertSame(
            $organization->id,
            $application->organization_id
        );

        $this->assertSame(
            $user->id,
            $application->user_id
        );

        $this->assertSame(
            $membershipType->id,
            $application->membership_type_id
        );

        $this->assertSame(
            MembershipApplicationStatus::DRAFT,
            $application->status
        );

        $this->assertSame(
            1,
            $application->current_step
        );

        $this->assertSame(
            [],
            $application->completed_steps
        );

        $this->assertSame(
            [],
            $application->data
        );
    }

    public function test_it_resumes_an_existing_draft(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create();

        $membershipType = $this->createMembershipType(
            $organization
        );

        $existingApplication = MembershipApplication::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_type_id' => $membershipType->id,
            'status' => MembershipApplicationStatus::DRAFT,
            'current_step' => 2,
            'completed_steps' => [1],
            'data' => [
                'personal' => [
                    'gender' => 'male',
                ],
            ],
        ]);

        $service = app(MembershipApplicationService::class);

        $application = $service->startOrResume(
            organization: $organization,
            userId: $user->id,
            membershipTypeId: $membershipType->id,
        );

        $this->assertTrue(
            $application->is($existingApplication)
        );

        $this->assertSame(
            2,
            $application->current_step
        );

        $this->assertSame(
            [1],
            $application->completed_steps
        );

        $this->assertSame(
            [
                'personal' => [
                    'gender' => 'male',
                ],
            ],
            $application->data
        );

        $this->assertDatabaseCount(
            'membership_applications',
            1
        );
    }

    public function test_a_draft_is_scoped_to_the_organization(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();

        $user = User::factory()->create();

        $membershipType = $this->createMembershipType(
            $organization
        );

        MembershipApplication::create([
            'organization_id' => $otherOrganization->id,
            'user_id' => $user->id,
            'membership_type_id' => $this->createMembershipType(
                $otherOrganization
            )->id,
            'status' => MembershipApplicationStatus::DRAFT,
            'current_step' => 2,
            'completed_steps' => [1],
            'data' => [],
        ]);

        $service = app(MembershipApplicationService::class);

        $application = $service->startOrResume(
            organization: $organization,
            userId: $user->id,
            membershipTypeId: $membershipType->id,
        );

        $this->assertNotNull($application);

        $this->assertNotSame(
            $otherOrganization->id,
            $application->organization_id
        );

        $this->assertDatabaseCount(
            'membership_applications',
            2
        );
    }

    public function test_it_does_not_resume_a_submitted_application(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create();

        $membershipType = $this->createMembershipType(
            $organization
        );

        MembershipApplication::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_type_id' => $membershipType->id,
            'status' => MembershipApplicationStatus::SUBMITTED,
            'current_step' => 4,
            'completed_steps' => [1, 2, 3, 4],
            'data' => [],
        ]);

        $service = app(MembershipApplicationService::class);

        $application = $service->startOrResume(
            organization: $organization,
            userId: $user->id,
            membershipTypeId: $membershipType->id,
        );

        $this->assertNotSame(
            MembershipApplicationStatus::SUBMITTED,
            $application->status
        );

        $this->assertSame(
            MembershipApplicationStatus::DRAFT,
            $application->status
        );

        $this->assertDatabaseCount(
            'membership_applications',
            2
        );
    }

    private function createMembershipType(
        Organization $organization,
    ): MembershipType {
        return MembershipType::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Primary Member',
            'code' => 'PRIMARY',
            'description' => null,
            'is_active' => true,
            'metadata' => [],
        ]);
    }
}