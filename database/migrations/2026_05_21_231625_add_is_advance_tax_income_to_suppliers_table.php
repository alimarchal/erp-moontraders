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
            $table->boolean('is_advance_tax_income')->default(false)->after('is_fmr_allowed');
        });

        // Kausar is the only current supplier whose settlement advance tax is
        // collected as income instead of handled through the normal advance tax path.
        DB::table('suppliers')
            ->where('id', 7)
            ->where('supplier_name', 'Kausar Oil & Ghee')
            ->update(['is_advance_tax_income' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('is_advance_tax_income');
        });
    }
};
