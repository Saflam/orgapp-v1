<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trustees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->unsignedBigInteger('trustee_id');
            $table->timestamps();

            $table->unique(['organization_id', 'member_id'], 'trustees_org_member_unique');
            $table->unique(['organization_id', 'trustee_id'], 'trustees_org_trustee_id_unique');
            $table->index(['organization_id', 'trustee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trustees');
    }
};
