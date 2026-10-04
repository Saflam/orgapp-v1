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
        Schema::create('member_dependants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();

            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();

            $table->date('date_of_birth')->nullable();

            $table->string('gender')->nullable();

            $table->json('metadata')->nullable();

            /*
            * When this dependant becomes a member, we link the
            * existing dependant record to the newly created member.
            */
            $table->foreignId('converted_member_id')
                ->nullable()
                ->constrained('members')
                ->nullOnDelete();

            $table->date('converted_at')->nullable();

            $table->timestamps();

            $table->index([
                'organization_id',
                'converted_member_id',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_dependants');
    }
};
