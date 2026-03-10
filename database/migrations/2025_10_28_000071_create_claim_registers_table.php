<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Claim Register - Supplier Claims & Recoveries
     *
     * Tracks claims raised against suppliers (Debtors A/C 1111) and
     * recoveries received via bank transfer (HBL Main Account).
     */
    public function up(): void
    {
        Schema::create('claim_registers', function (Blueprint $table) {
            $table->id();

            // Supplier & Transaction Details
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->date('transaction_date')->index();
            $table->string('reference_number')->nullable()->index();
            $table->text('description')->nullable();
            $table->string('claim_month')->nullable()->index();
            $table->date('date_of_dispatch')->nullable();

            // Transaction Type & Double-Entry Amounts
            $table->enum('transaction_type', ['claim', 'recovery'])->default('claim')->index()
                ->comment('claim = DR (supplier owes us), recovery = CR (we received payment)');
            $table->decimal('debit', 15, 2)->default(0)->comment('Claim amount (DR)');
            $table->decimal('credit', 15, 2)->default(0)->comment('Recovery amount (CR)');

            // GL Accounts (auto-set: debit=1111 Debtors, credit=HBL Main Bank COA)
            $table->foreignId('debit_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('credit_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();

            // Payment (always bank_transfer to HBL Main)
            $table->string('payment_method')->default('bank_transfer');
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();

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
            $table->index(['supplier_id', 'transaction_date']);
        });

        // Create triggers to prevent UPDATE/DELETE on posted claim registers
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // PostgreSQL syntax
            DB::unprepared("
                CREATE OR REPLACE FUNCTION fn_block_posted_claim_updates()
                RETURNS TRIGGER AS $$
                BEGIN
                    IF OLD.posted_at IS NOT NULL THEN
                        RAISE EXCEPTION 'Posted claim registers are immutable. Cannot update posted claims.';
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;
            ");

            DB::unprepared('
                CREATE TRIGGER trg_block_posted_claim_updates
                BEFORE UPDATE ON claim_registers
                FOR EACH ROW
                EXECUTE FUNCTION fn_block_posted_claim_updates();
            ');

            DB::unprepared("
                CREATE OR REPLACE FUNCTION fn_block_posted_claim_deletes()
                RETURNS TRIGGER AS $$
                BEGIN
                    IF OLD.posted_at IS NOT NULL THEN
                        RAISE EXCEPTION 'Cannot delete posted claim registers. Posted claims are immutable.';
                    END IF;
                    RETURN OLD;
                END;
                $$ LANGUAGE plpgsql;
            ");

            DB::unprepared('
                CREATE TRIGGER trg_block_posted_claim_deletes
                BEFORE DELETE ON claim_registers
                FOR EACH ROW
                EXECUTE FUNCTION fn_block_posted_claim_deletes();
            ');
        } else {
            // MySQL/MariaDB syntax
            DB::unprepared("
                CREATE TRIGGER trg_block_posted_claim_updates
                BEFORE UPDATE ON claim_registers
                FOR EACH ROW
                BEGIN
                    IF OLD.posted_at IS NOT NULL THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Posted claim registers are immutable. Cannot update posted claims.';
                    END IF;
                END
            ");

            DB::unprepared("
                CREATE TRIGGER trg_block_posted_claim_deletes
                BEFORE DELETE ON claim_registers
                FOR EACH ROW
                BEGIN
                    IF OLD.posted_at IS NOT NULL THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Cannot delete posted claim registers. Posted claims are immutable.';
                    END IF;
                END
            ");
        }
    }

    public function down(): void
    {
        // Drop triggers first (before dropping the table)
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_block_posted_claim_updates ON claim_registers');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_block_posted_claim_deletes ON claim_registers');
            DB::unprepared('DROP FUNCTION IF EXISTS fn_block_posted_claim_updates()');
            DB::unprepared('DROP FUNCTION IF EXISTS fn_block_posted_claim_deletes()');
        } else {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_block_posted_claim_updates');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_block_posted_claim_deletes');
        }

        Schema::dropIfExists('claim_registers');
    }
};
