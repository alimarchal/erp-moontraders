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
        // Customer Ledger - Tracks all financial transactions per customer
        Schema::create('customer_ledgers', function (Blueprint $table) {
            $table->id();
            $table->date('transaction_date')->index();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();

            // Transaction Details
            $table->string('transaction_type')->index()->comment('credit_sale, cash_recovery, cheque_recovery, return, adjustment');
            $table->string('reference_number')->nullable()->index()->comment('Settlement number, receipt number, etc.');
            $table->text('description')->nullable();

            // Double Entry - Debit increases receivable, Credit decreases it
            $table->decimal('debit', 15, 2)->default(0)->comment('Credit sales - increases customer receivable');
            $table->decimal('credit', 15, 2)->default(0)->comment('Payments/recoveries - decreases customer receivable');
            $table->decimal('balance', 15, 2)->default(0)->comment('Running balance after this transaction');

            // Related Records (Polymorphic for flexibility)
            $table->foreignId('sales_settlement_id')->nullable()->constrained('sales_settlements')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete()->comment('Salesman who made the transaction');
            $table->foreignId('credit_sale_id')->nullable()->constrained('credit_sales')->nullOnDelete();

            // Additional tracking
            $table->string('payment_method')->nullable()->comment('cash, cheque, bank_transfer');
            $table->string('cheque_number')->nullable();
            $table->date('cheque_date')->nullable();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'transaction_date']);
            $table->index(['transaction_type', 'transaction_date']);
            $table->index(['employee_id', 'transaction_date']);
        });

        // Salesman Ledger - Tracks credit sales and recoveries per salesman
        Schema::create('salesman_ledgers', function (Blueprint $table) {
            $table->id();
            $table->date('transaction_date')->index();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            // Transaction Details
            $table->string('transaction_type')->index()->comment('credit_sale, recovery, commission, adjustment');
            $table->string('reference_number')->nullable()->index();
            $table->text('description')->nullable();

            // Double Entry - Debit increases salesman's outstanding, Credit decreases it
            $table->decimal('debit', 15, 2)->default(0)->comment('Credit sales given - salesman owes company');
            $table->decimal('credit', 15, 2)->default(0)->comment('Cash deposited/recovered - salesman pays company');
            $table->decimal('balance', 15, 2)->default(0)->comment('Net outstanding with salesman');

            // Related Records
            $table->foreignId('sales_settlement_id')->nullable()->constrained('sales_settlements')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete()->comment('Customer involved in transaction');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete()->comment('Salesman employer');

            // Cash/Stock tracking
            $table->decimal('cash_amount', 15, 2)->default(0)->comment('Actual cash collected');
            $table->decimal('cheque_amount', 15, 2)->default(0)->comment('Cheques collected');
            $table->decimal('credit_amount', 15, 2)->default(0)->comment('Credit sales amount');

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'transaction_date']);
            $table->index(['transaction_type', 'transaction_date']);
            $table->index(['customer_id', 'transaction_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salesman_ledgers');
        Schema::dropIfExists('customer_ledgers');
    }
};
