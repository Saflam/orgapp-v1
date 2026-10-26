<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('member_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('membership_application_id')->nullable()->constrained('membership_applications')->nullOnDelete();
            $table->foreignId('membership_id')->nullable()->constrained('memberships')->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('receipt_number', 80)->unique();
            $table->decimal('total_amount', 12, 2);
            $table->string('currency', 3)->default('INR');
            $table->string('payment_method', 50)->nullable();
            $table->string('reference', 150)->nullable();
            $table->string('collector_scope_type', 30);
            $table->foreignId('collector_scope_id')->nullable();
            $table->timestamp('received_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'received_at'], 'mp_org_received_idx');
            $table->index(['member_id', 'received_at'], 'mp_member_received_idx');
            $table->index(['collector_scope_type', 'collector_scope_id', 'received_at'], 'mp_collector_scope_idx');
        });

        Schema::create('member_payment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_payment_id')->constrained('member_payments')->cascadeOnDelete();
            $table->foreignId('member_payment_obligation_id')->constrained('member_payment_obligations')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->unique(['member_payment_id', 'member_payment_obligation_id'], 'mpl_payment_obligation_unique');
        });

        Schema::create('member_payment_line_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_payment_line_id')->constrained('member_payment_lines')->cascadeOnDelete();
            $table->string('scope_type', 30);
            $table->foreignId('scope_id')->nullable();
            $table->decimal('percentage', 7, 4);
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->index(['member_payment_line_id', 'scope_type', 'scope_id'], 'mpla_line_scope_idx');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('member_payment_line_allocations');
        Schema::dropIfExists('member_payment_lines');
        Schema::dropIfExists('member_payments');
    }
};
