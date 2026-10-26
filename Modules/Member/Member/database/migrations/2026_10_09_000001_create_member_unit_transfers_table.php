<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_unit_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('member_id');
            $table->foreignId('from_unit_id');
            $table->foreignId('to_unit_id');
            $table->boolean('spouse_included')->default(true);
            $table->foreignId('spouse_member_id')->nullable();
            $table->string('status', 30)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable();
            $table->timestamps();

            $table->foreign('organization_id', 'mut_org_fk')
                ->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('member_id', 'mut_member_fk')
                ->references('id')->on('members')->cascadeOnDelete();
            $table->foreign(['organization_id', 'from_unit_id'], 'mut_org_from_unit_fk')
                ->references(['organization_id', 'id'])->on('units')->restrictOnDelete();
            $table->foreign(['organization_id', 'to_unit_id'], 'mut_org_to_unit_fk')
                ->references(['organization_id', 'id'])->on('units')->restrictOnDelete();
            $table->foreign('spouse_member_id', 'mut_spouse_member_fk')
                ->references('id')->on('members')->restrictOnDelete();
            $table->foreign('rejected_by', 'mut_rejected_by_fk')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('cancelled_by', 'mut_cancelled_by_fk')
                ->references('id')->on('users')->restrictOnDelete();

            $table->index(['organization_id', 'status'], 'mut_org_status_idx');
            $table->index(['member_id', 'status'], 'mut_member_status_idx');
            $table->index(['from_unit_id', 'status'], 'mut_from_unit_status_idx');
            $table->index(['to_unit_id', 'status'], 'mut_to_unit_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_unit_transfers');
    }
};
