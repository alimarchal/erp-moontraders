<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An online payment posted from the supplier Ledger Register goes out of the bank account chosen when it
     * is posted, and gets its own line in the Bank Register so it can be reconciled with the bank statement.
     */
    public function up(): void
    {
        Schema::table('supplier_ledger_registers', function (Blueprint $table) {
            $table->foreignId('bank_account_id')->nullable()->after('journal_entry_id')
                ->constrained('bank_accounts')->nullOnDelete();
        });

        Schema::table('bank_statement_entries', function (Blueprint $table) {
            $table->foreignId('supplier_ledger_register_id')->nullable()->after('settlement_row_id')
                ->constrained('supplier_ledger_registers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bank_statement_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_ledger_register_id');
        });

        Schema::table('supplier_ledger_registers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
        });
    }
};
