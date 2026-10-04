<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_addresses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('address_type')->default('home');

            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();

            $table->string('locality')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();

            $table->boolean('is_primary')->default(false);

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index([
                'user_id',
                'address_type',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
    }
};