<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Opening Balance": the money already in a bank account on the day the register starts. Posting it books
 * Dr the bank / Cr 3300 Opening Balance Equity (an overdraft the other way round), once per bank account.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('bank_entry_categories')->where('slug', 'opening-balance')->exists()) {
            return;
        }

        DB::table('bank_entry_categories')->insert([
            'name' => 'Opening Balance',
            'slug' => 'opening-balance',
            'type' => 'both',
            'default_chart_of_account_id' => DB::table('chart_of_accounts')->where('account_code', '3300')->value('id'),
            'is_active' => true,
            'sort_order' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('bank_entry_categories')->where('slug', 'opening-balance')->whereNotExists(
            fn ($query) => $query->select(DB::raw(1))->from('bank_statement_entries')->whereColumn('bank_statement_entries.bank_entry_category_id', 'bank_entry_categories.id')
        )->delete();
    }
};
