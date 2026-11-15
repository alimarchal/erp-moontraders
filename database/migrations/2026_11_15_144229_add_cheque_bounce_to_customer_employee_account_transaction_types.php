<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TYPES = ['credit_sale', 'recovery', 'recovery_cash', 'recovery_cheque', 'bank_transfer', 'opening_balance', 'adjustment', 'return_credit'];

    /**
     * A bounced cheque puts the amount back on the customer's account as a "cheque_bounce" debit.
     */
    public function up(): void
    {
        $this->setTypes([...self::TYPES, 'cheque_bounce']);
    }

    public function down(): void
    {
        $this->setTypes(self::TYPES);
    }

    /**
     * @param  array<int, string>  $types
     */
    private function setTypes(array $types): void
    {
        $list = "'".implode("','", $types)."'";

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE customer_employee_account_transactions DROP CONSTRAINT IF EXISTS customer_employee_account_transactions_transaction_type_check');
            DB::statement("ALTER TABLE customer_employee_account_transactions ADD CONSTRAINT customer_employee_account_transactions_transaction_type_check CHECK (transaction_type IN ({$list}))");
        } else {
            DB::statement("ALTER TABLE customer_employee_account_transactions MODIFY COLUMN transaction_type ENUM({$list}) NOT NULL COMMENT 'Type of transaction'");
        }
    }
};
