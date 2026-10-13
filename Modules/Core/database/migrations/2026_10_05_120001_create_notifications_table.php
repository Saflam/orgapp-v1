<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('user_id');
            $table->string('event', 150);
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->string('source_module', 100)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'user_id', 'created_at'], 'notifications_recipient_idx');
            $table->index(['organization_id', 'event', 'created_at'], 'notifications_event_idx');
            $table->foreign('organization_id', 'notifications_organization_fk')
                ->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('user_id', 'notifications_user_fk')
                ->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('notification_id');
            $table->string('channel', 50);
            $table->string('status', 30)->default('queued');
            $table->unsignedInteger('attempts')->default(0);
            $table->string('provider_message_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['notification_id', 'channel'], 'notification_deliveries_lookup_idx');
            $table->index(['status', 'queued_at'], 'notification_deliveries_status_idx');
            $table->foreign('notification_id', 'notification_deliveries_notification_fk')
                ->references('id')->on('notifications')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notifications');
    }
};
