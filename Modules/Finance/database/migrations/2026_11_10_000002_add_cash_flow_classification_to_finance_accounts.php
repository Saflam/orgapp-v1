<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('finance_accounts', function (Blueprint $table): void {
            $table->boolean('is_cash_account')->default(false)->after('is_active');
            $table->string('cash_flow_category', 16)->nullable()->after('is_cash_account');
        });
    }

    public function down(): void
    {
        Schema::table('finance_accounts', function (Blueprint $table): void {
            $table->dropColumn(['is_cash_account', 'cash_flow_category']);
        });
    }
};
