<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ChartOfAccountSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        // First, insert parent group accounts
        DB::table('chart_of_accounts')->insert([
            // ASSETS GROUP ACCOUNTS
            [
                'id' => 1,
                'parent_id' => null,
                'account_type_id' => 1, // Asset
                'account_code' => '1000',
                'account_name' => 'Current Assets',
                'normal_balance' => 'debit',
                'description' => 'Group account for current assets',
                'is_group' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'parent_id' => null,
                'account_type_id' => 1, // Asset
                'account_code' => '1500',
                'account_name' => 'Fixed Assets',
                'normal_balance' => 'debit',
                'description' => 'Group account for fixed assets',
                'is_group' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // LIABILITIES GROUP ACCOUNTS
            [
                'id' => 3,
                'parent_id' => null,
                'account_type_id' => 2, // Liability
                'account_code' => '2000',
                'account_name' => 'Current Liabilities',
                'normal_balance' => 'credit',
                'description' => 'Group account for current liabilities',
                'is_group' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 4,
                'parent_id' => null,
                'account_type_id' => 2, // Liability
                'account_code' => '2500',
                'account_name' => 'Long-term Liabilities',
                'normal_balance' => 'credit',
                'description' => 'Group account for long-term liabilities',
                'is_group' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // EQUITY GROUP ACCOUNTS
            [
                'id' => 5,
                'parent_id' => null,
                'account_type_id' => 3, // Equity
                'account_code' => '3000',
                'account_name' => 'Owner\'s Equity',
                'normal_balance' => 'credit',
                'description' => 'Group account for owner\'s equity',
                'is_group' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // REVENUE GROUP ACCOUNTS
            [
                'id' => 6,
                'parent_id' => null,
                'account_type_id' => 4, // Revenue
                'account_code' => '4000',
                'account_name' => 'Operating Revenue',
                'normal_balance' => 'credit',
                'description' => 'Group account for operating revenue',
                'is_group' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 7,
                'parent_id' => null,
                'account_type_id' => 4, // Revenue
                'account_code' => '4500',
                'account_name' => 'Other Revenue',
                'normal_balance' => 'credit',
                'description' => 'Group account for other revenue',
                'is_group' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // EXPENSE GROUP ACCOUNTS
            [
                'id' => 8,
                'parent_id' => null,
                'account_type_id' => 5, // Expense
                'account_code' => '5000',
                'account_name' => 'Operating Expenses',
                'normal_balance' => 'debit',
                'description' => 'Group account for operating expenses',
                'is_group' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 9,
                'parent_id' => null,
                'account_type_id' => 5, // Expense
                'account_code' => '6000',
                'account_name' => 'Administrative Expenses',
                'normal_balance' => 'debit',
                'description' => 'Group account for administrative expenses',
                'is_group' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // Now insert the detail accounts (posting accounts)
        DB::table('chart_of_accounts')->insert([
            // CURRENT ASSETS
            [
                'parent_id' => 1, // Current Assets
                'account_type_id' => 1, // Asset
                'account_code' => '1010',
                'account_name' => 'Cash',
                'normal_balance' => 'debit',
                'description' => 'Cash on hand and petty cash',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'parent_id' => 1, // Current Assets
                'account_type_id' => 1, // Asset
                'account_code' => '1020',
                'account_name' => 'Bank - Checking Account',
                'normal_balance' => 'debit',
                'description' => 'Primary business checking account',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'parent_id' => 1, // Current Assets
                'account_type_id' => 1, // Asset
                'account_code' => '1030',
                'account_name' => 'Bank - Savings Account',
                'normal_balance' => 'debit',
                'description' => 'Business savings account',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'parent_id' => 1, // Current Assets
                'account_type_id' => 1, // Asset
                'account_code' => '1100',
                'account_name' => 'Accounts Receivable',
                'normal_balance' => 'debit',
                'description' => 'Money owed to us by customers',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'parent_id' => 1, // Current Assets
                'account_type_id' => 1, // Asset
                'account_code' => '1200',
                'account_name' => 'Inventory',
                'normal_balance' => 'debit',
                'description' => 'Goods held for sale',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'parent_id' => 1, // Current Assets
                'account_type_id' => 1, // Asset
                'account_code' => '1300',
                'account_name' => 'Prepaid Expenses',
                'normal_balance' => 'debit',
                'description' => 'Expenses paid in advance',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // FIXED ASSETS
            [
                'parent_id' => 2, // Fixed Assets
                'account_type_id' => 1, // Asset
                'account_code' => '1510',
                'account_name' => 'Equipment',
                'normal_balance' => 'debit',
                'description' => 'Office and business equipment',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'parent_id' => 2, // Fixed Assets
                'account_type_id' => 1, // Asset
                'account_code' => '1520',
                'account_name' => 'Vehicles',
                'normal_balance' => 'debit',
                'description' => 'Company vehicles',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'parent_id' => 2, // Fixed Assets
                'account_type_id' => 1, // Asset
                'account_code' => '1530',
                'account_name' => 'Furniture & Fixtures',
                'normal_balance' => 'debit',
                'description' => 'Office furniture and fixtures',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // CURRENT LIABILITIES
            [
                'parent_id' => 3, // Current Liabilities
                'account_type_id' => 2, // Liability
                'account_code' => '2010',
                'account_name' => 'Accounts Payable',
                'normal_balance' => 'credit',
                'description' => 'Money owed to suppliers',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'parent_id' => 3, // Current Liabilities
                'account_type_id' => 2, // Liability
                'account_code' => '2020',
                'account_name' => 'Accrued Expenses',
                'normal_balance' => 'credit',
                'description' => 'Expenses incurred but not yet paid',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'parent_id' => 3, // Current Liabilities
                'account_type_id' => 2, // Liability
                'account_code' => '2030',
                'account_name' => 'Payroll Liabilities',
                'normal_balance' => 'credit',
                'description' => 'Wages and taxes payable',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'parent_id' => 3, // Current Liabilities
                'account_type_id' => 2, // Liability
                'account_code' => '2040',
                'account_name' => 'Sales Tax Payable',
                'normal_balance' => 'credit',
                'description' => 'Sales tax collected but not yet remitted',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // LONG-TERM LIABILITIES
            [
                'parent_id' => 4, // Long-term Liabilities
                'account_type_id' => 2, // Liability
                'account_code' => '2510',
                'account_name' => 'Bank Loan',
                'normal_balance' => 'credit',
                'description' => 'Long-term bank loans',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'parent_id' => 4, // Long-term Liabilities
                'account_type_id' => 2, // Liability
                'account_code' => '2520',
                'account_name' => 'Equipment Loan',
                'normal_balance' => 'credit',
                'description' => 'Loans for equipment purchases',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // EQUITY
            [
                'parent_id' => 5, // Owner's Equity
                'account_type_id' => 3, // Equity
                'account_code' => '3010',
                'account_name' => 'Owner\'s Capital',
                'normal_balance' => 'credit',
                'description' => 'Initial and additional capital investments',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'parent_id' => 5, // Owner's Equity
                'account_type_id' => 3, // Equity
                'account_code' => '3020',
                'account_name' => 'Owner\'s Drawings',
                'normal_balance' => 'debit',
                'description' => 'Owner withdrawals from business',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'parent_id' => 5, // Owner's Equity
                'account_type_id' => 3, // Equity
                'account_code' => '3030',
                'account_name' => 'Retained Earnings',
                'normal_balance' => 'credit',
                'description' => 'Accumulated profits retained in business',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // OPERATING REVENUE
            [
                'parent_id' => 6, // Operating Revenue
                'account_type_id' => 4, // Revenue
                'account_code' => '4010',
                'account_name' => 'Service Revenue',
                'normal_balance' => 'credit',
                'description' => 'Revenue from services provided',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'parent_id' => 6, // Operating Revenue
                'account_type_id' => 4, // Revenue
                'account_code' => '4020',
                'account_name' => 'Product Sales',
                'normal_balance' => 'credit',
                'description' => 'Revenue from product sales',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'parent_id' => 6, // Operating Revenue
                'account_type_id' => 4, // Revenue
                'account_code' => '4030',
                'account_name' => 'Consulting Revenue',
                'normal_balance' => 'credit',
                'description' => 'Revenue from consulting services',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // OTHER REVENUE
            [
                'parent_id' => 7, // Other Revenue
                'account_type_id' => 4, // Revenue
                'account_code' => '4510',
                'account_name' => 'Interest Income',
                'normal_balance' => 'credit',
                'description' => 'Interest earned on bank accounts',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'parent_id' => 7, // Other Revenue
                'account_type_id' => 4, // Revenue
                'account_code' => '4520',
                'account_name' => 'Rental Income',
                'normal_balance' => 'credit',
                'description' => 'Income from renting out property',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // OPERATING EXPENSES
            [
                'parent_id' => 8, // Operating Expenses
                'account_type_id' => 5, // Expense
                'account_code' => '5010',
                'account_name' => 'Cost of Goods Sold',
                'normal_balance' => 'debit',
                'description' => 'Direct costs of products sold',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'parent_id' => 8, // Operating Expenses
                'account_type_id' => 5, // Expense
                'account_code' => '5020',
                'account_name' => 'Rent Expense',
                'normal_balance' => 'debit',
                'description' => 'Office and facility rent',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'parent_id' => 8, // Operating Expenses
                'account_type_id' => 5, // Expense
                'account_code' => '5030',
                'account_name' => 'Utilities Expense',
                'normal_balance' => 'debit',
                'description' => 'Electricity, water, gas, internet',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'parent_id' => 8, // Operating Expenses
                'account_type_id' => 5, // Expense
                'account_code' => '5040',
                'account_name' => 'Marketing Expense',
                'normal_balance' => 'debit',
                'description' => 'Advertising and promotional costs',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'parent_id' => 8, // Operating Expenses
                'account_type_id' => 5, // Expense
                'account_code' => '5050',
                'account_name' => 'Travel Expense',
                'normal_balance' => 'debit',
                'description' => 'Business travel and transportation',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ADMINISTRATIVE EXPENSES
            [
                'parent_id' => 9, // Administrative Expenses
                'account_type_id' => 5, // Expense
                'account_code' => '6010',
                'account_name' => 'Salaries Expense',
                'normal_balance' => 'debit',
                'description' => 'Employee salaries and wages',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'parent_id' => 9, // Administrative Expenses
                'account_type_id' => 5, // Expense
                'account_code' => '6020',
                'account_name' => 'Professional Fees',
                'normal_balance' => 'debit',
                'description' => 'Legal, accounting, and consulting fees',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'parent_id' => 9, // Administrative Expenses
                'account_type_id' => 5, // Expense
                'account_code' => '6030',
                'account_name' => 'Office Supplies',
                'normal_balance' => 'debit',
                'description' => 'Stationery, printer supplies, etc.',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'parent_id' => 9, // Administrative Expenses
                'account_type_id' => 5, // Expense
                'account_code' => '6040',
                'account_name' => 'Insurance Expense',
                'normal_balance' => 'debit',
                'description' => 'Business insurance premiums',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'parent_id' => 9, // Administrative Expenses
                'account_type_id' => 5, // Expense
                'account_code' => '6050',
                'account_name' => 'Depreciation Expense',
                'normal_balance' => 'debit',
                'description' => 'Depreciation of fixed assets',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'parent_id' => 9, // Administrative Expenses
                'account_type_id' => 5, // Expense
                'account_code' => '6060',
                'account_name' => 'Bank Charges',
                'normal_balance' => 'debit',
                'description' => 'Bank fees and service charges',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
