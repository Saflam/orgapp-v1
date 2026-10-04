<?php

namespace Modules\Core\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\LoginActivity;
use Modules\Core\Models\OtpChallenge;

class AuthenticationService
{
    public function __construct(
        protected OtpService $otpService,
        protected EmailOtpChannel $emailOtpChannel,
        protected LoginActivityService $loginActivityService,
    ) {
    }

    public function requestEmailOtp(string $email): array
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'No account was found with this email address.',
            ]);
        }

        $result = $this->otpService->create(
            $user,
            'login',
            'email',
            $user->email,
        );

        $this->emailOtpChannel->send(
            $user,
            $result['challenge'],
            $result['code'],
        );

        return [
            'challenge' => $result['challenge'],
        ];
    }

    public function verifyEmailOtp(
        OtpChallenge $challenge,
        string $code,
    ): User {
        if ($challenge->purpose !== 'login') {
            throw ValidationException::withMessages([
                'code' => 'Invalid authentication challenge.',
            ]);
        }

        if ($challenge->channel !== 'email') {
            throw ValidationException::withMessages([
                'code' => 'Invalid authentication channel.',
            ]);
        }

        if (! $this->otpService->verify($challenge, $code)) {
            throw ValidationException::withMessages([
                'code' => 'The OTP is invalid or has expired.',
            ]);
        }

        $user = $challenge->user;

        Auth::login($user);

        $this->loginActivityService->recordLogin(
            $user,
            'email',
        );

        return $user;
    }

    public function verifyEmailOtpForApi(
        OtpChallenge $challenge,
        string $code,
        ?Request $request = null,
    ): string {
        if ($challenge->purpose !== 'login') {
            throw ValidationException::withMessages([
                'code' => 'Invalid authentication challenge.',
            ]);
        }

        if ($challenge->channel !== 'email') {
            throw ValidationException::withMessages([
                'code' => 'Invalid authentication channel.',
            ]);
        }

        if (! $this->otpService->verify($challenge, $code)) {
            throw ValidationException::withMessages([
                'code' => 'The OTP is invalid or has expired.',
            ]);
        }

        return $this->createApiToken(
            $challenge->user,
            'mobile',
            $request,
        );
    }

    public function createApiToken(
        User $user,
        string $name = 'mobile',
        ?Request $request = null,
    ): string {
        $token = $user->createToken($name);

        $this->loginActivityService->recordLogin(
            $user,
            'email',
            $token->accessToken,
            $request,
        );

        return $token->plainTextToken;
    }

    public function logout(User $user): void
    {
        $accessToken = $user->currentAccessToken();

        if ($accessToken === null) {
            return;
        }

        $activity = LoginActivity::query()
            ->where('token_id', $accessToken->id)
            ->whereNull('logged_out_at')
            ->first();

        if ($activity !== null) {
            $this->loginActivityService->recordLogout($activity);
        }

        $accessToken->delete();
    }
}