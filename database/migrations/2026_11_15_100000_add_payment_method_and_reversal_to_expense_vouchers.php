<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A voucher records how it was paid (cash, bank transfer, cheque or credit) and can be reversed later.
     * Existing vouchers get their method from the old payment type.
     */
    public function up(): void
    {
        Schema::table('expense_vouchers', function (Blueprint $table) {
            $table->string('payment_method', 20)->default('cash')->after('payment_type')->index();
            $table->foreignId('bank_account_id')->nullable()->after('payment_method')->constrained('bank_accounts')->nullOnDelete();
            $table->string('cheque_number', 30)->nullable()->after('bank_account_id');
            $table->date('cheque_date')->nullable()->after('cheque_number');
            $table->string('transaction_reference', 100)->nullable()->after('cheque_date');
            $table->foreignId('paid_from_account_id')->nullable()->after('transaction_reference')->constrained('chart_of_accounts')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->foreignId('reversal_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
        });

        DB::table('expense_vouchers')->where('payment_type', 'credit')->update(['payment_method' => 'credit']);
    }

    public function down(): void
    {
        Schema::table('expense_vouchers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reversal_journal_entry_id');
            $table->dropConstrainedForeignId('reversed_by');
            $table->dropColumn(['reversed_at', 'reversal_reason']);
            $table->dropConstrainedForeignId('paid_from_account_id');
            $table->dropConstrainedForeignId('bank_account_id');
            $table->dropIndex(['payment_method']);
            $table->dropColumn(['payment_method', 'cheque_number', 'cheque_date', 'transaction_reference']);
        });
    }
};
