<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_attendance_corrections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('event_id');
            $table->foreignId('event_pass_id')->constrained('event_passes')->cascadeOnDelete();
            $table->foreignId('corrected_by')->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('before_inside');
            $table->unsignedSmallInteger('after_inside');
            $table->integer('occupancy_delta');
            $table->string('reason', 1000);
            $table->timestamp('corrected_at');
            $table->timestamps();
            $table->foreign(['organization_id', 'event_id'], 'event_attendance_corrections_org_event_fk')
                ->references(['organization_id', 'id'])->on('events')->cascadeOnDelete();
            $table->index(['organization_id', 'event_id', 'corrected_at'], 'event_attendance_corrections_event_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_attendance_corrections');
    }
};
