<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', 'id', 'fi_org_fk')->cascadeOnDelete();
            $table->string('sku', 100);
            $table->string('title');
            $table->string('unit', 50)->default('unit');
            $table->decimal('selling_price', 20, 4);
            $table->string('currency', 3);
            $table->foreignId('sales_account_id')->nullable()->constrained('finance_accounts', 'id', 'fi_sales_fk')->nullOnDelete();
            $table->string('source', 32)->default('manual');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->boolean('is_system_managed')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'sku'], 'fi_sku_uq');
            $table->index(['organization_id', 'source', 'source_type', 'source_id'], 'fi_source_idx');
        });
    }

    public function down(): void { Schema::dropIfExists('finance_items'); }
};
