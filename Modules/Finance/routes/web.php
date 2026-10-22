<?php

use Illuminate\Support\Facades\Route;
use Modules\Finance\Livewire\FinancePeriodManagement;

Route::middleware(['auth', 'organization', 'organization.access', 'module:Finance'])
    ->group(function (): void {
        Route::get('/organization/finance/periods', FinancePeriodManagement::class)
            ->name('finance.periods');
    });
