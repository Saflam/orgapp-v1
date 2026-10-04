<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id');

            $table->foreignId('user_id');

            $table->foreignId('unit_id')->nullable();

            $table->string('membership_number');

            $table->date('joined_at')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            $table->foreign(
                ['organization_id', 'unit_id'],
                'members_org_unit_fk'
            )
                ->references(['organization_id', 'id'])
                ->on('units')
                ->restrictOnDelete();

            $table->unique(
                ['organization_id', 'user_id'],
                'members_org_user_unique'
            );

            $table->unique(
                ['organization_id', 'membership_number'],
                'members_org_number_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};