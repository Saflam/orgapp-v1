<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', 'id', 'fa_org_fk')->cascadeOnDelete();
            $table->foreignId('finance_scope_id')->constrained('finance_scopes', 'id', 'fa_scope_fk')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('finance_accounts', 'id', 'fa_parent_fk')->nullOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('account_type', 32);
            $table->string('normal_balance', 16);
            $table->boolean('is_system_managed')->default(false);
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'finance_scope_id', 'code'], 'fa_code_uq');
            $table->index(['organization_id', 'source_type', 'source_id'], 'fa_source_idx');
        });
    }

    public function down(): void { Schema::dropIfExists('finance_accounts'); }
};
