<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_tax_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name', 150);
            $table->decimal('rate', 9, 4);
            $table->string('applies_to', 16)->default('both');
            $table->foreignId('sales_account_id')->nullable()->constrained('finance_accounts')->restrictOnDelete();
            $table->foreignId('purchase_account_id')->nullable()->constrained('finance_accounts')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'code'], 'finance_tax_rates_org_code_uq');
            $table->index(['organization_id', 'is_active'], 'finance_tax_rates_org_active_idx');
        });

        Schema::create('finance_tax_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name', 150);
            $table->string('applies_to', 16)->default('both');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'code'], 'finance_tax_groups_org_code_uq');
        });

        Schema::create('finance_tax_group_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('finance_tax_group_id')->constrained('finance_tax_groups')->cascadeOnDelete();
            $table->foreignId('finance_tax_rate_id')->constrained('finance_tax_rates')->restrictOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['finance_tax_group_id', 'finance_tax_rate_id'], 'finance_tax_group_rate_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_tax_group_rates');
        Schema::dropIfExists('finance_tax_groups');
        Schema::dropIfExists('finance_tax_rates');
    }
};
