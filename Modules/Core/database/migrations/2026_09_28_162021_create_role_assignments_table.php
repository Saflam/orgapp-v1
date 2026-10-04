<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('role_assignments', function (Blueprint $table) {
        $table->id();

        $table->foreignId('organization_membership_id')
            ->constrained('organization_memberships')
            ->cascadeOnDelete();

        $table->foreignId('role_id')
            ->constrained('roles')
            ->cascadeOnDelete();

        $table->string('context_type', 100);

        $table->unsignedBigInteger('context_id');

        $table->timestamp('starts_at')->nullable();
        $table->timestamp('ends_at')->nullable();

        $table->timestamps();

        $table->unique(
            [
                'organization_membership_id',
                'role_id',
                'context_type',
                'context_id',
            ],
            'role_assignments_unique'
        );

        $table->index(
            ['context_type', 'context_id'],
            'role_assignments_context_idx'
        );

        $table->index(
            ['starts_at', 'ends_at'],
            'role_assignments_validity_idx'
        );
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_assignments');
    }
};
