<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Invoice Summary - Supplier Invoice Breakdown Report
     *
     * Tracks supplier invoices with detailed tax/discount breakdowns.
     * Formula: total_value_with_tax = discount_before_sales_tax + excise_duty + sales_tax_value + advance_tax
     */
    public function up(): void
    {
        Schema::create('invoice_summaries', function (Blueprint $table) {
            $table->id();

            // Supplier & Invoice Details
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->date('invoice_date')->index();
            $table->string('invoice_number')->index();
            $table->integer('cartons')->default(0);

            // Amounts
            $table->decimal('invoice_value', 15, 2)->default(0)->comment('Total invoice amount');
            $table->decimal('za_on_invoices', 15, 2)->default(0)->comment('0.5% deduction on invoices');
            $table->decimal('discount_value', 15, 2)->default(0)->comment('Discount value');
            $table->decimal('fmr_allowance', 15, 2)->default(0)->comment('FMR allowance');
            $table->decimal('discount_before_sales_tax', 15, 2)->default(0)->comment('Discount value before sales tax');
            $table->decimal('excise_duty', 15, 2)->default(0)->comment('Excise duty amount');
            $table->decimal('sales_tax_value', 15, 2)->default(0)->comment('Sales tax amount');
            $table->decimal('advance_tax', 15, 2)->default(0)->comment('Advance tax amount');
            $table->decimal('total_value_with_tax', 15, 2)->default(0)->comment('= disc_before_st + excise + sales_tax + advance_tax');

            // Notes
            $table->text('remarks')->nullable();

            // System Fields
            $table->userTracking();
            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index(['supplier_id', 'invoice_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_summaries');
    }
};
