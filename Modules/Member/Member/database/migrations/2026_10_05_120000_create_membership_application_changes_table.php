<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_application_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_application_id');
            $table->foreign('membership_application_id', 'mac_application_id_fk')
                ->references('id')
                ->on('membership_applications')
                ->cascadeOnDelete();
            $table->foreignId('actor_user_id');
            $table->foreign('actor_user_id', 'mac_actor_user_id_fk')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->json('before_data');
            $table->json('after_data');
            $table->timestamps();
            $table->index(['membership_application_id', 'created_at'], 'mac_application_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_application_changes');
    }
};
