<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use Illuminate\Database\Seeder;

class BankAccountSeeder extends Seeder
{
    public function run(): void
    {
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

        foreach ($bankAccounts as $account) {
            BankAccount::create($account);
        }
    }
}
