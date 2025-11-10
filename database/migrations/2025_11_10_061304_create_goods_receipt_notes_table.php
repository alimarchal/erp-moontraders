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
        Schema::create('goods_receipt_notes', function (Blueprint $table) {
            $table->id();

            // GRN Identity
            $table->string('grn_number', 50)->unique()->comment('Auto-generated: GRN-2025-0001');
            $table->date('receipt_date');

            // Source
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();

            // References
            $table->foreignId('purchase_order_id')->nullable()->comment('Link to PO when implemented');
            $table->string('supplier_invoice_number', 100)->nullable();
            $table->date('supplier_invoice_date')->nullable();

            // Financial Summary
            $table->decimal('total_quantity', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0)->comment('Sum of all line items');
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('freight_charges', 15, 2)->default(0);
            $table->decimal('other_charges', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0)->comment('total_amount + tax + freight + other');

            // Status & Workflow
            $table->enum('status', ['draft', 'received', 'posted', 'cancelled'])->default('draft')->index();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users');
            $table->foreignId('received_by')->constrained('users')->comment('User who created GRN');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete()->comment('User who verified quality');
            $table->timestamp('posted_at')->nullable();

            // Accounting Integration
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete()->comment('Auto-posted accounting entry');

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('supplier_id');
            $table->index('warehouse_id');
            $table->index('receipt_date');
            $table->index('supplier_invoice_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_notes');
    }
};
