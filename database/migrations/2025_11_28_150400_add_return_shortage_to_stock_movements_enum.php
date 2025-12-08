<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'return' and 'shortage' to movement_type enum
        DB::statement("ALTER TABLE stock_movements MODIFY COLUMN movement_type ENUM('grn','goods_issue','goods_return','transfer','adjustment','damage','theft','sale','return','shortage') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original enum (note: this will fail if there's data with 'return' or 'shortage')
        DB::statement("ALTER TABLE stock_movements MODIFY COLUMN movement_type ENUM('grn','goods_issue','goods_return','transfer','adjustment','damage','theft','sale') NOT NULL");
    }
};
