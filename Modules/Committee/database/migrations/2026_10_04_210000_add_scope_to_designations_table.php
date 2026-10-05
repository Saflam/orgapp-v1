<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('designations', function (Blueprint $table) {
            $table->string('scope', 30)
                ->default('central')
                ->after('code');

            $table->index(
                ['organization_id', 'scope'],
                'designations_org_scope_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('designations', function (Blueprint $table) {
            $table->dropIndex('designations_org_scope_idx');
            $table->dropColumn('scope');
        });
    }
};
