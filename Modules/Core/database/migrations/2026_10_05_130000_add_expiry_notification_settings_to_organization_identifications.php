<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_identifications', function (Blueprint $table): void {
            $table->boolean('has_expiry')->default(false)->after('requires_document');
            $table->json('expiry_reminder_days')->nullable()->after('has_expiry');
        });
    }

    public function down(): void
    {
        Schema::table('organization_identifications', function (Blueprint $table): void {
            $table->dropColumn(['has_expiry', 'expiry_reminder_days']);
        });
    }
};
