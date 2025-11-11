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
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number')->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->date('payment_date');

            $table->enum('payment_method', ['bank_transfer', 'cash', 'cheque', 'online'])->default('bank_transfer');
            $table->string('reference_number')->nullable()->comment('Cheque number, transaction ID, etc.');

            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();

            $table->enum('status', ['draft', 'posted', 'cancelled', 'bounced'])->default('draft');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // Pivot table for payment allocation to GRNs
        Schema::create('payment_grn_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_payment_id')->constrained('supplier_payments')->cascadeOnDelete();
            $table->foreignId('grn_id')->constrained('goods_receipt_notes')->cascadeOnDelete();
            $table->decimal('allocated_amount', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_grn_allocations');
        Schema::dropIfExists('supplier_payments');
    }
};
