<?php

namespace Modules\Core\Tests\Feature\Authentication;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\OtpChallenge;
use Modules\Core\Notifications\OtpNotification;
use Modules\Core\Services\AuthenticationService;
use Tests\TestCase;

class AuthenticationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_and_sends_an_email_login_otp(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $service = app(AuthenticationService::class);

        $result = $service->requestEmailOtp($user->email);

        $this->assertArrayHasKey('challenge', $result);

        $this->assertInstanceOf(
            OtpChallenge::class,
            $result['challenge']
        );

        $this->assertSame(
            $user->id,
            $result['challenge']->user_id
        );

        $this->assertSame(
            'login',
            $result['challenge']->purpose
        );

        Notification::assertSentTo(
            new AnonymousNotifiable,
            OtpNotification::class
        );
    }

    public function test_it_rejects_unknown_email(): void
    {
        $service = app(AuthenticationService::class);

        $this->expectException(ValidationException::class);

        $service->requestEmailOtp('unknown@example.com');
    }

    public function test_it_logs_the_user_in_after_valid_email_otp(): void
    {
        $user = User::factory()->create();

        $otpService = app(\Modules\Core\Services\OtpService::class);

        $result = $otpService->create(
            $user,
            'login',
            'email',
            $user->email,
        );

        $service = app(AuthenticationService::class);

        $authenticatedUser = $service->verifyEmailOtp(
            $result['challenge'],
            $result['code'],
        );

        $this->assertTrue(
            $authenticatedUser->is($user)
        );

        $this->assertAuthenticatedAs($user);
    }

    public function test_it_rejects_an_invalid_email_otp(): void
    {
        $user = User::factory()->create();

        $otpService = app(\Modules\Core\Services\OtpService::class);

        $result = $otpService->create(
            $user,
            'login',
            'email',
            $user->email,
        );

        $service = app(AuthenticationService::class);

        $this->expectException(ValidationException::class);

        $service->verifyEmailOtp(
            $result['challenge'],
            '000000',
        );

        $this->assertGuest();
    }
}