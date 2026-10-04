<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_modules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->string('module', 100);

            $table->boolean('is_enabled')
                ->default(false);

            $table->timestamps();

            $table->unique(
                ['organization_id', 'module'],
                'organization_modules_org_module_unique'
            );

            $table->index(
                ['organization_id', 'is_enabled'],
                'organization_modules_org_enabled_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_modules');
    }
};