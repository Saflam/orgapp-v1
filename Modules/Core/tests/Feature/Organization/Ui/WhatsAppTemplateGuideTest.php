<?php

namespace Modules\Core\Tests\Feature\Organization\Ui;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppTemplateGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_admin_can_open_whatsapp_template_guide(): void
    {
        $admin = User::factory()->create(['is_system_admin' => true]);

        $this->actingAs($admin)
            ->get('/system/whatsapp-templates')
            ->assertOk()
            ->assertSee('WhatsApp template guide')
            ->assertSee('saflivo_notification_update')
            ->assertSee('Phone Number ID')
            ->assertSee('Authorized verifier example values')
            ->assertSee('same template body and name can be used by both channel types')
            ->assertSee('Template names are not automatically shared or copied between WABAs.')
            ->assertSee('When creating a new organization')
            ->assertSee("Configure Saflivo's default WhatsApp channel", false)
            ->assertSee("Configure an organization's own WhatsApp channel", false)
            ->assertSee('Channel selection rules')
            ->assertSee('/api/v1/system/organizations/{organization}/notification-channels/whatsapp/permissions')
            ->assertSee('/api/v1/organization/notification-channels/whatsapp')
            ->assertSee('saflivo_login_otp')
            ->assertSee('/system/whatsapp-settings')
            ->assertSee('/system/whatsapp-usage');
    }

    public function test_non_system_admin_cannot_open_whatsapp_template_guide(): void
    {
        $user = User::factory()->create(['is_system_admin' => false]);

        $this->actingAs($user)
            ->get('/system/whatsapp-templates')
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login_from_whatsapp_template_guide(): void
    {
        $this->get('/system/whatsapp-templates')->assertRedirect();
    }
}
