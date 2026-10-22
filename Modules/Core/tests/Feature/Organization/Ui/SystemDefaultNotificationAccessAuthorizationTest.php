<?php

namespace Modules\Core\Tests\Feature\Organization\Ui;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Services\AuthorizationDefinitionProvisioningService;
use Tests\TestCase;

class SystemDefaultNotificationAccessAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(AuthorizationDefinitionProvisioningService::class)->provision();
    }

    public function test_non_system_admin_cannot_change_system_default_access_for_an_organization(): void
    {
        $user = User::factory()->create(['is_system_admin' => false]);
        $organization = Organization::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/system/organizations/{$organization->id}/notification-channels/sms/default", [
                'use_system_default' => true,
            ])
            ->assertForbidden();
    }

    public function test_system_admin_can_set_both_whatsapp_permissions_for_an_organization(): void
    {
        $user = User::factory()->create(['is_system_admin' => true]);
        $organization = Organization::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/system/organizations/{$organization->id}/notification-channels/whatsapp/permissions", [
                'is_allowed' => true,
                'use_system_default' => true,
            ])
            ->assertOk()
            ->assertJsonPath('is_allowed', true)
            ->assertJsonPath('use_system_default', true);

        $this->assertDatabaseHas('organization_notification_channels', [
            'organization_id' => $organization->id,
            'channel' => 'whatsapp',
            'is_allowed' => true,
            'use_system_default' => true,
        ]);
    }

    public function test_system_admin_can_change_system_default_access_for_an_organization(): void
    {
        $user = User::factory()->create(['is_system_admin' => true]);
        $organization = Organization::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/system/organizations/{$organization->id}/notification-channels/sms/default", [
                'use_system_default' => true,
            ])
            ->assertOk()
            ->assertJsonPath('use_system_default', true);

        $this->assertDatabaseHas('organization_notification_channels', [
            'organization_id' => $organization->id,
            'channel' => 'sms',
            'use_system_default' => true,
        ]);
    }
}
