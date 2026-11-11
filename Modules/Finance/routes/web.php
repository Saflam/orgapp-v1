<?php

use Illuminate\Support\Facades\Route;
use Modules\Finance\Http\Controllers\FinanceBalanceSheetPdfController;
use Modules\Finance\Http\Controllers\FinanceCashFlowStatementPdfController;
use Modules\Finance\Http\Controllers\FinanceIncomeStatementPdfController;
use Modules\Finance\Http\Controllers\FinanceTrialBalancePdfController;
use Modules\Finance\Livewire\FinanceBalanceSheetReport;
use Modules\Finance\Livewire\FinanceCashFlowStatementReport;
use Modules\Finance\Livewire\FinanceChartOfAccountsManagement;
use Modules\Finance\Livewire\FinanceIncomeStatementReport;
use Modules\Finance\Livewire\FinanceJournalDetail;
use Modules\Finance\Livewire\FinanceJournalManagement;
use Modules\Finance\Livewire\FinancePeriodManagement;
use Modules\Finance\Livewire\FinancePartyManagement;
use Modules\Finance\Livewire\FinanceReceivablesPayablesManagement;
use Modules\Finance\Livewire\FinanceSettlementManagement;
use Modules\Finance\Livewire\FinanceTaxConfigurationManagement;
use Modules\Finance\Livewire\FinanceTrialBalanceReport;

Route::middleware(['auth', 'organization', 'organization.access', 'module:Finance'])
    ->group(function (): void {
        Route::get('/organization/finance/accounts', FinanceChartOfAccountsManagement::class)->name('finance.accounts');
        Route::get('/organization/finance/periods', FinancePeriodManagement::class)->name('finance.periods');
        Route::get('/organization/finance/taxes', FinanceTaxConfigurationManagement::class)->name('finance.taxes');
        Route::get('/organization/finance/receivables-payables', FinanceReceivablesPayablesManagement::class)->name('finance.receivables-payables');
        Route::get('/organization/finance/parties', FinancePartyManagement::class)->name('finance.parties');
        Route::get('/organization/finance/journals', FinanceJournalManagement::class)->name('finance.journals');
        Route::get('/organization/finance/settlements', FinanceSettlementManagement::class)->name('finance.settlements');
        Route::get('/organization/finance/journals/{journalId}', FinanceJournalDetail::class)->name('finance.journals.show');
        Route::get('/organization/finance/reports/trial-balance', FinanceTrialBalanceReport::class)->name('finance.reports.trial-balance');
        Route::get('/organization/finance/reports/income-statement', FinanceIncomeStatementReport::class)->name('finance.reports.income-statement');
        Route::get('/organization/finance/reports/balance-sheet', FinanceBalanceSheetReport::class)->name('finance.reports.balance-sheet');
        Route::get('/organization/finance/reports/cash-flow-statement', FinanceCashFlowStatementReport::class)
            ->name('finance.reports.cash-flow-statement');
        Route::get('/organization/finance/reports/trial-balance/pdf', FinanceTrialBalancePdfController::class)
            ->name('finance.reports.trial-balance.pdf');
        Route::get('/organization/finance/reports/income-statement/pdf', FinanceIncomeStatementPdfController::class)
            ->name('finance.reports.income-statement.pdf');
        Route::get('/organization/finance/reports/balance-sheet/pdf', FinanceBalanceSheetPdfController::class)
            ->name('finance.reports.balance-sheet.pdf');
        Route::get('/organization/finance/reports/cash-flow-statement/pdf', FinanceCashFlowStatementPdfController::class)
            ->name('finance.reports.cash-flow-statement.pdf');
    });
