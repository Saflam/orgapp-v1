<?php

namespace Modules\Core\Tests\Feature\Organization;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationService;
use Tests\TestCase;

class OrganizationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function service(): OrganizationService
    {
        return app(OrganizationService::class);
    }

    public function test_it_can_activate_an_inactive_organization(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => false,
        ]);

        $result = $this->service()->activate($organization);

        $this->assertTrue($result->is_active);

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'is_active' => true,
        ]);
    }

    public function test_it_can_deactivate_an_active_organization(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => true,
        ]);

        $result = $this->service()->deactivate($organization);

        $this->assertFalse($result->is_active);

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'is_active' => false,
        ]);
    }

    public function test_deactivating_an_organization_preserves_its_memberships(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => true,
        ]);

        $user = User::factory()->create();

        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $this->service()->deactivate($organization);

        $this->assertDatabaseHas('organization_memberships', [
            'id' => $membership->id,
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);
    }

    public function test_activating_an_organization_preserves_its_memberships(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => false,
        ]);

        $user = User::factory()->create();

        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $this->service()->activate($organization);

        $this->assertDatabaseHas('organization_memberships', [
            'id' => $membership->id,
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);
    }

    public function test_deactivating_an_organization_preserves_its_configuration(): void
    {
        $organization = Organization::factory()->create([
            'name' => 'Kerala Cultural Association',
            'code' => 'KCA',
            'slug' => 'kerala-cultural-association',
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'is_active' => true,
            'settings' => [
                'theme' => 'blue',
                'locale' => 'en',
            ],
        ]);

        $this->service()->deactivate($organization);

        $organization->refresh();

        $this->assertSame(
            'Kerala Cultural Association',
            $organization->name
        );

        $this->assertSame('KCA', $organization->code);

        $this->assertSame(
            'kerala-cultural-association',
            $organization->slug
        );

        $this->assertSame('Asia/Kolkata', $organization->timezone);
        $this->assertSame('INR', $organization->currency);

        $this->assertSame([
            'theme' => 'blue',
            'locale' => 'en',
        ], $organization->settings);

        $this->assertFalse($organization->is_active);
    }
}