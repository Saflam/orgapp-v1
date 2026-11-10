<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('finance_journals', function (Blueprint $table): void {
            $table->foreignId('unit_id')
                ->nullable()
                ->after('finance_period_id')
                ->constrained('units', 'id', 'fj_unit_fk')
                ->nullOnDelete();

            $table->index(['organization_id', 'unit_id', 'transaction_date'], 'fj_org_unit_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('finance_journals', function (Blueprint $table): void {
            $table->dropIndex('fj_org_unit_date_idx');
            $table->dropConstrainedForeignId('unit_id');
        });
    }
};
