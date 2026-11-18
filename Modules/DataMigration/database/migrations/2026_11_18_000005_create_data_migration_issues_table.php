<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_migration_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('migration_run_id')
                ->constrained('data_migration_runs')
                ->cascadeOnDelete();
            $table->string('entity', 80);
            $table->string('source_table', 100)->nullable();
            $table->string('source_id', 191)->nullable();
            $table->string('issue_code', 100);
            $table->string('severity', 20)->default('warning');
            $table->text('message');
            $table->json('details')->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignId('resolved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['migration_run_id', 'status'], 'data_migration_issues_run_status_idx');
            $table->index(['entity', 'issue_code'], 'data_migration_issues_entity_code_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_migration_issues');
    }
};
