<?php

namespace Modules\Core\Tests\Feature\Authentication;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Models\OtpChallenge;
use Modules\Core\Notifications\OtpNotification;
use Modules\Core\Services\OtpService;
use Tests\TestCase;

class OtpServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_otp_challenge(): void
    {
        $user = User::factory()->create();

        $service = app(OtpService::class);

        $result = $service->create(
            $user,
            'login',
            'email',
            $user->email,
        );

        $challenge = $result['challenge'];

        $this->assertInstanceOf(
            OtpChallenge::class,
            $challenge
        );

        $this->assertSame($user->id, $challenge->user_id);
        $this->assertSame('login', $challenge->purpose);
        $this->assertSame('email', $challenge->channel);
        $this->assertSame($user->email, $challenge->destination);
        $this->assertNotEmpty($challenge->code_hash);
        $this->assertNull($challenge->verified_at);
        $this->assertSame(0, $challenge->attempts);
        $this->assertSame(5, $challenge->max_attempts);
        $this->assertTrue($challenge->expires_at->isFuture());
    }

    public function test_it_returns_the_plain_otp_only_when_creating_the_challenge(): void
    {
        $user = User::factory()->create();

        $service = app(OtpService::class);

        $result = $service->create(
            $user,
            'login',
            'email',
            $user->email,
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('challenge', $result);
        $this->assertArrayHasKey('code', $result);

        $this->assertInstanceOf(
            OtpChallenge::class,
            $result['challenge']
        );

        $this->assertMatchesRegularExpression(
            '/^\d{6}$/',
            $result['code']
        );

        $this->assertTrue(
            password_verify(
                $result['code'],
                $result['challenge']->code_hash
            )
        );
    }

    public function test_it_verifies_a_valid_otp(): void
    {
        $user = User::factory()->create();

        $service = app(OtpService::class);

        $result = $service->create(
            $user,
            'login',
            'email',
            $user->email,
        );

        $verified = $service->verify(
            $result['challenge'],
            $result['code'],
        );

        $this->assertTrue($verified);

        $this->assertNotNull(
            $result['challenge']->fresh()->verified_at
        );
    }

    public function test_it_rejects_an_invalid_otp(): void
    {
        $user = User::factory()->create();

        $service = app(OtpService::class);

        $result = $service->create(
            $user,
            'login',
            'email',
            $user->email,
        );

        $verified = $service->verify(
            $result['challenge'],
            '000000',
        );

        $this->assertFalse($verified);

        $this->assertSame(
            1,
            $result['challenge']->fresh()->attempts
        );

        $this->assertNull(
            $result['challenge']->fresh()->verified_at
        );
    }

    public function test_it_rejects_an_expired_otp(): void
    {
        $user = User::factory()->create();

        $service = app(OtpService::class);

        $result = $service->create(
            $user,
            'login',
            'email',
            $user->email,
        );

        $result['challenge']->update([
            'expires_at' => now()->subMinute(),
        ]);

        $verified = $service->verify(
            $result['challenge']->fresh(),
            $result['code'],
        );

        $this->assertFalse($verified);

        $this->assertSame(
            0,
            $result['challenge']->fresh()->attempts
        );
    }

    public function test_it_cannot_verify_an_already_verified_otp(): void
    {
        $user = User::factory()->create();

        $service = app(OtpService::class);

        $result = $service->create(
            $user,
            'login',
            'email',
            $user->email,
        );

        $service->verify(
            $result['challenge'],
            $result['code'],
        );

        $verifiedAgain = $service->verify(
            $result['challenge']->fresh(),
            $result['code'],
        );

        $this->assertFalse($verifiedAgain);
    }

    public function test_it_rejects_an_otp_after_maximum_attempts(): void
    {
        $user = User::factory()->create();

        $service = app(OtpService::class);

        $result = $service->create(
            $user,
            'login',
            'email',
            $user->email,
        );

        for ($i = 0; $i < 5; $i++) {
            $service->verify(
                $result['challenge']->fresh(),
                '000000',
            );
        }

        $challenge = $result['challenge']->fresh();

        $this->assertSame(5, $challenge->attempts);

        $verified = $service->verify(
            $challenge,
            $result['code'],
        );

        $this->assertFalse($verified);

        $this->assertSame(
            5,
            $challenge->fresh()->attempts
        );
    }

    public function test_successful_verification_does_not_increment_attempts(): void
    {
        $user = User::factory()->create();

        $service = app(OtpService::class);

        $result = $service->create(
            $user,
            'login',
            'email',
            $user->email,
        );

        $service->verify(
            $result['challenge'],
            $result['code'],
        );

        $challenge = $result['challenge']->fresh();

        $this->assertSame(0, $challenge->attempts);
        $this->assertNotNull($challenge->verified_at);
    }

    public function test_it_sends_the_otp_through_the_email_channel(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $service = app(OtpService::class);

        $result = $service->create(
            $user,
            'login',
            'email',
            $user->email,
        );

        Notification::assertSentTo(
            new AnonymousNotifiable,
            OtpNotification::class,
            function (OtpNotification $notification) use ($result) {
                return $notification->code() === $result['code'];
            },
        );
    }
}