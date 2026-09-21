<?php

namespace App\Console\Commands\Accounting;

use App\Models\ChartOfAccount;
use App\Services\DatabaseTriggerGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RepairJournalRounding extends Command
{
    /** Account the rounding residual is carried on. */
    private const ROUND_OFF_ACCOUNT_CODE = '5271';

    /**
     * The guards that make posted lines immutable. They are the point of the accounting
     * control, so they are lifted only for the moment the correction is written and are
     * always put back, including when the correction fails.
     */
    private const IMMUTABILITY_TRIGGERS = [
        'trg_block_posted_detail_updates',
        'trg_block_posted_detail_deletes',
    ];

    protected $signature = 'accounting:repair-journal-rounding
        {--max-difference=1.00 : Leave entries further out of balance than this alone}
        {--dry-run : Report what would change without saving}';

    protected $description = 'Correct the Round Off line on posted journal entries whose debits and credits '
        .'disagree by a rounding artefact. A journal entry cannot be brought back into balance by adding '
        .'another entry, so the offending line itself has to be corrected.';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $maxDifference = (float) $this->option('max-difference');

        $roundOffAccountId = ChartOfAccount::where('account_code', self::ROUND_OFF_ACCOUNT_CODE)->value('id');

        if (! $roundOffAccountId) {
            $this->error('Round Off account ('.self::ROUND_OFF_ACCOUNT_CODE.') not found.');

            return self::FAILURE;
        }

        $candidates = $this->unbalancedEntries()
            ->map(function ($entry) use ($roundOffAccountId, $maxDifference) {
                $difference = round((float) $entry->debits - (float) $entry->credits, 2);

                return (object) [
                    'id' => (int) $entry->id,
                    'entry_date' => $entry->entry_date,
                    'difference' => $difference,
                    'residual' => $this->residual((int) $entry->id, $roundOffAccountId),
                    // Anything larger than a rounding artefact is a posting error, and guessing
                    // at the right correction for it is not this command's job.
                    'repairable' => abs($difference) <= $maxDifference,
                ];
            });

        if ($candidates->isEmpty()) {
            $this->info('Every posted journal entry balances — nothing to do.');

            return self::SUCCESS;
        }

        $this->table(
            ['Entry', 'Date', 'Out by', 'Round Off becomes', 'Action'],
            $candidates->map(fn ($c) => [
                $c->id,
                $c->entry_date,
                number_format($c->difference, 2),
                abs($c->residual) < 0.01 ? '(line removed)' : ($c->residual > 0 ? 'Dr ' : 'Cr ').number_format(abs($c->residual), 2),
                $c->repairable ? 'repair' : 'SKIP — over --max-difference',
            ])->all()
        );

        $repairable = $candidates->where('repairable', true);

        if ($isDryRun) {
            $this->warn('DRY RUN — no data was changed. Re-run without --dry-run to apply.');

            return self::SUCCESS;
        }

        if ($repairable->isEmpty()) {
            $this->warn('Nothing within --max-difference to repair.');

            return self::SUCCESS;
        }

        $this->line('Lifting the posted-line guards for the duration of the correction...');

        $restore = app(DatabaseTriggerGuard::class)
            ->suspend('journal_entry_details', self::IMMUTABILITY_TRIGGERS);

        try {
            foreach ($repairable as $candidate) {
                $this->applyRepair($candidate, $roundOffAccountId);
            }
        } finally {
            $restore();
            $this->line('Posted-line guards restored.');
        }

        $stillWrong = $this->unbalancedEntries()->pluck('id');

        if ($stillWrong->isNotEmpty()) {
            $this->error('Still out of balance after repair: entry '.$stillWrong->implode(', '));

            return self::FAILURE;
        }

        $this->info(sprintf('Repaired %d entry(s). Every posted journal entry now balances.', $repairable->count()));

        Log::info('Repaired rounding on unbalanced posted journal entries', [
            'entries' => $repairable->pluck('id')->all(),
        ]);

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, object>
     */
    private function unbalancedEntries(): Collection
    {
        return DB::table('journal_entries as je')
            ->join('journal_entry_details as jed', 'jed.journal_entry_id', '=', 'je.id')
            ->where('je.status', 'posted')
            ->groupBy('je.id', 'je.entry_date')
            ->havingRaw('ABS(SUM(jed.debit) - SUM(jed.credit)) >= 0.01')
            ->select('je.id', 'je.entry_date', DB::raw('SUM(jed.debit) as debits'), DB::raw('SUM(jed.credit) as credits'))
            ->orderBy('je.entry_date')
            ->get();
    }

    /**
     * What the Round Off line has to carry for the entry to balance: the residual of every
     * other line, which is how the posting code derives it.
     */
    private function residual(int $journalEntryId, int $roundOffAccountId): float
    {
        $totals = DB::table('journal_entry_details')
            ->where('journal_entry_id', $journalEntryId)
            ->where('chart_of_account_id', '!=', $roundOffAccountId)
            ->selectRaw('COALESCE(SUM(debit), 0) as debits, COALESCE(SUM(credit), 0) as credits')
            ->first();

        return round((float) $totals->credits - (float) $totals->debits, 2);
    }

    private function applyRepair(object $candidate, int $roundOffAccountId): void
    {
        $line = DB::table('journal_entry_details')
            ->where('journal_entry_id', $candidate->id)
            ->where('chart_of_account_id', $roundOffAccountId)
            ->first();

        if (abs($candidate->residual) < 0.01) {
            if ($line) {
                DB::table('journal_entry_details')->where('id', $line->id)->delete();
                $this->line("  entry {$candidate->id}: removed the Round Off line");
            }

            return;
        }

        $values = [
            'debit' => $candidate->residual > 0 ? $candidate->residual : 0,
            'credit' => $candidate->residual < 0 ? abs($candidate->residual) : 0,
        ];

        if ($line) {
            DB::table('journal_entry_details')->where('id', $line->id)->update($values);
            $this->line("  entry {$candidate->id}: set the Round Off line to ".json_encode($values));

            return;
        }

        DB::table('journal_entry_details')->insert($values + [
            'journal_entry_id' => $candidate->id,
            'chart_of_account_id' => $roundOffAccountId,
            'line_no' => (int) DB::table('journal_entry_details')
                ->where('journal_entry_id', $candidate->id)->max('line_no') + 1,
            'description' => 'Rounding adjustment',
        ]);
        $this->line("  entry {$candidate->id}: added a Round Off line");
    }
}
