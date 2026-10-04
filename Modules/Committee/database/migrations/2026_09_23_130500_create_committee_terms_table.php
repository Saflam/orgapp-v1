<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committee_terms', function (Blueprint $table) {
            $table->id();

            $table->foreignId('committee_id')
                ->constrained('committees')
                ->restrictOnDelete();

            $table->date('start_date');
            $table->date('end_date');

            $table->string('status')->default('draft');

            $table->timestamps();

            $table->index([
                'committee_id',
                'status',
            ]);

            $table->index([
                'start_date',
                'end_date',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_terms');
    }
};