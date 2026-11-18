<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_migration_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->string('source_connection', 64)->default('legacy');
            $table->string('status', 30)->default('pending')->index();
            $table->boolean('is_dry_run')->default(true);
            $table->json('options')->nullable();
            $table->json('summary')->nullable();
            $table->foreignId('started_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'data_migration_runs_org_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_migration_runs');
    }
};
