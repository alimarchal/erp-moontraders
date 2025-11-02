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
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_name');
            $table->foreignId('price_list_id')->nullable()->constrained('price_lists')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_group')->nullable(); // optional text group
            $table->decimal('min_qty', 15, 2)->nullable();
            $table->decimal('max_qty', 15, 2)->nullable();
            $table->enum('price_type', ['discount_percent', 'discount_amount', 'fixed_rate'])->default('discount_percent');
            $table->decimal('value', 15, 2)->default(0);
            $table->date('valid_from')->nullable();
            $table->date('valid_upto')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'valid_from', 'valid_upto'], 'pr_active_date_idx');
            $table->index(['product_id', 'customer_id'], 'pr_prod_cust_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
    }
};
