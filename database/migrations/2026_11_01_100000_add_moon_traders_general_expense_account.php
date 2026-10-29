<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CODE = '5201';

    /**
     * The account an expense voucher of Moon Traders itself is booked to by default, under
     * Indirect Expenses (5200). Created here so production only needs `php artisan migrate`.
     */
    public function up(): void
    {
        $parent = DB::table('chart_of_accounts')->where('account_code', '5200')->first();

        if (! $parent || DB::table('chart_of_accounts')->where('account_code', self::CODE)->exists()) {
            return;
        }

        $now = now();

        DB::table('chart_of_accounts')->insert([
            'parent_id' => $parent->id,
            'account_type_id' => $parent->account_type_id,
            'currency_id' => $parent->currency_id,
            'account_code' => self::CODE,
            'account_name' => 'Moon Traders General Expenses',
            'normal_balance' => 'debit',
            'description' => 'Daily expenses of Moon Traders itself, booked by expense voucher',
            'is_group' => false,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        $id = DB::table('chart_of_accounts')->where('account_code', self::CODE)->value('id');

        if ($id && ! DB::table('journal_entry_details')->where('chart_of_account_id', $id)->exists()) {
            DB::table('chart_of_accounts')->where('id', $id)->delete();
        }
    }
};
