<?php

/**
 * Engro (supplier 4) credit correction as of 2026-08-31.
 *
 * Credit entries that were keyed into the wrong settlement are moved to the right
 * customer/salesman account. Settlements are NOT touched.
 *
 *  - Transfers (salesman ↔ salesman, customer ↔ customer): paired 'adjustment' rows,
 *    one debit + one credit of the same amount. GL Debtors (1111) is a single control
 *    account, so its total does not change and no journal entry is needed.
 *  - Husnain Traders 50,000: a 'recovery' row + one journal entry that follows the settlement flow.
 *    The money is already with the company, so the salesman clearing nets to zero:
 *      Dr 1123 Salesman Clearing / Cr 1111 Debtors   (recovery)
 *      Dr 1121 Cash              / Cr 1123 Salesman Clearing (cash already with company)
 *
 * Dry run (default) — everything is rolled back:
 *   php artisan tinker --execute 'require base_path("scripts/engro_credit_fix_2026_08_31.php");'
 * Apply:
 *   APPLY=1 php artisan tinker --execute 'require base_path("scripts/engro_credit_fix_2026_08_31.php");'
 *
 * Safe to re-run: it aborts without writing if any balance at 31 Aug differs from the
 * expected "before" value, or if the COR-ENG-260831-* entries already exist.
 */

use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\CustomerEmployeeAccountTransaction;
use App\Services\AccountingService;
use Illuminate\Support\Facades\DB;

$apply = getenv('APPLY') === '1';
$date = '2026-08-31';
$refPrefix = 'COR-ENG-260831-';
$note = 'Correction entry (not via settlement) - entered in wrong settlement, corrected as at 31 Aug 2026';

// [account id, customer code, employee id, balance as at 31 Aug before, balance after]
$accounts = [
    128 => ['N00000290601', 66, 3882328.00, 4589099.00],
    660 => ['N00000290601', 68, 706771.00, 0.00],
    254 => ['N00000003340', 66, -309800.00, 0.00],
    661 => ['N00000003340', 68, 309800.00, 0.00],
    640 => ['N00000005344', 66, 57100.00, 0.00],
    136 => ['N00000005344', 68, 1820.00, 58920.00],
    318 => ['N00000303832', 66, 311430.00, 309480.00],
    126 => ['N00000005668', 66, 298400.00, 300350.00],
    382 => ['N00000006575', 68, 7370.00, 0.00],
    144 => ['N00000003318', 68, -370.00, 7000.00],
    129 => ['N00000006871', 65, 182550.00, 132550.00],
];

// [from account (credit), to account (debit), amount, reason]
$transfers = [
    [660, 128, 706771.00, 'Credit sale CSI-260701-00001 (SETTLE 1043) was posted to Zeeshan Ismail, belongs to Seerat Abbas'],
    [661, 254, 309800.00, 'Credit sale CSI-260701-00002 (SETTLE 1043) was posted to Zeeshan Ismail, belongs to Seerat Abbas (recovered by Seerat Abbas REC-260813-00001)'],
    [640, 136, 57100.00, 'Credit sale CSI-260629-00002 (SETTLE 856) was posted to Seerat Abbas, belongs to Zeeshan Ismail'],
    [318, 126, 1950.00, 'Balance posted to Muzaffarabad Cash & Carry (N00000303832), belongs to AK Super Store (N00000005668)'],
    [382, 144, 7370.00, 'Balance posted to MEHRIA G. S (N00000006575), belongs to Pakeeza Bakers (N00000003318)'],
];

$recoveryAccountId = 129;
$recoveryAmount = 50000.00;

$balanceAsAt = fn (int $accountId): float => round((float) DB::table('customer_employee_account_transactions')
    ->where('customer_employee_account_id', $accountId)
    ->whereNull('deleted_at')
    ->where('transaction_date', '<=', $date)
    ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance')
    ->value('balance'), 2);

