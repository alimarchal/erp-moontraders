<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daily_inventory_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index()->comment('Snapshot date (end of day balance)');

            // Product being tracked
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            // Location (one should be set)
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();

            // Balance at end of day
            $table->decimal('quantity_on_hand', 15, 3)->default(0);
            $table->decimal('average_cost', 15, 2)->default(0);
            $table->decimal('total_value', 15, 2)->default(0)->comment('quantity_on_hand * average_cost');

            $table->timestamps();

            // Unique constraint: One snapshot per date per product per location
            $table->unique(['date', 'product_id', 'warehouse_id'], 'unique_snapshot_warehouse');
            $table->unique(['date', 'product_id', 'vehicle_id'], 'unique_snapshot_vehicle');

            // Indexes for fast lookups
            $table->index(['date', 'warehouse_id'], 'idx_snapshot_date_warehouse');
            $table->index(['date', 'vehicle_id'], 'idx_snapshot_date_vehicle');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_inventory_snapshots');
    }
};
