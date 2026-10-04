<?php

namespace Modules\Core\Tests\Feature\Authentication;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\OtpChallenge;
use Tests\TestCase;

class OtpChallengeTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_otp_challenge_can_be_created(): void
    {
        $user = User::factory()->create();

        $challenge = OtpChallenge::create([
            'user_id' => $user->id,
            'purpose' => 'login',
            'channel' => 'email',
            'destination' => $user->email,
            'code_hash' => password_hash('123456', PASSWORD_DEFAULT),
            'expires_at' => now()->addMinutes(5),
            'max_attempts' => 5,
        ]);

        $this->assertDatabaseHas('otp_challenges', [
            'id' => $challenge->id,
            'user_id' => $user->id,
            'purpose' => 'login',
            'channel' => 'email',
            'destination' => $user->email,
            'attempts' => 0,
            'max_attempts' => 5,
        ]);
    }

    public function test_otp_code_is_not_stored_as_plaintext(): void
    {
        $user = User::factory()->create();

        $plainCode = '123456';

        $challenge = OtpChallenge::create([
            'user_id' => $user->id,
            'purpose' => 'login',
            'channel' => 'email',
            'destination' => $user->email,
            'code_hash' => password_hash($plainCode, PASSWORD_DEFAULT),
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->assertNotSame(
            $plainCode,
            $challenge->code_hash
        );

        $this->assertTrue(
            password_verify($plainCode, $challenge->code_hash)
        );
    }

    public function test_it_belongs_to_a_user(): void
    {
        $user = User::factory()->create();

        $challenge = OtpChallenge::create([
            'user_id' => $user->id,
            'purpose' => 'login',
            'channel' => 'email',
            'destination' => $user->email,
            'code_hash' => password_hash('123456', PASSWORD_DEFAULT),
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->assertTrue(
            $challenge->user->is($user)
        );
    }

    public function test_expiration_and_verification_are_cast_to_datetime(): void
    {
        $user = User::factory()->create();

        $challenge = OtpChallenge::create([
            'user_id' => $user->id,
            'purpose' => 'login',
            'channel' => 'email',
            'destination' => $user->email,
            'code_hash' => password_hash('123456', PASSWORD_DEFAULT),
            'expires_at' => now()->addMinutes(5),
            'verified_at' => now(),
        ]);

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $challenge->expires_at
        );

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $challenge->verified_at
        );
    }
}