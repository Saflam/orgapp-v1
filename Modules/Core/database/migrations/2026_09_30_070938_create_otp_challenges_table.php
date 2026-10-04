<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_challenges', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('purpose', 50);
            $table->string('channel', 30);
            $table->string('destination');

            $table->string('code_hash');

            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();

            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);

            $table->timestamps();

            $table->index(
                ['user_id', 'purpose', 'created_at'],
                'otp_challenges_user_purpose_created_idx'
            );

            $table->index(
                ['expires_at', 'verified_at'],
                'otp_challenges_expiry_verified_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_challenges');
    }
};