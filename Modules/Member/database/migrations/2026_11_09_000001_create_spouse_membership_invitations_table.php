<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spouse_membership_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inviter_member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('invited_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->string('invitee_name');
            $table->char('token_hash', 64)->unique();
            $table->string('status', 30)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('membership_application_id')->nullable()->constrained('membership_applications')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'inviter_member_id', 'status'], 'spouse_invite_member_status_idx');
            $table->index(['organization_id', 'email', 'status'], 'spouse_invite_email_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spouse_membership_invitations');
    }
};
