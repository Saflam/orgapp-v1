<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_message_usages')) {
            return;
        }

        Schema::create('whatsapp_message_usages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('notification_delivery_id')->nullable();
            $table->unsignedBigInteger('otp_challenge_id')->nullable();
            $table->string('service_category', 20);
            $table->string('channel_source', 20);
            $table->string('provider_message_id')->nullable()->index();
            $table->string('status', 20)->default('accepted');
            $table->string('meta_pricing_category', 30)->nullable();
            $table->boolean('meta_billable')->nullable();
            $table->decimal('meta_cost_amount', 12, 6)->nullable();
            $table->char('meta_cost_currency', 3)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'service_category', 'created_at'], 'wa_usage_org_service_created_idx');
            $table->index(['channel_source', 'service_category', 'created_at'], 'wa_usage_source_service_created_idx');
            $table->foreign('organization_id', 'wa_usage_organization_fk')->references('id')->on('organizations')->nullOnDelete();
            $table->foreign('user_id', 'wa_usage_user_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('notification_delivery_id', 'wa_usage_delivery_fk')->references('id')->on('notification_deliveries')->nullOnDelete();
            $table->foreign('otp_challenge_id', 'wa_usage_otp_fk')->references('id')->on('otp_challenges')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_usages');
    }
};
