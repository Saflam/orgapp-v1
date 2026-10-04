<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Api\AuthenticationController;
use Modules\Core\Http\Controllers\Api\OrganizationController;

Route::prefix('v1')->group(function () {
    Route::post('/login/otp/request', [AuthenticationController::class, 'requestEmailOtp']);
    Route::post('/login/otp/verify', [AuthenticationController::class, 'verifyEmailOtp']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthenticationController::class, 'me',]);
        Route::post('/auth/logout', [AuthenticationController::class, 'logout',]);

        Route::middleware(['organization','organization.access'])->group(function () {
            Route::get('/organization', [OrganizationController::class, 'show',]);
        });

        Route::prefix('system')->middleware('system.admin')->group(function () {
            Route::post('/organizations', [OrganizationController::class, 'store'],);
            Route::post('/organizations/{organization}/activate', [OrganizationController::class, 'activate'],);
            Route::post('/organizations/{organization}/deactivate', [OrganizationController::class, 'deactivate'],);
            Route::post('/organizations/{organization}/super-admin', [OrganizationController::class, 'replaceSuperAdmin'],);
            Route::delete('/organizations/{organization}/super-admin', [OrganizationController::class, 'removeSuperAdmin'],);

            Route::get('/organizations/{organization}/modules', [OrganizationController::class, 'modules'],);
            Route::post('/organizations/{organization}/modules/{module}/enable', [OrganizationController::class, 'enableModule'],);
            Route::post('/organizations/{organization}/modules/{module}/disable', [OrganizationController::class, 'disableModule'],);
        });
    });
});