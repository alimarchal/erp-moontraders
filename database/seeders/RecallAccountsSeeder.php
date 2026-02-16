<?php

namespace Database\Seeders;

use App\Models\AccountType;
use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

class RecallAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $expenseType = AccountType::where('code', 'EXP')->first();

        if (! $expenseType) {
            $this->command->warn('Expense account type not found. Creating accounts without account type.');
        }

        // Find the parent account (Indirect Expenses)
        $parentAccount = ChartOfAccount::where('account_name', 'Indirect Expenses')->first();
        
        if (! $parentAccount) {
            $this->command->error('Parent account "Indirect Expenses" not found. Please run ChartOfAccountSeeder first.');
            return;
        }

        // Get the highest account code under this parent to append new accounts
        $lastChildCode = ChartOfAccount::where('parent_id', $parentAccount->id)
            ->orderBy('account_code', 'desc')
            ->value('account_code');

        // Calculate next codes based on existing pattern
        // The parent is at level 1, children are at level 2 with pattern like 5210, 5220, etc.
        $baseCode = (int) substr($parentAccount->account_code, 0, 2); // Get "52" from "5200"
        
        // Find the next available slot
        $existingCodes = ChartOfAccount::where('parent_id', $parentAccount->id)
            ->pluck('account_code')
            ->toArray();

        $accounts = [
            [
                'account_name' => 'Stock Loss on Recalls',
                'description' => 'Losses from product recalls initiated by suppliers',
            ],
            [
                'account_name' => 'Stock Loss - Damage',
                'description' => 'Inventory losses due to physical damage',
            ],
            [
                'account_name' => 'Stock Loss - Theft',
                'description' => 'Inventory losses due to theft or shrinkage',
            ],
            [
                'account_name' => 'Stock Loss - Expiry',
                'description' => 'Inventory losses due to expired products',
            ],
            [
                'account_name' => 'Stock Loss - Other',
                'description' => 'Other inventory losses and adjustments',
            ],
        ];

        // Generate codes that won't conflict
        foreach ($accounts as $index => $accountData) {
            // Check if account already exists by name
            $existing = ChartOfAccount::where('account_name', $accountData['account_name'])->first();

            if ($existing) {
                $this->command->info("Account '{$accountData['account_name']}' already exists with code {$existing->account_code}.");
                continue;
            }

            // Find next available code
            $childIndex = count($existingCodes) + 1;
            $a = (($childIndex - 1) % 9) + 1;
            $b = intdiv(($childIndex - 1), 9);
            $newCode = $baseCode . $a . $b;

            // Make sure code doesn't already exist
            while (in_array($newCode, $existingCodes)) {
                $childIndex++;
                $a = (($childIndex - 1) % 9) + 1;
                $b = intdiv(($childIndex - 1), 9);
                $newCode = $baseCode . $a . $b;
            }

            $account = ChartOfAccount::create([
                'account_code' => $newCode,
                'account_name' => $accountData['account_name'],
                'description' => $accountData['description'],
                'account_type_id' => $expenseType?->id,
                'parent_id' => $parentAccount->id,
                'level' => 2,
                'is_active' => true,
                'is_system_account' => false,
                'is_group' => false,
                'normal_balance' => 'debit',
            ]);

            $existingCodes[] = $newCode;
            $this->command->info("Created account {$newCode} - {$accountData['account_name']}");
        }
    }
}
