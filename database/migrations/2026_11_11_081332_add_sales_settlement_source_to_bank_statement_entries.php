<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A bank register line raised from a sales settlement remembers the settlement and the line it came from.
     */
    public function up(): void
    {
        Schema::table('bank_statement_entries', function (Blueprint $table) {
            $table->foreignId('sales_settlement_id')->nullable()->after('import_id')->constrained('sales_settlements')->nullOnDelete();
            $table->string('settlement_section', 20)->nullable()->after('sales_settlement_id')->comment('recoveries, bank_transfers or bank_slips');
            $table->unsignedBigInteger('settlement_row_id')->nullable()->after('settlement_section')->comment('Current id of the settlement line; it changes when the settlement is re-saved.');
        });
    }

    public function down(): void
    {
        Schema::table('bank_statement_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_settlement_id');
            $table->dropColumn(['settlement_section', 'settlement_row_id']);
        });
    }
};
