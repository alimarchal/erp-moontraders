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


        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_no')->unique();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('supplier_id')->nullable()->constrained();
            $table->foreignId('warehouse_id')->constrained();

            $table->date('purchase_date');
            $table->decimal('purchase_cost', 15, 4)->comment('Cost per unit');
            $table->decimal('qty_received', 15, 3);
            $table->decimal('qty_remaining', 15, 3);

            $table->date('manufacturing_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('supplier_batch_no')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'warehouse_id', 'is_active']);
            $table->index('expiry_date');
        });


        Schema::create('stock_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('warehouse_id')->constrained();
            $table->date('posting_date');
            $table->string('voucher_type'); // 'Purchase Receipt', 'Delivery Note', etc.
            $table->unsignedBigInteger('voucher_id');

            $table->decimal('qty', 15, 3); // +ve for IN, -ve for OUT
            $table->decimal('incoming_rate', 15, 4)->nullable()->comment('Cost per unit for purchases');
            $table->decimal('outgoing_rate', 15, 4)->nullable()->comment('Cost per unit for sales (FIFO/LIFO calculated)');
            $table->decimal('valuation_rate', 15, 4)->comment('Running average rate');
            $table->decimal('stock_value', 15, 2)->comment('Qty * valuation_rate');

            $table->decimal('balance_qty', 15, 3)->comment('Running balance');
            $table->decimal('balance_value', 15, 2)->comment('Running value');

            $table->foreignId('journal_entry_id')->nullable()->constrained();
            $table->timestamps();

            $table->index(['product_id', 'warehouse_id', 'posting_date']);
        });




        // Schema::create('stock_ledger_entries', function (Blueprint $table) {
        //     $table->id();
        //     $table->foreignId('product_id')->constrained();
        //     $table->foreignId('warehouse_id')->constrained();
        //     $table->foreignId('batch_id')->nullable()->constrained();

        //     $table->date('posting_date');
        //     $table->string('voucher_type'); // 'Purchase', 'Sale', 'Transfer', 'Adjustment'
        //     $table->unsignedBigInteger('voucher_id');

        //     $table->decimal('qty', 15, 3); // +ve IN, -ve OUT
        //     $table->decimal('rate', 15, 4)->comment('Cost per unit');
        //     $table->decimal('amount', 15, 2); // qty * rate

        //     $table->decimal('balance_qty', 15, 3);
        //     $table->decimal('balance_value', 15, 2);

        //     $table->foreignId('journal_entry_id')->nullable()->constrained();
        //     $table->timestamps();

        //     $table->index(['product_id', 'warehouse_id', 'posting_date']);
        //     $table->index(['batch_id', 'posting_date']);
        // });

        Schema::create('stock_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('stock_ledger_entry_id')->constrained();
            $table->decimal('qty', 15, 3);
            $table->decimal('rate', 15, 4)->comment('Purchase cost');
            $table->timestamps();

            $table->index(['product_id', 'warehouse_id']);
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number')->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete(); // delivery person
            $table->date('sale_date');
            $table->date('delivery_date')->nullable();
            $table->enum('payment_type', ['cash', 'credit'])->default('cash');
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('balance_amount', 15, 2)->default(0);
            $table->decimal('cost_of_goods_sold', 15, 2)->default(0)->comment('COGS for this sale');
            $table->text('notes')->nullable();

            // Double-entry accounting integration
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete()
                ->comment('Links to journal entry: Debit AR/Cash & COGS, Credit Sales Revenue & Inventory');
            $table->enum('posting_status', ['draft', 'posted', 'cancelled'])->default('draft')
                ->comment('draft=not posted to accounting, posted=journal entry created, cancelled=voided');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['sale_date', 'posting_status']);
            $table->index(['customer_id', 'payment_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
