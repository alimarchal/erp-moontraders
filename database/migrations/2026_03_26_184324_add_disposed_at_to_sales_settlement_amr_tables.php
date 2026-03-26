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
        Schema::table('sales_settlement_amr_powders', function (Blueprint $table) {
            $table->timestamp('disposed_at')->nullable()->after('is_disposed');
        });

        Schema::table('sales_settlement_amr_liquids', function (Blueprint $table) {
            $table->timestamp('disposed_at')->nullable()->after('is_disposed');
        });

        DB::table('sales_settlement_amr_powders')
            ->where('is_disposed', true)
            ->whereNull('disposed_at')
            ->update(['disposed_at' => DB::raw('updated_at')]);

        DB::table('sales_settlement_amr_liquids')
            ->where('is_disposed', true)
            ->whereNull('disposed_at')
            ->update(['disposed_at' => DB::raw('updated_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_settlement_amr_powders', function (Blueprint $table) {
            $table->dropColumn('disposed_at');
        });

        Schema::table('sales_settlement_amr_liquids', function (Blueprint $table) {
            $table->dropColumn('disposed_at');
        });
    }
};
