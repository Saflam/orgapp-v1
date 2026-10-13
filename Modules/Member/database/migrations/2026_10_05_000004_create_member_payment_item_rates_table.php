<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('member_payment_item_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_payment_item_id')->constrained('member_payment_items')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('INR');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
            $table->index(['member_payment_item_id', 'effective_from', 'effective_to'], 'mpir_item_effective_idx');
        });

        Schema::create('member_payment_item_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_payment_item_rate_id')->constrained('member_payment_item_rates', 'id', 'mpia_rate_fk')->cascadeOnDelete();
            $table->string('scope_type', 30); // organization = central, unit = member's unit
            $table->decimal('percentage', 7, 4);
            $table->timestamps();
            $table->index(['member_payment_item_rate_id', 'scope_type'], 'mpia_rate_scope_idx');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('member_payment_item_allocations');
        Schema::dropIfExists('member_payment_item_rates');
    }
};
