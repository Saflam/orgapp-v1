<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_relationships', function (Blueprint $table) {
            $table->id();

            $table->foreignId('member_id')
                ->constrained('members')
                ->cascadeOnDelete();

            $table->foreignId('related_member_id')
                ->constrained('members')
                ->cascadeOnDelete();

            $table->string('relationship_type', 50);

            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique(
                [
                    'member_id',
                    'related_member_id',
                    'relationship_type',
                ],
                'member_rel_member_related_type_unique'
            );

            $table->index(
                ['member_id', 'relationship_type'],
                'member_rel_member_type_idx'
            );

            $table->index(
                ['related_member_id', 'relationship_type'],
                'member_rel_related_type_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_relationships');
    }
};