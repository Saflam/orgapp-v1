<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_notification_channels', function (Blueprint $table): void {
            if (! Schema::hasColumn('organization_notification_channels', 'utility_allowed')) {
                $table->boolean('utility_allowed')->default(false)->after('is_allowed');
            }
            if (! Schema::hasColumn('organization_notification_channels', 'marketing_allowed')) {
                $table->boolean('marketing_allowed')->default(false)->after('utility_allowed');
            }
        });

    }

    public function down(): void
    {
        Schema::table('organization_notification_channels', function (Blueprint $table): void {
            if (Schema::hasColumn('organization_notification_channels', 'marketing_allowed')) {
                $table->dropColumn('marketing_allowed');
            }
            if (Schema::hasColumn('organization_notification_channels', 'utility_allowed')) {
                $table->dropColumn('utility_allowed');
            }
        });
    }
};
