<?php

namespace Modules\Member\Tests\Feature;

use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Member\Enums\MembershipStatus;
use Modules\Member\Models\Member;
use Modules\Member\Models\Membership;
use Modules\Member\Models\MembershipType;
use Modules\Member\Services\MembershipLifecycleService;
use Tests\TestCase;

class MembershipLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_in_review_membership_can_be_activated(): void
    {
        $membership = $this->createMembership(
            MembershipStatus::IN_REVIEW
        );

        $service = new MembershipLifecycleService();

        $service->activate($membership);

        $this->assertSame(
            MembershipStatus::ACTIVE,
            $membership->fresh()->status
        );
    }

    public function test_active_membership_can_become_dormant(): void
    {
        $membership = $this->createMembership(
            MembershipStatus::ACTIVE
        );

        $service = new MembershipLifecycleService();

        $service->markDormant($membership);

        $this->assertSame(
            MembershipStatus::DORMANT,
            $membership->fresh()->status
        );
    }

    public function test_dormant_membership_can_expire(): void
    {
        $membership = $this->createMembership(
            MembershipStatus::DORMANT
        );

        $service = new MembershipLifecycleService();

        $service->expire($membership);

        $this->assertSame(
            MembershipStatus::EXPIRED,
            $membership->fresh()->status
        );
    }

    public function test_active_membership_can_be_cancelled(): void
    {
        $membership = $this->createMembership(
            MembershipStatus::ACTIVE
        );

        $service = new MembershipLifecycleService();

        $service->cancel($membership);

        $this->assertSame(
            MembershipStatus::CANCELLED,
            $membership->fresh()->status
        );
    }

    public function test_dormant_membership_can_be_cancelled(): void
    {
        $membership = $this->createMembership(
            MembershipStatus::DORMANT
        );

        $service = new MembershipLifecycleService();

        $service->cancel($membership);

        $this->assertSame(
            MembershipStatus::CANCELLED,
            $membership->fresh()->status
        );
    }

    public function test_active_membership_cannot_be_activated_again(): void
    {
        $membership = $this->createMembership(
            MembershipStatus::ACTIVE
        );

        $service = new MembershipLifecycleService();

        $this->expectException(DomainException::class);

        $service->activate($membership);
    }

    public function test_in_review_membership_cannot_become_dormant(): void
    {
        $membership = $this->createMembership(
            MembershipStatus::IN_REVIEW
        );

        $service = new MembershipLifecycleService();

        $this->expectException(DomainException::class);

        $service->markDormant($membership);
    }

    public function test_active_membership_cannot_expire_directly(): void
    {
        $membership = $this->createMembership(
            MembershipStatus::ACTIVE
        );

        $service = new MembershipLifecycleService();

        $this->expectException(DomainException::class);

        $service->expire($membership);
    }

    public function test_expired_membership_cannot_be_cancelled(): void
    {
        $membership = $this->createMembership(
            MembershipStatus::EXPIRED
        );

        $service = new MembershipLifecycleService();

        $this->expectException(DomainException::class);

        $service->cancel($membership);
    }

    public function test_cancelled_membership_cannot_be_activated(): void
    {
        $membership = $this->createMembership(
            MembershipStatus::CANCELLED
        );

        $service = new MembershipLifecycleService();

        $this->expectException(DomainException::class);

        $service->activate($membership);
    }

    private function createMembership(
        MembershipStatus $status
    ): Membership {
        $organization = Organization::factory()->create();

        $membershipType = MembershipType::create([
            'organization_id' => $organization->id,
            'name' => 'Primary',
            'code' => 'PRIMARY',
        ]);

        $member = Member::factory()
            ->forOrganization($organization)
            ->create([
                'membership_number' => 'M-0001',
            ]);

        return Membership::create([
            'member_id' => $member->id,
            'membership_type_id' => $membershipType->id,
            'starts_at' => '2026-01-01',
            'ends_at' => '2026-12-31',
            'status' => $status,
        ]);
    }
}