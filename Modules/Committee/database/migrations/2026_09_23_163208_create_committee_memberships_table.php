<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committee_memberships', function (Blueprint $table) {
            $table->id();

            $table->foreignId('committee_term_id')
                ->constrained('committee_terms')
                ->restrictOnDelete();

            $table->foreignId('member_id')
                ->constrained('members')
                ->restrictOnDelete();

            $table->foreignId('designation_id')
                ->constrained('designations')
                ->restrictOnDelete();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->string('status')->default('active');

            $table->timestamps();

            $table->index([
                'committee_term_id',
                'member_id',
            ]);

            $table->index([
                'member_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_memberships');
    }
};