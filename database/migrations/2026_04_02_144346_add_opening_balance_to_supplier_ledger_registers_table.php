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
        Schema::table('supplier_ledger_registers', function (Blueprint $table) {
            $table->decimal('opening_balance', 15, 2)->default(0)->after('online_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_ledger_registers', function (Blueprint $table) {
            $table->dropColumn('opening_balance');
        });
    }
};
