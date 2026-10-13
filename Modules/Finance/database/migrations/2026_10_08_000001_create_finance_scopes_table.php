<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', 'id', 'fs_org_fk')->cascadeOnDelete();
            $table->string('scope_type', 32);
            $table->unsignedBigInteger('scope_id');
            $table->string('name');
            $table->string('code', 100);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'scope_type', 'scope_id'], 'fs_scope_uq');
            $table->index(['organization_id', 'scope_type', 'scope_id'], 'fs_scope_idx');
        });
    }

    public function down(): void { Schema::dropIfExists('finance_scopes'); }
};
