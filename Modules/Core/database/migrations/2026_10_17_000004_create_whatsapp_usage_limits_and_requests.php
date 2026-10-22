<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organization_whatsapp_usage_limits')) {
            Schema::create('organization_whatsapp_usage_limits', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('organization_id')->unique();
                $table->unsignedInteger('monthly_limit')->nullable();
                $table->unsignedTinyInteger('warning_threshold')->default(80);
                $table->date('period_start')->nullable();
                $table->unsignedInteger('period_reserved_count')->default(0);
                $table->timestamps();
                $table->foreign('organization_id', 'wa_limit_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('whatsapp_usage_limit_requests')) {
            Schema::create('whatsapp_usage_limit_requests', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('organization_id');
                $table->unsignedBigInteger('requested_by');
                $table->unsignedInteger('current_limit')->nullable();
                $table->unsignedInteger('requested_limit');
                $table->text('reason');
                $table->string('status', 20)->default('pending');
                $table->unsignedInteger('granted_limit')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->text('review_note')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->index(['organization_id', 'status'], 'wa_limit_req_org_status_idx');
                $table->foreign('organization_id', 'wa_limit_req_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
                $table->foreign('requested_by', 'wa_limit_req_user_fk')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('reviewed_by', 'wa_limit_req_reviewer_fk')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('whatsapp_usage_limit_audits')) {
            Schema::create('whatsapp_usage_limit_audits', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('organization_id');
                $table->unsignedBigInteger('changed_by')->nullable();
                $table->unsignedBigInteger('request_id')->nullable();
                $table->unsignedInteger('previous_limit')->nullable();
                $table->unsignedInteger('new_limit')->nullable();
                $table->string('action', 30);
                $table->text('reason')->nullable();
                $table->timestamps();
                $table->index(['organization_id', 'created_at'], 'wa_limit_audit_org_created_idx');
                $table->foreign('organization_id', 'wa_limit_audit_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
                $table->foreign('changed_by', 'wa_limit_audit_user_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('request_id', 'wa_limit_audit_request_fk')->references('id')->on('whatsapp_usage_limit_requests')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_usage_limit_audits');
        Schema::dropIfExists('whatsapp_usage_limit_requests');
        Schema::dropIfExists('organization_whatsapp_usage_limits');
    }
};
