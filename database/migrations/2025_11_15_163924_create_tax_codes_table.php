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
        Schema::create('tax_codes', function (Blueprint $table) {
            $table->id();
            $table->string('tax_code', 20)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();

            $table->enum('tax_type', ['sales_tax', 'gst', 'vat', 'withholding_tax', 'excise', 'customs_duty'])->default('sales_tax');
            $table->enum('calculation_method', ['percentage', 'fixed_amount'])->default('percentage');

            // Accounting integration
            $table->foreignId('tax_payable_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('tax_receivable_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_compound')->default(false); // Tax on tax
            $table->boolean('included_in_price')->default(false); // Tax inclusive pricing

            $table->softDeletes();
            $table->timestamps();

            $table->index('is_active');
            $table->index('tax_type');
            $table->index('calculation_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_codes');
    }
};
