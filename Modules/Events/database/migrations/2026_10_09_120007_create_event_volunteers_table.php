<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_volunteers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('event_id');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamp('assigned_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign(['organization_id', 'event_id'], 'event_volunteers_org_event_fk')
                ->references(['organization_id', 'id'])
                ->on('events')
                ->cascadeOnDelete();

            $table->index(['organization_id', 'event_id', 'user_id', 'status'], 'event_volunteers_org_event_user_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_volunteers');
    }
};
