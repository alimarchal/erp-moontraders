<?php

namespace App\Console\Commands\Accounting;

use App\Models\ChartOfAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckJournalBalance extends Command
{
    /** Account the rounding residual is carried on. */
    private const ROUND_OFF_ACCOUNT_CODE = '5271';

    protected $signature = 'accounting:check-journal-balance';

    protected $description = 'List posted journal entries whose debits and credits disagree, and say what the '
        .'Round Off line on each should have been. Posted lines are immutable at the database level, so this '
        .'reports rather than repairs — correcting one is a reversal, not an edit.';

    public function handle(): int
    {
        $unbalanced = DB::table('journal_entries as je')
            ->join('journal_entry_details as jed', 'jed.journal_entry_id', '=', 'je.id')
            ->where('je.status', 'posted')
            ->groupBy('je.id', 'je.entry_date', 'je.description')
            ->havingRaw('ABS(SUM(jed.debit) - SUM(jed.credit)) >= 0.01')
            ->select(
                'je.id',
                'je.entry_date',
                'je.description',
                DB::raw('SUM(jed.debit) as debits'),
                DB::raw('SUM(jed.credit) as credits')
            )
            ->orderBy('je.entry_date')
            ->get();

        if ($unbalanced->isEmpty()) {
            $this->info('Every posted journal entry balances.');

            return self::SUCCESS;
        }

        $roundOffAccountId = ChartOfAccount::where('account_code', self::ROUND_OFF_ACCOUNT_CODE)->value('id');
        $total = 0.0;

        $rows = $unbalanced->map(function ($entry) use ($roundOffAccountId, &$total) {
            $difference = round((float) $entry->debits - (float) $entry->credits, 2);
            $total += $difference;

            return [
                $entry->id,
                $entry->entry_date,
                Str::limit($entry->description ?? '', 40),
                number_format($difference, 2),
                $roundOffAccountId ? $this->describeRoundOff($entry->id, $roundOffAccountId) : 'n/a',
            ];
        })->all();

        $this->table(['Entry', 'Date', 'Description', 'Out by', 'Round Off should be'], $rows);
        $this->warn(sprintf(
            '%d entry(s) out of balance, %s in total — this is what the Trial Balance reports as a difference.',
            $unbalanced->count(),
            number_format($total, 2)
        ));
        $this->line('Posted lines cannot be edited (trg_block_posted_detail_updates). Correcting one means');
        $this->line('reversing the entry and re-posting it, which is an accounting decision.');

        return self::SUCCESS;
    }

    /**
     * What the Round Off line has to carry for the entry to balance: the residual of every
     * other line, which is how the posting code now derives it.
     */
    private function describeRoundOff(int $journalEntryId, int $roundOffAccountId): string
    {
        $totals = DB::table('journal_entry_details')
            ->where('journal_entry_id', $journalEntryId)
            ->where('chart_of_account_id', '!=', $roundOffAccountId)
            ->selectRaw('COALESCE(SUM(debit), 0) as debits, COALESCE(SUM(credit), 0) as credits')
            ->first();

        $residual = round((float) $totals->credits - (float) $totals->debits, 2);

        if (abs($residual) < 0.01) {
            return 'no Round Off line at all';
        }

        return ($residual > 0 ? 'Dr ' : 'Cr ').number_format(abs($residual), 2);
    }
}
