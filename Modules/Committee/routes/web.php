<?php

use Illuminate\Support\Facades\Route;
use Modules\Committee\Http\Controllers\CommitteeController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('committees', CommitteeController::class)->names('committee');
});
