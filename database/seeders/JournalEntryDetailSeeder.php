<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JournalEntryDetailSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        // Resolve account IDs by account_name that exist in your Chart of Accounts seeder
        $id = fn (string $name) => DB::table('chart_of_accounts')->where('account_name', $name)->value('id');

        $rows = [
            // 1) Initial capital investment - $50,000
            ['journal_entry_id' => 1, 'account' => 'Cash', 'debit' => 50000.00, 'credit' => 0.00, 'description' => 'Cash received from owner investment'],
            ['journal_entry_id' => 1, 'account' => 'Capital Stock', 'debit' => 0.00, 'credit' => 50000.00, 'description' => 'Initial capital investment'],

            // 2) Purchase office equipment - $5,000
            ['journal_entry_id' => 2, 'account' => 'Office Equipments', 'debit' => 5000.00, 'credit' => 0.00, 'description' => 'Office equipment purchase'],
            ['journal_entry_id' => 2, 'account' => 'Cash', 'debit' => 0.00, 'credit' => 5000.00, 'description' => 'Payment for equipment'],

            // 3) Paid monthly rent - $2,000
            ['journal_entry_id' => 3, 'account' => 'Office Rent', 'debit' => 2000.00, 'credit' => 0.00, 'description' => 'Monthly office rent'],
            ['journal_entry_id' => 3, 'account' => 'Cash', 'debit' => 0.00, 'credit' => 2000.00, 'description' => 'Rent payment'],

            // 4) Service revenue on credit - $8,500
            ['journal_entry_id' => 4, 'account' => 'Debtors', 'debit' => 8500.00, 'credit' => 0.00, 'description' => 'Consulting services to ABC Corp'],
            ['journal_entry_id' => 4, 'account' => 'Service', 'debit' => 0.00, 'credit' => 8500.00, 'description' => 'Revenue from consulting'],

            // 5) Purchase office supplies - $300
            ['journal_entry_id' => 5, 'account' => 'Print and Stationery', 'debit' => 300.00, 'credit' => 0.00, 'description' => 'Stationery and supplies'],
            ['journal_entry_id' => 5, 'account' => 'Cash', 'debit' => 0.00, 'credit' => 300.00, 'description' => 'Payment for supplies'],

            // 6) Received payment from client - $8,500
            ['journal_entry_id' => 6, 'account' => 'Cash', 'debit' => 8500.00, 'credit' => 0.00, 'description' => 'Payment received from ABC Corp'],
            ['journal_entry_id' => 6, 'account' => 'Debtors', 'debit' => 0.00, 'credit' => 8500.00, 'description' => 'Collection of receivable'],

            // 7) Paid utility bills - $450
            ['journal_entry_id' => 7, 'account' => 'Utility Expenses', 'debit' => 450.00, 'credit' => 0.00, 'description' => 'Utilities bill'],
            ['journal_entry_id' => 7, 'account' => 'Cash', 'debit' => 0.00, 'credit' => 450.00, 'description' => 'Utility payments'],

            // 8) Owner withdrawal - $3,000
            ['journal_entry_id' => 8, 'account' => 'Dividends Paid', 'debit' => 3000.00, 'credit' => 0.00, 'description' => 'Owner withdrawal'],
            ['journal_entry_id' => 8, 'account' => 'Cash', 'debit' => 0.00, 'credit' => 3000.00, 'description' => 'Cash withdrawal'],

            // 9) Bank loan received - $20,000
            ['journal_entry_id' => 9, 'account' => 'Cash', 'debit' => 20000.00, 'credit' => 0.00, 'description' => 'Loan proceeds received'],
            ['journal_entry_id' => 9, 'account' => 'Secured Loans', 'debit' => 0.00, 'credit' => 20000.00, 'description' => 'Loan liability recorded'],

            // 10) Monthly depreciation - $250
            ['journal_entry_id' => 10, 'account' => 'Depreciation', 'debit' => 250.00, 'credit' => 0.00, 'description' => 'Depreciation expense'],
            ['journal_entry_id' => 10, 'account' => 'Accumulated Depreciation', 'debit' => 0.00, 'credit' => 250.00, 'description' => 'Accumulated depreciation'],
        ];

        $payload = [];
        $lineNo = 1;
        $currentJournalId = null;

        foreach ($rows as $r) {
            $accountId = $id($r['account']);
            if (! $accountId) {
                // Skip if account not found to avoid seeder failure; could also throw exception
                continue;
            }

            // Reset line number for new journal entry
            if ($currentJournalId !== $r['journal_entry_id']) {
                $lineNo = 1;
                $currentJournalId = $r['journal_entry_id'];
            }

            $payload[] = [
                'journal_entry_id' => $r['journal_entry_id'],
                'chart_of_account_id' => $accountId,
                'line_no' => $lineNo++,
                'debit' => $r['debit'],
                'credit' => $r['credit'],
                'description' => $r['description'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('journal_entry_details')->insert($payload);
    }
}
