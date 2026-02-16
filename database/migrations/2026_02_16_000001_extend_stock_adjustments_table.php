<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE stock_adjustments 
            MODIFY adjustment_type ENUM('damage', 'theft', 'count_variance', 'expiry', 'recall', 'other') DEFAULT 'count_variance'");

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->foreignId('product_recall_id')->nullable()->after('warehouse_id')
                ->constrained('product_recalls')->nullOnDelete();
            $table->timestamp('posted_at')->nullable()->after('posted_by');
            $table->foreignId('updated_by')->nullable()->after('created_by')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->dropForeign(['product_recall_id']);
            $table->dropColumn('product_recall_id');
            $table->dropColumn('posted_at');
            $table->dropForeign(['updated_by']);
            $table->dropColumn('updated_by');
        });

        DB::statement("ALTER TABLE stock_adjustments 
            MODIFY adjustment_type ENUM('damage', 'theft', 'count_variance', 'expiry', 'other') DEFAULT 'count_variance'");
    }
};
