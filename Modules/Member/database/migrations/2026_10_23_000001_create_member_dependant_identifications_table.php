<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_dependant_identifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dependant_id')->constrained('member_dependants')->cascadeOnDelete();
            $table->foreignId('identification_type_id')->constrained('identification_types')->restrictOnDelete();
            $table->string('identification_number')->nullable();
            $table->date('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['dependant_id', 'identification_type_id'], 'mdi_dependant_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_dependant_identifications');
    }
};
