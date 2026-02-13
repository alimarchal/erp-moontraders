<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Employee Salary Transactions — Double-Entry Subsidiary Ledger
     *
     * Every financial interaction with an employee is recorded here.
     * Works like a double-entry ledger: each row has debit/credit columns.
     *
     * Debit/Credit Convention:
     *   Debit  = amount employee receives or is owed (salary, advance, bonus, loan)
     *   Credit = amount deducted or recovered from employee (advance recovery, fine, deduction)
     *
     * Balance = SUM(debit) - SUM(credit)
     *   Positive = company owes employee
     *   Negative = employee owes company
     *
     * GL Integration:
     *   User selects debit_account_id and credit_account_id from Chart of Accounts.
     *   On posting, a journal entry is created via SalaryService → AccountingService.
     *   The journal_entry_id is stored here for audit trail.
     */
    public function up(): void
    {
        Schema::create('employee_salary_transactions', function (Blueprint $table) {
            $table->id();

            // ── Employee & Supplier Reference ─────────────────────────────
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete()
                ->comment('Employee this transaction belongs to');

            $table->foreignId('supplier_id')
                ->nullable()
                ->constrained('suppliers')
                ->nullOnDelete()
                ->comment('Supplier the employee belongs to (denormalized for supplier-wise reports)');

            // ── Transaction Details ───────────────────────────────────────
            $table->date('transaction_date')->index()
                ->comment('Date of the transaction');

            $table->string('reference_number')->nullable()->index()
                ->comment('Transaction reference e.g. SAL-2026-001, ADV-2026-015');

            $table->enum('transaction_type', [
                'Salary', 'Advance', 'AdvanceRecovery', 'Deduction', 'Bonus',
                'Loan', 'LoanRecovery', 'Expense', 'ExpenseReimbursement',
                'Shortage', 'ShortageRecovery', 'Incentive', 'OvertimePay',
                'FineDeduction', 'SalaryPayment', 'Adjustment',
            ])->index()->comment('Type of salary transaction');

            $table->text('description')->nullable()
                ->comment('Description e.g. "January 2026 Salary", "Fuel Advance"');

            // ── Salary Period ─────────────────────────────────────────────
            $table->string('salary_month')->nullable()->index()
                ->comment('Human-readable salary period e.g. "January 2026"');

            $table->date('period_start')->nullable()->index()
                ->comment('Start date of the salary period');

            $table->date('period_end')->nullable()->index()
                ->comment('End date of the salary period');

            // ── Double-Entry Amounts ──────────────────────────────────────
            $table->decimal('debit', 15, 2)->default(0)
                ->comment('Debit: amount employee receives/is owed (salary, advance, bonus, reimbursement)');

            $table->decimal('credit', 15, 2)->default(0)
                ->comment('Credit: amount deducted/recovered from employee (advance recovery, fine, shortage)');

            // ── GL Account References ─────────────────────────────────────
            $table->foreignId('debit_account_id')->nullable()
                ->constrained('chart_of_accounts')
                ->nullOnDelete()
                ->comment('Debit GL account selected by user (e.g. Salary Expense 5281, Employee Advances)');

            $table->foreignId('credit_account_id')->nullable()
                ->constrained('chart_of_accounts')
                ->nullOnDelete()
                ->comment('Credit GL account selected by user (e.g. Cash, Bank, Payroll Payable 2112)');

            // ── Payment Details ───────────────────────────────────────────
            $table->enum('payment_method', ['cash', 'cheque', 'bank_transfer'])->nullable()
                ->comment('How payment was made');

            $table->string('cheque_number')->nullable()
                ->comment('Cheque number (only when payment_method = cheque)');

            $table->date('cheque_date')->nullable()
                ->comment('Cheque date (only when payment_method = cheque)');

            $table->foreignId('bank_account_id')->nullable()
                ->constrained('bank_accounts')
                ->nullOnDelete()
                ->comment('Bank account used for payment');

            // ── Status & Linking ──────────────────────────────────────────
            $table->enum('status', ['Pending', 'Approved', 'Paid', 'Cancelled'])->default('Pending')->index()
                ->comment('Pending = created, Approved = verified, Paid = posted to GL, Cancelled = voided');

            $table->foreignId('sales_settlement_id')->nullable()
                ->constrained('sales_settlements')
                ->nullOnDelete()
                ->comment('Link to sales settlement if transaction originated from a settlement');

            $table->foreignId('employee_salary_id')->nullable()
                ->constrained('employee_salaries')
                ->nullOnDelete()
                ->comment('Link to salary structure record if this is a salary payment');

            $table->foreignId('journal_entry_id')->nullable()
                ->constrained('journal_entries')
                ->nullOnDelete()
                ->comment('GL journal entry created when transaction is posted');

            // ── Additional Info ───────────────────────────────────────────
            $table->text('notes')->nullable()
                ->comment('Additional remarks');

            // ── System Fields ─────────────────────────────────────────────
            $table->userTracking();
            $table->softDeletes();
            $table->timestamps();

            // ── Composite Indexes for Report Performance ──────────────────
            $table->index(['employee_id', 'transaction_date'], 'idx_est_employee_date');
            $table->index(['employee_id', 'status'], 'idx_est_employee_status');
            $table->index(['employee_id', 'transaction_type'], 'idx_est_employee_type');
            $table->index(['supplier_id', 'transaction_date'], 'idx_est_supplier_date');
            $table->index(['period_start', 'period_end'], 'idx_est_period');
            $table->index(['transaction_type', 'transaction_date'], 'idx_est_type_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_transactions');
    }
};
