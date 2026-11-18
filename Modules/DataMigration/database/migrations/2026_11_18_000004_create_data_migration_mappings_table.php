<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_migration_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('migration_run_id')
                ->nullable()
                ->constrained('data_migration_runs')
                ->nullOnDelete();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->string('source_connection', 64);
            $table->string('source_table', 100);
            $table->string('source_id', 191);
            $table->string('target_table', 100);
            $table->string('target_id', 191)->nullable();
            $table->char('source_checksum', 64)->nullable();
            $table->timestamp('mapped_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'source_connection', 'source_table', 'source_id'],
                'data_migration_mappings_source_unique'
            );
            $table->index(
                ['organization_id', 'target_table', 'target_id'],
                'data_migration_mappings_target_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_migration_mappings');
    }
};
