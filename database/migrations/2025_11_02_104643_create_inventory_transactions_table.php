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
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();

            // Transaction details
            $table->enum('transaction_type', ['receipt', 'sale', 'adjustment', 'transfer_in', 'transfer_out'])
                ->comment('Type of inventory movement');
            $table->string('transaction_number')->comment('Reference to source document');
            $table->date('transaction_date');

            // Quantity movements
            $table->decimal('quantity_in', 15, 2)->default(0)->comment('Quantity received/added');
            $table->decimal('quantity_out', 15, 2)->default(0)->comment('Quantity sold/removed');
            $table->decimal('balance', 15, 2)->comment('Running balance after this transaction');

            // Cost tracking for COGS
            $table->decimal('unit_cost', 15, 2)->comment('Cost per unit for this transaction');
            $table->decimal('total_cost', 15, 2)->comment('Total cost value of this transaction');
            $table->decimal('balance_value', 15, 2)->comment('Total inventory value after transaction');

            // Source document references
            $table->morphs('transactionable'); // Polymorphic relation to StockReceipt, Sale, etc.

            // Double-entry link
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete()
                ->comment('Links to accounting journal entry');

            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes for reporting
            $table->index(['product_id', 'warehouse_id', 'transaction_date']);
            $table->index(['transaction_type', 'transaction_date']);
            // Note: morphs() already creates an index on transactionable_type and transactionable_id
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};
