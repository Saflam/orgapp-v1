<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('finance_accounts')) {
            return;
        }

        DB::table('finance_accounts')
            ->whereIn('code', ['1000', '1100'])
            ->where('account_type', 'asset')
            ->update(['is_cash_account' => true]);

        DB::table('finance_accounts')
            ->whereIn('code', ['1200', '2000', '4000', '5000'])
            ->whereNull('cash_flow_category')
            ->update(['cash_flow_category' => 'operating']);
    }

    public function down(): void
    {
        // Keep accounting classifications on rollback; they are valid account settings.
    }
};
