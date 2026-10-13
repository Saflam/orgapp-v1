<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('event_pass_attendees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('event_id');
            $table->foreignId('event_pass_id')->constrained('event_passes')->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('email', 254)->nullable();
            $table->string('category', 40)->default('guest');
            $table->string('status', 20)->default('outside');
            $table->timestamp('first_entered_at')->nullable();
            $table->timestamp('last_entered_at')->nullable();
            $table->timestamp('last_exited_at')->nullable();
            $table->unsignedInteger('entry_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign(['organization_id', 'event_id'], 'event_pass_attendees_org_event_fk')
                ->references(['organization_id', 'id'])->on('events')->cascadeOnDelete();
            $table->index(['event_pass_id', 'status'], 'event_pass_attendees_pass_status_idx');
            $table->unique(['event_pass_id', 'email'], 'event_pass_attendees_pass_email_unique');
        });
        Schema::table('event_attendance_scans', function (Blueprint $table): void {
            $table->foreignId('event_pass_attendee_id')->nullable()->after('event_pass_id')
                ->constrained('event_pass_attendees')->nullOnDelete();
        });
    }
    public function down(): void {
        Schema::table('event_attendance_scans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('event_pass_attendee_id');
        });
        Schema::dropIfExists('event_pass_attendees');
    }
};
