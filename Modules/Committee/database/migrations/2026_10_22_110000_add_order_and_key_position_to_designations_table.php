<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('designations', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')->default(100)->after('description');
            $table->boolean('is_key_position')->default(false)->after('sort_order');
            $table->index(['organization_id', 'scope', 'sort_order'], 'designations_org_scope_order_idx');
        });
    }

    public function down(): void
    {
        Schema::table('designations', function (Blueprint $table): void {
            $table->dropIndex('designations_org_scope_order_idx');
            $table->dropColumn(['sort_order', 'is_key_position']);
        });
    }
};
