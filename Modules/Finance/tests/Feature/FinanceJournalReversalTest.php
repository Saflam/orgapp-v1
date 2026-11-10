<?php

namespace Modules\Finance\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Core\Models\Organization;
use Modules\Finance\Enums\FinanceJournalStatus;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceJournal;
use Modules\Finance\Services\FinanceAccountProvisioningService;
use Modules\Finance\Services\FinanceJournalService;
use Modules\Finance\Services\FinancePeriodService;
use Modules\Finance\Services\FinanceScopeService;
use Tests\TestCase;

class FinanceJournalReversalTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        app(FinanceAccountProvisioningService::class)->provisionDefaults($organization, $scope);

        $periods = app(FinancePeriodService::class);
        $period = $periods->create($organization, $scope, [
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

        $original = app(FinanceJournalService::class)->post($organization, $scope, $period, [
            'transaction_date' => '2026-10-10',
            'description' => 'Original manual journal',
            'lines' => [
                ['finance_account_id' => $cash->id, 'description' => 'Cash received', 'debit' => '125.5000'],
                ['finance_account_id' => $income->id, 'description' => 'Income recognized', 'credit' => '125.5000'],
            ],
        ]);

        return compact('organization', 'scope', 'period', 'cash', 'income', 'original', 'periods');
    }

    public function test_reversal_posts_swapped_lines_and_preserves_original_journal(): void
    {
        $fixture = $this->fixture();

        $reversal = app(FinanceJournalService::class)->reverse(
            $fixture['organization'],
            $fixture['scope'],
            $fixture['original'],
            $fixture['period'],
            '2026-11-01',
            'Correcting an incorrectly recorded transaction.',
        );

        $reversal->load('lines', 'originalJournal');

        $this->assertSame(FinanceJournalStatus::POSTED, $reversal->status);
        $this->assertSame($fixture['original']->id, $reversal->reversal_of_journal_id);
        $this->assertSame($fixture['original']->journal_number, $reversal->originalJournal->journal_number);
        $this->assertSame('Correcting an incorrectly recorded transaction.', $reversal->reversal_reason);
        $this->assertSame('125.5000', $reversal->lines->firstWhere('finance_account_id', $fixture['cash']->id)->credit);
        $this->assertSame('125.5000', $reversal->lines->firstWhere('finance_account_id', $fixture['income']->id)->debit);
        $this->assertSame('125.5000', $fixture['original']->fresh()->lines()->where('finance_account_id', $fixture['cash']->id)->value('debit'));
        $this->assertDatabaseHas('finance_journals', [
            'id' => $fixture['original']->id,
            'status' => 'posted',
            'reversal_of_journal_id' => null,
        ]);
    }

    public function test_reversal_can_be_posted_in_a_later_open_period_when_original_period_is_closed(): void
    {
        $fixture = $this->fixture();
        $fixture['periods']->close($fixture['period']);

        $laterPeriod = $fixture['periods']->create($fixture['organization'], $fixture['scope'], [
            'name' => 'FY 2027',
            'code' => 'FY2027',
            'starts_on' => '2027-01-01',
            'ends_on' => '2027-12-31',
        ]);

        $reversal = app(FinanceJournalService::class)->reverse(
            $fixture['organization'],
            $fixture['scope'],
            $fixture['original'],
            $laterPeriod,
            '2027-02-01',
            'Correction identified after year end.',
        );

        $this->assertSame($laterPeriod->id, $reversal->finance_period_id);
        $this->assertSame('2027-02-01', $reversal->transaction_date->toDateString());
    }

    public function test_journal_cannot_be_reversed_more_than_once(): void
    {
        $fixture = $this->fixture();
        $service = app(FinanceJournalService::class);

        $service->reverse(
            $fixture['organization'], $fixture['scope'], $fixture['original'],
            $fixture['period'], '2026-11-01', 'First reversal.',
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This journal has already been reversed.');

        $service->reverse(
            $fixture['organization'], $fixture['scope'], $fixture['original'],
            $fixture['period'], '2026-11-02', 'Second reversal attempt.',
        );
    }

    public function test_reversal_journal_cannot_itself_be_reversed(): void
    {
        $fixture = $this->fixture();
        $service = app(FinanceJournalService::class);
        $reversal = $service->reverse(
            $fixture['organization'], $fixture['scope'], $fixture['original'],
            $fixture['period'], '2026-11-01', 'First reversal.',
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A reversal journal cannot itself be reversed.');

        $service->reverse(
            $fixture['organization'], $fixture['scope'], $reversal,
            $fixture['period'], '2026-11-02', 'Attempt to reverse a reversal.',
        );
    }

    public function test_reversal_requires_an_open_period_and_date_within_that_period(): void
    {
        $fixture = $this->fixture();
        $closedPeriod = $fixture['periods']->close($fixture['period']);

        try {
            app(FinanceJournalService::class)->reverse(
                $fixture['organization'], $fixture['scope'], $fixture['original'],
                $closedPeriod, '2026-11-01', 'Closed period should be rejected.',
            );
            $this->fail('A reversal must not be posted to a closed period.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('The financial period is closed.', $exception->getMessage());
        }

        $openPeriod = $fixture['periods']->create($fixture['organization'], $fixture['scope'], [
            'name' => 'FY 2027',
            'code' => 'FY2027',
            'starts_on' => '2027-01-01',
            'ends_on' => '2027-12-31',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The reversal transaction date is outside the financial period.');

        app(FinanceJournalService::class)->reverse(
            $fixture['organization'], $fixture['scope'], $fixture['original'],
            $openPeriod, '2026-12-31', 'Date outside selected period.',
        );
    }

    public function test_draft_journal_cannot_be_reversed(): void
    {
        $fixture = $this->fixture();
        $draft = FinanceJournal::create([
            'organization_id' => $fixture['organization']->id,
            'finance_scope_id' => $fixture['scope']->id,
            'finance_period_id' => $fixture['period']->id,
            'journal_number' => 'JRN-DRAFT-REVERSAL',
            'transaction_date' => '2026-10-10',
            'status' => FinanceJournalStatus::DRAFT,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only posted journals can be reversed.');

        app(FinanceJournalService::class)->reverse(
            $fixture['organization'], $fixture['scope'], $draft,
            $fixture['period'], '2026-11-01', 'Draft journal reversal attempt.',
        );
    }
}
