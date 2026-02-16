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

        $accounts = [
            [
                'account_code' => '5280',
                'account_name' => 'Stock Loss on Recalls',
                'description' => 'Losses from product recalls initiated by suppliers',
                'account_type_id' => $expenseType?->id,
                'parent_id' => $this->findParentAccount('5200'),
                'level' => 3,
                'is_active' => true,
                'is_system_account' => false,
            ],
            [
                'account_code' => '5281',
                'account_name' => 'Stock Loss - Damage',
                'description' => 'Inventory losses due to physical damage',
                'account_type_id' => $expenseType?->id,
                'parent_id' => $this->findParentAccount('5200'),
                'level' => 3,
                'is_active' => true,
                'is_system_account' => false,
            ],
            [
                'account_code' => '5282',
                'account_name' => 'Stock Loss - Theft',
                'description' => 'Inventory losses due to theft or shrinkage',
                'account_type_id' => $expenseType?->id,
                'parent_id' => $this->findParentAccount('5200'),
                'level' => 3,
                'is_active' => true,
                'is_system_account' => false,
            ],
            [
                'account_code' => '5283',
                'account_name' => 'Stock Loss - Expiry',
                'description' => 'Inventory losses due to expired products',
                'account_type_id' => $expenseType?->id,
                'parent_id' => $this->findParentAccount('5200'),
                'level' => 3,
                'is_active' => true,
                'is_system_account' => false,
            ],
            [
                'account_code' => '5284',
                'account_name' => 'Stock Loss - Other',
                'description' => 'Other inventory losses and adjustments',
                'account_type_id' => $expenseType?->id,
                'parent_id' => $this->findParentAccount('5200'),
                'level' => 3,
                'is_active' => true,
                'is_system_account' => false,
            ],
        ];

        foreach ($accounts as $accountData) {
            $existing = ChartOfAccount::where('account_code', $accountData['account_code'])->first();

            if ($existing) {
                $this->command->info("Account {$accountData['account_code']} - {$accountData['account_name']} already exists.");
            } else {
                ChartOfAccount::create($accountData);
                $this->command->info("Created account {$accountData['account_code']} - {$accountData['account_name']}");
            }
        }
    }

    private function findParentAccount(string $code): ?int
    {
        $parent = ChartOfAccount::where('account_code', $code)->first();

        return $parent?->id;
    }
}
