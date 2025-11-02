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
        Schema::create('delivery_notes', function (Blueprint $table) {
            $table->id();

            // Document Information
            $table->string('delivery_note_number')->unique()->comment('Unique delivery note reference');
            $table->date('delivery_date');
            $table->time('departure_time')->nullable();
            $table->time('estimated_arrival')->nullable();
            $table->time('actual_arrival')->nullable();

            // Vehicle and Driver Assignment
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete()
                ->comment('Vehicle assigned for delivery');
            $table->foreignId('driver_id')->constrained('employees')->cascadeOnDelete()
                ->comment('Driver (employee) assigned');

            // Customer and Location
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete()
                ->comment('Optional link to sales order');
            $table->text('delivery_address')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_phone')->nullable();

            // Source and Transit Warehouses
            $table->foreignId('source_warehouse_id')->constrained('warehouses')->cascadeOnDelete()
                ->comment('Warehouse from which goods loaded');
            $table->foreignId('transit_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete()
                ->comment('Transit warehouse (vehicle as warehouse)');

            // Status Tracking
            $table->enum('status', ['draft', 'in_transit', 'delivered', 'partially_delivered', 'returned', 'cancelled'])
                ->default('draft')->index();
            $table->text('delivery_notes')->nullable()->comment('Notes about delivery');
            $table->text('return_reason')->nullable()->comment('Reason if items returned');

            // Financial Summary
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('received_amount', 15, 2)->default(0)->comment('Amount collected from customer');
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid');

            // Distance and Route
            $table->decimal('distance_km', 10, 2)->nullable()->comment('Total distance traveled');
            $table->string('route')->nullable()->comment('Route taken');

            // Double-Entry Accounting Integration
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete()
                ->comment('Journal entry for COGS on delivery');
            $table->enum('posting_status', ['draft', 'posted', 'cancelled'])->default('draft')->index();

            // References and Approval
            $table->foreignId('approved_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for reporting
            $table->index(['delivery_date', 'status']);
            $table->index(['vehicle_id', 'delivery_date']);
            $table->index(['driver_id', 'delivery_date']);
            $table->index(['customer_id', 'delivery_date']);
            $table->index(['posting_status', 'delivery_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_notes');
    }
};
