<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spouse_relationship_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requester_application_id')->nullable()->constrained('membership_applications')->nullOnDelete();
            $table->foreignId('requester_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('target_member_id')->constrained('members')->cascadeOnDelete();
            $table->string('status', 30)->default('pending');
            $table->foreignId('responded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'target_member_id', 'status'], 'spouse_req_target_status_idx');
            $table->index(['requester_application_id', 'status'], 'spouse_req_application_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spouse_relationship_requests');
    }
};
