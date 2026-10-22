<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('whatsapp_message_usages', 'delivery_status')) {
            Schema::table('whatsapp_message_usages', function (Blueprint $table): void {
                $table->string('delivery_status', 20)->nullable()->index();
            });
        }

        if (Schema::hasTable('whatsapp_webhook_events')) {
            return;
        }

        Schema::create('whatsapp_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->char('event_hash', 64)->unique();
            $table->string('provider_message_id')->index();
            $table->string('status', 20);
            $table->timestamp('provider_timestamp')->nullable();
            $table->string('error_code', 40)->nullable();
            $table->string('error_title', 255)->nullable();
            $table->string('pricing_category', 40)->nullable();
            $table->boolean('billable')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['provider_message_id', 'status'], 'wa_webhook_message_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_webhook_events');
        if (Schema::hasColumn('whatsapp_message_usages', 'delivery_status')) {
            Schema::table('whatsapp_message_usages', function (Blueprint $table): void {
                $table->dropColumn('delivery_status');
            });
        }
    }
};
