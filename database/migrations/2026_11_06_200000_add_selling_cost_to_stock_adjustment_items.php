<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_adjustment_items', function (Blueprint $table) {
            // Set only when the line changes the batch's selling cost; applied when the adjustment is posted.
            $table->decimal('selling_price', 15, 2)->nullable()->after('unit_cost');
            $table->string('selling_scope', 10)->default('batch')->after('selling_price')->comment('batch = this batch, all = every batch of the product that holds stock');
        });
    }

    public function down(): void
    {
        Schema::table('stock_adjustment_items', function (Blueprint $table) {
            $table->dropColumn(['selling_price', 'selling_scope']);
        });
    }
};
