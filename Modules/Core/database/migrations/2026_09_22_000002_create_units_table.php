<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->string('code', 50);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'id'], 'units_org_id_unique');
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'parent_id']);

            $table->foreign(['organization_id', 'parent_id'], 'units_org_parent_fk')
                ->references(['organization_id', 'id'])
                ->on('units')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
