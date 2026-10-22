<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_cost_reconciliation_batches') && ! Schema::hasColumn('whatsapp_cost_reconciliation_batches', 'discrepancy_count')) {
            Schema::table('whatsapp_cost_reconciliation_batches', function (Blueprint $table): void {
                $table->unsignedInteger('discrepancy_count')->default(0);
            });
        }

        if (Schema::hasTable('whatsapp_cost_reconciliation_items')) {
            Schema::table('whatsapp_cost_reconciliation_items', function (Blueprint $table): void {
                if (! Schema::hasColumn('whatsapp_cost_reconciliation_items', 'internal_cost_amount')) {
                    $table->decimal('internal_cost_amount', 12, 6)->nullable();
                }
                if (! Schema::hasColumn('whatsapp_cost_reconciliation_items', 'internal_cost_currency')) {
                    $table->char('internal_cost_currency', 3)->nullable();
                }
                if (! Schema::hasColumn('whatsapp_cost_reconciliation_items', 'internal_pricing_category')) {
                    $table->string('internal_pricing_category', 40)->nullable();
                }
                if (! Schema::hasColumn('whatsapp_cost_reconciliation_items', 'internal_billable')) {
                    $table->boolean('internal_billable')->nullable();
                }
                if (! Schema::hasColumn('whatsapp_cost_reconciliation_items', 'discrepancy_details')) {
                    $table->json('discrepancy_details')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('whatsapp_cost_reconciliation_items')) {
            Schema::table('whatsapp_cost_reconciliation_items', function (Blueprint $table): void {
                foreach (['internal_cost_amount', 'internal_cost_currency', 'internal_pricing_category', 'internal_billable', 'discrepancy_details'] as $column) {
                    if (Schema::hasColumn('whatsapp_cost_reconciliation_items', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
        if (Schema::hasTable('whatsapp_cost_reconciliation_batches') && Schema::hasColumn('whatsapp_cost_reconciliation_batches', 'discrepancy_count')) {
            Schema::table('whatsapp_cost_reconciliation_batches', function (Blueprint $table): void {
                $table->dropColumn('discrepancy_count');
            });
        }
    }
};
