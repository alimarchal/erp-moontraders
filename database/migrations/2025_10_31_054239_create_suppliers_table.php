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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();

            // Basic Information
            $table->string('supplier_name')->unique();
            $table->string('short_name')->nullable();
            $table->string('country')->nullable();
            $table->string('supplier_group')->nullable();
            $table->string('supplier_type')->nullable();

            // Flags
            $table->boolean('is_transporter')->default(false);
            $table->boolean('is_internal_supplier')->default(false);
            $table->boolean('disabled')->default(false);

            // Defaults
            $table->foreignId('default_currency_id')->nullable()->constrained('currencies')->onDelete('set null');
            $table->foreignId('default_bank_account_id')->nullable()->constrained('chart_of_accounts')->onDelete('set null');
            $table->string('default_price_list')->nullable();

            // More Information
            $table->text('supplier_details')->nullable();
            $table->string('website')->nullable();
            $table->string('print_language')->nullable();

            // Primary Address and Contact
            $table->text('supplier_primary_address')->nullable();
            $table->text('supplier_primary_contact')->nullable();

            // Tax and Registration
            $table->string('tax_id')->nullable();
            $table->decimal('sales_tax', 5, 2)->default(18.00)->comment('Sales tax percentage');
            $table->string('pan_number')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('supplier_name');
            $table->index('country');
            $table->index('supplier_group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
