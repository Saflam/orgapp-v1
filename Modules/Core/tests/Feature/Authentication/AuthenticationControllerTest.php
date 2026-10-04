<?php

namespace Modules\Core\Tests\Feature\Authentication;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Core\Models\LoginActivity;
use Modules\Core\Notifications\OtpNotification;
use Modules\Core\Services\AuthenticationService;
use Modules\Core\Services\OtpService;
use Tests\TestCase;

class AuthenticationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_request_an_email_otp(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'member@example.com',
        ]);

        $response = $this->postJson('/api/v1/login/otp/request', [
            'email' => $user->email,
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'challenge_id',
            ]);

        Notification::assertSentTo(
            new AnonymousNotifiable,
            OtpNotification::class
        );
    }

    public function test_it_requires_a_valid_email(): void
    {
        $response = $this->postJson('/api/v1/login/otp/request', []);

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'email',
        ]);
    }

    public function test_it_rejects_an_invalid_email(): void
    {
        $response = $this->postJson('/api/v1/login/otp/request', [
            'email' => 'not-an-email',
        ]);

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'email',
        ]);
    }

    public function test_it_does_not_allow_unknown_email_to_request_otp(): void
    {
        $response = $this->postJson('/api/v1/login/otp/request', [
            'email' => 'unknown@example.com',
        ]);

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'email',
        ]);
    }

    public function test_it_can_authenticate_with_a_valid_email_otp(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
        ]);

        $otpService = app(OtpService::class);

        $result = $otpService->create(
            $user,
            'login',
            'email',
            $user->email,
        );

        $response = $this->postJson('/api/v1/login/otp/verify', [
            'challenge_id' => $result['challenge']->id,
            'code' => $result['code'],
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'token',
                'token_type',
            ])
            ->assertJson([
                'message' => 'Authentication successful.',
                'token_type' => 'Bearer',
            ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'mobile',
        ]);

        $this->assertNotNull(
            $result['challenge']->fresh()->verified_at
        );
    }

    public function test_it_rejects_an_invalid_otp(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
        ]);

        $otpService = app(OtpService::class);

        $result = $otpService->create(
            $user,
            'login',
            'email',
            $user->email,
        );

        $response = $this->postJson('/api/v1/login/otp/verify', [
            'challenge_id' => $result['challenge']->id,
            'code' => '000000',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'code',
            ]);

        $this->assertGuest();

        $this->assertSame(
            1,
            $result['challenge']->fresh()->attempts
        );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0
        );
    }

    public function test_it_rejects_an_expired_otp(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
        ]);

        $otpService = app(OtpService::class);

        $result = $otpService->create(
            $user,
            'login',
            'email',
            $user->email,
        );

        $result['challenge']->update([
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->postJson('/api/v1/login/otp/verify', [
            'challenge_id' => $result['challenge']->id,
            'code' => $result['code'],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'code',
            ]);

        $this->assertGuest();

        $this->assertSame(
            0,
            $result['challenge']->fresh()->attempts
        );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0
        );
    }

    public function test_it_cannot_reuse_a_verified_otp(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
        ]);

        $otpService = app(OtpService::class);

        $result = $otpService->create(
            $user,
            'login',
            'email',
            $user->email,
        );

        $this->postJson('/api/v1/login/otp/verify', [
            'challenge_id' => $result['challenge']->id,
            'code' => $result['code'],
        ])->assertOk();

        $tokenCount = \Laravel\Sanctum\PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->count();

        $this->assertSame(1, $tokenCount);

        $response = $this->postJson('/api/v1/login/otp/verify', [
            'challenge_id' => $result['challenge']->id,
            'code' => $result['code'],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'code',
            ]);

        $this->assertSame(
            1,
            $result['challenge']->fresh()->verified_at !== null
                ? 1
                : 0
        );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            1
        );
    }

    public function test_it_can_create_an_api_token_for_a_user(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
        ]);

        $authenticationService = app(AuthenticationService::class);

        $token = $authenticationService->createApiToken($user);

        $this->assertNotEmpty($token);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'mobile',
        ]);

        $this->assertGuest();
    }

    public function test_it_can_verify_an_email_otp_and_receive_an_api_token(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
        ]);

        $otpService = app(OtpService::class);

        $result = $otpService->create(
            $user,
            'login',
            'email',
            $user->email,
        );

        $response = $this->postJson('/api/v1/login/otp/verify', [
            'challenge_id' => $result['challenge']->id,
            'code' => $result['code'],
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'token',
                'token_type',
            ])
            ->assertJson([
                'message' => 'Authentication successful.',
                'token_type' => 'Bearer',
            ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'mobile',
        ]);

        $this->assertGuest();

        $this->assertNotNull(
            $result['challenge']->fresh()->verified_at
        );
    }

    public function test_it_rejects_an_invalid_email_otp(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
        ]);

        $result = app(AuthenticationService::class)
            ->requestEmailOtp($user->email);

        $response = $this->postJson('/api/v1/login/otp/verify', [
            'challenge_id' => $result['challenge']->id,
            'code' => '000000',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'code',
            ]);

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0
        );
    }

    public function test_a_sanctum_token_can_access_the_authenticated_user_endpoint(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
        ]);

        $authenticationService = app(AuthenticationService::class);

        $token = $authenticationService->createApiToken($user);

        $response = $this->withToken($token)
            ->getJson('/api/v1/auth/me');

        $response
            ->assertOk()
            ->assertJson([
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                ],
            ]);
    }

    public function test_the_authenticated_user_endpoint_rejects_unauthenticated_requests(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertUnauthorized();
    }

    public function test_it_can_logout_and_revoke_the_current_api_token(): void
    {
        $user = User::factory()->create();

        $authenticationService = app(AuthenticationService::class);

        $token = $authenticationService->createApiToken($user);

        $response = $this->withToken($token)
            ->postJson('/api/v1/auth/logout');

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Logged out successfully.',
            ]);

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0
        );
    }

    public function test_a_revoked_token_cannot_access_protected_routes(): void
    {
        $user = User::factory()->create();

        $authenticationService = app(AuthenticationService::class);

        $token = $authenticationService->createApiToken($user);

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0
        );

        app('auth')->forgetGuards();

        $response = $this->withToken($token)
            ->getJson('/api/v1/auth/me');

        $response->assertUnauthorized();
    }

    public function test_it_can_return_the_authenticated_user(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
        ]);

        $token = app(AuthenticationService::class)
            ->createApiToken($user);

        $response = $this->withToken($token)
            ->getJson('/api/v1/auth/me');

        $response
            ->assertOk()
            ->assertJson([
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                ],
            ]);
    }

    public function test_it_rejects_an_unauthenticated_me_request(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertUnauthorized();
    }

    public function test_it_records_login_activity_when_api_login_succeeds(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
        ]);

        $result = app(OtpService::class)->create(
            $user,
            'login',
            'email',
            $user->email,
        );

        $response = $this
            ->withHeaders([
                'User-Agent' => 'Test Mobile App',
            ])
            ->postJson('/api/v1/login/otp/verify', [
                'challenge_id' => $result['challenge']->id,
                'code' => $result['code'],
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('login_activities', [
            'user_id' => $user->id,
            'channel' => 'email',
            'user_agent' => 'Test Mobile App',
        ]);

        $this->assertDatabaseCount(
            'login_activities',
            1
        );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            1
        );
    }

    public function test_logout_records_logout_time_for_the_current_login_activity(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
        ]);

        $result = app(OtpService::class)->create(
            $user,
            'login',
            'email',
            $user->email,
        );

        $response = $this->postJson('/api/v1/login/otp/verify', [
            'challenge_id' => $result['challenge']->id,
            'code' => $result['code'],
        ]);

        $response->assertOk();

        $token = $response->json('token');

        $this->assertDatabaseCount(
            'login_activities',
            1
        );

        $activity = \Modules\Core\Models\LoginActivity::first();

        $this->assertNotNull($activity);
        $this->assertNull($activity->logged_out_at);

        $logoutResponse = $this
            ->withToken($token)
            ->postJson('/api/v1/auth/logout');

        $logoutResponse
            ->assertOk()
            ->assertJson([
                'message' => 'Logged out successfully.',
            ]);

        $activity = $activity->fresh();

        $this->assertNotNull(
            $activity->logged_out_at
        );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0
        );
    }

    public function test_logout_only_closes_the_current_login_activity(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
        ]);

        $otpService = app(OtpService::class);

        $first = $otpService->create(
            $user,
            'login',
            'email',
            $user->email,
        );

        $firstResponse = $this->postJson(
            '/api/v1/login/otp/verify',
            [
                'challenge_id' => $first['challenge']->id,
                'code' => $first['code'],
            ]
        );

        $firstToken = $firstResponse->json('token');

        Auth::logout();

        $second = $otpService->create(
            $user,
            'login',
            'email',
            $user->email,
        );

        $secondResponse = $this->postJson(
            '/api/v1/login/otp/verify',
            [
                'challenge_id' => $second['challenge']->id,
                'code' => $second['code'],
            ]
        );

        $secondToken = $secondResponse->json('token');

        $this->assertDatabaseCount(
            'login_activities',
            2
        );

        $firstPersonalAccessToken = PersonalAccessToken::findToken(
            $firstToken
        );

        $this->assertNotNull($firstPersonalAccessToken);

        $firstActivity = LoginActivity::query()
            ->where('user_id', $user->id)
            ->where('token_id', $firstPersonalAccessToken->id)
            ->first();

        $this->assertNotNull($firstActivity);

        $this->assertNotNull($firstActivity);

        $logoutResponse = $this
            ->withToken($firstToken)
            ->postJson('/api/v1/auth/logout');

        $logoutResponse->assertOk();

        $this->assertNotNull(
            $firstActivity->fresh()->logged_out_at
        );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            1
        );

        $secondActivity = \Modules\Core\Models\LoginActivity::query()
            ->whereNull('logged_out_at')
            ->first();

        $this->assertNotNull($secondActivity);
        $this->assertSame(
            'mobile',
            $secondActivity->token->name
        );

        // The second token must still work.
        $this->withToken($secondToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk();
    }
}