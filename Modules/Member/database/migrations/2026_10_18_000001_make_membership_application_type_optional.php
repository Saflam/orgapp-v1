<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_applications', function (Blueprint $table): void {
            $table->foreignId('membership_type_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Membership types must be assigned before rolling back this migration.
        Schema::table('membership_applications', function (Blueprint $table): void {
            $table->foreignId('membership_type_id')->nullable(false)->change();
        });
    }
};
