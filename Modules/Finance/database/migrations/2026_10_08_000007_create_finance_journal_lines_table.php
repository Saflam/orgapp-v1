<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_journal_lines', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('journal_id')
                ->constrained('finance_journals', 'id', 'fjl_journal_fk')
                ->cascadeOnDelete();

            $table->foreignId('finance_account_id')
                ->constrained('finance_accounts', 'id', 'fjl_account_fk')
                ->restrictOnDelete();

            $table->text('description')->nullable();

            $table->decimal('debit', 20, 4)->default(0);
            $table->decimal('credit', 20, 4)->default(0);

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(
                ['finance_account_id', 'journal_id'],
                'fjl_account_journal_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_journal_lines');
    }
};
