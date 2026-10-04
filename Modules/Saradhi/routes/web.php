<?php

use Illuminate\Support\Facades\Route;
use Modules\Saradhi\Http\Controllers\SaradhiController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('saradhis', SaradhiController::class)->names('saradhi');
});
