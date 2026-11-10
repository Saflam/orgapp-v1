<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('finance_journals', function (Blueprint $table): void {
            $table->foreignId('reversal_of_journal_id')
                ->nullable()
                ->after('finance_period_id')
                ->constrained('finance_journals', 'id', 'fj_reversal_original_fk')
                ->restrictOnDelete();

            $table->unique('reversal_of_journal_id', 'fj_reversal_original_uq');
            $table->text('reversal_reason')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('finance_journals', function (Blueprint $table): void {
            $table->dropUnique('fj_reversal_original_uq');
            $table->dropForeign('fj_reversal_original_fk');
            $table->dropColumn(['reversal_of_journal_id', 'reversal_reason']);
        });
    }
};
