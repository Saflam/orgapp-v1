<?php

namespace App\Http\Controllers\Auth;

use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\PhoneNumbers\PhoneNumberNormalizer;
use App\Support\Organization\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Core\Models\OtpChallenge;
use Modules\Core\Services\OtpService;
use libphonenumber\PhoneNumberUtil;
use InvalidArgumentException;

class OrganizationRegistrationController
{
    public function show(Request $request, CurrentOrganization $currentOrganization): View|RedirectResponse
    {
        if ($request->user()) {
            // A signed-in user who reaches registration should continue to the
            // membership application rather than being sent to the organization home.
            return redirect()->route('organization.membership.apply');
        }

        $organization = $currentOrganization->get();
        $countries = $this->supportedPhoneCountries();
        $configuredCountry = strtoupper((string) (
            data_get($organization, 'settings.default_phone_country_iso2')
            ?? data_get($organization, 'settings.country_iso2')
            ?? config('app.default_phone_country', 'IN')
        ));
        $defaultPhoneCountry = collect($countries)->contains(fn (array $country): bool => $country['iso2'] === $configuredCountry)
            ? $configuredCountry
            : 'IN';

        return view('auth.register', [
            'organization' => $organization,
            'phoneCountries' => $countries,
            'defaultPhoneCountry' => $defaultPhoneCountry,
        ]);
    }

