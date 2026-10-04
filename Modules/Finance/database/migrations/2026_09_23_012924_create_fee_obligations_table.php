<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_obligations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('member_id')
                ->constrained('members')
                ->cascadeOnDelete();

            $table->foreignId('membership_id')
                ->nullable()
                ->constrained('memberships')
                ->nullOnDelete();

            $table->foreignId('fee_type_id')
                ->constrained('fee_types')
                ->restrictOnDelete();

            $table->foreignId('fee_policy_id')
                ->nullable()
                ->constrained('fee_policies')
                ->nullOnDelete();

            $table->decimal('amount', 12, 2);

            $table->date('period_start');
            $table->date('period_end');

            $table->date('due_at')->nullable();

            $table->string('status', 30)->default('due');

            $table->date('waived_at')->nullable();
            $table->text('waiver_reason')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(
                ['member_id', 'status'],
                'fee_obl_member_status_idx'
            );

            $table->index(
                ['membership_id', 'status'],
                'fee_obl_membership_status_idx'
            );

            $table->index(
                ['fee_type_id', 'period_start', 'period_end'],
                'fee_obl_type_period_idx'
            );

            $table->index(
                ['due_at', 'status'],
                'fee_obl_due_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_obligations');
    }
};