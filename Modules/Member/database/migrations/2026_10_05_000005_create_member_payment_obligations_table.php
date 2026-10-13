<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('member_payment_obligations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('membership_application_id')->nullable()->constrained('membership_applications')->cascadeOnDelete();
            $table->foreignId('membership_id')->nullable()->constrained('memberships')->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('members')->cascadeOnDelete();
            $table->foreignId('member_payment_item_id')->nullable()->constrained('member_payment_items')->nullOnDelete();
            $table->foreignId('member_payment_item_rate_id')->nullable()->constrained('member_payment_item_rates')->nullOnDelete();
            $table->string('item_title');
            $table->string('occurrence_key', 80)->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('payable_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('INR');
            $table->string('status', 30)->default('pending');
            $table->date('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['member_id', 'member_payment_item_id', 'occurrence_key'], 'mpo_member_item_occurrence_unique');
            $table->unique(['membership_application_id', 'member_payment_item_id', 'occurrence_key'], 'mpo_application_item_occurrence_unique');
            $table->index(['organization_id', 'status', 'due_at'], 'mpo_org_status_due_idx');
            $table->index(['member_id', 'status', 'due_at'], 'mpo_member_status_due_idx');
        });

        Schema::create('member_payment_obligation_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_payment_obligation_id')->constrained('member_payment_obligations', 'id', 'mp_obl_alloc_fk')->cascadeOnDelete();
            $table->string('scope_type', 30);
            $table->foreignId('scope_id')->nullable();
            $table->decimal('percentage', 7, 4);
            $table->decimal('original_amount', 12, 2);
            $table->decimal('payable_amount', 12, 2);
            $table->timestamps();
            $table->index(['member_payment_obligation_id', 'scope_type', 'scope_id'], 'mpoa_obligation_scope_idx');
        });

        Schema::create('member_payment_obligation_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_payment_obligation_id')->constrained('member_payment_obligations', 'id', 'mp_obl_disc_fk')->cascadeOnDelete();
            $table->decimal('original_amount', 12, 2);
            $table->decimal('discount_amount', 12, 2);
            $table->decimal('payable_amount', 12, 2);
            $table->text('reason');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('member_payment_obligation_discounts');
        Schema::dropIfExists('member_payment_obligation_allocations');
        Schema::dropIfExists('member_payment_obligations');
    }
};
