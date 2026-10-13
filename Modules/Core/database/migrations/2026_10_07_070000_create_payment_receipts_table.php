<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_receipts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->string('owner_module', 100);
            $table->string('source_type', 150);
            $table->unsignedBigInteger('source_id');
            $table->string('receipt_number', 100);
            $table->timestamp('issued_at');
            $table->json('data')->nullable();
            $table->string('document_path')->nullable();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'source_type', 'source_id'],
                'payment_receipts_source_unique'
            );
            $table->unique('receipt_number', 'payment_receipts_number_unique');
            $table->index(
                ['organization_id', 'owner_module', 'issued_at'],
                'payment_receipts_owner_idx'
            );

            $table->foreign('organization_id', 'payment_receipts_organization_fk')
                ->references('id')
                ->on('organizations')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_receipts');
    }
};
