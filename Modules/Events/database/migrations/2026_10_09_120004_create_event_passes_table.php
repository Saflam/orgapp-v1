<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_passes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('event_id');
            $table->foreignId('event_participant_id')->constrained('event_participants')->cascadeOnDelete();
            // Store only a SHA-256 hash; the bearer token is returned once at issuance.
            $table->char('token_hash', 64)->unique();
            $table->string('status', 20)->default('active');
            $table->unsignedSmallInteger('capacity')->default(1);
            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign(['organization_id', 'event_id'], 'event_passes_org_event_fk')
                ->references(['organization_id', 'id'])->on('events')->cascadeOnDelete();
            $table->unique('event_participant_id');
            $table->index(['organization_id', 'event_id', 'status'], 'event_passes_org_event_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_passes');
    }
};
