<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committees', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();

            $table->foreignId('committee_type_id')
                ->constrained('committee_types')
                ->restrictOnDelete();

            $table->foreignId('unit_id')
                ->nullable()
                ->constrained('units')
                ->restrictOnDelete();

            $table->foreignId('parent_committee_id')
                ->nullable()
                ->constrained('committees')
                ->nullOnDelete();

            $table->string('name');

            $table->string('kind');
            $table->string('status')->default('active');

            $table->timestamps();

            $table->index([
                'organization_id',
                'kind',
                'status',
            ]);

            $table->index([
                'organization_id',
                'unit_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committees');
    }
};