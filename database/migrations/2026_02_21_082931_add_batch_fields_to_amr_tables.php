<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_settlement_amr_powders', function (Blueprint $table) {
            $table->foreignId('stock_batch_id')->nullable()->after('product_id')->constrained('stock_batches')->nullOnDelete();
            $table->string('batch_code', 100)->nullable()->after('stock_batch_id');
        });

        Schema::table('sales_settlement_amr_liquids', function (Blueprint $table) {
            $table->foreignId('stock_batch_id')->nullable()->after('product_id')->constrained('stock_batches')->nullOnDelete();
            $table->string('batch_code', 100)->nullable()->after('stock_batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales_settlement_amr_powders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_batch_id');
            $table->dropColumn('batch_code');
        });

        Schema::table('sales_settlement_amr_liquids', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_batch_id');
            $table->dropColumn('batch_code');
        });
    }
};
