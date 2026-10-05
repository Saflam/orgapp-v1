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
        Schema::create('membership_application_status_histories', function (
            Blueprint $table
        ) {
            $table->id();

            $table->foreignId('membership_application_id');

            $table->string('from_status')->nullable();

            $table->string('to_status');

            $table->foreignId('actor_user_id')->nullable();

            $table->text('notes')->nullable();

            $table->boolean('allow_reapply')->default(false);

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->foreign(
                'membership_application_id',
                'mah_application_id_fk'
            )
                ->references('id')
                ->on('membership_applications')
                ->cascadeOnDelete();

            $table->foreign(
                'actor_user_id',
                'mah_actor_user_id_fk'
            )
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(
                ['membership_application_id', 'created_at'],
                'mah_application_created_idx'
            );

            $table->index(
                ['actor_user_id', 'created_at'],
                'mah_actor_created_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membership_application_status_histories');
    }
};