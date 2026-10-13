<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('finance_journals', function (Blueprint $table): void {
            $table->unique(
                ['organization_id', 'finance_scope_id', 'source_type', 'source_id'],
                'fj_source_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('finance_journals', function (Blueprint $table): void {
            $table->dropUnique('fj_source_unique');
        });
    }
};
