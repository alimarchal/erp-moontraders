<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stock_valuation_layers', function (Blueprint $table) {
            $table->decimal('value_remaining', 15, 2)->default(0)->after('total_value');
        });

        DB::statement('UPDATE stock_valuation_layers SET value_remaining = quantity_remaining * unit_cost');
    }

    public function down(): void
    {
        Schema::table('stock_valuation_layers', function (Blueprint $table) {
            $table->dropColumn('value_remaining');
        });
    }
};
