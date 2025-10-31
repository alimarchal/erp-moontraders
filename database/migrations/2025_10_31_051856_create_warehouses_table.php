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
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();

            // Basic Information
            $table->string('warehouse_name')->unique();
            $table->boolean('disabled')->default(false);
            $table->boolean('is_group')->default(false);

            // Hierarchy (Nested Set Model)
            $table->foreignId('parent_warehouse_id')->nullable()->constrained('warehouses')->onDelete('set null');
            $table->integer('lft')->default(0)->index();
            $table->integer('rgt')->default(0)->index();

            // Company and Type
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('set null');
            $table->foreignId('warehouse_type_id')->nullable()->constrained('warehouse_types')->onDelete('set null');

            // Special Warehouse Flags
            $table->boolean('is_rejected_warehouse')->default(false);
            $table->foreignId('default_in_transit_warehouse_id')->nullable()->constrained('warehouses')->onDelete('set null');

            // Accounting
            $table->foreignId('account_id')->nullable()->constrained('chart_of_accounts')->onDelete('set null');

            // Contact Information
            $table->string('email_id')->nullable();
            $table->string('phone_no')->nullable();
            $table->string('mobile_no')->nullable();

            // Address
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('pin')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('company_id');
            $table->index('parent_warehouse_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
