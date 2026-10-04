<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saradhi_member_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('member_id')
                ->unique()
                ->constrained('members')
                ->cascadeOnDelete();

            $table->string('governorate')->nullable();
            $table->string('sndp_branch')->nullable();
            $table->string('sndp_branch_number')->nullable();
            $table->string('sndp_union')->nullable();

            $table->string('introducer_name')->nullable();
            $table->string('introducer_calling_code')->nullable();
            $table->string('introducer_phone')->nullable();
            $table->string('introducer_mid')->nullable();

            $table->foreignId('introducer_unit_id')
                ->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->foreign('introducer_unit_id')
                ->references('id')
                ->on('units')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saradhi_member_profiles');
    }
};