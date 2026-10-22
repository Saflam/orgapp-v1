<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_cost_reconciliation_batches')) {
            Schema::create('whatsapp_cost_reconciliation_batches', function (Blueprint $table): void {
                $table->id();
                $table->string('source_filename', 255);
                $table->unsignedBigInteger('imported_by')->nullable();
                $table->unsignedInteger('row_count')->default(0);
                $table->unsignedInteger('matched_count')->default(0);
                $table->unsignedInteger('unmatched_count')->default(0);
                $table->unsignedInteger('duplicate_count')->default(0);
                $table->unsignedInteger('invalid_count')->default(0);
                $table->timestamp('imported_at')->nullable();
                $table->timestamps();
                $table->foreign('imported_by', 'wa_cost_batch_importer_fk')->references('id')->on('users')->nullOnDelete();
                $table->index('imported_at');
            });
        }

        if (! Schema::hasTable('whatsapp_cost_reconciliation_items')) {
            Schema::create('whatsapp_cost_reconciliation_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('batch_id');
                $table->unsignedBigInteger('usage_id')->nullable();
                $table->unsignedBigInteger('organization_id')->nullable();
                $table->string('provider_message_id', 255)->nullable();
                $table->decimal('provider_cost_amount', 12, 6)->nullable();
                $table->char('provider_cost_currency', 3)->nullable();
                $table->string('pricing_category', 40)->nullable();
                $table->boolean('billable')->nullable();
                $table->timestamp('provider_occurred_at')->nullable();
                $table->string('reconciliation_status', 20);
                $table->string('issue', 255)->nullable();
                $table->json('source_row')->nullable();
                $table->timestamps();
                $table->foreign('batch_id', 'wa_cost_item_batch_fk')->references('id')->on('whatsapp_cost_reconciliation_batches')->cascadeOnDelete();
                $table->foreign('usage_id', 'wa_cost_item_usage_fk')->references('id')->on('whatsapp_message_usages')->nullOnDelete();
                $table->foreign('organization_id', 'wa_cost_item_org_fk')->references('id')->on('organizations')->nullOnDelete();
                $table->index(['provider_message_id', 'reconciliation_status'], 'wa_cost_item_message_status_idx');
                $table->index(['organization_id', 'provider_cost_currency'], 'wa_cost_item_org_currency_idx');
                $table->index(['reconciliation_status', 'created_at'], 'wa_cost_item_status_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_cost_reconciliation_items');
        Schema::dropIfExists('whatsapp_cost_reconciliation_batches');
    }
};
