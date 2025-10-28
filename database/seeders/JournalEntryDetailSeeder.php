<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class JournalEntryDetailSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        DB::table('journal_entry_details')->insert([
            // Transaction 1: Initial capital investment - $50,000
            [
                'journal_entry_id' => 1,
                'chart_of_account_id' => 11, // Bank - Checking Account (1020)
                'debit' => 50000.00,
                'credit' => 0.00,
                'description' => 'Cash received from owner investment',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'journal_entry_id' => 1,
                'chart_of_account_id' => 17, // Owner's Capital (3010)
                'debit' => 0.00,
                'credit' => 50000.00,
                'description' => 'Initial capital investment',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Transaction 2: Purchase office equipment - $5,000
            [
                'journal_entry_id' => 2,
                'chart_of_account_id' => 16, // Equipment (1510)
                'debit' => 5000.00,
                'credit' => 0.00,
                'description' => 'Office computer and furniture',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'journal_entry_id' => 2,
                'chart_of_account_id' => 11, // Bank - Checking Account (1020)
                'debit' => 0.00,
                'credit' => 5000.00,
                'description' => 'Payment for equipment',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Transaction 3: Paid monthly rent - $2,000
            [
                'journal_entry_id' => 3,
                'chart_of_account_id' => 26, // Rent Expense (5020)
                'debit' => 2000.00,
                'credit' => 0.00,
                'description' => 'January office rent',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'journal_entry_id' => 3,
                'chart_of_account_id' => 11, // Bank - Checking Account (1020)
                'debit' => 0.00,
                'credit' => 2000.00,
                'description' => 'Rent payment',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Transaction 4: Service revenue - $8,500
            [
                'journal_entry_id' => 4,
                'chart_of_account_id' => 13, // Accounts Receivable (1100)
                'debit' => 8500.00,
                'credit' => 0.00,
                'description' => 'Consulting services to ABC Corp',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'journal_entry_id' => 4,
                'chart_of_account_id' => 20, // Service Revenue (4010)
                'debit' => 0.00,
                'credit' => 8500.00,
                'description' => 'Revenue from consulting',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Transaction 5: Purchase office supplies - $300
            [
                'journal_entry_id' => 5,
                'chart_of_account_id' => 36, // Office Supplies (6030)
                'debit' => 300.00,
                'credit' => 0.00,
                'description' => 'Stationery and printer supplies',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'journal_entry_id' => 5,
                'chart_of_account_id' => 11, // Bank - Checking Account (1020)
                'debit' => 0.00,
                'credit' => 300.00,
                'description' => 'Payment for supplies',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Transaction 6: Received payment from client - $8,500
            [
                'journal_entry_id' => 6,
                'chart_of_account_id' => 11, // Bank - Checking Account (1020)
                'debit' => 8500.00,
                'credit' => 0.00,
                'description' => 'Payment received from ABC Corp',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'journal_entry_id' => 6,
                'chart_of_account_id' => 13, // Accounts Receivable (1100)
                'debit' => 0.00,
                'credit' => 8500.00,
                'description' => 'Collection of receivable',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Transaction 7: Paid utility bills - $450
            [
                'journal_entry_id' => 7,
                'chart_of_account_id' => 27, // Utilities Expense (5030)
                'debit' => 450.00,
                'credit' => 0.00,
                'description' => 'Electricity and internet bills',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'journal_entry_id' => 7,
                'chart_of_account_id' => 11, // Bank - Checking Account (1020)
                'debit' => 0.00,
                'credit' => 450.00,
                'description' => 'Utility payments',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Transaction 8: Owner withdrawal - $3,000
            [
                'journal_entry_id' => 8,
                'chart_of_account_id' => 18, // Owner's Drawings (3020)
                'debit' => 3000.00,
                'credit' => 0.00,
                'description' => 'Personal withdrawal by owner',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'journal_entry_id' => 8,
                'chart_of_account_id' => 11, // Bank - Checking Account (1020)
                'debit' => 0.00,
                'credit' => 3000.00,
                'description' => 'Cash withdrawal',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Transaction 9: Bank loan received - $20,000
            [
                'journal_entry_id' => 9,
                'chart_of_account_id' => 11, // Bank - Checking Account (1020)
                'debit' => 20000.00,
                'credit' => 0.00,
                'description' => 'Loan proceeds received',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'journal_entry_id' => 9,
                'chart_of_account_id' => 21, // Bank Loan (2510)
                'debit' => 0.00,
                'credit' => 20000.00,
                'description' => 'Long-term bank loan',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Transaction 10: Monthly depreciation - $250
            [
                'journal_entry_id' => 10,
                'chart_of_account_id' => 38, // Depreciation Expense (6050)
                'debit' => 250.00,
                'credit' => 0.00,
                'description' => 'Depreciation on equipment',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'journal_entry_id' => 10,
                'chart_of_account_id' => 16, // Equipment (1510)
                'debit' => 0.00,
                'credit' => 250.00,
                'description' => 'Accumulated depreciation',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
