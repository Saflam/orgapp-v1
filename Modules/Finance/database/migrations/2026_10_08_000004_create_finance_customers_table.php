<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', 'id', 'fc_org_fk')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users', 'id', 'fc_user_fk')->nullOnDelete();
            $table->string('customer_type', 32);
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->json('billing_address')->nullable();
            $table->boolean('is_system_managed')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'user_id'], 'fc_user_uq');
            $table->index(['organization_id', 'customer_type'], 'fc_type_idx');
        });
    }

    public function down(): void { Schema::dropIfExists('finance_customers'); }
};
