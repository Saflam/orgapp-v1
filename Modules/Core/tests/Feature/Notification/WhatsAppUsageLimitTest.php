<?php

namespace Modules\Core\Tests\Feature\Notification;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\WhatsAppMessageUsage;
use Modules\Core\Services\WhatsAppUsageLimitExceeded;
use Modules\Core\Services\WhatsAppUsageLimitService;
use Tests\TestCase;

class WhatsAppUsageLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_limit_reserves_capacity_and_blocks_excess_sends(): void
    {
        $organization = Organization::factory()->create();
        $service = app(WhatsAppUsageLimitService::class);
        $service->setLimit($organization, 2, 80);

        $this->assertTrue($service->reserve($organization));
        $this->assertTrue($service->reserve($organization));
        try {
            $service->reserve($organization);
            $this->fail('Expected the third reservation to be blocked.');
        } catch (WhatsAppUsageLimitExceeded $exception) {
            $this->assertStringContainsString('monthly WhatsApp message limit', $exception->getMessage());
        }

        $usage = $service->currentUsage($organization);
        $this->assertSame(2, $usage['used']);
        $this->assertSame(0, $usage['remaining']);
        $this->assertTrue($usage['exhausted']);
    }

    public function test_failed_send_can_release_reserved_capacity(): void
    {
        $organization = Organization::factory()->create();
        $service = app(WhatsAppUsageLimitService::class);
        $service->setLimit($organization, 1);
        $reserved = $service->reserve($organization);
        $service->release($organization, $reserved);

        $this->assertTrue($service->reserve($organization));
    }

    public function test_limit_usage_is_initialized_from_current_month_accepted_ledger_rows(): void
    {
        $organization = Organization::factory()->create();
        WhatsAppMessageUsage::query()->create([
            'organization_id' => $organization->id,
            'service_category' => 'utility',
            'channel_source' => 'system',
            'provider_message_id' => 'wamid.test.1',
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);
        $service = app(WhatsAppUsageLimitService::class);
        $service->setLimit($organization, 3);

        $this->assertSame(1, $service->currentUsage($organization)['used']);
        $this->assertTrue($service->reserve($organization));
        $this->assertSame(2, $service->currentUsage($organization)['used']);
    }
}
