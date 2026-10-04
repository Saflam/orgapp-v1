<?php

namespace Modules\Finance\Tests\Feature;

use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Finance\Models\FeeObligation;
use Modules\Finance\Models\FeeType;
use Modules\Finance\Services\FeeObligationService;
use Modules\Member\Models\Member;
use Tests\TestCase;

class FeeObligationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_outstanding_amount_is_calculated_from_completed_payments(): void
    {
        [$member, $feeType] = $this->createFinanceData();

        $obligation = FeeObligation::create([
            'member_id' => $member->id,
            'fee_type_id' => $feeType->id,
            'amount' => 1000,
            'period_start' => '2026-01-01',
            'period_end' => '2026-12-31',
            'status' => 'due',
        ]);

        $obligation->payments()->create([
            'amount' => 400,
            'paid_at' => '2026-03-01 10:00:00',
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        $obligation->payments()->create([
            'amount' => 300,
            'paid_at' => '2026-03-02 10:00:00',
            'payment_method' => 'bank_transfer',
            'status' => 'pending',
        ]);

        $service = new FeeObligationService();

        $this->assertSame(
            600.0,
            $service->outstandingAmount($obligation)
        );
    }

    public function test_completed_obligation_is_considered_settled(): void
    {
        [$member, $feeType] = $this->createFinanceData();

        $obligation = FeeObligation::create([
            'member_id' => $member->id,
            'fee_type_id' => $feeType->id,
            'amount' => 500,
            'period_start' => '2026-01-01',
            'period_end' => '2026-12-31',
            'status' => 'due',
        ]);

        $obligation->payments()->create([
            'amount' => 500,
            'paid_at' => '2026-03-01 10:00:00',
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        $service = new FeeObligationService();

        $this->assertTrue(
            $service->isSettled($obligation)
        );
    }

    public function test_payment_cannot_exceed_outstanding_amount(): void
    {
        [$member, $feeType] = $this->createFinanceData();

        $obligation = FeeObligation::create([
            'member_id' => $member->id,
            'fee_type_id' => $feeType->id,
            'amount' => 500,
            'period_start' => '2026-01-01',
            'period_end' => '2026-12-31',
            'status' => 'due',
        ]);

        $service = new FeeObligationService();

        $this->expectException(DomainException::class);

        $service->recordPayment(
            $obligation,
            600,
            'cash'
        );
    }

    public function test_payment_is_recorded_against_obligation(): void
    {
        [$member, $feeType] = $this->createFinanceData();

        $obligation = FeeObligation::create([
            'member_id' => $member->id,
            'fee_type_id' => $feeType->id,
            'amount' => 1000,
            'period_start' => '2026-01-01',
            'period_end' => '2026-12-31',
            'status' => 'due',
        ]);

        $service = new FeeObligationService();

        $payment = $service->recordPayment(
            $obligation,
            400,
            'bank_transfer',
            'TXN-001'
        );

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'fee_obligation_id' => $obligation->id,
            'amount' => 400,
            'payment_method' => 'bank_transfer',
            'reference' => 'TXN-001',
            'status' => 'completed',
        ]);
    }

    public function test_obligation_can_be_waived(): void
    {
        [$member, $feeType] = $this->createFinanceData();

        $obligation = FeeObligation::create([
            'member_id' => $member->id,
            'fee_type_id' => $feeType->id,
            'amount' => 1000,
            'period_start' => '2026-01-01',
            'period_end' => '2026-12-31',
            'status' => 'due',
        ]);

        $service = new FeeObligationService();

        $service->waive(
            $obligation,
            'Approved welfare exemption'
        );

        $obligation->refresh();

        $this->assertSame('waived', $obligation->status);
        $this->assertSame(
            'Approved welfare exemption',
            $obligation->waiver_reason
        );
        $this->assertNotNull($obligation->waived_at);
    }

    private function createFinanceData(): array
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'code' => 'TEST',
            'slug' => 'test',
        ]);

        $member = Member::factory()
            ->forOrganization($organization)
            ->create([
                'membership_number' => 'M-0001',
            ]);

        $feeType = FeeType::create([
            'organization_id' => $organization->id,
            'code' => 'MEMBERSHIP',
            'name' => 'Membership Fee',
        ]);

        return [$member, $feeType];
    }
}