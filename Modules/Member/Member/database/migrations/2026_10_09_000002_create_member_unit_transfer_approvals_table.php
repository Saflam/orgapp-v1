<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_unit_transfer_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_unit_transfer_id');
            $table->foreignId('organization_id');
            $table->foreignId('unit_id');
            $table->string('side', 20);
            $table->string('status', 20)->default('pending');
            $table->foreignId('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->foreign('member_unit_transfer_id', 'muta_transfer_fk')
                ->references('id')->on('member_unit_transfers')->cascadeOnDelete();
            $table->foreign('organization_id', 'muta_org_fk')
                ->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign(['organization_id', 'unit_id'], 'muta_org_unit_fk')
                ->references(['organization_id', 'id'])->on('units')->restrictOnDelete();
            $table->foreign('decided_by', 'muta_decided_by_fk')
                ->references('id')->on('users')->restrictOnDelete();

            $table->unique(
                ['member_unit_transfer_id', 'side'],
                'muta_transfer_side_unique'
            );
            $table->index(['unit_id', 'status'], 'muta_unit_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_unit_transfer_approvals');
    }
};
