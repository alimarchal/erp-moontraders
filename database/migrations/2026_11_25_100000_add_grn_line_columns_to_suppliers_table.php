<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The order (and visibility) of a GRN line's price, deduction and tax inputs for this supplier.
     * Null means the default order (Supplier::GRN_LINE_COLUMNS). Layout only: it never changes any calculation.
     */
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->json('grn_line_columns')->nullable()->after('qty_entry_mode');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('grn_line_columns');
        });
    }
};