$salesmanTotals = fn (): array => DB::table('customer_employee_account_transactions as t')
    ->join('customer_employee_accounts as cea', 'cea.id', '=', 't.customer_employee_account_id')
    ->join('employees as e', 'e.id', '=', 'cea.employee_id')
    ->whereNull('t.deleted_at')
    ->whereIn('e.id', [65, 66, 68])
    ->where('t.transaction_date', '<=', $date)
    ->groupBy('e.id', 'e.name')
    ->orderBy('e.id')
    ->selectRaw('e.id, e.name, SUM(t.debit) - SUM(t.credit) as balance')
    ->get()
    ->mapWithKeys(fn ($row) => [$row->name => round((float) $row->balance, 2)])
    ->all();

$glBalance = fn (string $code): float => round((float) DB::table('journal_entry_details as d')
    ->join('journal_entries as j', 'j.id', '=', 'd.journal_entry_id')
    ->join('chart_of_accounts as a', 'a.id', '=', 'd.chart_of_account_id')
    ->where('a.account_code', $code)
    ->where('j.status', 'posted')
    ->whereNull('j.deleted_at')
    ->selectRaw('COALESCE(SUM(d.debit), 0) - COALESCE(SUM(d.credit), 0) as balance')
    ->value('balance'), 2);

$ceatTotal = fn (): float => round((float) DB::table('customer_employee_account_transactions')
    ->whereNull('deleted_at')
    ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance')
    ->value('balance'), 2);

echo ($apply ? '*** APPLY ***' : '*** DRY RUN (rolled back) ***').PHP_EOL.PHP_EOL;

// ---- Pre-checks: nothing is written unless every account is exactly as expected ----
$problems = [];

if (CustomerEmployeeAccountTransaction::withTrashed()->where('reference_number', 'like', $refPrefix.'%')->exists()) {
    $problems[] = "Already applied: rows with reference {$refPrefix}* exist.";
}

foreach ($accounts as $accountId => [$customerCode, $employeeId, $before]) {
    $row = DB::table('customer_employee_accounts as cea')
        ->join('customers as c', 'c.id', '=', 'cea.customer_id')
        ->where('cea.id', $accountId)
        ->first(['c.customer_code', 'cea.employee_id']);

    if (! $row || $row->customer_code !== $customerCode || (int) $row->employee_id !== $employeeId) {
        $problems[] = "Account {$accountId} is not {$customerCode} / employee {$employeeId}.";

        continue;
    }

    $actual = $balanceAsAt($accountId);
    if (abs($actual - $before) > 0.001) {
        $problems[] = "Account {$accountId} ({$customerCode}) balance at {$date} is {$actual}, expected {$before}.";
    }
}

$clearing = ChartOfAccount::where('account_code', '1123')->first();
$cash = ChartOfAccount::where('account_code', '1121')->first();
$debtors = ChartOfAccount::where('account_code', '1111')->first();
$salesCostCenter = CostCenter::where('code', 'CC004')->first();

if (! $clearing || ! $cash || ! $debtors || ! $salesCostCenter) {
    $problems[] = 'Account 1123 / 1121 / 1111 or cost center CC004 not found.';
}

if ($problems !== []) {
    echo 'ABORTED - nothing written:'.PHP_EOL.' - '.implode(PHP_EOL.' - ', $problems).PHP_EOL;

    return;
}

$salesmenBefore = $salesmanTotals();
$gl1111Before = $glBalance('1111');
$gl1123Before = $glBalance('1123');
$gl1121Before = $glBalance('1121');
$ceatBefore = $ceatTotal();

$accountLabel = fn (int $accountId): string => DB::table('customer_employee_accounts as cea')
    ->join('customers as c', 'c.id', '=', 'cea.customer_id')
    ->join('employees as e', 'e.id', '=', 'cea.employee_id')
    ->where('cea.id', $accountId)
    ->selectRaw("CONCAT(c.customer_name, ' (', c.customer_code, ') - ', e.name) as label")
    ->value('label');

