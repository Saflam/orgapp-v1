<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_periods', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations', 'id', 'fp_org_fk')
                ->cascadeOnDelete();

            $table->foreignId('finance_scope_id')
                ->constrained('finance_scopes', 'id', 'fp_scope_fk')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('code', 50);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 16)->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'finance_scope_id', 'code'],
                'fp_code_uq'
            );

            $table->index(
                ['finance_scope_id', 'starts_on', 'ends_on'],
                'fp_date_idx'
            );

            $table->index(
                ['organization_id', 'status'],
                'fp_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_periods');
    }
};
