<?php

namespace Modules\Member\Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Member\Enums\MembershipApplicationStatus;
use Modules\Member\Enums\MembershipStatus;
use Modules\Member\Models\MembershipApplication;
use Modules\Member\Models\MembershipType;
use Modules\Member\Services\MembershipApplicationWorkflowService;
use Tests\TestCase;

class MembershipApplicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_every_accepted_transition(): void
    {
        [$application, $actor] = $this->applicationContext();
        $service = app(MembershipApplicationWorkflowService::class);

        $service->transition($application, MembershipApplicationStatus::VERIFIED, $actor);
        $service->transition($application->refresh(), MembershipApplicationStatus::REVIEWED, $actor);
        $service->transition($application->refresh(), MembershipApplicationStatus::APPROVED, $actor);
        $service->transition($application->refresh(), MembershipApplicationStatus::PAYMENT, $actor);

        $this->assertSame(MembershipApplicationStatus::PAYMENT, $application->refresh()->status);
        $this->assertDatabaseCount('membership_application_status_histories', 4);
    }

    public function test_rejection_requires_reason_and_can_allow_reapplication(): void
    {
        [$application, $actor] = $this->applicationContext();
        $service = app(MembershipApplicationWorkflowService::class);

        $this->expectException(ValidationException::class);

        $service->reject(
            application: $application,
            actor: $actor,
            notes: '',
            allowReapply: true,
        );
    }

    public function test_invalid_transition_is_rejected(): void
    {
        [$application, $actor] = $this->applicationContext();

        $this->expectException(ValidationException::class);

        app(MembershipApplicationWorkflowService::class)->transition(
            application: $application,
            to: MembershipApplicationStatus::APPROVED,
            actor: $actor,
        );
    }

    public function test_confirmation_creates_member_and_active_membership(): void
    {
        [$application, $actor] = $this->applicationContext();
        $service = app(MembershipApplicationWorkflowService::class);

        foreach ([
            MembershipApplicationStatus::VERIFIED,
            MembershipApplicationStatus::REVIEWED,
            MembershipApplicationStatus::APPROVED,
            MembershipApplicationStatus::PAYMENT,
        ] as $status) {
            $service->transition($application->refresh(), $status, $actor);
        }

        $confirmed = $service->confirm(
            application: $application->refresh(),
            actor: $actor,
            membershipNumber: 'MEM-TEST-001',
            startsAt: '2026-10-05',
        );

        $this->assertSame(MembershipApplicationStatus::CONFIRMED, $confirmed->status);

        $member = \Modules\Member\Models\Member::query()->firstOrFail();
        $this->assertSame('MEM-TEST-001', $member->membership_number);
        $this->assertSame($member->id, $confirmed->member_id);
        $this->assertDatabaseHas('memberships', [
            'member_id' => $member->id,
            'status' => MembershipStatus::ACTIVE->value,
        ]);
    }

    private function applicationContext(): array
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $actor->id,
            'status' => 'active',
        ]);

        $membershipType = MembershipType::create([
            'organization_id' => $organization->id,
            'name' => 'Regular',
            'code' => 'REGULAR-' . uniqid(),
            'is_active' => true,
            'metadata' => [],
        ]);

        $application = MembershipApplication::create([
            'organization_id' => $organization->id,
            'user_id' => $actor->id,
            'membership_type_id' => $membershipType->id,
            'status' => MembershipApplicationStatus::SUBMITTED,
            'current_step' => 3,
            'completed_steps' => [1, 2, 3],
            'data' => [
                'member' => [
                    'first_name' => 'Test',
                    'blood_group' => 'O+',
                ],
                'address' => [
                    'address_type' => 'home',
                    'address_line_1' => '123 Main Street',
                ],
                'extension_data' => [],
                'identifications' => [],
            ],
            'submitted_at' => now(),
        ]);

        return [$application, $actor];
    }
}
