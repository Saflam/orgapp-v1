<?php

use Illuminate\Support\Facades\Route;
use Modules\Saradhi\Http\Controllers\SaradhiController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('saradhis', SaradhiController::class)->names('saradhi');
});
