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

        // Clear the table to avoid conflicts on re-seed
        DB::table('chart_of_accounts')->delete();

        // This is the raw data parsed from your 82-record CSV
        $csvData = [
            ['name' => 'Application of Funds (Assets)', 'parent' => 'Application of Funds (Assets)', 'type' => '', 'is_group' => 1, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Current Assets', 'parent' => 'Application of Funds (Assets)', 'type' => '', 'is_group' => 1, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Accounts Receivable', 'parent' => 'Current Assets', 'type' => '', 'is_group' => 1, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Debtors', 'parent' => 'Accounts Receivable', 'type' => 'Receivable', 'is_group' => 0, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Bank Accounts', 'parent' => 'Current Assets', 'type' => 'Bank', 'is_group' => 1, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Cash In Hand', 'parent' => 'Current Assets', 'type' => 'Cash', 'is_group' => 1, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Cash', 'parent' => 'Cash In Hand', 'type' => 'Cash', 'is_group' => 0, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Loans and Advances (Assets)', 'parent' => 'Current Assets', 'type' => '', 'is_group' => 1, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Employee Advances', 'parent' => 'Loans and Advances (Assets)', 'type' => 'Payable', 'is_group' => 0, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Securities and Deposits', 'parent' => 'Current Assets', 'type' => '', 'is_group' => 1, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Earnest Money', 'parent' => 'Securities and Deposits', 'type' => '', 'is_group' => 0, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Stock Assets', 'parent' => 'Current Assets', 'type' => 'Stock', 'is_group' => 1, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Stock In Hand', 'parent' => 'Stock Assets', 'type' => 'Stock', 'is_group' => 0, 'root_type' => 'Asset', 'disabled' => 0],
            ['name' => 'Tax Assets', 'parent' => 'Current Assets', 'type' => '', 'is_group' => 1, 'root_type' => 'Asset', 'disabled' => 0],
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
            ['name' => 'Income', 'parent' => 'Income', 'type' => '', 'is_group' => 1, 'root_type' => 'Income', 'disabled' => 0],
            ['name' => 'Direct Income', 'parent' => 'Income', 'type' => '', 'is_group' => 1, 'root_type' => 'Income', 'disabled' => 0],
            ['name' => 'Sales', 'parent' => 'Direct Income', 'type' => '', 'is_group' => 0, 'root_type' => 'Income', 'disabled' => 0],
            ['name' => 'Service', 'parent' => 'Direct Income', 'type' => '', 'is_group' => 0, 'root_type' => 'Income', 'disabled' => 0],
            ['name' => 'Indirect Income', 'parent' => 'Income', 'type' => '', 'is_group' => 1, 'root_type' => 'Income', 'disabled' => 0],
            ['name' => 'Source of Funds (Liabilities)', 'parent' => 'Source of Funds (Liabilities)', 'type' => '', 'is_group' => 1, 'root_type' => 'Liability', 'disabled' => 0],
            ['name' => 'Current Liabilities', 'parent' => 'Source of Funds (Liabilities)', 'type' => '', 'is_group' => 1, 'root_type' => 'Liability', 'disabled' => 0],
            ['name' => 'Accounts Payable', 'parent' => 'Current Liabilities', 'type' => '', 'is_group' => 1, 'root_type' => 'Liability', 'disabled' => 0],
            ['name' => 'Creditors', 'parent' => 'Accounts Payable', 'type' => 'Payable', 'is_group' => 0, 'root_type' => 'Liability', 'disabled' => 0],
            ['name' => 'Payroll Payable', 'parent' => 'Accounts Payable', 'type' => '', 'is_group' => 0, 'root_type' => 'Liability', 'disabled' => 0],
            ['name' => 'Duties and Taxes', 'parent' => 'Current Liabilities', 'type' => 'Tax', 'is_group' => 1, 'root_type' => 'Liability', 'disabled' => 0],
            ['name' => 'GST', 'parent' => 'Duties and Taxes', 'type' => 'Tax', 'is_group' => 0, 'root_type' => 'Liability', 'disabled' => 0],
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

        // Maps account_name -> new_id
        $nameToIdMap = [];

        // Maps new_id -> ['code' => '...', 'level' => 0]
        // This stores the generated code and level for each parent
        $idToDataMap = [];

        // Maps parent_id -> next_child_index
        // e.g., $parentChildCounter[15] = 3 (next child of parent 15 is index 3)
        $parentChildCounter = [];

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
                $newCode = $rootPrefix . "000"; // e.g., 1000, 2000
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
                    // Child of a Root (e.g., Current Assets, Fixed Assets)
                    // Use your specific list for Asset children
                    if ($parentCode == '1000') {
                        if ($accountName === 'Current Assets')
                            $newCode = '1100';
                        elseif ($accountName === 'Fixed Assets')
                            $newCode = '1200';
                        elseif ($accountName === 'Investments')
                            $newCode = '1300';
                        elseif ($accountName === 'Temporary Accounts')
                            $newCode = '1400';
                        // Fallback just in case
                        else
                            $newCode = '1' . $childIndex . '00';
                    } else {
                        // Generic logic for other roots (Liability, Equity, etc.)
                        $rootPrefix = substr($parentCode, 0, 1);
                        $newCode = $rootPrefix . $childIndex . "00"; // e.g., 2100, 4100
                    }
                } elseif ($level == 2) {
                    // Child of Level 1 (e.g., Accounts Receivable)
                    $base = substr($parentCode, 0, 2); // e.g., "11" from "1100"
                    $suffix = str_pad($childIndex, 2, '0', STR_PAD_LEFT); // "01", "02"
                    $newCode = $base . $suffix; // "1101"
                } else {
                    // Child of Level 2+ (e.g., Debtors)
                    $base = $parentCode; // e.g., "1101"
                    $suffix = str_pad($childIndex, 2, '0', STR_PAD_LEFT); // "01"
                    $newCode = $base . $suffix; // "110101"
                }
            }

            // Store this account's data for its future children
            $idToDataMap[$newId] = ['code' => $newCode, 'level' => $level];

            // --- Add to final insert array ---
            $accountsToInsert[] = [
                'id' => $newId,
                'parent_id' => $parentId,
                'account_type_id' => $accountTypeId,
                'account_code' => $newCode,
                'account_name' => $accountName,
                'normal_balance' => $normalBalance,
                'description' => $row['type'] ?: null,
                'is_group' => (bool) $row['is_group'],
                'is_active' => !(bool) $row['disabled'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Insert all accounts
        DB::table('chart_of_accounts')->insert($accountsToInsert);
    }

    /**
     * Maps the JSON root_type string to the account_type_id.
     */
    private function getAccountTypeId(string $rootType): int
    {
        return match ($rootType) {
            'Asset' => 1,
            'Liability' => 2,
            'Equity' => 3,
            'Income' => 4, // Maps CSV "Income" to your "Revenue" type
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
            4 => '4', // Revenue
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
            2, 3, 4 => 'credit', // Liability, Equity, Revenue
            default => 'debit',
        };
    }
}