<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_attendance_corrections', function (Blueprint $table): void {
            $table->foreignId('event_pass_attendee_id')->nullable()->after('event_pass_id')->constrained('event_pass_attendees')->nullOnDelete();
            $table->string('before_attendee_status', 16)->nullable()->after('occupancy_delta');
            $table->string('after_attendee_status', 16)->nullable()->after('before_attendee_status');
            $table->index(['event_pass_attendee_id', 'corrected_at'], 'event_attendance_corrections_attendee_time_idx');
        });
    }

    public function down(): void
    {
        Schema::table('event_attendance_corrections', function (Blueprint $table): void {
            $table->dropIndex('event_attendance_corrections_attendee_time_idx');
            $table->dropConstrainedForeignId('event_pass_attendee_id');
            $table->dropColumn(['before_attendee_status', 'after_attendee_status']);
        });
    }
};
