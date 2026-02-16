<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE stock_adjustments 
                MODIFY adjustment_type ENUM('damage', 'theft', 'count_variance', 'expiry', 'recall', 'other') DEFAULT 'count_variance'");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE stock_adjustments 
                ALTER COLUMN adjustment_type TYPE VARCHAR(255)");
            
            DB::statement("DROP TYPE IF EXISTS stock_adjustment_type_old CASCADE");
            DB::statement("CREATE TYPE stock_adjustment_type_old AS ENUM('damage', 'theft', 'count_variance', 'expiry', 'other')");
            DB::statement("CREATE TYPE stock_adjustment_type_new AS ENUM('damage', 'theft', 'count_variance', 'expiry', 'recall', 'other')");
            
            DB::statement("ALTER TABLE stock_adjustments 
                ALTER COLUMN adjustment_type TYPE stock_adjustment_type_new 
                USING adjustment_type::text::stock_adjustment_type_new");
            
            DB::statement("DROP TYPE stock_adjustment_type_old");
        }

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

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE stock_adjustments 
                MODIFY adjustment_type ENUM('damage', 'theft', 'count_variance', 'expiry', 'other') DEFAULT 'count_variance'");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE stock_adjustments 
                ALTER COLUMN adjustment_type TYPE VARCHAR(255)");
            
            DB::statement("DROP TYPE IF EXISTS stock_adjustment_type_new CASCADE");
            DB::statement("CREATE TYPE stock_adjustment_type_old AS ENUM('damage', 'theft', 'count_variance', 'expiry', 'other')");
            
            DB::statement("ALTER TABLE stock_adjustments 
                ALTER COLUMN adjustment_type TYPE stock_adjustment_type_old 
                USING adjustment_type::text::stock_adjustment_type_old");
            
            DB::statement("DROP TYPE IF EXISTS stock_adjustment_type_new");
        }
    }
};
