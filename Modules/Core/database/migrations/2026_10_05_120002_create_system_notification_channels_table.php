<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_notification_channels', function (Blueprint $table): void {
            $table->id();
            $table->string('channel', 50)->unique('snc_channel_unique');
            $table->boolean('is_enabled')->default(false);
            $table->json('credentials')->nullable();
            $table->timestamps();
        });

        Schema::table('organization_notification_channels', function (Blueprint $table): void {
            $table->boolean('use_system_default')
                ->default(false)
                ->after('is_enabled');
            $table->json('credentials')->nullable()->after('use_system_default');
            $table->dropColumn('configuration');
        });

        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->string('channel_source', 20)
                ->default('organization')
                ->after('channel');
            $table->index(
                ['channel', 'channel_source', 'status'],
                'notification_deliveries_channel_source_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->dropIndex('notification_deliveries_channel_source_idx');
            $table->dropColumn('channel_source');
        });

        Schema::table('organization_notification_channels', function (Blueprint $table): void {
            $table->dropColumn('use_system_default');
        });

        Schema::dropIfExists('system_notification_channels');
    }
};
