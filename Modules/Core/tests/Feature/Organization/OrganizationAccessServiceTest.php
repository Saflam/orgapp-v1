<?php

namespace Modules\Core\Tests\Feature\Organization;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationAccessService;
use Tests\TestCase;

class OrganizationAccessServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_access_an_organization_they_belong_to(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $service = app(OrganizationAccessService::class);

        $this->assertTrue(
            $service->canAccess($user, $organization)
        );
    }

    public function test_user_cannot_access_an_organization_they_do_not_belong_to(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        $service = app(OrganizationAccessService::class);

        $this->assertFalse(
            $service->canAccess($user, $organization)
        );
    }

    public function test_user_cannot_access_an_inactive_organization_membership(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'suspended',
        ]);

        $service = app(OrganizationAccessService::class);

        $this->assertFalse(
            $service->canAccess($user, $organization)
        );
    }

    public function test_ensure_access_throws_for_unauthorized_user(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        $service = app(OrganizationAccessService::class);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $service->ensureAccess($user, $organization);
    }

    public function test_user_cannot_access_an_invited_organization_membership(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'invited',
        ]);

        $service = app(OrganizationAccessService::class);

        $this->assertFalse(
            $service->canAccess($user, $organization)
        );
    }
}