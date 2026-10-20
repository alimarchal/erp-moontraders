<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bank_reconciliations', function (Blueprint $table) {
            $table->date('period_from')->nullable()->after('bank_account_id');
            $table->date('period_to')->nullable()->after('period_from');
            $table->decimal('opening_balance', 15, 2)->default(0)->after('statement_balance');
            $table->decimal('reconciled_total', 15, 2)->default(0)->after('difference');

            $table->index(['bank_account_id', 'period_to']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_reconciliations', function (Blueprint $table) {
            $table->dropIndex(['bank_account_id', 'period_to']);
            $table->dropColumn(['period_from', 'period_to', 'opening_balance', 'reconciled_total']);
        });
    }
};
