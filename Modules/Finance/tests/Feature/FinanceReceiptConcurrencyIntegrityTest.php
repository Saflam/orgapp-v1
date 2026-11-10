<?php

namespace Modules\Finance\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Models\Organization;
use Modules\Finance\Enums\FinanceJournalStatus;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceJournal;
use Modules\Finance\Services\FinanceAccountProvisioningService;
use Modules\Finance\Services\FinanceJournalService;
use Modules\Finance\Services\FinancePeriodService;
use Modules\Finance\Services\FinanceReceiptService;
use Modules\Finance\Services\FinanceScopeService;
use Tests\TestCase;

class FinanceReceiptConcurrencyIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_issuance_uses_the_current_locked_journal_status(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        app(FinanceAccountProvisioningService::class)->provisionDefaults($organization, $scope);

        $period = app(FinancePeriodService::class)->create($organization, $scope, [
            'name' => 'FY 2026',
            'code' => 'FY2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);

        $cash = FinanceAccount::query()
            ->where('finance_scope_id', $scope->id)
            ->where('code', '1000')
            ->firstOrFail();
        $income = FinanceAccount::query()
            ->where('finance_scope_id', $scope->id)
            ->where('code', '4000')
            ->firstOrFail();

        $journal = app(FinanceJournalService::class)->post($organization, $scope, $period, [
            'transaction_date' => '2026-10-08',
            'description' => 'Receipt status integrity test',
            'lines' => [
                ['finance_account_id' => $cash->id, 'debit' => '100.00'],
                ['finance_account_id' => $income->id, 'credit' => '100.00'],
            ],
        ]);

        // Simulate a stale in-memory journal instance after its persisted status changes.
        DB::table('finance_journals')
            ->where('id', $journal->id)
            ->update(['status' => 'draft']);

        $this->assertSame(FinanceJournalStatus::POSTED, $journal->status);

        try {
            app(FinanceReceiptService::class)->issue($organization, $scope, $journal);
            $this->fail('A receipt must not be issued from a journal that is no longer posted.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'A receipt can only be issued for a posted journal.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseMissing('finance_receipts', [
            'finance_journal_id' => $journal->id,
        ]);
    }
}
