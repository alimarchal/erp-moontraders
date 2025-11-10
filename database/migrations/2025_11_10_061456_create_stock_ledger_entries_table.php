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
        Schema::create('stock_ledger_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('stock_batch_id')->nullable()->constrained('stock_batches')->nullOnDelete();

            // Entry Details
            $table->date('entry_date')->index();
            $table->foreignId('stock_movement_id')->constrained('stock_movements')->cascadeOnDelete();

            // Quantities (Running Balance)
            $table->decimal('quantity_in', 15, 2)->default(0);
            $table->decimal('quantity_out', 15, 2)->default(0);
            $table->decimal('quantity_balance', 15, 2)->comment('Running total after this entry');

            // Valuation
            $table->decimal('valuation_rate', 15, 2)->comment('Cost per unit at this point');
            $table->decimal('stock_value', 15, 2)->comment('quantity_balance × valuation_rate');

            // Reference (Polymorphic)
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->timestamp('created_at');

            // Indexes
            $table->index(['product_id', 'warehouse_id', 'entry_date']);
            $table->index('stock_batch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_ledger_entries');
    }
};
