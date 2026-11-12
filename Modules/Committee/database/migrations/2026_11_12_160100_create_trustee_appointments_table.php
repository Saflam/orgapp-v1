<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trustee_appointments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trustee_id')->constrained('trustees')->restrictOnDelete();
            $table->foreignId('committee_id')->constrained('committees')->restrictOnDelete();
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->date('appointed_at');
            $table->date('ended_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('end_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['member_id', 'status'], 'trustee_appointments_member_status_idx');
            $table->index(['committee_id', 'status'], 'trustee_appointments_committee_status_idx');
            $table->index(['trustee_id', 'appointed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trustee_appointments');
    }
};
