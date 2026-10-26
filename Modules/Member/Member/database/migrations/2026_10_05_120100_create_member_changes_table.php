<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id');
            $table->foreign('member_id', 'mc_member_id_fk')
                ->references('id')
                ->on('members')
                ->cascadeOnDelete();
            $table->foreignId('actor_user_id');
            $table->foreign('actor_user_id', 'mc_actor_user_id_fk')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->json('before_data');
            $table->json('after_data');
            $table->timestamps();
            $table->index(['member_id', 'created_at'], 'mc_member_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_changes');
    }
};
