<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('committee_memberships', function (Blueprint $table): void {
            $table->text('end_reason')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('committee_memberships', function (Blueprint $table): void {
            $table->dropColumn('end_reason');
        });
    }
};
