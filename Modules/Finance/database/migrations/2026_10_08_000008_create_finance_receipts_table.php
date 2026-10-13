<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_receipts', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations', 'id', 'fr_org_fk')
                ->cascadeOnDelete();

            $table->foreignId('finance_scope_id')
                ->constrained('finance_scopes', 'id', 'fr_scope_fk')
                ->cascadeOnDelete();

            $table->foreignId('finance_journal_id')
                ->constrained('finance_journals', 'id', 'fr_journal_fk')
                ->restrictOnDelete();

            $table->string('receipt_number', 100);
            $table->dateTime('issued_at');
            $table->string('status', 20)->default('issued');

            $table->string('source_type')->nullable();
            $table->string('source_id')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique('finance_journal_id', 'fr_journal_uq');
            $table->unique(['organization_id', 'finance_scope_id', 'receipt_number'], 'fr_number_uq');
            $table->index(['organization_id', 'issued_at'], 'fr_issued_idx');
            $table->index(['source_type', 'source_id'], 'fr_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_receipts');
    }
};
