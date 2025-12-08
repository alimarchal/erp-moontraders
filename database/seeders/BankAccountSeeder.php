<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class BankAccountSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // Find the "Bank Accounts" parent group in Chart of Accounts
        $bankAccountsGroup = ChartOfAccount::where('account_name', 'Bank Accounts')->first();

        if (! $bankAccountsGroup) {
            throw new \Exception('Bank Accounts group not found in Chart of Accounts. Please run ChartOfAccountSeeder first.');
        }

        // Get base currency
        $baseCurrency = \DB::table('currencies')->where('is_base_currency', true)->first();

        $bankAccounts = [
            [
                'account_name' => 'HBL Main Account',
                'account_number' => '24997000284199',
                'bank_name' => 'Habib Bank Limited',
                'is_active' => true,
            ],
            [
                'account_name' => 'HBL Secondary Account',
                'account_number' => '24997000289703',
                'bank_name' => 'Habib Bank Limited',
                'is_active' => true,
            ],
            [
                'account_name' => 'Meezan Account',
                'account_number' => '01298400',
                'bank_name' => 'Meezan Bank',
                'is_active' => true,
            ],
            [
                'account_name' => 'Al Barka Account',
                'account_number' => '0105697241013',
                'bank_name' => 'Al Baraka Bank',
                'is_active' => true,
            ],
            [
                'account_name' => 'HBL Account',
                'account_number' => '15547901098303',
                'bank_name' => 'Habib Bank Limited',
                'is_active' => true,
            ],
            [
                'account_name' => 'HBL SHAHPUR Account',
                'account_number' => '15547902209755',
                'bank_name' => 'Habib Bank Limited',
                'branch' => 'SHAHPUR',
                'is_active' => true,
            ],
            [
                'account_name' => 'HBL Account',
                'account_number' => '15547901927555',
                'bank_name' => 'Habib Bank Limited',
                'is_active' => true,
            ],
            [
                'account_name' => 'Meezan Account',
                'account_number' => '0110057820',
                'bank_name' => 'Meezan Bank',
                'is_active' => true,
            ],
            [
                'account_name' => 'Meezan Account',
                'account_number' => '34010110778826',
                'bank_name' => 'Meezan Bank',
                'is_active' => true,
            ],
            [
                'account_name' => 'Meezan Account',
                'account_number' => '0112394389',
                'bank_name' => 'Meezan Bank',
                'is_active' => true,
            ],
        ];

        $counter = 1;
        foreach ($bankAccounts as $accountData) {
            // Generate proper account code: 1120 -> 11201, 11202, ..., 11209, then 11210
            // Pattern: accounts 1-9 use format 1120X, account 10 uses 11210
            $baseCode = substr($bankAccountsGroup->account_code, 0, 3);  // "112"

            if ($counter <= 9) {
                $accountCode = $baseCode.'0'.$counter;  // "11201" to "11209"
            } else {
                $accountCode = $baseCode.$counter;  // "11210"
            }

            // Create a Chart of Account entry for this bank account
            $chartOfAccount = ChartOfAccount::create([
                'parent_id' => $bankAccountsGroup->id,
                'account_type_id' => $bankAccountsGroup->account_type_id,
                'currency_id' => $baseCurrency->id,
                'account_code' => $accountCode,
                'account_name' => $accountData['account_name'].' - '.$accountData['account_number'],
                'normal_balance' => 'debit',
                'description' => 'Bank',
                'is_group' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Create the bank account and link it to the Chart of Account
            BankAccount::create(array_merge($accountData, [
                'chart_of_account_id' => $chartOfAccount->id,
            ]));

            $counter++;
        }
    }
}
