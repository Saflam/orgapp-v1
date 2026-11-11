<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_cost_centers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('code', 60);
            $table->string('owner_type', 30);
            $table->unsignedBigInteger('owner_id');
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'owner_type', 'owner_id'], 'fcc_owner_unique');
            $table->unique(['organization_id', 'code'], 'fcc_code_unique');
            $table->index(['organization_id', 'is_active'], 'fcc_org_active_idx');
        });

        Schema::table('finance_journal_lines', function (Blueprint $table): void {
            $table->foreignId('finance_cost_center_id')
                ->nullable()
                ->after('finance_account_id')
                ->constrained('finance_cost_centers', 'id', 'fjl_cost_center_fk')
                ->restrictOnDelete();
            $table->index(['finance_cost_center_id', 'journal_id'], 'fjl_costcenter_journal_idx');
        });
    }

    public function down(): void
    {
        Schema::table('finance_journal_lines', function (Blueprint $table): void {
            $table->dropIndex('fjl_costcenter_journal_idx');
            $table->dropConstrainedForeignId('finance_cost_center_id');
        });
        Schema::dropIfExists('finance_cost_centers');
    }
};
