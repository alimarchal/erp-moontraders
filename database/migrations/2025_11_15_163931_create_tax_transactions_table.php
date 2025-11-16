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
        Schema::create('tax_transactions', function (Blueprint $table) {
            $table->id();

            // Polymorphic source (sales_invoice, purchase_invoice, goods_receipt_notes, etc.)
            $table->string('taxable_type'); // App\Models\SalesInvoice
            $table->unsignedBigInteger('taxable_id');

            $table->foreignId('tax_code_id')->constrained('tax_codes')->restrictOnDelete();
            $table->foreignId('tax_rate_id')->constrained('tax_rates')->restrictOnDelete();

            $table->date('transaction_date');
            $table->decimal('taxable_amount', 15, 2);
            $table->decimal('tax_rate', 5, 2);
            $table->decimal('tax_amount', 15, 2);

            $table->enum('tax_direction', ['payable', 'receivable'])->default('payable');

            $table->timestamps();

            $table->index(['taxable_type', 'taxable_id']);
            $table->index(['tax_code_id', 'transaction_date']);
            $table->index('transaction_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_transactions');
    }
};
