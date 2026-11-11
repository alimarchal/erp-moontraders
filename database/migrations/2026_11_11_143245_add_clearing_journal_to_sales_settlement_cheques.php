<?php

use App\Services\ChequeClearingPostingService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * A cleared settlement cheque now leaves Cheques in Hand (1122): the cash brought back goes to Cash (1121), or a cheque
 * deposited at the bank goes to that bank. Cheques cleared before this had no journal; they were all cleared with cash
 * brought back, so each one is booked once on its own cleared date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_settlement_cheques', function (Blueprint $table) {
            $table->string('cleared_via', 10)->nullable()->after('cleared_amount');
            $table->foreignId('cleared_bank_account_id')->nullable()->after('cleared_via')->constrained('bank_accounts')->nullOnDelete();
            $table->foreignId('clearing_journal_entry_id')->nullable()->after('cleared_bank_account_id')->constrained('journal_entries')->nullOnDelete();
        });

        $result = app(ChequeClearingPostingService::class)->postMissing();

        Log::info('Cleared cheques booked out of Cheques in Hand', $result);
    }

    public function down(): void
    {
        Schema::table('sales_settlement_cheques', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clearing_journal_entry_id');
            $table->dropConstrainedForeignId('cleared_bank_account_id');
            $table->dropColumn('cleared_via');
        });
    }
};
