<?php

namespace Modules\Core\Tests\Feature\Notification;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Modules\Core\Models\Organization;
use Modules\Core\Models\WhatsAppCostReconciliationBatch;
use Modules\Core\Models\WhatsAppMessageUsage;
use Modules\Core\Services\WhatsAppCostReconciliationService;
use Tests\TestCase;

class WhatsAppCostReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_matches_internal_usage_and_records_provider_cost(): void
    {
        $organization = Organization::factory()->create();
        $usage = WhatsAppMessageUsage::query()->create([
            'organization_id' => $organization->id,
            'service_category' => 'utility',
            'channel_source' => 'system',
            'provider_message_id' => 'wamid.cost-001',
            'status' => 'accepted',
            'meta_billable' => true,
            'accepted_at' => now(),
        ]);
        $file = UploadedFile::fake()->createWithContent(
            'provider-costs.csv',
            "provider_message_id,cost_amount,currency,pricing_category,billable,occurred_at\nwamid.cost-001,0.012500,USD,utility,true,2026-10-17T10:30:00Z\n"
        );

        $batch = app(WhatsAppCostReconciliationService::class)->importCsv($file, null);

        $this->assertSame(1, $batch->row_count);
        $this->assertSame(1, $batch->matched_count);
        $this->assertSame(0, $batch->unmatched_count);
        $this->assertDatabaseHas('whatsapp_cost_reconciliation_items', [
            'batch_id' => $batch->id,
            'usage_id' => $usage->id,
            'organization_id' => $organization->id,
            'reconciliation_status' => 'matched',
            'provider_cost_currency' => 'USD',
        ]);
        $this->assertEquals(0.0125, (float) $usage->fresh()->meta_cost_amount);
        $this->assertSame('USD', $usage->fresh()->meta_cost_currency);
        $this->assertSame('utility', $usage->fresh()->meta_pricing_category);
        $this->assertTrue($usage->fresh()->meta_billable);
    }

    public function test_import_marks_unknown_ids_unmatched_and_does_not_assign_an_organization(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'provider-costs.csv',
            "provider_message_id,cost_amount,currency,pricing_category,billable,occurred_at\nwamid.not-in-ledger,0.100000,EUR,marketing,yes,2026-10-17T10:30:00Z\n"
        );

        $batch = app(WhatsAppCostReconciliationService::class)->importCsv($file, null);

        $this->assertSame(1, $batch->unmatched_count);
        $this->assertDatabaseHas('whatsapp_cost_reconciliation_items', [
            'batch_id' => $batch->id,
            'organization_id' => null,
            'reconciliation_status' => 'unmatched',
        ]);
    }

    public function test_reimported_provider_message_id_is_marked_duplicate_and_excluded_from_cost_totals(): void
    {
        WhatsAppMessageUsage::query()->create([
            'service_category' => 'utility',
            'channel_source' => 'system',
            'provider_message_id' => 'wamid.cost-duplicate',
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);
        $csv = "provider_message_id,cost_amount,currency,pricing_category,billable,occurred_at\nwamid.cost-duplicate,0.020000,USD,utility,true,2026-10-17T10:30:00Z\n";
        $service = app(WhatsAppCostReconciliationService::class);

        $first = $service->importCsv(UploadedFile::fake()->createWithContent('first.csv', $csv), null);
        $second = $service->importCsv(UploadedFile::fake()->createWithContent('second.csv', $csv), null);

        $this->assertSame(1, $first->matched_count);
        $this->assertSame(1, $second->duplicate_count);
        $this->assertSame(1, WhatsAppCostReconciliationBatch::query()->findOrFail($second->id)->duplicate_count);
        $this->assertDatabaseHas('whatsapp_cost_reconciliation_items', [
            'batch_id' => $second->id,
            'reconciliation_status' => 'duplicate',
        ]);
    }

    public function test_pending_provider_cost_is_matched_when_internal_usage_arrives_later(): void
    {
        $csv = "provider_message_id,cost_amount,currency,pricing_category,billable,occurred_at\nwamid.delayed-001,0.045600,USD,marketing,true,2026-10-17T10:30:00Z\n";
        $service = app(WhatsAppCostReconciliationService::class);
        $batch = $service->importCsv(UploadedFile::fake()->createWithContent('delayed.csv', $csv), null);

        $this->assertSame(1, $batch->unmatched_count);

        $organization = Organization::factory()->create();
        $usage = WhatsAppMessageUsage::query()->create([
            'organization_id' => $organization->id,
            'service_category' => 'marketing',
            'channel_source' => 'system',
            'provider_message_id' => 'wamid.delayed-001',
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        $result = $service->reconcilePending();

        $this->assertSame(1, $result['matched']);
        $this->assertSame(0, $result['duplicate']);
        $this->assertSame(0, $result['still_unmatched']);
        $this->assertDatabaseHas('whatsapp_cost_reconciliation_items', [
            'batch_id' => $batch->id,
            'usage_id' => $usage->id,
            'organization_id' => $organization->id,
            'reconciliation_status' => 'matched',
            'issue' => null,
        ]);
        $this->assertSame(1, $batch->fresh()->matched_count);
        $this->assertSame(0, $batch->fresh()->unmatched_count);
        $this->assertEquals(0.0456, (float) $usage->fresh()->meta_cost_amount);
    }

    public function test_retry_does_not_apply_provider_cost_twice_and_marks_later_row_duplicate(): void
    {
        $csv = "provider_message_id,cost_amount,currency,pricing_category,billable,occurred_at\nwamid.delayed-duplicate,0.010000,USD,utility,true,2026-10-17T10:30:00Z\n";
        $service = app(WhatsAppCostReconciliationService::class);
        $firstBatch = $service->importCsv(UploadedFile::fake()->createWithContent('early.csv', $csv), null);

        WhatsAppMessageUsage::query()->create([
            'service_category' => 'utility',
            'channel_source' => 'system',
            'provider_message_id' => 'wamid.delayed-duplicate',
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        $firstRetry = $service->reconcilePending();
        $this->assertSame(1, $firstRetry['matched']);

        // Simulate a duplicate provider row imported later for the same message.
        $secondBatch = $service->importCsv(UploadedFile::fake()->createWithContent('later.csv', $csv), null);
        $secondRetry = $service->reconcilePending();

        $this->assertSame(1, $secondBatch->duplicate_count);
        $this->assertSame(0, $secondRetry['matched']);
        $this->assertSame(0, $secondRetry['duplicate']);
        $this->assertSame(1, $firstBatch->fresh()->matched_count);
        $this->assertDatabaseCount('whatsapp_cost_reconciliation_items', 2);
    }


    public function test_import_records_discrepancies_before_updating_internal_usage_values(): void
    {
        $organization = Organization::factory()->create();
        $usage = WhatsAppMessageUsage::query()->create([
            'organization_id' => $organization->id,
            'service_category' => 'utility',
            'channel_source' => 'system',
            'provider_message_id' => 'wamid.cost-diff-001',
            'status' => 'accepted',
            'meta_cost_amount' => '0.010000',
            'meta_cost_currency' => 'USD',
            'meta_pricing_category' => 'utility',
            'meta_billable' => true,
            'accepted_at' => now(),
        ]);
        $file = UploadedFile::fake()->createWithContent(
            'provider-costs.csv',
            "provider_message_id,cost_amount,currency,pricing_category,billable,occurred_at\nwamid.cost-diff-001,0.012500,EUR,marketing,false,2026-10-17T10:30:00Z\n"
        );

        $batch = app(WhatsAppCostReconciliationService::class)->importCsv($file, null);
        $usage->refresh();
        $item = $batch->items()->firstOrFail();

        $this->assertSame(1, $batch->discrepancy_count);
        $this->assertSame('discrepancy', $item->reconciliation_status);
        $this->assertSame(['cost_amount', 'currency', 'pricing_category', 'billable'], array_keys($item->discrepancy_details));
        $this->assertEquals(0.010000, (float) $item->internal_cost_amount);
        $this->assertSame('USD', $item->internal_cost_currency);
        $this->assertEquals(0.0125, (float) $usage->meta_cost_amount);
        $this->assertSame('EUR', $usage->meta_cost_currency);
    }

    public function test_discrepancy_message_id_cannot_be_applied_again_by_later_import(): void
    {
        WhatsAppMessageUsage::query()->create([
            'service_category' => 'utility',
            'channel_source' => 'system',
            'provider_message_id' => 'wamid.cost-diff-duplicate',
            'status' => 'accepted',
            'meta_cost_amount' => '0.010000',
            'meta_cost_currency' => 'USD',
            'accepted_at' => now(),
        ]);
        $csv = "provider_message_id,cost_amount,currency,pricing_category,billable,occurred_at\nwamid.cost-diff-duplicate,0.012500,EUR,marketing,false,2026-10-17T10:30:00Z\n";
        $service = app(WhatsAppCostReconciliationService::class);

        $first = $service->importCsv(UploadedFile::fake()->createWithContent('first.csv', $csv), null);
        $second = $service->importCsv(UploadedFile::fake()->createWithContent('second.csv', $csv), null);

        $this->assertSame(1, $first->discrepancy_count);
        $this->assertSame(1, $second->duplicate_count);
    }


    public function test_duplicate_provider_message_ids_inside_one_csv_are_counted_once(): void
    {
        WhatsAppMessageUsage::query()->create([
            'service_category' => 'utility',
            'channel_source' => 'system',
            'provider_message_id' => 'wamid.same-file-duplicate',
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);
        $csv = "provider_message_id,cost_amount,currency,pricing_category,billable,occurred_at\n"
            . "wamid.same-file-duplicate,0.020000,USD,utility,true,2026-10-17T10:30:00Z\n"
            . "wamid.same-file-duplicate,0.020000,USD,utility,true,2026-10-17T10:30:00Z\n";

        $batch = app(WhatsAppCostReconciliationService::class)->importCsv(
            UploadedFile::fake()->createWithContent('same-file-duplicates.csv', $csv),
            null
        );

        $this->assertSame(2, $batch->row_count);
        $this->assertSame(1, $batch->matched_count);
        $this->assertSame(1, $batch->duplicate_count);
        $this->assertDatabaseCount('whatsapp_cost_reconciliation_items', 2);
    }

    public function test_invalid_cost_precision_and_values_are_retained_as_invalid_rows_without_rolling_back_valid_rows(): void
    {
        $organization = Organization::factory()->create();
        WhatsAppMessageUsage::query()->create([
            'organization_id' => $organization->id,
            'service_category' => 'utility',
            'channel_source' => 'system',
            'provider_message_id' => 'wamid.valid-cost-row',
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);
        $csv = "provider_message_id,cost_amount,currency,pricing_category,billable,occurred_at\n"
            . "wamid.valid-cost-row,0.123456,USD,utility,true,2026-10-17T10:30:00Z\n"
            . "wamid.too-precise,0.1234567,USD,utility,true,2026-10-17T10:30:00Z\n"
            . "wamid.too-large,1000000.000000,USD,utility,true,2026-10-17T10:30:00Z\n"
            . "wamid.negative,-0.010000,USD,utility,true,2026-10-17T10:30:00Z\n";

        $batch = app(WhatsAppCostReconciliationService::class)->importCsv(
            UploadedFile::fake()->createWithContent('mixed-validity.csv', $csv),
            null
        );

        $this->assertSame(4, $batch->row_count);
        $this->assertSame(1, $batch->matched_count);
        $this->assertSame(3, $batch->invalid_count);
        $this->assertDatabaseHas('whatsapp_cost_reconciliation_items', [
            'batch_id' => $batch->id,
            'provider_message_id' => 'wamid.too-precise',
            'reconciliation_status' => 'invalid',
        ]);
        $this->assertDatabaseHas('whatsapp_cost_reconciliation_items', [
            'batch_id' => $batch->id,
            'provider_message_id' => 'wamid.too-large',
            'reconciliation_status' => 'invalid',
        ]);
        $this->assertDatabaseHas('whatsapp_cost_reconciliation_items', [
            'batch_id' => $batch->id,
            'provider_message_id' => 'wamid.negative',
            'reconciliation_status' => 'invalid',
        ]);
        $this->assertEquals(0.123456, (float) WhatsAppMessageUsage::query()->where('provider_message_id', 'wamid.valid-cost-row')->value('meta_cost_amount'));
    }

    public function test_import_rejects_csv_missing_required_header(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'bad.csv',
            "message_id,amount,currency\nwamid.x,0.1,USD\n"
        );

        try {
            app(WhatsAppCostReconciliationService::class)->importCsv($file, null);
            $this->fail('Expected missing header validation to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Missing required CSV column', $exception->getMessage());
        }

        $this->assertDatabaseCount('whatsapp_cost_reconciliation_batches', 0);
    }
}
