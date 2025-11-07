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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number')->unique();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->enum('payment_method', ['cash', 'cheque', 'bank_transfer', 'online'])->default('cash');
            $table->string('reference_number')->nullable(); // cheque number, transaction id, etc.
            $table->text('notes')->nullable();

            // Cheque-specific fields
            $table->string('cheque_number')->nullable()->comment('Cheque number if payment_method is cheque');
            $table->string('cheque_bank')->nullable()->comment('Bank name on cheque');
            $table->string('cheque_branch')->nullable()->comment('Bank branch');
            $table->date('cheque_date')->nullable()->comment('Date written on cheque');
            $table->date('cheque_clearance_date')->nullable()->comment('Date when cheque cleared');
            $table->enum('cheque_status', ['pending', 'cleared', 'bounced', 'cancelled'])->nullable()
                ->comment('Cheque clearance status');

            // Double-entry accounting integration
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete()
                ->comment('Links to journal entry: Debit Cash/Bank, Credit Accounts Receivable');
            $table->enum('posting_status', ['draft', 'posted', 'cancelled'])->default('draft')
                ->comment('draft=not posted to accounting, posted=journal entry created, cancelled=voided');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['payment_date', 'posting_status']);
            $table->index(['customer_id', 'payment_method']);
            $table->index(['cheque_status', 'cheque_clearance_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
