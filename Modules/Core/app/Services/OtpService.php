<?php

namespace Modules\Core\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Models\OtpChallenge;

class OtpService
{
    private const OTP_LENGTH = 6;

    private const DEFAULT_EXPIRY_MINUTES = 5;

    private const DEFAULT_MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly EmailOtpChannel $emailChannel,
    ) {
    }

    public function create(
        User $user,
        string $purpose,
        string $channel,
        string $destination,
    ): array {
        $code = $this->generateCode();

        $challenge = OtpChallenge::create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'channel' => $channel,
            'destination' => $destination,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(
                self::DEFAULT_EXPIRY_MINUTES
            ),
            'attempts' => 0,
            'max_attempts' => self::DEFAULT_MAX_ATTEMPTS,
        ]);

        $this->emailChannel->send(
            $user,
            $challenge,
            $code,
        );

        return [
            'challenge' => $challenge,
            'code' => $code,
        ];
    }

    public function verify(
        OtpChallenge $challenge,
        string $code,
    ): bool {
        if ($challenge->verified_at !== null) {
            return false;
        }

        if ($challenge->expires_at->isPast()) {
            return false;
        }

        if ($challenge->attempts >= $challenge->max_attempts) {
            return false;
        }

        if (! Hash::check($code, $challenge->code_hash)) {
            $challenge->increment('attempts');

            return false;
        }

        $challenge->update([
            'verified_at' => now(),
        ]);

        return true;
    }

    private function generateCode(): string
    {
        return str_pad(
            (string) random_int(0, 999999),
            self::OTP_LENGTH,
            '0',
            STR_PAD_LEFT
        );
    }
}