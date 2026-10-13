<?php

use App\Http\Controllers\Auth\EmailOtpLoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Session-based browser sign-in. The API's bearer-token flow remains unchanged.
Route::middleware('guest')->group(function () {
    Route::get('/login', [EmailOtpLoginController::class, 'showLogin'])->name('login');
    Route::post('/login/otp', [EmailOtpLoginController::class, 'requestOtp'])->name('login.request-otp');
    Route::get('/login/verify', [EmailOtpLoginController::class, 'showVerify'])->name('login.verify.form');
    Route::post('/login/verify', [EmailOtpLoginController::class, 'verifyOtp'])->name('login.verify');
});

// Local-only design-system preview. Never expose mock UI data in production.
if (app()->environment('local')) {
    Route::view('/ui-preview', 'ui.preview')->name('ui.preview');
}
