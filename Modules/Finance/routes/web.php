<?php

use Illuminate\Support\Facades\Route;
use Modules\Finance\Http\Controllers\FinanceTrialBalancePdfController;
use Modules\Finance\Livewire\FinanceChartOfAccountsManagement;
use Modules\Finance\Livewire\FinanceJournalDetail;
use Modules\Finance\Livewire\FinanceJournalManagement;
use Modules\Finance\Livewire\FinancePeriodManagement;
use Modules\Finance\Livewire\FinanceTaxConfigurationManagement;
use Modules\Finance\Livewire\FinanceTrialBalanceReport;

Route::middleware(['auth', 'organization', 'organization.access', 'module:Finance'])
    ->group(function (): void {
        Route::get('/organization/finance/accounts', FinanceChartOfAccountsManagement::class)->name('finance.accounts');
        Route::get('/organization/finance/periods', FinancePeriodManagement::class)->name('finance.periods');
        Route::get('/organization/finance/taxes', FinanceTaxConfigurationManagement::class)->name('finance.taxes');
        Route::get('/organization/finance/journals', FinanceJournalManagement::class)->name('finance.journals');
        Route::get('/organization/finance/journals/{journalId}', FinanceJournalDetail::class)->name('finance.journals.show');
        Route::get('/organization/finance/reports/trial-balance', FinanceTrialBalanceReport::class)->name('finance.reports.trial-balance');
        Route::get('/organization/finance/reports/trial-balance/pdf', FinanceTrialBalancePdfController::class)
            ->name('finance.reports.trial-balance.pdf');
    });
