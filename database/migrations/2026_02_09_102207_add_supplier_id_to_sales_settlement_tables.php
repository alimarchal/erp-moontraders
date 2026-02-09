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
        Schema::table('sales_settlements', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->constrained();
        });

        // specific logic to backfill supplier_id from employees would go here or in a seeder
        // We can do a raw update for speed if needed, or iterate.
        // DB::statement("UPDATE sales_settlements JOIN employees ON sales_settlements.employee_id = employees.id SET sales_settlements.supplier_id = employees.supplier_id");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_settlements', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn('supplier_id');
        });
    }
};
