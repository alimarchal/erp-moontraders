<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
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
            ->whereNull('reverses_transaction_id')
            ->where('notes', 'like', 'Reversal of transaction #%')
            ->select(['id', 'customer_employee_account_id', 'notes'])
            ->chunkById(500, function (Collection $reversals): void {
                $this->linkReversals($reversals);
            });
    }

    /**
     * One lookup and one update per batch. A link is kept only when the original is a row
     * of the same account, so a mistyped note cannot tie two customers' ledgers together.
     *
     * @param  Collection<int, object>  $reversals
     */
    private function linkReversals(Collection $reversals): void
    {
        $originalIds = $reversals
            ->mapWithKeys(fn (object $reversal) => [
                $reversal->id => preg_match('/^Reversal of transaction #(\d+)\b/', $reversal->notes, $match) ? (int) $match[1] : null,
            ])
            ->filter();

        $originalAccounts = DB::table('customer_employee_account_transactions')
            ->whereIn('id', $originalIds->values()->unique()->all())
            ->pluck('customer_employee_account_id', 'id');

        $links = $originalIds->filter(fn (int $originalId, int $reversalId) => (int) ($originalAccounts[$originalId] ?? 0)
            === (int) $reversals->firstWhere('id', $reversalId)->customer_employee_account_id);

        if ($links->isEmpty()) {
            return;
        }

        $cases = $links->map(fn (int $originalId, int $reversalId) => "WHEN {$reversalId} THEN {$originalId}")->implode(' ');

        DB::table('customer_employee_account_transactions')
            ->whereIn('id', $links->keys()->all())
            ->update(['reverses_transaction_id' => DB::raw("CASE id {$cases} END")]);
    }

    public function down(): void
    {
        Schema::table('customer_employee_account_transactions', function (Blueprint $table) {
            $table->dropForeign(self::FOREIGN_KEY);
            $table->dropColumn('reverses_transaction_id');
        });
    }
};
