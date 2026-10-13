<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Models\OtpChallenge;
use Modules\Core\Services\AuthenticationService;

class EmailOtpLoginController
{
    public function showLogin(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->intended('/');
        }

        return view('auth.login');
    }

    public function requestOtp(Request $request, AuthenticationService $authenticationService): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $result = $authenticationService->requestEmailOtp($validated['email']);

        $request->session()->put([
            'auth.otp_challenge_id' => $result['challenge']->id,
            'auth.otp_email' => $validated['email'],
        ]);

        return redirect()->route('login.verify.form')
            ->with('status', 'A sign-in code has been sent to your email address.');
    }

    public function showVerify(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('auth.otp_challenge_id')) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Request a sign-in code first.']);
        }

        return view('auth.verify-otp', [
            'email' => $request->session()->get('auth.otp_email'),
        ]);
    }

    public function verifyOtp(Request $request, AuthenticationService $authenticationService): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $challengeId = $request->session()->get('auth.otp_challenge_id');
        $challenge = OtpChallenge::query()->findOrFail($challengeId);

        $authenticationService->verifyEmailOtp($challenge, $validated['code']);

        $request->session()->regenerate();
        $request->session()->forget(['auth.otp_challenge_id', 'auth.otp_email']);

        return redirect()->intended('/')
            ->with('status', 'You are now signed in.');
    }
}
