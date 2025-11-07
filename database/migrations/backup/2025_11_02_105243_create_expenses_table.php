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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number')->unique();
            $table->date('expense_date');
            $table->enum('expense_category', [
                'rent',
                'utilities',
                'office_supplies',
                'repairs',
                'insurance',
                'marketing',
                'travel',
                'professional_fees',
                'miscellaneous'
            ])->comment('General expense categories');
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->enum('payment_method', ['cash', 'cheque', 'bank_transfer', 'online'])->default('cash');
            $table->string('reference_number')->nullable()->comment('Cheque number, invoice number, etc.');

            // Cheque details if applicable
            $table->string('cheque_number')->nullable();
            $table->string('cheque_bank')->nullable();
            $table->date('cheque_date')->nullable();
            $table->date('cheque_clearance_date')->nullable();
            $table->enum('cheque_status', ['pending', 'cleared', 'bounced', 'cancelled'])->nullable();

            $table->string('vendor_name')->nullable()->comment('Supplier/vendor name');
            $table->string('receipt_number')->nullable();
            $table->text('notes')->nullable();

            // Optional cost center for tracking
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();

            // Double-entry accounting integration
            $table->foreignId('expense_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete()
                ->comment('Expense account based on expense_category');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete()
                ->comment('Links to journal entry: Debit Expense Account, Credit Cash/Bank');
            $table->enum('posting_status', ['draft', 'posted', 'cancelled'])->default('draft')
                ->comment('draft=not posted to accounting, posted=journal entry created, cancelled=voided');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['expense_date', 'posting_status']);
            $table->index(['expense_category', 'expense_date']);
            $table->index(['cheque_status', 'cheque_clearance_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
