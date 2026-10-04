<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('fee_obligation_id')
                ->constrained('fee_obligations')
                ->restrictOnDelete();

            $table->decimal('amount', 12, 2);

            $table->dateTime('paid_at');

            $table->string('payment_method', 50);

            $table->string('reference', 100)->nullable();

            $table->string('status', 30)->default('completed');

            $table->dateTime('reversed_at')->nullable();

            $table->text('reversal_reason')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(
                ['fee_obligation_id', 'status'],
                'payments_obligation_status_idx'
            );

            $table->index(
                ['paid_at', 'status'],
                'payments_paid_status_idx'
            );

            $table->index(
                ['reference'],
                'payments_reference_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};