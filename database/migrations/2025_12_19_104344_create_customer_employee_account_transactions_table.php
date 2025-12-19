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
        Schema::create('customer_employee_account_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_employee_account_id')
                ->constrained('customer_employee_accounts')
                ->cascadeOnDelete()
                ->name('fk_ceat_account_id');
            $table->date('transaction_date')->index();
            $table->enum('transaction_type', [
                'credit_sale',
                'recovery_cash',
                'recovery_cheque',
                'bank_transfer',
                'opening_balance',
                'adjustment',
                'return_credit',
            ])->index()->comment('Type of transaction');
            $table->string('reference_number')->nullable()->index()->comment('Settlement number, cheque number, etc.');
            $table->foreignId('sales_settlement_id')
                ->nullable()
                ->constrained('sales_settlements')
                ->nullOnDelete()
                ->name('fk_ceat_settlement_id');
            $table->string('invoice_number')->nullable();
            $table->text('description');

            // Double-entry columns
            $table->decimal('debit', 15, 2)->default(0)->comment('Customer owes money (credit sales)');
            $table->decimal('credit', 15, 2)->default(0)->comment('Customer pays money (recoveries)');

            // Payment details
            $table->string('payment_method')->nullable()->comment('cash, cheque, bank_transfer');
            $table->string('cheque_number')->nullable();
            $table->date('cheque_date')->nullable();
            $table->foreignId('bank_account_id')
                ->nullable()
                ->constrained('bank_accounts')
                ->nullOnDelete()
                ->name('fk_ceat_bank_account_id');

            $table->text('notes')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->name('fk_ceat_created_by');
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['customer_employee_account_id', 'transaction_date'], 'idx_account_date');
            $table->index(['transaction_type', 'transaction_date'], 'idx_type_date');
            $table->index('sales_settlement_id', 'idx_settlement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_employee_account_transactions');
    }
};
