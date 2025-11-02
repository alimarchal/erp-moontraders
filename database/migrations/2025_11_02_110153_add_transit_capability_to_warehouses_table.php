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
        Schema::table('warehouses', function (Blueprint $table) {
            // Transit Warehouse Capability (following ERPNext pattern)
            $table->boolean('is_transit_warehouse')->default(false)->after('is_rejected_warehouse')
                ->comment('If true, this warehouse represents goods in transit (e.g., vehicle)');

            // Vehicle-Warehouse Linkage (for vehicle-based transit warehouses)
            $table->foreignId('linked_vehicle_id')->nullable()->after('is_transit_warehouse')
                ->constrained('vehicles')->nullOnDelete()
                ->comment('If transit warehouse, links to specific vehicle');

            // Add index for quick transit warehouse queries
            $table->index('is_transit_warehouse');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropForeign(['linked_vehicle_id']);
            $table->dropIndex(['is_transit_warehouse']);
            $table->dropColumn(['is_transit_warehouse', 'linked_vehicle_id']);
        });
    }
};
