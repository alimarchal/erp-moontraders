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
        Schema::table('goods_receipt_note_items', function (Blueprint $table) {
            $table->decimal('extended_value', 15, 2)->after('unit_price_per_case')->nullable()->default(0)->comment('qty_cases × unit_price_per_case');
            $table->decimal('discounted_value_before_tax', 15, 2)->after('fmr_allowance')->nullable()->default(0)->comment('extended_value - discount - fmr_allowance');
            $table->decimal('total_value_with_taxes', 15, 2)->after('advance_income_tax')->nullable()->default(0)->comment('Final total including all taxes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goods_receipt_note_items', function (Blueprint $table) {
            $table->dropColumn(['extended_value', 'discounted_value_before_tax', 'total_value_with_taxes']);
        });
    }
};
