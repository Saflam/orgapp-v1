<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_identifications', function (Blueprint $table): void {
            $table->date('expires_at')->nullable()->after('identification_number');
            $table->index(['identification_type_id', 'expires_at'], 'user_identifications_type_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::table('user_identifications', function (Blueprint $table): void {
            $table->dropIndex('user_identifications_type_expiry_idx');
            $table->dropColumn('expires_at');
        });
    }
};
