<?php

namespace Modules\Core\Tests\Feature\Organization\Ui;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppSettingsUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_admin_can_open_whatsapp_settings_and_usage(): void
    {
        $admin = User::factory()->create(['is_system_admin' => true]);
        $this->actingAs($admin)->get('/system/whatsapp-settings')
            ->assertOk()
            ->assertSee('Saflivo default WhatsApp channel')
            ->assertSee('Notifications')
            ->assertSee('Default channel settings')
            ->assertSee('WhatsApp template guide')
            ->assertSee('Test connection')
            ->assertSee('page-head-actions', false)
            ->assertSee('WhatsApp management navigation')
            ->assertSee('Failed deliveries');
        $this->actingAs($admin)->get('/system/whatsapp-usage')->assertOk()->assertSee('WhatsApp usage')->assertSee('Usage records')->assertSee('Failed deliveries')->assertSee('Default channel settings');
        $this->actingAs($admin)->get('/system/whatsapp-usage-records')->assertOk()->assertSee('WhatsApp usage records')->assertSee('All organizations')->assertSee('Accepted sends in selected period')->assertSee('Default channel settings');
        $this->actingAs($admin)->get('/system/whatsapp-failed-deliveries')->assertOk()->assertSee('Failed WhatsApp deliveries')->assertSee('All organizations')->assertSee('Usage & limits');
        $this->actingAs($admin)->get('/system/whatsapp-message-history')->assertOk()->assertSee('WhatsApp message history')->assertSee('Service category')->assertSee('Message records')->assertSee('Default channel settings');
    }

    public function test_non_system_admin_cannot_open_system_whatsapp_settings_or_usage(): void
    {
        $user = User::factory()->create(['is_system_admin' => false]);
        $this->actingAs($user)->get('/system/whatsapp-settings')->assertForbidden();
        $this->actingAs($user)->get('/system/whatsapp-usage')->assertForbidden();
        $this->actingAs($user)->get('/system/whatsapp-usage-records')->assertForbidden();
        $this->actingAs($user)->get('/system/whatsapp-failed-deliveries')->assertForbidden();
        $this->actingAs($user)->get('/system/whatsapp-message-history')->assertForbidden();
    }
}
