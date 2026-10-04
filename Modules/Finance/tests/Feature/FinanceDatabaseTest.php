<?php

namespace Modules\Finance\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Finance\Models\FeeObligation;
use Modules\Finance\Models\FeePolicy;
use Modules\Finance\Models\FeeType;
use Modules\Finance\Models\Payment;
use Modules\Member\Models\Member;
use Modules\Member\Models\Membership;
use Modules\Member\Models\MembershipType;
use Tests\TestCase;

class FinanceDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_fee_obligation_can_be_created_for_a_member(): void
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'code' => 'TEST',
            'slug' => 'test',
        ]);

        $membershipType = MembershipType::create([
            'organization_id' => $organization->id,
            'name' => 'Primary',
            'code' => 'PRIMARY',
        ]);

        $member = Member::factory()
    ->forOrganization($organization->id)
    ->create([
        'membership_number' => 'M-0001',
    ]);

        $membership = Membership::create([
            'member_id' => $member->id,
            'membership_type_id' => $membershipType->id,
            'starts_at' => '2026-01-01',
            'ends_at' => '2026-12-31',
            'status' => 'active',
        ]);

        $feeType = FeeType::create([
            'organization_id' => $organization->id,
            'code' => 'MEMBERSHIP',
            'name' => 'Membership Fee',
        ]);

        $feePolicy = FeePolicy::create([
            'fee_type_id' => $feeType->id,
            'membership_type_id' => $membershipType->id,
            'name' => 'Primary Membership Fee',
            'amount' => 500,
            'frequency' => 'yearly',
            'effective_from' => '2026-01-01',
        ]);

        $obligation = FeeObligation::create([
            'member_id' => $member->id,
            'membership_id' => $membership->id,
            'fee_type_id' => $feeType->id,
            'fee_policy_id' => $feePolicy->id,
            'amount' => 500,
            'period_start' => '2026-01-01',
            'period_end' => '2026-12-31',
            'due_at' => '2026-03-31',
            'status' => 'due',
        ]);

        $this->assertTrue(
            $obligation->member->is($member)
        );

        $this->assertTrue(
            $obligation->membership->is($membership)
        );

        $this->assertTrue(
            $obligation->feeType->is($feeType)
        );

        $this->assertTrue(
            $obligation->feePolicy->is($feePolicy)
        );
    }

    public function test_payment_belongs_to_fee_obligation(): void
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'code' => 'TEST',
            'slug' => 'test',
        ]);

        $membershipType = MembershipType::create([
            'organization_id' => $organization->id,
            'name' => 'Primary',
            'code' => 'PRIMARY',
        ]);

        $member = Member::factory()
    ->forOrganization($organization->id)
    ->create([
        'membership_number' => 'M-0001',
    ]);

        $membership = Membership::create([
            'member_id' => $member->id,
            'membership_type_id' => $membershipType->id,
            'starts_at' => '2026-01-01',
            'ends_at' => '2026-12-31',
            'status' => 'active',
        ]);

        $feeType = FeeType::create([
            'organization_id' => $organization->id,
            'code' => 'MEMBERSHIP',
            'name' => 'Membership Fee',
        ]);

        $obligation = FeeObligation::create([
            'member_id' => $member->id,
            'membership_id' => $membership->id,
            'fee_type_id' => $feeType->id,
            'amount' => 500,
            'period_start' => '2026-01-01',
            'period_end' => '2026-12-31',
            'status' => 'due',
        ]);

        $payment = Payment::create([
            'fee_obligation_id' => $obligation->id,
            'amount' => 500,
            'paid_at' => '2026-03-15 10:30:00',
            'payment_method' => 'cash',
            'status' => 'completed',
            'reference' => 'REC-0001',
        ]);

        $this->assertTrue(
            $payment->feeObligation->is($obligation)
        );

        $this->assertCount(
            1,
            $obligation->payments
        );
    }

    public function test_multiple_payments_can_be_applied_to_one_obligation(): void
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'code' => 'TEST',
            'slug' => 'test',
        ]);

        $member = Member::factory()
    ->forOrganization($organization->id)
    ->create([
        'membership_number' => 'M-0001',
    ]);

        $feeType = FeeType::create([
            'organization_id' => $organization->id,
            'code' => 'MEMBERSHIP',
            'name' => 'Membership Fee',
        ]);

        $obligation = FeeObligation::create([
            'member_id' => $member->id,
            'fee_type_id' => $feeType->id,
            'amount' => 1000,
            'period_start' => '2026-01-01',
            'period_end' => '2026-12-31',
            'status' => 'due',
        ]);

        Payment::create([
            'fee_obligation_id' => $obligation->id,
            'amount' => 400,
            'paid_at' => '2026-03-01 10:00:00',
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        Payment::create([
            'fee_obligation_id' => $obligation->id,
            'amount' => 600,
            'paid_at' => '2026-03-15 10:00:00',
            'payment_method' => 'bank_transfer',
            'status' => 'completed',
        ]);

        $this->assertCount(
            2,
            $obligation->payments
        );

        $this->assertEquals(
            1000,
            $obligation->payments
                ->where('status', 'completed')
                ->sum('amount')
        );
    }
}