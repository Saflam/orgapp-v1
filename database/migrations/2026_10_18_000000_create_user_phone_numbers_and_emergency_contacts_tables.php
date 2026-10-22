<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_phone_numbers', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Examples: mobile, whatsapp, home, alternate.
            // Keep this extensible instead of using a database enum.
            $table->string('type', 50);
            $table->string('label')->nullable();
            $table->string('phone_e164', 20);
            $table->char('country_iso2', 2)->nullable();
            $table->string('extension', 20)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index('phone_e164');
        });

        Schema::create('user_emergency_contacts', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('contact_name');
            $table->string('relationship', 100)->nullable();
            $table->string('phone_e164', 20);
            $table->char('country_iso2', 2)->nullable();
            $table->string('extension', 20)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_primary']);
            $table->index('phone_e164');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_emergency_contacts');
        Schema::dropIfExists('user_phone_numbers');
    }
};
