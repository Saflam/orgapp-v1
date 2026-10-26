<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_dependant_relationships', function (Blueprint $table) {
            $table->id();

            $table->foreignId('member_id')
                ->constrained('members')
                ->restrictOnDelete();

            $table->foreignId('dependant_id')
                ->constrained('member_dependants')
                ->restrictOnDelete();

            $table->string('relationship_type');

            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(
                ['member_id', 'relationship_type'],
                'mdr_member_type_index'
            );

            $table->index(
                ['dependant_id', 'relationship_type'],
                'mdr_dependant_type_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_dependant_relationships');
    }
};