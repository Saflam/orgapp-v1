<?php

namespace Modules\Core\Tests\Feature\Authentication;

use App\Models\User;
use App\Support\Organization\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Modules\Core\Models\LoginActivity;
use Modules\Core\Models\Organization;
use Modules\Core\Services\LoginActivityService;
use Tests\TestCase;

class LoginActivityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_a_login(): void
    {
        $user = User::factory()->create();

        $request = Request::create(
            '/api/v1/login/otp/verify',
            'POST'
        );

        $request->server->set(
            'REMOTE_ADDR',
            '127.0.0.1'
        );

        $request->headers->set(
            'User-Agent',
            'Test Browser'
        );

        $service = app(LoginActivityService::class);

        $activity = $service->recordLogin(
            $user,
            'email',
            null,
            $request,
        );

        $this->assertInstanceOf(
            LoginActivity::class,
            $activity
        );

        $this->assertSame(
            $user->id,
            $activity->user_id
        );

        $this->assertSame(
            'email',
            $activity->channel
        );

        $this->assertSame(
            '127.0.0.1',
            $activity->ip_address
        );

        $this->assertSame(
            'Test Browser',
            $activity->user_agent
        );

        $this->assertNotNull(
            $activity->logged_in_at
        );
    }

    public function test_it_records_the_current_organization(): void
    {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();

        app(CurrentOrganization::class)
            ->set($organization);

        $service = app(LoginActivityService::class);

        $activity = $service->recordLogin(
            $user,
            'email'
        );

        $this->assertSame(
            $organization->id,
            $activity->organization_id
        );
    }

    public function test_it_records_logout(): void
    {
        $user = User::factory()->create();

        $service = app(LoginActivityService::class);

        $activity = $service->recordLogin(
            $user,
            'email'
        );

        $this->assertNull(
            $activity->logged_out_at
        );

        $service->recordLogout($activity);

        $this->assertNotNull(
            $activity->fresh()->logged_out_at
        );
    }
}