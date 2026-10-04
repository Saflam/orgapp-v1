<?php

namespace Modules\Core\Tests\Feature\Organization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Services\AuthenticationService;
use Modules\Core\Services\OrganizationModuleService;
use Tests\TestCase;

class OrganizationModuleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_admin_can_list_organization_modules(): void
    {
        $organization = Organization::factory()->create();

        $systemAdmin = User::factory()->create([
            'is_system_admin' => true,
        ]);

        $token = app(AuthenticationService::class)
            ->createApiToken($systemAdmin);

        $response = $this
            ->withToken($token)
            ->getJson(
                "/api/v1/system/organizations/{$organization->id}/modules"
            );

        $response
            ->assertOk()
            ->assertJsonStructure([
                'modules' => [
                    '*' => [
                        'module',
                        'is_enabled',
                    ],
                ],
            ]);
    }

    public function test_system_admin_can_enable_a_module(): void
    {
        $organization = Organization::factory()->create();

        $systemAdmin = User::factory()->create([
            'is_system_admin' => true,
        ]);

        app(OrganizationModuleService::class)->disable(
            $organization,
            'Member',
        );

        $token = app(AuthenticationService::class)
            ->createApiToken($systemAdmin);

        $response = $this
            ->withToken($token)
            ->postJson(
                "/api/v1/system/organizations/{$organization->id}/modules/Member/enable"
            );

        $response
            ->assertOk()
            ->assertJson([
                'message' =>
                    'Organization module enabled successfully.',
                'module' => [
                    'module' => 'Member',
                    'is_enabled' => true,
                ],
            ]);

        $this->assertTrue(
            app(OrganizationModuleService::class)->isEnabled(
                $organization,
                'Member',
            )
        );
    }

    public function test_system_admin_can_disable_a_module(): void
    {
        $organization = Organization::factory()->create();

        $systemAdmin = User::factory()->create([
            'is_system_admin' => true,
        ]);

        app(OrganizationModuleService::class)->enable(
            $organization,
            'Member',
        );

        $token = app(AuthenticationService::class)
            ->createApiToken($systemAdmin);

        $response = $this
            ->withToken($token)
            ->postJson(
                "/api/v1/system/organizations/{$organization->id}/modules/Member/disable"
            );

        $response
            ->assertOk()
            ->assertJson([
                'message' =>
                    'Organization module disabled successfully.',
                'module' => [
                    'module' => 'Member',
                    'is_enabled' => false,
                ],
            ]);

        $this->assertFalse(
            app(OrganizationModuleService::class)->isEnabled(
                $organization,
                'Member',
            )
        );
    }

    public function test_non_system_admin_cannot_manage_modules(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create([
            'is_system_admin' => false,
        ]);

        $token = app(AuthenticationService::class)
            ->createApiToken($user);

        $response = $this
            ->withToken($token)
            ->postJson(
                "/api/v1/system/organizations/{$organization->id}/modules/Member/enable"
            );

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_manage_modules(): void
    {
        $organization = Organization::factory()->create();

        $response = $this->postJson(
            "/api/v1/system/organizations/{$organization->id}/modules/Member/enable"
        );

        $response->assertUnauthorized();
    }

    public function test_unknown_module_cannot_be_enabled(): void
    {
        $organization = Organization::factory()->create();

        $systemAdmin = User::factory()->create([
            'is_system_admin' => true,
        ]);

        $token = app(AuthenticationService::class)
            ->createApiToken($systemAdmin);

        $response = $this
            ->withToken($token)
            ->postJson(
                "/api/v1/system/organizations/{$organization->id}/modules/UnknownModule/enable"
            );

        $response->assertStatus(422);
    }
}