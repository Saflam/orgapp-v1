<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('member_payment_items', function (Blueprint $table): void {
            $table->string('family_payment_policy', 30)->default('individual')->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('member_payment_items', function (Blueprint $table): void {
            $table->dropColumn('family_payment_policy');
        });
    }
};
