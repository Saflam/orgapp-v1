<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Models\OtpChallenge;
use Modules\Core\Services\AuthenticationService;
use App\Support\Organization\OrganizationResolver;
use App\Models\OrganizationMembership;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Services\AuthorizationService;

class EmailOtpLoginController
{
    public function showLogin(
        Request $request,
        OrganizationResolver $organizationResolver,
        AuthorizationService $authorizationService,
    ): View|RedirectResponse {
        if ($request->user()) {
            $destination = $this->destinationAfterLogin(
                $request,
                $organizationResolver,
                $authorizationService,
            );

            $tenant = $organizationResolver->resolve($request->getHost());
            $user = $request->user();
            $isTenantAdministrator = $tenant !== null && (
                $user->is_system_admin || $authorizationService->can(
                    $user,
                    'organization.settings.manage',
                    new AuthorizationContext($tenant->id),
                )
            );

            // A protected page such as /membership/apply takes precedence over
            // the normal post-login destination, including for organization admins.
            // Ignore stale authentication URLs to prevent a verification redirect loop.
            $intended = $this->pullSafeIntendedDestination($request);

            return redirect($intended ?? $destination);
        }

        return view('auth.login');
    }

    public function requestOtp(Request $request, AuthenticationService $authenticationService, OrganizationResolver $organizationResolver): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        // Tenant-host login can use that organization's WhatsApp permissions and channel.
        // Central-host login has no tenant context and therefore keeps email-only OTP.
        $organization = $organizationResolver->resolve($request->getHost());
        $result = $authenticationService->requestEmailOtp($validated['email'], $organization);

        $request->session()->put([
            'auth.otp_challenge_id' => $result['challenge']->id,
            'auth.otp_email' => $validated['email'],
        ]);

        return redirect()->route('login.verify.form')
            ->with('status', 'A sign-in code has been sent to your email address. If WhatsApp is enabled for this organization and a phone number is available, it may also be sent to WhatsApp.');
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

    public function verifyOtp(
        Request $request,
        AuthenticationService $authenticationService,
        OrganizationResolver $organizationResolver,
        AuthorizationService $authorizationService,
    ): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $challengeId = $request->session()->get('auth.otp_challenge_id');
        $challenge = OtpChallenge::query()->findOrFail($challengeId);

        $authenticationService->verifyEmailOtp($challenge, $validated['code']);

        $request->session()->regenerate();
        $request->session()->forget(['auth.otp_challenge_id', 'auth.otp_email']);

        $destination = $this->destinationAfterLogin(
            $request,
            $organizationResolver,
            $authorizationService,
        );

        $tenant = $organizationResolver->resolve($request->getHost());
        $user = $request->user();
        $isTenantAdministrator = $tenant !== null && $user && (
            $user->is_system_admin || $authorizationService->can(
                $user,
                'organization.settings.manage',
                new AuthorizationContext($tenant->id),
            )
        );

        // Return to the page that initiated authentication (for example,
        // /membership/apply). If there is no safe intended page, retain the
        // existing role-aware destination, including the tenant-admin behavior.
        $intended = $this->pullSafeIntendedDestination($request);

        return redirect($intended ?? $destination)
            ->with('status', 'You are now signed in.');
    }


    /**
     * Consume a same-host intended URL, excluding authentication endpoints.
     * This keeps login/OTP flows from redirecting back to themselves and avoids
     * accepting arbitrary external redirect destinations.
     */
    private function pullSafeIntendedDestination(Request $request): ?string
    {
        $intended = $request->session()->pull('url.intended');

        if (! is_string($intended) || trim($intended) === '') {
            return null;
        }

        $host = parse_url($intended, PHP_URL_HOST);
        if (is_string($host) && strcasecmp($host, $request->getHost()) !== 0) {
            return null;
        }

        $path = parse_url($intended, PHP_URL_PATH);
        if (! is_string($path) || ! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return null;
        }

        if (in_array($path, [
            '/login',
            '/login/otp',
            '/login/verify',
            '/register',
            '/register/verify',
        ], true)) {
            return null;
        }

        $query = parse_url($intended, PHP_URL_QUERY);

        return $path . (is_string($query) && $query !== '' ? '?' . $query : '');
    }

    private function destinationAfterLogin(
        Request $request,
        OrganizationResolver $organizationResolver,
        AuthorizationService $authorizationService,
    ): string {
        $user = $request->user();
        $organization = $organizationResolver->resolve($request->getHost());

        if ($organization === null) {
            return $user?->is_system_admin ? '/admin' : '/';
        }

        if ($user?->is_system_admin || ($user && $authorizationService->can(
            $user,
            'organization.settings.manage',
            new AuthorizationContext($organization->id),
        ))) {
            // On an organization host, administrators belong in organization settings.
            // /admin is reserved for the central system-administration host.
            return '/organization/settings';
        }

        $isActiveMember = $user && OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        return $isActiveMember ? '/member/profile' : '/';
    }
}
