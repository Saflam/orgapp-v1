<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_migration_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('migration_run_id')
                ->constrained('data_migration_runs')
                ->cascadeOnDelete();
            $table->string('entity', 80);
            $table->unsignedInteger('batch_number');
            $table->string('cursor_value', 191)->nullable();
            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['migration_run_id', 'entity', 'batch_number'],
                'data_migration_batches_run_entity_batch_unique'
            );
            $table->index(['migration_run_id', 'status'], 'data_migration_batches_run_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_migration_batches');
    }
};
