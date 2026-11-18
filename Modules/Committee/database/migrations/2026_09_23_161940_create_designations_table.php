<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();

            $table->string('name');
            $table->string('code');
            $table->string('scope', 30)->default('central');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(100);
            $table->boolean('is_key_position')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique([
                'organization_id',
                'code',
            ]);
            $table->index(['organization_id', 'scope'], 'designations_org_scope_idx');
            $table->index(['organization_id', 'scope', 'sort_order'], 'designations_org_scope_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('designations');
    }
};