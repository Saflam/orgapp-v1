<?php

namespace Modules\Finance\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Finance\Services\FinanceJournalService;
use Modules\Finance\Services\FinancePeriodService;
use Modules\Finance\Services\FinanceScopeService;
use Modules\Finance\Services\MemberPaymentFinanceService;
use Modules\Member\Models\MemberPayment;
use PDOException;
use Tests\TestCase;

class MemberPaymentFinanceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_payment_rethrows_integrity_error_when_no_matching_journal_exists(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable($organization, 'Core');
        app(OrganizationModuleService::class)->enable($organization, 'Finance');

        $scope = app(FinanceScopeService::class)->organizationScope($organization);

        app(FinancePeriodService::class)->create($organization, $scope, [
            'name' => 'FY 2026',
            'code' => 'FY2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);

        $payment = MemberPayment::create([
            'organization_id' => $organization->id,
            'receipt_number' => 'MP-INTEGRITY-001',
            'total_amount' => 250,
            'currency' => 'INR',
            'payment_method' => 'cash',
            'collector_scope_type' => 'organization',
            'collector_scope_id' => $organization->id,
            'received_at' => '2026-10-08 10:00:00',
            'metadata' => [],
        ]);

        $originalException = new QueryException(
            'sqlite',
            'insert into finance_journals (...) values (...)',
            [],
            new PDOException('SQLSTATE[23000]: Integrity constraint violation: foreign key constraint failed', 23000),
        );

        $journalService = $this->createMock(FinanceJournalService::class);
        $journalService
            ->expects($this->once())
            ->method('post')
            ->willThrowException($originalException);

        $this->app->instance(FinanceJournalService::class, $journalService);

        try {
            app(MemberPaymentFinanceService::class)->handle($payment);
            $this->fail('The original database integrity exception should be rethrown.');
        } catch (QueryException $exception) {
            $this->assertSame($originalException, $exception);
        }
    }
}
