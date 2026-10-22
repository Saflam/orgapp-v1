<?php

use App\Http\Controllers\Auth\EmailOtpLoginController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Support\Organization\OrganizationResolver;

Route::get('/', function (\Illuminate\Http\Request $request, OrganizationResolver $resolver) {
    // Tenant hosts use the organization website; the base host remains Saflivo's product site.
    if ($resolver->resolve($request->getHost())) {
        return redirect()->route('organization.home');
    }

    return view('welcome');
})->name('home');

// Dedicated system administration entry point. Authentication and authorization are enforced server-side.
Route::middleware(['auth', 'system.admin'])->prefix('admin')->name('system.admin.')->group(function (): void {
    Route::get('/', fn () => redirect()->route('core.organizations.index'))->name('home');
});

// The login page must be reachable by authenticated users too, so the controller can
// send them to the correct dashboard instead of guest middleware redirecting to '/'.
Route::get('/login', [EmailOtpLoginController::class, 'showLogin'])->name('login');

// OTP issuance and verification are only for guests. The API bearer-token flow is unchanged.
Route::middleware('guest')->group(function () {
    Route::post('/login/otp', [EmailOtpLoginController::class, 'requestOtp'])->name('login.request-otp');
    Route::get('/login/verify', [EmailOtpLoginController::class, 'showVerify'])->name('login.verify.form');
    Route::post('/login/verify', [EmailOtpLoginController::class, 'verifyOtp'])->name('login.verify');
});

// Browser logout is available on both the central host and tenant hosts.
Route::post('/logout', function (Request $request) {
    Auth::guard('web')->logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    // Relative redirect preserves the current host: tenant root resolves to its
    // organization homepage; the central root resolves to the Saflivo website.
    return redirect('/');
})->middleware('auth')->name('logout');

// Local-only design-system preview. Never expose mock UI data in production.
if (app()->environment('local')) {
    Route::view('/ui-preview', 'ui.preview')->name('ui.preview');
}
