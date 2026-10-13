<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_journals', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations', 'id', 'fj_org_fk')
                ->cascadeOnDelete();

            $table->foreignId('finance_scope_id')
                ->constrained('finance_scopes', 'id', 'fj_scope_fk')
                ->restrictOnDelete();

            $table->foreignId('finance_period_id')
                ->constrained('finance_periods', 'id', 'fj_period_fk')
                ->restrictOnDelete();

            $table->string('journal_number', 80);
            $table->date('transaction_date');
            $table->text('description')->nullable();

            $table->string('source_type', 150)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->string('status', 16)->default('posted');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'finance_scope_id', 'journal_number'],
                'fj_number_uq'
            );

            $table->index(
                ['finance_scope_id', 'transaction_date'],
                'fj_scope_date_idx'
            );

            $table->index(
                ['source_type', 'source_id'],
                'fj_source_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_journals');
    }
};
