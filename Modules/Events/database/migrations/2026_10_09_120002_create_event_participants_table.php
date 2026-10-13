<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('member_id')->nullable();
            $table->string('participant_type', 20);
            $table->string('name', 200)->nullable();
            $table->string('email', 254)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('status', 30)->default('invited');
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign(['organization_id', 'event_id'], 'event_participants_org_event_fk')
                ->references(['organization_id', 'id'])
                ->on('events')
                ->cascadeOnDelete();

            $table->foreign('member_id')
                ->references('id')
                ->on('members')
                ->nullOnDelete();

            $table->unique(['event_id', 'member_id'], 'event_participants_event_member_unique');
            $table->index(['organization_id', 'event_id', 'status'], 'event_participants_org_event_status_idx');
            $table->unique(['event_id', 'email'], 'event_participants_event_email_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_participants');
    }
};
