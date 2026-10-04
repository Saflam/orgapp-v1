<?php

namespace Modules\Core\Tests\Feature\Authentication;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\LoginActivity;
use Modules\Core\Models\Organization;
use Tests\TestCase;

class LoginActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_a_login_activity(): void
    {
        $user = User::factory()->create();

        $activity = LoginActivity::create([
            'user_id' => $user->id,
            'channel' => 'email',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Browser',
            'device_name' => 'Test Device',
            'platform' => 'Test OS',
            'logged_in_at' => now(),
        ]);

        $this->assertDatabaseHas('login_activities', [
            'id' => $activity->id,
            'user_id' => $user->id,
            'channel' => 'email',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Browser',
            'device_name' => 'Test Device',
            'platform' => 'Test OS',
        ]);
    }

    public function test_it_can_belong_to_an_organization(): void
    {
        $user = User::factory()->create();

        $organization = Organization::factory()->create();

        $activity = LoginActivity::create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'channel' => 'email',
            'logged_in_at' => now(),
        ]);

        $this->assertTrue(
            $activity->organization->is($organization)
        );
    }

    public function test_organization_is_optional(): void
    {
        $user = User::factory()->create();

        $activity = LoginActivity::create([
            'user_id' => $user->id,
            'channel' => 'email',
            'logged_in_at' => now(),
        ]);

        $this->assertNull($activity->organization_id);
        $this->assertNull($activity->organization);
    }

    public function test_it_can_record_logout(): void
    {
        $user = User::factory()->create();

        $activity = LoginActivity::create([
            'user_id' => $user->id,
            'channel' => 'email',
            'logged_in_at' => now(),
        ]);

        $activity->update([
            'logged_out_at' => now(),
        ]);

        $this->assertNotNull(
            $activity->fresh()->logged_out_at
        );
    }
}