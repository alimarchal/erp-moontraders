<?php

use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Services\AccountingService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Settlements posted before the clearing-leg fix left a debit balance on Salesman Clearing (1123).
     * Each is listed with the balance it is expected to hold, so a settlement is only adjusted while
     * its 1123 balance still equals that amount: running this twice, or on a database where one of
     * them was already corrected by hand, posts nothing for it.
     *
     * The first five are settlements whose credit sales, bank transfers and cheques added up to more
     * than the goods sold (the cash portion came out negative and its clearing leg was never posted):
     * Dr Sales / Cr Salesman Clearing, which is the leg the posting now writes. The last is a 1.96
     * rounding difference between the expense lines and the cash handed in: Dr Round Off.
     *
     * @var array<string, array{amount: float, debit: string, reason: string}>
     */
    private const RESIDUALS = [
        'SETTLE-2026-0086' => ['amount' => 32821.66, 'debit' => '4110', 'reason' => 'Cheque sales above the cash portion of goods sold'],
        'SETTLE-2026-0522' => ['amount' => 12596.91, 'debit' => '4110', 'reason' => 'Credit sales and bank transfers above goods sold'],
        'SETTLE-2026-0709' => ['amount' => 153483.16, 'debit' => '4110', 'reason' => 'Credit sales and bank slips above goods sold'],
        'SETTLE-2026-2039' => ['amount' => 132574.98, 'debit' => '4110', 'reason' => 'Credit sales and bank slips above goods sold'],
        'SETTLE-2026-2073' => ['amount' => 225.00, 'debit' => '4110', 'reason' => 'Invoice amount above goods sold (advance tax income)'],
        'SETTLE-2026-2022' => ['amount' => 1.96, 'debit' => '5271', 'reason' => 'Rounding difference on expenses'],
    ];

    public function up(): void
    {
        $clearing = ChartOfAccount::where('account_code', '1123')->value('id');

        if (! $clearing) {
            return;
        }

        $date = now()->toDateString();
        $posted = [];

        DB::transaction(function () use ($clearing, $date, &$posted): void {
            foreach (self::RESIDUALS as $settlement => $residual) {
                $balance = $this->clearingBalance($clearing, $settlement);

                if (abs($balance - $residual['amount']) >= 0.01) {
                    continue;
                }

                $this->post($clearing, $settlement, $residual, $date);
                $posted[$settlement] = $residual['amount'];
            }
        });

        if ($posted !== []) {
            Log::info('Cleared Salesman Clearing residuals', ['entries' => $posted, 'date' => $date]);
        }
    }

    /**
     * Posted journals cannot be edited, and the adjustments are separate entries, so there is nothing
     * to undo safely.
     */
    public function down(): void {}

    private function clearingBalance(int $clearingAccountId, string $settlement): float
    {
        return round((float) DB::table('journal_entry_details as d')
            ->join('journal_entries as e', 'e.id', '=', 'd.journal_entry_id')
            ->where('e.status', 'posted')
            ->where('d.chart_of_account_id', $clearingAccountId)
            ->where('e.reference', 'like', $settlement.'%')
            ->selectRaw('COALESCE(SUM(d.debit - d.credit), 0) as balance')
            ->value('balance'), 2);
    }

    /**
     * @param  array{amount: float, debit: string, reason: string}  $residual
     */
    private function post(int $clearingAccountId, string $settlement, array $residual, string $date): void
    {
        $debitAccountId = ChartOfAccount::where('account_code', $residual['debit'])->value('id');
        $costCenterId = CostCenter::where('code', 'CC004')->value('id');

        if (! $debitAccountId || ! $costCenterId) {
            throw new RuntimeException("Account {$residual['debit']} or cost center CC004 is missing; cannot clear {$settlement}.");
        }

        $description = "{$residual['reason']} - {$settlement}";

        $result = app(AccountingService::class)->createJournalEntry([
            'entry_date' => $date,
            'reference' => 'CLEAR-1123-'.$settlement,
            'description' => "Salesman Clearing residual cleared - {$settlement}",
            'lines' => [
                ['account_id' => $debitAccountId, 'debit' => $residual['amount'], 'credit' => 0, 'description' => $description, 'cost_center_id' => $costCenterId],
                ['account_id' => $clearingAccountId, 'debit' => 0, 'credit' => $residual['amount'], 'description' => $description, 'cost_center_id' => $costCenterId],
            ],
            'auto_post' => true,
        ]);

        if (! $result['success']) {
            throw new RuntimeException("Could not post the clearing entry for {$settlement}: {$result['message']}");
        }
    }
};
