<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_identifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('identification_type_id')
                ->constrained('identification_types')
                ->cascadeOnDelete();

            $table->string('identification_number');

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique(
                ['user_id', 'identification_type_id'],
                'user_identifications_user_type_unique'
            );

            $table->index(
                ['user_id'],
                'user_identifications_user_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_identifications');
    }
};