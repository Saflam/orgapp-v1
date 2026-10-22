<?php

namespace Modules\Core\Tests\Feature\Notification;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\OrganizationNotificationChannel;
use Modules\Core\Models\SystemNotificationChannel;
use Modules\Core\Services\NotificationChannelResolver;
use Tests\TestCase;

class WhatsAppServiceEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_is_available_when_feature_allowed_but_utility_and_marketing_need_approval(): void
    {
        $organization = Organization::factory()->create();
        $credentials = ['phone_number_id' => '123', 'access_token' => 'secret', 'otp_template_name' => 'login_otp', 'template_name' => 'utility_template', 'marketing_template_name' => 'marketing_template'];
        OrganizationNotificationChannel::query()->create(['organization_id' => $organization->id, 'channel' => 'whatsapp', 'is_allowed' => true, 'is_enabled' => true, 'use_system_default' => false, 'utility_allowed' => false, 'marketing_allowed' => false, 'credentials' => $credentials]);
        $resolver = app(NotificationChannelResolver::class);
        $this->assertNotNull($resolver->resolve($organization, 'whatsapp', 'otp'));
        $this->assertNull($resolver->resolve($organization, 'whatsapp', 'utility'));
        $this->assertNull($resolver->resolve($organization, 'whatsapp', 'marketing'));
    }

    public function test_default_channel_is_not_used_without_both_feature_and_default_permission(): void
    {
        $organization = Organization::factory()->create();
        SystemNotificationChannel::query()->create(['channel' => 'whatsapp', 'is_enabled' => true, 'credentials' => ['phone_number_id' => '123', 'access_token' => 'secret', 'otp_template_name' => 'login_otp']]);
        OrganizationNotificationChannel::query()->create(['organization_id' => $organization->id, 'channel' => 'whatsapp', 'is_allowed' => true, 'is_enabled' => false, 'use_system_default' => false, 'utility_allowed' => false, 'marketing_allowed' => false]);
        $this->assertNull(app(NotificationChannelResolver::class)->resolve($organization, 'whatsapp', 'otp'));
    }
}
