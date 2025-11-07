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