DB::beginTransaction();

try {
    $sequence = 0;
    $nextReference = function () use (&$sequence, $refPrefix): string {
        $sequence++;

        return $refPrefix.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
    };

    $insert = fn (array $row) => CustomerEmployeeAccountTransaction::create($row + [
        'transaction_date' => $date,
        'notes' => $note,
    ]);

    foreach ($transfers as [$fromId, $toId, $amount, $reason]) {
        $reference = $nextReference();
        $from = $accountLabel($fromId);
        $to = $accountLabel($toId);

        $insert([
            'customer_employee_account_id' => $fromId,
            'transaction_type' => 'adjustment',
            'reference_number' => $reference,
            'description' => "Correction: transferred to {$to} - {$reason}",
            'debit' => 0,
            'credit' => $amount,
        ]);
        $insert([
            'customer_employee_account_id' => $toId,
            'transaction_type' => 'adjustment',
            'reference_number' => $reference,
            'description' => "Correction: received from {$from} - {$reason}",
            'debit' => $amount,
            'credit' => 0,
        ]);

        printf('%s  %-70s Cr %12s%s', $reference, $from, number_format($amount, 2), PHP_EOL);
        printf('%s  %-70s Dr %12s%s', $reference, $to, number_format($amount, 2), PHP_EOL);
    }

    // Husnain Traders — correction recovery through Salesman Clearing, like a settlement recovery.
    $reference = $nextReference();
    $label = $accountLabel($recoveryAccountId);
    $description = "Correction: Cash Recovery from Debtor - {$label} (ID: 65) - {$reference} - not via settlement";
    $cashDescription = "Correction: Cash already with company (recovery not recorded, lockdown period) - Adnan Awan (ID: 65) - {$reference}";

    $result = app(AccountingService::class)->createJournalEntry([
        'entry_date' => $date,
        'reference' => $reference,
        'description' => 'Correction: Recovery from Husnain Traders (N00000006871) - Adnan Awan - not via settlement',
        'lines' => [
            ['line_no' => 1, 'account_id' => $clearing->id, 'debit' => $recoveryAmount, 'credit' => 0, 'description' => $description, 'cost_center_id' => $salesCostCenter->id],
            ['line_no' => 2, 'account_id' => $debtors->id, 'debit' => 0, 'credit' => $recoveryAmount, 'description' => $description, 'cost_center_id' => $salesCostCenter->id],
            ['line_no' => 3, 'account_id' => $cash->id, 'debit' => $recoveryAmount, 'credit' => 0, 'description' => $cashDescription, 'cost_center_id' => $salesCostCenter->id],
            ['line_no' => 4, 'account_id' => $clearing->id, 'debit' => 0, 'credit' => $recoveryAmount, 'description' => $cashDescription, 'cost_center_id' => $salesCostCenter->id],
        ],
        'auto_post' => true,
    ]);

    if (! $result['success'] || $result['data']->status !== 'posted') {
        throw new RuntimeException('Journal entry failed: '.$result['message']);
    }

    $journalEntry = $result['data'];

    $insert([
        'customer_employee_account_id' => $recoveryAccountId,
        'transaction_type' => 'recovery',
        'reference_number' => $reference,
        'description' => $description,
        'debit' => 0,
        'credit' => $recoveryAmount,
        'payment_method' => 'cash',
        'journal_entry_id' => $journalEntry->id,
        'posted_at' => now(),
    ]);

    printf('%s  %-70s Cr %12s  (JE #%d: Dr 1123/Cr 1111 + Dr 1121/Cr 1123)%s', $reference, $label, number_format($recoveryAmount, 2), $journalEntry->id, PHP_EOL);

    // ---- Post-checks ----
    $failures = [];

    foreach ($accounts as $accountId => [$customerCode, $employeeId, $before, $after]) {
        $actual = $balanceAsAt($accountId);
        if (abs($actual - $after) > 0.001) {
            $failures[] = "Account {$accountId} ({$customerCode}) is {$actual}, expected {$after}.";
        }
    }

    $salesmenAfter = $salesmanTotals();
    $expectedSalesmen = ['Adnan Awan' => 269550.00, 'Seerat Abbas' => 5809269.00, 'Zeeshan Ismail' => 990292.00];
    foreach ($expectedSalesmen as $name => $expected) {
        if (abs(($salesmenAfter[$name] ?? 0) - $expected) > 0.001) {
            $failures[] = "{$name} total is {$salesmenAfter[$name]}, expected {$expected}.";
        }
    }

    $gl1111After = $glBalance('1111');
    $gl1123After = $glBalance('1123');
    $gl1121After = $glBalance('1121');
    $ceatAfter = $ceatTotal();

    if (abs(($gl1111After - $gl1111Before) - ($ceatAfter - $ceatBefore)) > 0.001) {
        $failures[] = 'GL 1111 and customer ledger moved by different amounts.';
    }

    if (abs($gl1123After - $gl1123Before) > 0.001 || abs(($gl1121After - $gl1121Before) - $recoveryAmount) > 0.001) {
        $failures[] = 'Salesman Clearing must stay unchanged and Cash must rise by the recovery.';
    }

    $adnanClearing = (float) DB::table('journal_entry_details')
        ->where('journal_entry_id', $journalEntry->id)
        ->where('chart_of_account_id', $clearing->id)
        ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance')
        ->value('balance');
    if (abs($adnanClearing) > 0.001) {
        $failures[] = "JE #{$journalEntry->id} leaves {$adnanClearing} on Salesman Clearing.";
    }

    $unbalanced = DB::table('journal_entry_details')
        ->where('journal_entry_id', $journalEntry->id)
        ->selectRaw('SUM(debit) - SUM(credit) as diff')
        ->value('diff');
    if (abs((float) $unbalanced) > 0.001) {
        $failures[] = "JE #{$journalEntry->id} is not balanced ({$unbalanced}).";
    }

    echo PHP_EOL.'Salesman totals at '.$date.':'.PHP_EOL;
    foreach ($salesmenAfter as $name => $balance) {
        printf('  %-16s %14s -> %14s%s', $name, number_format($salesmenBefore[$name], 2), number_format($balance, 2), PHP_EOL);
    }
    printf('  %-16s %14s -> %14s%s', 'TOTAL', number_format(array_sum($salesmenBefore), 2), number_format(array_sum($salesmenAfter), 2), PHP_EOL);

    echo PHP_EOL.'Ledger movement:'.PHP_EOL;
    printf('  Customer ledger (all) %14s%s', number_format($ceatAfter - $ceatBefore, 2), PHP_EOL);
    printf('  GL 1111 Debtors       %14s%s', number_format($gl1111After - $gl1111Before, 2), PHP_EOL);
    printf('  GL 1123 Sal. Clearing %14s%s', number_format($gl1123After - $gl1123Before, 2), PHP_EOL);
    printf('  GL 1121 Cash          %14s%s', number_format($gl1121After - $gl1121Before, 2), PHP_EOL);

    if ($failures !== []) {
        throw new RuntimeException("Checks failed:\n - ".implode("\n - ", $failures));
    }

    echo PHP_EOL.'All checks passed.'.PHP_EOL;

    if ($apply) {
        DB::commit();
        echo 'COMMITTED.'.PHP_EOL;
    } else {
        DB::rollBack();
        echo 'Rolled back - nothing saved. Run with APPLY=1 to save.'.PHP_EOL;
    }
} catch (Throwable $e) {
    DB::rollBack();
    echo PHP_EOL.'ROLLED BACK: '.$e->getMessage().PHP_EOL;
}
