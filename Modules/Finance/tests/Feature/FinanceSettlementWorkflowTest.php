<?php

namespace Modules\Finance\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceSettlement;
use Modules\Finance\Models\FinanceSettlementObligation;
use Modules\Finance\Services\FinancePeriodService;
use Modules\Finance\Services\FinanceScopeService;
use Modules\Finance\Services\FinanceSettlementService;
use Modules\Member\Models\MemberPayment;
use Modules\Member\Models\MemberPaymentLine;
use Modules\Member\Models\MemberPaymentObligation;
use Tests\TestCase;

class FinanceSettlementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private Unit $unitA;
    private Unit $unitB;
    private $scope;
    private FinanceAccount $cashA;
    private FinanceAccount $cashCentral;
    private $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->unitA = Unit::factory()->create(['organization_id' => $this->organization->id]);
        $this->unitB = Unit::factory()->create(['organization_id' => $this->organization->id]);
        $this->scope = app(FinanceScopeService::class)->organizationScope($this->organization);

        $this->cashA = $this->account('1001', 'Unit A Cash');
        $this->cashCentral = $this->account('1002', 'Central Bank');
        $this->period = app(FinancePeriodService::class)->create($this->organization, $this->scope, [
            'name' => 'Current Financial Year',
            'code' => 'FYTEST' . now()->format('Y'),
            'starts_on' => now()->startOfYear()->toDateString(),
            'ends_on' => now()->endOfYear()->toDateString(),
        ]);
    }

    public function test_payment_allocations_create_obligations_for_other_scopes_only(): void
    {
        $payment = $this->paymentCollectedByUnitA();

        app(FinanceSettlementService::class)->createObligationsForPayment($payment);

        $this->assertDatabaseHas('finance_settlement_obligations', [
            'member_payment_id' => $payment->id,
            'debtor_scope_type' => 'unit',
            'debtor_scope_id' => $this->unitA->id,
            'creditor_scope_type' => 'organization',
            'creditor_scope_id' => $this->organization->id,
            'original_amount' => '60.00',
            'outstanding_amount' => '60.00',
        ]);
        $this->assertDatabaseHas('finance_settlement_obligations', [
            'member_payment_id' => $payment->id,
            'debtor_scope_type' => 'unit',
            'debtor_scope_id' => $this->unitA->id,
            'creditor_scope_type' => 'unit',
            'creditor_scope_id' => $this->unitB->id,
            'original_amount' => '10.00',
        ]);
        $this->assertDatabaseMissing('finance_settlement_obligations', [
            'member_payment_id' => $payment->id,
            'creditor_scope_type' => 'unit',
            'creditor_scope_id' => $this->unitA->id,
        ]);

        app(FinanceSettlementService::class)->createObligationsForPayment($payment);
        $this->assertSame(2, FinanceSettlementObligation::query()->where('member_payment_id', $payment->id)->count());
    }

    public function test_recording_posts_cash_transfer_journal_and_confirmation_clears_balance(): void
    {
        $payment = $this->paymentCollectedByUnitA();
        $service = app(FinanceSettlementService::class);
        $service->createObligationsForPayment($payment);
        $obligation = FinanceSettlementObligation::query()
            ->where('member_payment_id', $payment->id)
            ->where('creditor_scope_type', 'organization')
            ->firstOrFail();

        $settlement = $service->record(
            $this->organization,
            $obligation,
            '25.00',
            now()->toDateString(),
            $this->cashA->id,
            $this->cashCentral->id,
        );

        $this->assertSame('pending_confirmation', $settlement->status);
        $this->assertNotNull($settlement->finance_journal_id);
        // The settlement journal transfers cash and clears the matching
        // inter-scope payable/receivable in the same balanced journal.
        $this->assertCount(4, $settlement->journal->lines);
        $this->assertEquals(50.0, $settlement->journal->lines->sum(fn ($line) => (float) $line->debit));
        $this->assertEquals(50.0, $settlement->journal->lines->sum(fn ($line) => (float) $line->credit));
        $this->assertSame('60.00', $obligation->fresh()->outstanding_amount);

        $service->confirm($this->organization, $settlement);
        $this->assertSame('35.00', $obligation->fresh()->outstanding_amount);
        $this->assertSame('confirmed', $settlement->fresh()->status);
    }

    public function test_rejecting_transfer_reverses_journal_without_clearing_obligation(): void
    {
        $payment = $this->paymentCollectedByUnitA();
        $service = app(FinanceSettlementService::class);
        $service->createObligationsForPayment($payment);
        $obligation = FinanceSettlementObligation::query()
            ->where('member_payment_id', $payment->id)
            ->where('creditor_scope_type', 'organization')
            ->firstOrFail();

        $settlement = $service->record(
            $this->organization,
            $obligation,
            '20.00',
            now()->toDateString(),
            $this->cashA->id,
            $this->cashCentral->id,
        );

        $service->reject($this->organization, $settlement, 'Transfer not received.');

        $this->assertSame('rejected', $settlement->fresh()->status);
        $this->assertNotNull($settlement->journal->fresh()->reversal()->first());
        $this->assertSame('60.00', $obligation->fresh()->outstanding_amount);
    }

    public function test_settlement_cannot_use_inactive_or_non_cash_accounts(): void
    {
        $payment = $this->paymentCollectedByUnitA();
        $service = app(FinanceSettlementService::class);
        $service->createObligationsForPayment($payment);
        $obligation = FinanceSettlementObligation::query()->where('member_payment_id', $payment->id)->firstOrFail();

        $nonCash = $this->account('2001', 'Payables', 'liability', false);
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $service->record(
            $this->organization,
            $obligation,
            '10.00',
            now()->toDateString(),
            $nonCash->id,
            $this->cashCentral->id,
        );
    }


    public function test_default_cash_and_bank_accounts_are_provisioned_for_settlements_and_cash_flow(): void
    {
        app(\Modules\Finance\Services\FinanceAccountProvisioningService::class)
            ->provisionDefaults($this->organization, $this->scope);

        $cash = FinanceAccount::query()->where('organization_id', $this->organization->id)->where('code', '1000')->firstOrFail();
        $bank = FinanceAccount::query()->where('organization_id', $this->organization->id)->where('code', '1100')->firstOrFail();
        $income = FinanceAccount::query()->where('organization_id', $this->organization->id)->where('code', '4000')->firstOrFail();

        $this->assertTrue($cash->is_cash_account);
        $this->assertTrue($bank->is_cash_account);
        $this->assertSame('operating', $income->cash_flow_category);
    }

    private function paymentCollectedByUnitA(): MemberPayment
    {
        $payment = MemberPayment::create([
            'organization_id' => $this->organization->id,
            'receipt_number' => 'MP-SETTLEMENT-' . uniqid(),
            'total_amount' => '100.00',
            'currency' => 'INR',
            'collector_scope_type' => 'unit',
            'collector_scope_id' => $this->unitA->id,
            'received_at' => now(),
        ]);

        $obligation = MemberPaymentObligation::create([
            'organization_id' => $this->organization->id,
            'item_title' => 'Membership fee',
            'amount' => '100.00',
            'payable_amount' => '100.00',
            'paid_amount' => '100.00',
            'currency' => 'INR',
            'status' => 'paid',
        ]);
        $line = MemberPaymentLine::create([
            'member_payment_id' => $payment->id,
            'member_payment_obligation_id' => $obligation->id,
            'amount' => '100.00',
        ]);

        $line->allocations()->createMany([
            ['scope_type' => 'organization', 'scope_id' => $this->organization->id, 'percentage' => '60.0000', 'amount' => '60.00'],
            ['scope_type' => 'unit', 'scope_id' => $this->unitA->id, 'percentage' => '30.0000', 'amount' => '30.00'],
            ['scope_type' => 'unit', 'scope_id' => $this->unitB->id, 'percentage' => '10.0000', 'amount' => '10.00'],
        ]);

        return $payment->fresh();
    }

    private function account(string $code, string $name, string $type = 'asset', bool $cash = true): FinanceAccount
    {
        return FinanceAccount::create([
            'organization_id' => $this->organization->id,
            'finance_scope_id' => $this->scope->id,
            'code' => $code,
            'name' => $name,
            'account_type' => $type,
            'normal_balance' => $type === 'liability' ? 'credit' : 'debit',
            'is_system_managed' => false,
            'is_active' => true,
            'is_cash_account' => $cash,
            'metadata' => [],
        ]);
    }
}
