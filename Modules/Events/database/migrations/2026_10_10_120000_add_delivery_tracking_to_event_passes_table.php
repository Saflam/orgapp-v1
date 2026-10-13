<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_passes', function (Blueprint $table): void {
            $table->string('notification_delivery_status', 20)->default('not_requested')->after('revoked_at');
            $table->timestamp('notification_sent_at')->nullable()->after('notification_delivery_status');
            $table->timestamp('notification_failed_at')->nullable()->after('notification_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('event_passes', function (Blueprint $table): void {
            $table->dropColumn([
                'notification_delivery_status',
                'notification_sent_at',
                'notification_failed_at',
            ]);
        });
    }
};
