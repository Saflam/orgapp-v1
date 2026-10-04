<?php

namespace Modules\Core\Tests\Feature\Authorization;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationModuleService;
use Tests\TestCase;

class EnsureModuleEnabledMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'example.test',
        ]);

        Route::middleware([
            'auth',
            'organization',
            'organization.access',
            'module:Member',
        ])->get('/__test/member-module', function () {
            return response()->json([
                'message' => 'allowed',
            ]);
        });
    }

    public function test_an_unauthenticated_user_is_rejected(): void
    {
        $organization = Organization::factory()->create([
            'slug' => 'org-one',
        ]);

        $response = $this->getJson(
            $this->organizationUrl($organization)
        );

        $response->assertUnauthorized();
    }

    public function test_an_enabled_module_allows_access(): void
    {
        $organization = Organization::factory()->create([
            'slug' => 'org-one',
        ]);

        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        app(OrganizationModuleService::class)->enable(
            $organization,
            'Member',
        );

        $response = $this
            ->actingAs($user)
            ->getJson(
                $this->organizationUrl($organization)
            );

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'allowed',
            ]);
    }

    public function test_a_disabled_module_rejects_access(): void
    {
        $organization = Organization::factory()->create([
            'slug' => 'org-one',
        ]);

        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        app(OrganizationModuleService::class)->disable(
            $organization,
            'Member',
        );

        $response = $this
            ->actingAs($user)
            ->getJson(
                $this->organizationUrl($organization)
            );

        $response
            ->assertForbidden()
            ->assertJson([
                'message' =>
                    'The [Member] module is not enabled for this organization.',
            ]);
    }

    private function organizationUrl(
        Organization $organization,
    ): string {
        return 'http://'
            . $organization->slug
            . '.example.test'
            . '/__test/member-module';
    }
}