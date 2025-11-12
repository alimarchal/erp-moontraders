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
        Schema::table('stock_batches', function (Blueprint $table) {
            $table->string('lot_number', 100)->nullable()->after('supplier_batch_number')->comment('Internal lot code from GRN');
            $table->string('storage_location', 100)->nullable()->after('notes')->comment('Warehouse rack/bin location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_batches', function (Blueprint $table) {
            $table->dropColumn(['lot_number', 'storage_location']);
        });
    }
};
