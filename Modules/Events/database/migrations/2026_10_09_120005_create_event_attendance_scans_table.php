<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_passes', function (Blueprint $table): void {
            $table->unsignedSmallInteger('current_inside')->default(0)->after('capacity');
        });

        Schema::create('event_attendance_scans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('event_id');
            $table->foreignId('event_pass_id')->constrained('event_passes')->cascadeOnDelete();
            $table->foreignId('scanned_by')->constrained('users')->restrictOnDelete();
            $table->string('action', 10);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->timestamp('scanned_at');
            $table->timestamps();

            $table->foreign(['organization_id', 'event_id'], 'event_attendance_scans_org_event_fk')
                ->references(['organization_id', 'id'])->on('events')->cascadeOnDelete();
            $table->index(['organization_id', 'event_id', 'scanned_at'], 'event_attendance_scans_event_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_attendance_scans');
        Schema::table('event_passes', function (Blueprint $table): void {
            $table->dropColumn('current_inside');
        });
    }
};
