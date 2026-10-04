<?php

namespace Modules\Core\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Models\OtpChallenge;
use Modules\Core\Services\AuthenticationService;

class AuthenticationController
{
    public function __construct(
        protected AuthenticationService $authenticationService,
    ) {
    }

    public function requestEmailOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => [
                'required',
                'email',
            ],
        ]);

        $result = $this->authenticationService->requestEmailOtp(
            $validated['email']
        );

        return response()->json([
            'message' => 'OTP sent successfully.',
            'challenge_id' => $result['challenge']->id,
        ]);
    }

    public function verifyEmailOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'challenge_id' => [
                'required',
                'integer',
                'exists:otp_challenges,id',
            ],
            'code' => [
                'required',
                'digits:6',
            ],
        ]);

        $challenge = OtpChallenge::findOrFail(
            $validated['challenge_id']
        );

        $token = $this->authenticationService->verifyEmailOtpForApi(
            $challenge,
            $validated['code'],
            $request,
        );

        return response()->json([
            'message' => 'Authentication successful.',
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => [
                'id' => $request->user()->id,
                'email' => $request->user()->email,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authenticationService->logout(
            $request->user()
        );

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}