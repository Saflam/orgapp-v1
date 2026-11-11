<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_settlement_obligations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('debtor_scope_type', 30);
            $table->unsignedBigInteger('debtor_scope_id')->nullable();
            $table->string('creditor_scope_type', 30);
            $table->unsignedBigInteger('creditor_scope_id')->nullable();
            $table->foreignId('member_payment_id')->nullable()->constrained('member_payments')->nullOnDelete();
            $table->foreignId('member_payment_line_id')->nullable()->constrained('member_payment_lines')->nullOnDelete();
            $table->decimal('original_amount', 12, 2);
            $table->decimal('outstanding_amount', 12, 2);
            $table->string('currency', 3)->default('INR');
            $table->string('status', 24)->default('outstanding');
            $table->date('transaction_date');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['member_payment_line_id', 'creditor_scope_type', 'creditor_scope_id'], 'fso_payment_beneficiary_uq');
            $table->index(['organization_id', 'debtor_scope_type', 'debtor_scope_id', 'status'], 'fso_debtor_status_idx');
            $table->index(['organization_id', 'creditor_scope_type', 'creditor_scope_id', 'status'], 'fso_creditor_status_idx');
        });

        Schema::create('finance_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('finance_settlement_obligation_id')->constrained('finance_settlement_obligations')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('transaction_date');
            $table->foreignId('payer_cash_account_id')->constrained('finance_accounts')->restrictOnDelete();
            $table->foreignId('payee_cash_account_id')->constrained('finance_accounts')->restrictOnDelete();
            $table->foreignId('finance_journal_id')->nullable()->constrained('finance_journals')->restrictOnDelete();
            $table->string('status', 30)->default('pending_confirmation');
            $table->string('reference', 150)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique('finance_journal_id', 'fs_journal_unique');
            $table->index(['organization_id', 'status', 'transaction_date'], 'fs_org_status_date_idx');
        });

        Schema::create('finance_settlement_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('finance_settlement_id')->constrained('finance_settlements')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['finance_settlement_id', 'created_at'], 'fsa_settlement_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_settlement_audits');
        Schema::dropIfExists('finance_settlements');
        Schema::dropIfExists('finance_settlement_obligations');
    }
};