    public function register(
        Request $request,
        CurrentOrganization $currentOrganization,
        OtpService $otpService,
    ): RedirectResponse {
        $countries = $this->supportedPhoneCountries();
        $supportedRegions = array_column($countries, 'iso2');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone_country_iso2' => ['required', 'string', 'size:2', Rule::in($supportedRegions)],
            'phone' => [
                'required',
                'string',
                'max:30',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if (! is_string($value)) {
                        $fail('Enter a valid phone number.');
                        return;
                    }

                    $phone = trim($value);
                    $country = str_starts_with($phone, '+')
                        ? null
                        : strtoupper((string) $request->input('phone_country_iso2', ''));

                    try {
                        $this->normalizeRegistrationPhone($phone, $country ?? strtoupper((string) $request->input('phone_country_iso2', '')));
                    } catch (InvalidArgumentException) {
                        $fail('Enter a valid phone number. If you enter a number without a + country code, select its country.');
                    }
                },
            ],
        ]);

        $rawPhone = trim($validated['phone']);
        // An explicitly international number is authoritative; do not apply the
        // dropdown's default country to it or reject it because the dropdown differs.
        $normalizedPhone = $this->normalizeRegistrationPhone(
            $rawPhone,
            strtoupper($validated['phone_country_iso2']),
        )['phone_e164'];

        $existingAccount = User::query()
            ->where('email', mb_strtolower(trim($validated['email'])))
            ->orWhere('phone', $normalizedPhone)
            ->orWhere('phone', $rawPhone) // compatibility with accounts created before E.164 normalization
            ->exists();

        if ($existingAccount) {
            return back()
                ->withInput($request->only('name', 'email', 'phone', 'phone_country_iso2'))
                ->withErrors([
                    'email' => 'An account with this email address or phone number already exists. Please sign in with your existing account.',
                ]);
        }

        $user = DB::transaction(function () use ($validated, $normalizedPhone): User {
            return User::query()->create([
                'name' => trim($validated['name']),
                'email' => mb_strtolower(trim($validated['email'])),
                'phone' => $normalizedPhone,
                'password' => null,
                'email_verified_at' => null,
            ]);
        });

        try {
            $result = $otpService->create(
                $user,
                'registration',
                'email',
                $user->email,
            );
        } catch (\Throwable $exception) {
            // Avoid leaving an unusable account behind if the verification email could not be sent.
            $user->delete();
            throw $exception;
        }

        $request->session()->put([
            'auth.registration_challenge_id' => $result['challenge']->id,
            'auth.registration_email' => $user->email,
            'auth.registration_organization_id' => $currentOrganization->get()->id,
        ]);

        return redirect()->route('organization.register.verify.form')
            ->with('status', 'A verification code has been sent to your email address.');
    }

    public function showVerify(Request $request, CurrentOrganization $currentOrganization): View|RedirectResponse
    {
        if (! $request->session()->has('auth.registration_challenge_id')) {
            return redirect()->route('organization.register')
                ->withErrors(['email' => 'Start registration before entering a verification code.']);
        }

        if ((int) $request->session()->get('auth.registration_organization_id') !== $currentOrganization->get()->id) {
            $request->session()->forget([
                'auth.registration_challenge_id',
                'auth.registration_email',
                'auth.registration_organization_id',
            ]);

            return redirect()->route('organization.register')
                ->withErrors(['email' => 'Please restart registration from this organization website.']);
        }

        return view('auth.verify-registration-otp', [
            'email' => $request->session()->get('auth.registration_email'),
            'organization' => $currentOrganization->get(),
        ]);
    }

    public function verify(
        Request $request,
        CurrentOrganization $currentOrganization,
        OtpService $otpService,
    ): RedirectResponse {
        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $challengeId = $request->session()->get('auth.registration_challenge_id');
        $challenge = OtpChallenge::query()->with('user')->find($challengeId);

        if (
            ! $challenge
            || $challenge->purpose !== 'registration'
            || $challenge->channel !== 'email'
            || (int) $request->session()->get('auth.registration_organization_id') !== $currentOrganization->get()->id
        ) {
            return redirect()->route('organization.register')
                ->withErrors(['code' => 'This registration verification session is invalid. Please register again.']);
        }

        if (! $otpService->verify($challenge, $validated['code'])) {
            return back()->withErrors(['code' => 'The verification code is invalid or has expired.']);
        }

        DB::transaction(function () use ($challenge, $currentOrganization): void {
            $user = User::query()->lockForUpdate()->findOrFail($challenge->user_id);
            $user->forceFill(['email_verified_at' => now()])->save();

            OrganizationMembership::query()->firstOrCreate(
                [
                    'organization_id' => $currentOrganization->get()->id,
                    'user_id' => $user->id,
                ],
                [
                    'status' => 'active',
                    'joined_at' => now(),
                    'metadata' => ['source' => 'self_registration'],
                ],
            );
        });

        Auth::login($challenge->user->fresh());
        $request->session()->regenerate();
        $request->session()->forget([
            'auth.registration_challenge_id',
            'auth.registration_email',
            'auth.registration_organization_id',
        ]);

        $intended = $request->session()->pull('url.intended');
        $intendedHost = is_string($intended) ? parse_url($intended, PHP_URL_HOST) : null;
        $intendedPath = is_string($intended) ? parse_url($intended, PHP_URL_PATH) : null;
        $safeIntended = is_string($intended)
            && trim($intended) !== ''
            && (! is_string($intendedHost) || strcasecmp($intendedHost, $request->getHost()) === 0)
            && is_string($intendedPath)
            && str_starts_with($intendedPath, '/')
            && ! str_starts_with($intendedPath, '//')
            && ! in_array($intendedPath, [
                '/login',
                '/login/otp',
                '/login/verify',
                '/register',
                '/register/verify',
            ], true);

        $destination = $safeIntended
            ? $intendedPath . (($query = parse_url($intended, PHP_URL_QUERY)) ? '?' . $query : '')
            : route('organization.membership.apply');

        return redirect($destination)
            ->with('status', 'Your account is verified and you now have access to ' . $currentOrganization->get()->name . '.');
    }

    /**
     * Normalize a registration number. A leading + or 00 is explicitly
     * international; a country-code prefix without + is accepted when it
     * parses as a valid international number after national parsing fails.
     *
     * @return array{phone_e164: string, country_iso2: ?string, country_calling_code: string, national_number: string, extension: ?string}
     */
    private function normalizeRegistrationPhone(string $value, string $countryIso2): array
    {
        $value = trim($value);
        $normalizer = app(PhoneNumberNormalizer::class);

        if (str_starts_with($value, '+')) {
            return $normalizer->normalize($value, null);
        }

        if (str_starts_with($value, '00')) {
            return $normalizer->normalize('+' . substr($value, 2), null);
        }

        try {
            return $normalizer->normalize($value, $countryIso2);
        } catch (InvalidArgumentException $nationalException) {
            $digits = preg_replace('/\D+/', '', $value);
            if (! is_string($digits) || strlen($digits) < 8) {
                throw $nationalException;
            }

            $phoneUtil = PhoneNumberUtil::getInstance();
            $callingCodes = collect($phoneUtil->getSupportedRegions())
                ->map(fn (string $region): string => (string) $phoneUtil->getCountryCodeForRegion($region))
                ->filter(fn (string $code): bool => $code !== '0' && str_starts_with($digits, $code))
                ->unique()
                ->sortByDesc(fn (string $code): int => strlen($code))
                ->values();

            foreach ($callingCodes as $callingCode) {
                if (strlen($digits) <= strlen($callingCode) + 5) {
                    continue;
                }

                try {
                    return $normalizer->normalize('+' . $digits, null);
                } catch (InvalidArgumentException) {
                    // Try the next possible calling-code prefix.
                }
            }

            throw $nationalException;
        }
    }

    /** @return array<int, array{iso2: string, calling_code: string, label: string}> */
    private function supportedPhoneCountries(): array
    {
        $phoneUtil = PhoneNumberUtil::getInstance();

        return collect($phoneUtil->getSupportedRegions())
            ->filter(fn (string $region): bool => preg_match('/^[A-Z]{2}$/', $region) === 1)
            ->map(function (string $region) use ($phoneUtil): array {
                $name = $region;
                if (class_exists(\Symfony\Component\Intl\Countries::class)) {
                    try {
                        $name = \Symfony\Component\Intl\Countries::getName($region) ?: $region;
                    } catch (\Throwable) {
                        $name = $region;
                    }
                }

                return [
                    'iso2' => $region,
                    'calling_code' => (string) $phoneUtil->getCountryCodeForRegion($region),
                    'label' => $name,
                ];
            })
            ->filter(fn (array $country): bool => $country['calling_code'] !== '0')
            ->sortBy('label')
            ->values()
            ->all();
    }

}
