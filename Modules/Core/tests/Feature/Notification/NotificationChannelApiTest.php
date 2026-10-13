<?php

namespace Modules\Core\Tests\Feature\Notification;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Services\AuthorizationDefinitionProvisioningService;
use Tests\TestCase;

class NotificationChannelApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(AuthorizationDefinitionProvisioningService::class)->provision();
    }

    public function test_system_admin_can_configure_system_whatsapp_channel(): void
    {
        $admin = User::factory()->create(['is_system_admin' => true]);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withToken($token)->putJson(
            '/api/v1/system/notification-channels/whatsapp',
            [
                'is_enabled' => true,
                'credentials' => [
                    'api_key' => 'secret',
                    'sender' => 'ORG',
                ],
            ],
        );

        $response
            ->assertOk()
            ->assertJson([
                'channel' => 'whatsapp',
                'is_enabled' => true,
                'has_credentials' => true,
            ]);
    }

    public function test_organization_super_admin_can_configure_organization_channel(): void
    {
        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'lvh.me',
        ]);

        $organization = Organization::factory()->create([
            'slug' => 'org1',
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $role = \Modules\Core\Models\Role::query()
            ->whereNull('organization_id')
            ->where('code', 'super_admin')
            ->where('is_system', true)
            ->firstOrFail();

        \Modules\Core\Models\RoleAssignment::create([
            'organization_membership_id' => OrganizationMembership::query()
                ->where('organization_id', $organization->id)
                ->where('user_id', $user->id)
                ->value('id'),
            'role_id' => $role->id,
            'context_type' => 'organization',
            'context_id' => $organization->id,
            'starts_at' => now(),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withToken($token)->putJson(
            'http://org1.lvh.me/api/v1/organization/notification-channels/sms',
            [
                'is_enabled' => true,
                'credentials' => ['api_key' => 'organization-secret'],
            ],
        );

        $response
            ->assertOk()
            ->assertJson([
                'channel' => 'sms',
                'is_enabled' => true,
                'use_system_default' => false,
                'has_credentials' => true,
            ]);
    }

    public function test_regular_organization_member_cannot_configure_notification_channel(): void
    {
        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'lvh.me',
        ]);

        $organization = Organization::factory()->create([
            'slug' => 'org1',
            'is_active' => true,
        ]);
        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)->putJson(
            'http://org1.lvh.me/api/v1/organization/notification-channels/sms',
            [
                'is_enabled' => true,
                'credentials' => ['api_key' => 'secret'],
            ],
        )->assertForbidden();
    }
}
