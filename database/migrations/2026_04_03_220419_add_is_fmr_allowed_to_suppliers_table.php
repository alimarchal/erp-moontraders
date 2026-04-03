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
        Schema::table('suppliers', function (Blueprint $table) {
            $table->boolean('is_fmr_allowed')->default(false)->after('is_internal_supplier');
        });

        // Set Engro Corporation to is_fmr_allowed = true
        DB::table('suppliers')
            ->where('supplier_name', 'Engro Corporation')
            ->update(['is_fmr_allowed' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('is_fmr_allowed');
        });
    }
};
