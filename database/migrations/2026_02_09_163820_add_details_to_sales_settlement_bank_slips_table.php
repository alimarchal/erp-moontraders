<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales_settlement_bank_slips', function (Blueprint $table) {
            $table->string('reference_number')->nullable()->after('amount');
            $table->date('deposit_date')->nullable()->after('reference_number');
            $table->text('notes')->nullable()->after('deposit_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_settlement_bank_slips', function (Blueprint $table) {
            $table->dropColumn(['reference_number', 'deposit_date', 'notes']);
        });
    }
};
