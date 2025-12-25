<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChartOfAccountSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        // Clear the table to avoid conflicts on re-seed
        DB::table('chart_of_accounts')->delete();

        // Get the base currency (PKR)
        $baseCurrency = DB::table('currencies')->where('is_base_currency', true)->first();
        if (! $baseCurrency) {
            throw new \Exception('Base currency not found. Please run CurrencySeeder first.');
        }

        // This is the raw data parsed from your 82-record CSV
        $csvData = [
            ['name' => 'Application of Funds (Assets)', 'parent' => 'Application of Funds (Assets)', 'type' => '', 'is_group' => 1, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Current Assets', 'parent' => 'Application of Funds (Assets)', 'type' => '', 'is_group' => 1, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Accounts Receivable', 'parent' => 'Current Assets', 'type' => '', 'is_group' => 1, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Debtors', 'parent' => 'Accounts Receivable', 'type' => 'Receivable', 'is_group' => 0, 'root_type' => 'Asset', 'disabled' => 0],

            ['name' => 'Cash In Hand', 'parent' => 'Current Assets', 'type' => 'Cash', 'is_group' => 1, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Cash', 'parent' => 'Cash In Hand', 'type' => 'Cash', 'is_group' => 0, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Cheques in Hand', 'parent' => 'Cash In Hand', 'type' => 'Cash', 'is_group' => 0, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Loans and Advances (Assets)', 'parent' => 'Current Assets', 'type' => '', 'is_group' => 1, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Employee Advances', 'parent' => 'Loans and Advances (Assets)', 'type' => 'Payable', 'is_group' => 0, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Securities and Deposits', 'parent' => 'Current Assets', 'type' => '', 'is_group' => 1, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Earnest Money', 'parent' => 'Securities and Deposits', 'type' => '', 'is_group' => 0, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Stock Assets', 'parent' => 'Current Assets', 'type' => 'Stock', 'is_group' => 1, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Stock In Hand', 'parent' => 'Stock Assets', 'type' => 'Stock', 'is_group' => 0, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Inventory - GST Component', 'parent' => 'Stock Assets', 'type' => 'Stock', 'is_group' => 0, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Inventory - Tax Component', 'parent' => 'Stock Assets', 'type' => 'Stock', 'is_group' => 0, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Inventory - Excise Duty', 'parent' => 'Stock Assets', 'type' => 'Stock', 'is_group' => 0, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Van Stock', 'parent' => 'Stock Assets', 'type' => 'Stock', 'is_group' => 0, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Tax Assets', 'parent' => 'Current Assets', 'type' => '', 'is_group' => 1, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Bank Accounts', 'parent' => 'Current Assets', 'type' => 'Bank', 'is_group' => 1, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Advance Tax', 'parent' => 'Tax Assets', 'type' => 'Tax', 'is_group' => 0, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Fixed Assets', 'parent' => 'Application of Funds (Assets)', 'type' => '', 'is_group' => 1, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Accumulated Depreciation', 'parent' => 'Fixed Assets', 'type' => 'Accumulated Depreciation', 'is_group' => 0, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Buildings', 'parent' => 'Fixed Assets', 'type' => 'Fixed Asset', 'is_group' => 0, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Capital Equipments', 'parent' => 'Fixed Assets', 'type' => 'Fixed Asset', 'is_group' => 0, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'CWIP Account', 'parent' => 'Fixed Assets', 'type' => 'Capital Work in Progress', 'is_group' => 0, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Electronic Equipments', 'parent' => 'Fixed Assets', 'type' => 'Fixed Asset', 'is_group' => 0, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Furnitures and Fixtures', 'parent' => 'Fixed Assets', 'type' => 'Fixed Asset', 'is_group' => 0, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Office Equipments', 'parent' => 'Fixed Assets', 'type' => 'Fixed Asset', 'is_group' => 0, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Plants and Machineries', 'parent' => 'Fixed Assets', 'type' => 'Fixed Asset', 'is_group' => 0, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Softwares', 'parent' => 'Fixed Assets', 'type' => 'Fixed Asset', 'is_group' => 0, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Investments', 'parent' => 'Application of Funds (Assets)', 'type' => '', 'is_group' => 1, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Temporary Accounts', 'parent' => 'Application of Funds (Assets)', 'type' => '', 'is_group' => 1, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Temporary Opening', 'parent' => 'Temporary Accounts', 'type' => 'Temporary', 'is_group' => 0, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Equity', 'parent' => 'Equity', 'type' => '', 'is_group' => 1, 'root_type' => 'Equity', 'disabled' => 0],
            ['name' => 'Capital Stock', 'parent' => 'Equity', 'type' => 'Equity', 'is_group' => 0, 'root_type' => 'Equity', 'disabled' => 0],
            ['name' => 'Dividends Paid', 'parent' => 'Equity', 'type' => 'Equity', 'is_group' => 0, 'root_type' => 'Equity', 'disabled' => 0],
            ['name' => 'Opening Balance Equity', 'parent' => 'Equity', 'type' => 'Equity', 'is_group' => 0, 'root_type' => 'Equity', 'disabled' => 0],
            ['name' => 'Retained Earnings', 'parent' => 'Equity', 'type' => 'Equity', 'is_group' => 0, 'root_type' => 'Equity', 'disabled' => 0],
            ['name' => 'Revaluation Surplus', 'parent' => 'Equity', 'type' => 'Equity', 'is_group' => 0, 'root_type' => 'Equity', 'disabled' => 0],
            ['name' => 'Expenses', 'parent' => 'Expenses', 'type' => '', 'is_group' => 1, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Direct Expenses', 'parent' => 'Expenses', 'type' => '', 'is_group' => 1, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Stock Expenses', 'parent' => 'Direct Expenses', 'type' => '', 'is_group' => 1, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Cost of Goods Sold', 'parent' => 'Stock Expenses', 'type' => 'Cost of Goods Sold', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Expenses Included In Asset Valuation', 'parent' => 'Stock Expenses', 'type' => 'Expenses Included In Asset Valuation', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Expenses Included In Valuation', 'parent' => 'Stock Expenses', 'type' => 'Expenses Included In Valuation', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Stock Adjustment', 'parent' => 'Stock Expenses', 'type' => 'Stock Adjustment', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Indirect Expenses', 'parent' => 'Expenses', 'type' => '', 'is_group' => 1, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Administrative Expenses', 'parent' => 'Indirect Expenses', 'type' => '', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Commission on Sales', 'parent' => 'Indirect Expenses', 'type' => '', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Depreciation', 'parent' => 'Indirect Expenses', 'type' => 'Depreciation', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Entertainment Expenses', 'parent' => 'Indirect Expenses', 'type' => '', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Exchange Gain/Loss', 'parent' => 'Indirect Expenses', 'type' => '', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Freight and Forwarding Charges', 'parent' => 'Indirect Expenses', 'type' => 'Chargeable', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Gain/Loss on Asset Disposal', 'parent' => 'Indirect Expenses', 'type' => '', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Impairment', 'parent' => 'Indirect Expenses', 'type' => '', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Legal Expenses', 'parent' => 'Indirect Expenses', 'type' => '', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Marketing Expenses', 'parent' => 'Indirect Expenses', 'type' => 'Chargeable', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Miscellaneous Expenses', 'parent' => 'Indirect Expenses', 'type' => 'Chargeable', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Office Maintenance Expenses', 'parent' => 'Indirect Expenses', 'type' => '', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Office Rent', 'parent' => 'Indirect Expenses', 'type' => '', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Postal Expenses', 'parent' => 'Indirect Expenses', 'type' => '', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Print and Stationery', 'parent' => 'Indirect Expenses', 'type' => '', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Round Off', 'parent' => 'Indirect Expenses', 'type' => 'Round Off', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Salary', 'parent' => 'Indirect Expenses', 'type' => '', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Sales Expenses', 'parent' => 'Indirect Expenses', 'type' => '', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Telephone Expenses', 'parent' => 'Indirect Expenses', 'type' => '', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Travel Expenses', 'parent' => 'Indirect Expenses', 'type' => '', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Utility Expenses', 'parent' => 'Indirect Expenses', 'type' => '', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Write Off', 'parent' => 'Indirect Expenses', 'type' => '', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'AMR Powder', 'parent' => 'Indirect Expenses', 'type' => '', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'AMR Liquid', 'parent' => 'Indirect Expenses', 'type' => '', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Toll Tax / Labor', 'parent' => 'Indirect Expenses', 'type' => '', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Food/Salesman/Loader Charges', 'parent' => 'Indirect Expenses', 'type' => '', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Scheme Discount Expense', 'parent' => 'Indirect Expenses', 'type' => '', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Inventory Shortage', 'parent' => 'Indirect Expenses', 'type' => '', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Percentage Expense', 'parent' => 'Indirect Expenses', 'type' => '', 'is_group' => 0, 'root_type' => 'Expense', 'disabled' => 0],
            ['name' => 'Income', 'parent' => 'Income', 'type' => '', 'is_group' => 1, 'root_type' => 'Income', 'disabled' => 0],
            ['name' => 'Direct Income', 'parent' => 'Income', 'type' => '', 'is_group' => 1, 'root_type' => 'Income', 'disabled' => 0],
            ['name' => 'Sales', 'parent' => 'Direct Income', 'type' => '', 'is_group' => 0, 'root_type' => 'Income', 'disabled' => 0],
            ['name' => 'Service', 'parent' => 'Direct Income', 'type' => '', 'is_group' => 0, 'root_type' => 'Income', 'disabled' => 0],
            ['name' => 'Indirect Income', 'parent' => 'Income', 'type' => '', 'is_group' => 1, 'root_type' => 'Income', 'disabled' => 0],
            ['name' => 'FMR Allowance', 'parent' => 'Indirect Income', 'type' => '', 'is_group' => 0, 'root_type' => 'Income', 'disabled' => 0],
            ['name' => 'Source of Funds (Liabilities)', 'parent' => 'Source of Funds (Liabilities)', 'type' => '', 'is_group' => 1, 'root_type' => 'Liability', 'disabled' => 0],
            ['name' => 'Current Liabilities', 'parent' => 'Source of Funds (Liabilities)', 'type' => '', 'is_group' => 1, 'root_type' => 'Liability', 'disabled' => 0],
            ['name' => 'Accounts Payable', 'parent' => 'Current Liabilities', 'type' => '', 'is_group' => 1, 'root_type' => 'Liability', 'disabled' => 0],
            ['name' => 'Creditors', 'parent' => 'Accounts Payable', 'type' => 'Payable', 'is_group' => 0, 'root_type' => 'Liability', 'disabled' => 0],
            ['name' => 'Payroll Payable', 'parent' => 'Accounts Payable', 'type' => '', 'is_group' => 0, 'root_type' => 'Liability', 'disabled' => 0],
            ['name' => 'Duties and Taxes', 'parent' => 'Current Liabilities', 'type' => 'Tax', 'is_group' => 1, 'root_type' => 'Liability', 'disabled' => 0],
            ['name' => 'General Sales Tax (GST)', 'parent' => 'Duties and Taxes', 'type' => 'Tax', 'is_group' => 0, 'root_type' => 'Liability', 'disabled' => 0],
            ['name' => 'Excise Duty', 'parent' => 'Duties and Taxes', 'type' => 'Tax', 'is_group' => 0, 'root_type' => 'Liability', 'disabled' => 0],
            ['name' => 'Loans (Liabilities)', 'parent' => 'Current Liabilities', 'type' => '', 'is_group' => 1, 'root_type' => 'Liability', 'disabled' => 0],
            ['name' => 'Bank Overdraft Account', 'parent' => 'Loans (Liabilities)', 'type' => '', 'is_group' => 0, 'root_type' => 'Liability', 'disabled' => 0],
            ['name' => 'Secured Loans', 'parent' => 'Loans (Liabilities)', 'type' => '', 'is_group' => 0, 'root_type' => 'Liability', 'disabled' => 0],
            ['name' => 'Unsecured Loans', 'parent' => 'Loans (Liabilities)', 'type' => '', 'is_group' => 0, 'root_type' => 'Liability', 'disabled' => 0],
            ['name' => 'Stock Liabilities', 'parent' => 'Current Liabilities', 'type' => '', 'is_group' => 1, 'root_type' => 'Liability', 'disabled' => 0],
            ['name' => 'Asset Received But Not Billed', 'parent' => 'Stock Liabilities', 'type' => 'Asset Received But Not Billed', 'is_group' => 0, 'root_type' => 'Liability', 'disabled' => 0],
            ['name' => 'Stock Received But Not Billed', 'parent' => 'Stock Liabilities', 'type' => 'Stock Received But Not Billed', 'is_group' => 0, 'root_type' => 'Liability', 'disabled' => 0],
        ];

        $accountsToInsert = [];
        $idCounter = 1;

        // --- Helper maps for code generation ---
        $nameToIdMap = [];
        $idToDataMap = []; // Stores ['code' => '...', 'level' => 0, 'id' => 1]
        $parentChildCounter = []; // Stores [parentId => nextChildIndex]

        foreach ($csvData as $row) {
            $newId = $idCounter++;
            $accountName = $row['name'];
            $parentName = $row['parent'];

            $nameToIdMap[$accountName] = $newId;

            $accountTypeId = $this->getAccountTypeId($row['root_type']);
            $normalBalance = $this->getNormalBalance($accountTypeId);

            $newCode = null;
            $parentId = null;
            $level = 0;

            // --- Determine Parent, Level, and Code ---
            $isRoot = ($accountName === $parentName);

            if ($isRoot) {
                $parentId = null;
                $level = 0;
                $rootPrefix = $this->getRootPrefix($accountTypeId);
                $newCode = $rootPrefix.'000'; // e.g., 1000, 2000
            } else {
                // This is a child. We assume the parent was already processed.
                $parentId = $nameToIdMap[$parentName];
                $parentData = $idToDataMap[$parentId];
                $parentCode = $parentData['code'];
                $parentLevel = $parentData['level'];

                $level = $parentLevel + 1;

                // Get or initialize the child counter for this parent
                $childIndex = $parentChildCounter[$parentId] ?? 1;
                // Increment the counter for the *next* sibling
                $parentChildCounter[$parentId] = $childIndex + 1;

                // --- Generate Code based on Level ---
                if ($level == 1) {
                    // Child of a Root (e.g., Current Assets) -> 1100
                    $base = substr($parentCode, 0, 1); // "1"
                    $newCode = $base.$childIndex.'00'; // "1" + "1" + "00" = "1100"
                } elseif ($level == 2) {
                    // Child of Level 1 (e.g., children under 5200) — always 4 digits
                    // Pattern keeps first 9 as XY10, XY20, ... XY90, then XY11, XY21, ...
                    // This ensures codes never exceed 4 digits even when childIndex >= 10
                    $base2 = substr($parentCode, 0, 2);
                    $a = (($childIndex - 1) % 9) + 1; // 1..9 cycles
                    $b = intdiv(($childIndex - 1), 9); // 0,1,2,... increments every 9 children
                    $newCode = $base2.$a.$b; // "52" . 1 . 0 => 5210, then 5220... then 5211, 5221, etc.
                } elseif ($level == 3) {
                    // Child of Level 2 (e.g., Debtors) -> 1111
                    $base = substr($parentCode, 0, 3); // "111"
                    $newCode = $base.$childIndex; // "111" + "1" = "1111"
                } else {
                    // Deeper levels -> 11111, 111111
                    $base = $parentCode;
                    $newCode = $base.$childIndex; // "1111" + "1" = "11111"
                }
            }

            // Store this account's data for its future children
            $idToDataMap[$newId] = ['code' => $newCode, 'level' => $level, 'id' => $newId];

            // --- Add to final insert array ---
            $accountsToInsert[] = [
                'id' => $newId,
                'parent_id' => $parentId,
                'account_type_id' => $accountTypeId,
                'currency_id' => $baseCurrency->id,
                'account_code' => $newCode,
                'account_name' => $accountName,
                'normal_balance' => $normalBalance,
                'description' => $row['type'] ?: null,
                'is_group' => (bool) $row['is_group'],
                'is_active' => ! (bool) $row['disabled'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Insert all accounts
        DB::table('chart_of_accounts')->insert($accountsToInsert);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('chart_of_accounts', 'id'), MAX(id)) FROM chart_of_accounts");
        }
    }

    /**
     * Maps the CSV root_type string to the account_type_id.
     */
    private function getAccountTypeId(string $rootType): int
    {
        return match ($rootType) {
            'Asset' => 1,
            'Liability' => 2,
            'Equity' => 3,
            'Income' => 4, // Income type
            'Expense' => 5,
            default => 1, // Default to Asset
        };
    }

    /**
     * Gets the 1-digit prefix for root account codes.
     */
    private function getRootPrefix(int $accountTypeId): string
    {
        return match ($accountTypeId) {
            1 => '1', // Asset
            2 => '2', // Liability
            3 => '3', // Equity
            4 => '4', // Income
            5 => '5', // Expense
            default => '9', // Fallback
        };
    }

    /**
     * Gets the normal balance based on the account_type_id.
     */
    private function getNormalBalance(int $accountTypeId): string
    {
        return match ($accountTypeId) {
            1, 5 => 'debit', // Asset, Expense
            2, 3, 4 => 'credit', // Liability, Equity, Income
            default => 'debit',
        };
    }
}
