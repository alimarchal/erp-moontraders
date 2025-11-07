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
        Schema::create('employee_salaries', function (Blueprint $table) {
            $table->id();
            $table->string('salary_number')->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('month')->comment('Format: YYYY-MM');
            $table->decimal('basic_salary', 15, 2);
            $table->decimal('allowances', 15, 2)->default(0)->comment('Housing, transport, etc.');
            $table->decimal('deductions', 15, 2)->default(0)->comment('Tax, insurance, loans, etc.');
            $table->decimal('net_salary', 15, 2)->comment('basic + allowances - deductions');
            $table->date('payment_date');
            $table->enum('payment_method', ['cash', 'cheque', 'bank_transfer'])->default('bank_transfer');
            $table->string('reference_number')->nullable()->comment('Cheque number or transaction ID');

            // Cheque details if applicable
            $table->string('cheque_number')->nullable();
            $table->string('cheque_bank')->nullable();
            $table->date('cheque_date')->nullable();

            $table->text('notes')->nullable();

            // Double-entry accounting integration
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete()
                ->comment('Links to journal entry: Debit Salary Expense, Credit Cash/Bank');
            $table->enum('posting_status', ['draft', 'posted', 'cancelled'])->default('draft')
                ->comment('draft=not posted to accounting, posted=journal entry created, cancelled=voided');

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_id', 'month']);
            $table->index(['payment_date', 'posting_status']);
            $table->index(['month', 'posting_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_salaries');
    }
};
