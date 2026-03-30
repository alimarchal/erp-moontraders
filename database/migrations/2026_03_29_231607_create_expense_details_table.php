<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expense Details - Category-based expense tracking with double-entry GL posting.
     *
     * Categories: Stationary, TCS, Tonner & IT, Salaries, Fuel, Van Work
     */
    public function up(): void
    {
        Schema::create('expense_details', function (Blueprint $table) {
            $table->id();

            // Core Fields
            $table->enum('category', ['stationary', 'tcs', 'tonner_it', 'salaries', 'fuel', 'van_work'])->index();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->date('transaction_date')->index();
            $table->text('description')->nullable();

            // Amount
            $table->decimal('amount', 15, 2)->default(0);

            // Fuel-specific fields
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->string('vehicle_type')->nullable();
            $table->foreignId('driver_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->decimal('liters', 10, 2)->nullable();

            // Salaries-specific fields
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('employee_no')->nullable();

            // Double-Entry Amounts
            $table->decimal('debit', 15, 2)->default(0)->comment('Expense amount (DR)');
            $table->decimal('credit', 15, 2)->default(0)->comment('Always 0 for expenses');

            // GL Accounts (auto-set based on category)
            $table->foreignId('debit_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('credit_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();

            // Notes
            $table->text('notes')->nullable();

            // Posting Info
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();

            // System Fields
            $table->userTracking();
            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index(['category', 'transaction_date']);
        });

        // Create triggers to prevent UPDATE/DELETE on posted expense details
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared("
                CREATE OR REPLACE FUNCTION fn_block_posted_expense_updates()
                RETURNS TRIGGER AS $$
                BEGIN
                    IF OLD.posted_at IS NOT NULL THEN
                        RAISE EXCEPTION 'Posted expense details are immutable. Cannot update posted expenses.';
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;
            ");

            DB::unprepared('
                CREATE TRIGGER trg_block_posted_expense_updates
                BEFORE UPDATE ON expense_details
                FOR EACH ROW
                EXECUTE FUNCTION fn_block_posted_expense_updates();
            ');

            DB::unprepared("
                CREATE OR REPLACE FUNCTION fn_block_posted_expense_deletes()
                RETURNS TRIGGER AS $$
                BEGIN
                    IF OLD.posted_at IS NOT NULL THEN
                        RAISE EXCEPTION 'Cannot delete posted expense details. Posted expenses are immutable.';
                    END IF;
                    RETURN OLD;
                END;
                $$ LANGUAGE plpgsql;
            ");

            DB::unprepared('
                CREATE TRIGGER trg_block_posted_expense_deletes
                BEFORE DELETE ON expense_details
                FOR EACH ROW
                EXECUTE FUNCTION fn_block_posted_expense_deletes();
            ');
        } else {
            DB::unprepared("
                CREATE TRIGGER trg_block_posted_expense_updates
                BEFORE UPDATE ON expense_details
                FOR EACH ROW
                BEGIN
                    IF OLD.posted_at IS NOT NULL THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Posted expense details are immutable. Cannot update posted expenses.';
                    END IF;
                END
            ");

            DB::unprepared("
                CREATE TRIGGER trg_block_posted_expense_deletes
                BEFORE DELETE ON expense_details
                FOR EACH ROW
                BEGIN
                    IF OLD.posted_at IS NOT NULL THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Cannot delete posted expense details. Posted expenses are immutable.';
                    END IF;
                END
            ");
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_block_posted_expense_updates ON expense_details');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_block_posted_expense_deletes ON expense_details');
            DB::unprepared('DROP FUNCTION IF EXISTS fn_block_posted_expense_updates()');
            DB::unprepared('DROP FUNCTION IF EXISTS fn_block_posted_expense_deletes()');
        } else {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_block_posted_expense_updates');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_block_posted_expense_deletes');
        }

        Schema::dropIfExists('expense_details');
    }
};
