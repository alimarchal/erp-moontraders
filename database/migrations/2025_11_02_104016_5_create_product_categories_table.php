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
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('category_code')->unique();
            $table->string('category_name');
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->nullOnDelete()->comment('For hierarchical categories');

            // Link to default Chart of Accounts for automatic posting
            $table->foreignId('default_inventory_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete()->comment('Default Asset account for this category');
            $table->foreignId('default_cogs_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete()->comment('Default COGS expense account');
            $table->foreignId('default_sales_revenue_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete()->comment('Default Sales revenue account');

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
        Schema::dropIfExists('product_categories');
    }
};
