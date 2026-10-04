<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designation_permissions', function (Blueprint $table) {
            $table->foreignId('designation_id')
                ->constrained('designations')
                ->restrictOnDelete();

            $table->foreignId('permission_id')
                ->constrained('permissions')
                ->restrictOnDelete();

            $table->primary([
                'designation_id',
                'permission_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('designation_permissions');
    }
};