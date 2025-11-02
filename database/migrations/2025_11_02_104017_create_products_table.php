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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_code')->unique();
            $table->string('product_name');
            $table->text('description')->nullable();
            $table->string('unit_of_measure')->default('PCS'); // PCS, KG, LTR, BOX, etc.
            $table->decimal('reorder_level', 10, 2)->default(0);
            $table->decimal('unit_price', 15, 2)->default(0)->comment('Selling price');
            $table->decimal('cost_price', 15, 2)->default(0)->comment('Average cost for COGS calculation');

            // Inventory tracking
            $table->enum('valuation_method', ['FIFO', 'LIFO', 'Average', 'Standard'])->default('Average')
                ->comment('Inventory costing method for COGS');

            // Link to Chart of Accounts for automatic posting
            $table->foreignId('inventory_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete()
                ->comment('Asset account: Inventory - [Product Category]');
            $table->foreignId('cogs_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete()
                ->comment('Expense account: Cost of Goods Sold');
            $table->foreignId('sales_revenue_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete()
                ->comment('Revenue account: Sales Revenue - [Product Category]');

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
