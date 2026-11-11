<?php

use App\Models\SalesSettlement;
use App\Services\BankRegister\SettlementBankRegisterSync;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Settlements posted before the register took their bank money have no register lines. Every posted settlement with
 * online recoveries, bank transfers or bank slips gets its lines now, booked by the settlement's own journal (nothing new
 * is posted), so the register can be reconciled against the bank statements from the start. Running it again changes
 * nothing: the sync keeps the lines it already made.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sync = app(SettlementBankRegisterSync::class);
        $totals = ['settlements' => 0, 'linked' => 0, 'kept' => 0, 'flagged' => 0];

        SalesSettlement::query()
            ->where('status', 'posted')
            ->whereNotNull('journal_entry_id')
            ->where(fn ($query) => $query->whereHas('bankSlips')
                ->orWhereHas('bankTransfers')
                ->orWhereHas('recoveries', fn ($recoveries) => $recoveries->whereNotIn('payment_method', ['cash', 'cheque'])))
            ->chunkById(200, function ($settlements) use ($sync, &$totals) {
                foreach ($settlements as $settlement) {
                    $result = DB::transaction(fn () => $sync->sync($settlement));
                    $sync->linkAttachments($settlement);

                    $totals['settlements']++;
                    $totals['linked'] += $result['linked'];
                    $totals['kept'] += $result['kept'];
                    $totals['flagged'] += $result['flagged'];
                }
            });

        Log::info('Settlement bank money added to the Bank Register', $totals);
    }

    public function down(): void
    {
        // The lines are part of the register's history and are kept.
    }
};
