<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_identifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('identification_type_id')
                ->constrained('identification_types')
                ->cascadeOnDelete();

            $table->boolean('is_enabled')
                ->default(true);

            $table->boolean('is_required')
                ->default(false);

            $table->boolean('requires_document')
                ->default(false);

            $table->timestamps();

            $table->unique(
                [
                    'organization_id',
                    'identification_type_id',
                ],
                'organization_identifications_org_type_unique'
            );

            $table->index(
                [
                    'organization_id',
                    'is_enabled',
                ],
                'organization_identifications_org_enabled_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_identifications');
    }
};