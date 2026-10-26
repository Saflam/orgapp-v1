<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_applications', function (Blueprint $table) {
            $table->foreignId('member_id')
                ->nullable()
                ->after('membership_type_id')
                ->constrained('members')
                ->nullOnDelete();

            $table->unique(
                'member_id',
                'membership_applications_member_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('membership_applications', function (Blueprint $table) {
            $table->dropUnique('membership_applications_member_unique');
            $table->dropForeign(['member_id']);
            $table->dropColumn('member_id');
        });
    }
};
