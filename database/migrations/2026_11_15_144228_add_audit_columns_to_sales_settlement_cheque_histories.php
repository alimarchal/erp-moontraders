<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every cheque status change records how it was cleared, the batch, and the journals it posted or reversed.
     * The ids are plain columns, not foreign keys: a history row is never changed, even when its batch is deleted.
     */
    public function up(): void
    {
        Schema::table('sales_settlement_cheque_histories', function (Blueprint $table) {
            $table->string('cleared_via', 10)->nullable()->after('cleared_amount');
            $table->unsignedBigInteger('bank_account_id')->nullable()->after('cleared_via');
            $table->unsignedBigInteger('cheque_clearing_id')->nullable()->index()->after('bank_account_id');
            $table->unsignedBigInteger('journal_entry_id')->nullable()->index()->after('cheque_clearing_id');
            $table->unsignedBigInteger('reversed_journal_entry_id')->nullable()->after('journal_entry_id');
            $table->decimal('bank_charge', 15, 2)->nullable()->after('reversed_journal_entry_id');
            $table->json('denominations')->nullable()->after('bank_charge');
        });
    }

    public function down(): void
    {
        Schema::table('sales_settlement_cheque_histories', function (Blueprint $table) {
            $table->dropIndex(['cheque_clearing_id']);
            $table->dropIndex(['journal_entry_id']);
            $table->dropColumn(['cleared_via', 'bank_account_id', 'cheque_clearing_id', 'journal_entry_id', 'reversed_journal_entry_id', 'bank_charge', 'denominations']);
        });
    }
};
