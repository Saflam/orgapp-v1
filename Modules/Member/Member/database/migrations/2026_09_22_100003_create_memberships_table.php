<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();

            $table->foreignId('member_id')
                ->constrained('members')
                ->cascadeOnDelete();

            $table->foreignId('membership_type_id')->constrained('membership_types')->restrictOnDelete();

            $table->date('starts_at');

            $table->date('ends_at')->nullable();

            $table->string('status', 30)->default('in_review');

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['member_id', 'status'],'memberships_member_status_idx');

            $table->index(['membership_type_id', 'status'], 'memberships_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};