<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Purchase Price Difference (5274) carries what is left on Stock Received But Not
 * Billed (2142) once a supplier's goods receipts and invoices have both been posted:
 * the rupees by which the supplier's invoice differs from what the GRN recorded.
 *
 * A fresh database gets its chart of accounts from the seeders after migrating, so
 * this only acts when the parent group already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        $parent = DB::table('chart_of_accounts')->where('account_code', '5200')->first();

        if (! $parent || DB::table('chart_of_accounts')->where('account_code', '5274')->exists()) {
            return;
        }

        DB::table('chart_of_accounts')->insert([
            'parent_id' => $parent->id,
            'account_type_id' => $parent->account_type_id,
            'currency_id' => $parent->currency_id,
            'account_code' => '5274',
            'account_name' => 'Purchase Price Difference',
            'normal_balance' => 'debit',
            'description' => 'Difference between supplier invoices and the goods receipts they bill',
            'is_group' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $accountId = DB::table('chart_of_accounts')->where('account_code', '5274')->value('id');

        if ($accountId && ! DB::table('journal_entry_details')->where('chart_of_account_id', $accountId)->exists()) {
            DB::table('chart_of_accounts')->where('id', $accountId)->delete();
        }
    }
};
