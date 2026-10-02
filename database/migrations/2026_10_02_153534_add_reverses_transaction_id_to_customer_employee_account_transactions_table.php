<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FOREIGN_KEY = 'fk_ceat_reverses_transaction_id';

    /**
     * Reverting a sales settlement offsets each of its customer ledger rows with an
     * `adjustment` row dated on the day of the revert. Linking that row to the one it
     * undoes lets reports drop both, instead of reading a reversed recovery as fresh
     * credit or a reversed sale as a payment against some other sale.
     *
     * Reversals written before this column existed carry the link only in their notes
     * ("Reversal of transaction #123 for settlement ..."), so it is read back from there.
     */
    public function up(): void
    {
        Schema::table('customer_employee_account_transactions', function (Blueprint $table) {
            $table->foreignId('reverses_transaction_id')
                ->nullable()
                ->after('sales_settlement_id')
                ->constrained('customer_employee_account_transactions', 'id', self::FOREIGN_KEY)
                ->nullOnDelete();
        });

        DB::table('customer_employee_account_transactions')
            ->where('transaction_type', 'adjustment')
            ->where('notes', 'like', 'Reversal of transaction #%')
            ->orderBy('id')
            ->get(['id', 'customer_employee_account_id', 'notes'])
            ->each(function (object $reversal): void {
                if (! preg_match('/^Reversal of transaction #(\d+)\b/', $reversal->notes, $match)) {
                    return;
                }

                $originalExists = DB::table('customer_employee_account_transactions')
                    ->where('id', (int) $match[1])
                    ->where('customer_employee_account_id', $reversal->customer_employee_account_id)
                    ->exists();

                if ($originalExists) {
                    DB::table('customer_employee_account_transactions')
                        ->where('id', $reversal->id)
                        ->update(['reverses_transaction_id' => (int) $match[1]]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('customer_employee_account_transactions', function (Blueprint $table) {
            $table->dropForeign(self::FOREIGN_KEY);
            $table->dropColumn('reverses_transaction_id');
        });
    }
};
