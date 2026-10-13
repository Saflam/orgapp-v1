<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_notification_channels', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->string('channel', 50);
            $table->boolean('is_enabled')->default(false);
            $table->json('configuration')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'channel'], 'onc_org_channel_unique');
            $table->index(['organization_id', 'is_enabled'], 'onc_org_enabled_idx');
            $table->foreign('organization_id', 'onc_organization_fk')
                ->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('user_notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('user_id');
            $table->string('channel', 50);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'user_id', 'channel'], 'unp_org_user_channel_unique');
            $table->index(['organization_id', 'user_id'], 'unp_org_user_idx');
            $table->foreign('organization_id', 'unp_organization_fk')
                ->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('user_id', 'unp_user_fk')
                ->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
        Schema::dropIfExists('organization_notification_channels');
    }
};
