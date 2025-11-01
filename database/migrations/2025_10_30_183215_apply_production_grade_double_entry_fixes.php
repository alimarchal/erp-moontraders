<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Production-grade fixes for double-entry accounting system:
     * 1. Stricter debit/credit XOR (one side MUST be > 0)
     * 2. Accounting period check on UPDATE to 'posted'
     * 3. Immutability triggers for posted entries
     * 4. Positive exchange rate constraint
     * 5. Report group ENUM constraint
     * 6. Unique account type names
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // 1. Fix debit/credit XOR - MUST have exactly one side > 0
        $this->fixDebitCreditXOR();

        // 2. Add fx_rate_to_base positive constraint
        $this->addFxRateConstraint();

        // 3. Add report_group constraint
        $this->addReportGroupConstraint();

        // 4. Add unique constraint on account_types.type_name
        $this->addUniqueAccountTypeName();

        // 5. Add immutability and period update triggers
        if ($driver === 'pgsql') {
            $this->addPostgreSQLTriggers();
        } else {
            $this->addMySQLTriggers();
        }
    }

    /**
     * Fix debit/credit XOR constraint - one side MUST be > 0
     */
    private function fixDebitCreditXOR(): void
    {
        $driver = DB::connection()->getDriverName();

        // SQLite doesn't support ALTER TABLE ADD/DROP CONSTRAINT
        if ($driver !== 'sqlite') {
            DB::statement("
                ALTER TABLE journal_entry_details
                DROP CONSTRAINT IF EXISTS chk_debit_xor_credit
            ");

            DB::statement("
                ALTER TABLE journal_entry_details
                ADD CONSTRAINT chk_debit_xor_credit
                CHECK (
                    (debit > 0 AND credit = 0) OR
                    (credit > 0 AND debit = 0)
                )
            ");
        }
    }

    /**
     * Add positive exchange rate constraint
     */
    private function addFxRateConstraint(): void
    {
        $driver = DB::connection()->getDriverName();

        // SQLite doesn't support ALTER TABLE ADD CONSTRAINT
        if ($driver !== 'sqlite') {
            DB::statement("
                ALTER TABLE journal_entries
                ADD CONSTRAINT chk_fx_rate_positive
                CHECK (fx_rate_to_base > 0)
            ");
        }
    }

    /**
     * Add report_group constraint to balance sheet or income statement only
     */
    private function addReportGroupConstraint(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("
                ALTER TABLE account_types
                DROP CONSTRAINT IF EXISTS chk_report_group
            ");

            DB::statement("
                ALTER TABLE account_types
                ADD CONSTRAINT chk_report_group
                CHECK (report_group IN ('BalanceSheet', 'IncomeStatement'))
            ");
        } else {
            // MySQL/MariaDB
            try {
                DB::statement("
                    ALTER TABLE account_types
                    ADD CONSTRAINT chk_report_group
                    CHECK (report_group IN ('BalanceSheet', 'IncomeStatement'))
                ");
            } catch (\Exception $e) {
                \Log::warning('Could not add report_group constraint: ' . $e->getMessage());
            }
        }
    }    /**
         * Add unique constraint on account type names
         */
    private function addUniqueAccountTypeName(): void
    {
        Schema::table('account_types', function (Blueprint $table) {
            $table->unique('type_name');
        });
    }

    /**
     * Add PostgreSQL triggers for immutability and period check on update
     */
    private function addPostgreSQLTriggers(): void
    {
        // Function to block updates to posted journal entries
        DB::unprepared("
            CREATE OR REPLACE FUNCTION block_posted_journal_changes()
            RETURNS TRIGGER AS $$
            BEGIN
                IF OLD.status = 'posted' THEN
                    RAISE EXCEPTION 'Posted journal entries are immutable. Create a reversing entry instead.';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        DB::unprepared("
            DROP TRIGGER IF EXISTS trg_block_posted_journal_updates ON journal_entries;
            
            CREATE TRIGGER trg_block_posted_journal_updates
            BEFORE UPDATE ON journal_entries
            FOR EACH ROW
            EXECUTE FUNCTION block_posted_journal_changes();
        ");

        // Function to block deletes on posted journal entries
        DB::unprepared("
            CREATE OR REPLACE FUNCTION block_posted_journal_deletes()
            RETURNS TRIGGER AS $$
            BEGIN
                IF OLD.status = 'posted' THEN
                    RAISE EXCEPTION 'Cannot delete posted journal entries. Create a reversing entry instead.';
                END IF;
                RETURN OLD;
            END;
            $$ LANGUAGE plpgsql;
        ");

        DB::unprepared("
            DROP TRIGGER IF EXISTS trg_block_posted_journal_deletes ON journal_entries;
            
            CREATE TRIGGER trg_block_posted_journal_deletes
            BEFORE DELETE ON journal_entries
            FOR EACH ROW
            EXECUTE FUNCTION block_posted_journal_deletes();
        ");

        // Function to block changes to posted journal entry details
        DB::unprepared("
            CREATE OR REPLACE FUNCTION block_posted_detail_changes()
            RETURNS TRIGGER AS $$
            DECLARE
                v_status VARCHAR(20);
            BEGIN
                SELECT status INTO v_status
                FROM journal_entries
                WHERE id = OLD.journal_entry_id;
                
                IF v_status = 'posted' THEN
                    RAISE EXCEPTION 'Lines of a posted journal are immutable. Create a reversing entry instead.';
                END IF;

                IF TG_OP = 'DELETE' THEN
                    RETURN OLD;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        DB::unprepared("
            DROP TRIGGER IF EXISTS trg_block_posted_detail_updates ON journal_entry_details;
            
            CREATE TRIGGER trg_block_posted_detail_updates
            BEFORE UPDATE ON journal_entry_details
            FOR EACH ROW
            EXECUTE FUNCTION block_posted_detail_changes();
        ");

        DB::unprepared("
            DROP TRIGGER IF EXISTS trg_block_posted_detail_deletes ON journal_entry_details;
            
            CREATE TRIGGER trg_block_posted_detail_deletes
            BEFORE DELETE ON journal_entry_details
            FOR EACH ROW
            EXECUTE FUNCTION block_posted_detail_changes();
        ");

        // Enhanced period check on UPDATE when status changes to 'posted'
        DB::unprepared("
            DROP TRIGGER IF EXISTS trg_check_accounting_period_update ON journal_entries;
            
            CREATE TRIGGER trg_check_accounting_period_update
            BEFORE UPDATE ON journal_entries
            FOR EACH ROW
            WHEN (NEW.status = 'posted' AND OLD.status != 'posted')
            EXECUTE FUNCTION check_accounting_period();
        ");

        // Auto-set accounting_period_id based on entry_date
        DB::unprepared("
            CREATE OR REPLACE FUNCTION auto_set_accounting_period()
            RETURNS TRIGGER AS $$
            DECLARE
                v_period_id BIGINT;
                v_period_status VARCHAR(20);
            BEGIN
                -- Find the accounting period for the entry_date
                SELECT id, status INTO v_period_id, v_period_status
                FROM accounting_periods
                WHERE NEW.entry_date BETWEEN start_date AND end_date
                LIMIT 1;

                IF v_period_id IS NULL THEN
                    RAISE EXCEPTION 'No accounting period found for date %', NEW.entry_date;
                END IF;

                -- Auto-set the period_id
                NEW.accounting_period_id := v_period_id;

                -- If posting, verify period is open
                IF NEW.status = 'posted' AND v_period_status != 'open' THEN
                    RAISE EXCEPTION 'Cannot post to % accounting period', v_period_status;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        DB::unprepared("
            DROP TRIGGER IF EXISTS trg_auto_set_accounting_period ON journal_entries;
            
            CREATE TRIGGER trg_auto_set_accounting_period
            BEFORE INSERT OR UPDATE ON journal_entries
            FOR EACH ROW
            EXECUTE FUNCTION auto_set_accounting_period();
        ");
    }

    /**
     * Add MySQL/MariaDB triggers for immutability and period check on update
     */
    private function addMySQLTriggers(): void
    {
        try {
            // Trigger to block updates on posted journal entries
            try {
                DB::unprepared("DROP TRIGGER IF EXISTS trg_block_posted_journal_updates");
            } catch (\Exception $e) {
                // Ignore
            }

            try {
                DB::unprepared("
                    CREATE TRIGGER trg_block_posted_journal_updates
                    BEFORE UPDATE ON journal_entries
                    FOR EACH ROW
                    BEGIN
                        IF OLD.status = 'posted' THEN
                            SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Posted journal entries are immutable. Create a reversing entry instead.';
                        END IF;
                    END
                ");
            } catch (\Exception $e) {
                \Log::warning('Skipped trg_block_posted_journal_updates: ' . $e->getMessage());
            }

            // Trigger to block deletes on posted journal entries
            try {
                DB::unprepared("DROP TRIGGER IF EXISTS trg_block_posted_journal_deletes");
            } catch (\Exception $e) {
                // Ignore
            }

            try {
                DB::unprepared("
                    CREATE TRIGGER trg_block_posted_journal_deletes
                    BEFORE DELETE ON journal_entries
                    FOR EACH ROW
                    BEGIN
                        IF OLD.status = 'posted' THEN
                            SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Cannot delete posted journal entries. Create a reversing entry instead.';
                        END IF;
                    END
                ");
            } catch (\Exception $e) {
                \Log::warning('Skipped trg_block_posted_journal_deletes: ' . $e->getMessage());
            }

            // Trigger to block updates on posted journal entry details
            try {
                DB::unprepared("DROP TRIGGER IF EXISTS trg_block_posted_detail_updates");
            } catch (\Exception $e) {
                // Ignore
            }

            try {
                DB::unprepared("
                    CREATE TRIGGER trg_block_posted_detail_updates
                    BEFORE UPDATE ON journal_entry_details
                    FOR EACH ROW
                    BEGIN
                        DECLARE v_status VARCHAR(20);
                        
                        SELECT status INTO v_status
                        FROM journal_entries
                        WHERE id = OLD.journal_entry_id;
                        
                        IF v_status = 'posted' THEN
                            SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Lines of a posted journal are immutable. Create a reversing entry instead.';
                        END IF;
                    END
                ");
            } catch (\Exception $e) {
                \Log::warning('Skipped trg_block_posted_detail_updates: ' . $e->getMessage());
            }

            // Trigger to block deletes on posted journal entry details
            try {
                DB::unprepared("DROP TRIGGER IF EXISTS trg_block_posted_detail_deletes");
            } catch (\Exception $e) {
                // Ignore
            }

            try {
                DB::unprepared("
                    CREATE TRIGGER trg_block_posted_detail_deletes
                    BEFORE DELETE ON journal_entry_details
                    FOR EACH ROW
                    BEGIN
                        DECLARE v_status VARCHAR(20);
                        
                        SELECT status INTO v_status
                        FROM journal_entries
                        WHERE id = OLD.journal_entry_id;
                        
                        IF v_status = 'posted' THEN
                            SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Cannot delete lines of a posted journal. Create a reversing entry instead.';
                        END IF;
                    END
                ");
            } catch (\Exception $e) {
                \Log::warning('Skipped trg_block_posted_detail_deletes: ' . $e->getMessage());
            }

            // Enhanced period check on UPDATE when status changes to 'posted'
            try {
                DB::unprepared("DROP TRIGGER IF EXISTS trg_check_accounting_period_update");
            } catch (\Exception $e) {
                // Ignore
            }

            try {
                DB::unprepared("
                    CREATE TRIGGER trg_check_accounting_period_update
                    BEFORE UPDATE ON journal_entries
                    FOR EACH ROW
                    BEGIN
                        DECLARE v_period_status VARCHAR(20);
                        DECLARE v_error_msg VARCHAR(500);
                        
                        IF NEW.status = 'posted' AND OLD.status != 'posted' THEN
                            SELECT ap.status INTO v_period_status
                            FROM accounting_periods ap
                            WHERE NEW.entry_date BETWEEN ap.start_date AND ap.end_date
                            LIMIT 1;

                            IF v_period_status IS NULL THEN
                                SET v_error_msg = CONCAT('No accounting period found for date ', NEW.entry_date);
                                SIGNAL SQLSTATE '45000'
                                SET MESSAGE_TEXT = v_error_msg;
                            END IF;

                            IF v_period_status <> 'open' THEN
                                SET v_error_msg = CONCAT('Cannot post to ', v_period_status, ' accounting period');
                                SIGNAL SQLSTATE '45000'
                                SET MESSAGE_TEXT = v_error_msg;
                            END IF;
                        END IF;
                    END
                ");
            } catch (\Exception $e) {
                \Log::warning('Skipped trg_check_accounting_period_update: ' . $e->getMessage());
            }

            // Auto-set accounting_period_id based on entry_date
            try {
                DB::unprepared("DROP TRIGGER IF EXISTS trg_auto_set_accounting_period");
            } catch (\Exception $e) {
                // Ignore
            }

            try {
                DB::unprepared("
                    CREATE TRIGGER trg_auto_set_accounting_period
                    BEFORE INSERT ON journal_entries
                    FOR EACH ROW
                    BEGIN
                        DECLARE v_period_id BIGINT;
                        DECLARE v_period_status VARCHAR(20);
                        DECLARE v_error_msg VARCHAR(500);

                        -- Find the accounting period for the entry_date
                        SELECT id, status INTO v_period_id, v_period_status
                        FROM accounting_periods
                        WHERE NEW.entry_date BETWEEN start_date AND end_date
                        LIMIT 1;

                        IF v_period_id IS NULL THEN
                            SET v_error_msg = CONCAT('No accounting period found for date ', NEW.entry_date);
                            SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = v_error_msg;
                        END IF;

                        -- Auto-set the period_id
                        SET NEW.accounting_period_id = v_period_id;

                        -- If posting, verify period is open
                        IF NEW.status = 'posted' AND v_period_status <> 'open' THEN
                            SET v_error_msg = CONCAT('Cannot post to ', v_period_status, ' accounting period');
                            SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = v_error_msg;
                        END IF;
                    END
                ");
            } catch (\Exception $e) {
                \Log::warning('Skipped trg_auto_set_accounting_period: ' . $e->getMessage());
            }

            // Auto-set on UPDATE as well
            try {
                DB::unprepared("DROP TRIGGER IF EXISTS trg_auto_set_accounting_period_update");
            } catch (\Exception $e) {
                // Ignore
            }

            try {
                DB::unprepared("
                    CREATE TRIGGER trg_auto_set_accounting_period_update
                    BEFORE UPDATE ON journal_entries
                    FOR EACH ROW
                    BEGIN
                        DECLARE v_period_id BIGINT;
                        DECLARE v_period_status VARCHAR(20);
                        DECLARE v_error_msg VARCHAR(500);

                        -- Find the accounting period for the entry_date
                        SELECT id, status INTO v_period_id, v_period_status
                        FROM accounting_periods
                        WHERE NEW.entry_date BETWEEN start_date AND end_date
                        LIMIT 1;

                        IF v_period_id IS NULL THEN
                            SET v_error_msg = CONCAT('No accounting period found for date ', NEW.entry_date);
                            SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = v_error_msg;
                        END IF;

                        -- Auto-set the period_id
                        SET NEW.accounting_period_id = v_period_id;

                        -- If posting, verify period is open
                        IF NEW.status = 'posted' AND v_period_status <> 'open' THEN
                            SET v_error_msg = CONCAT('Cannot post to ', v_period_status, ' accounting period');
                            SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = v_error_msg;
                        END IF;
                    END
                ");
            } catch (\Exception $e) {
                \Log::warning('Skipped trg_auto_set_accounting_period_update: ' . $e->getMessage());
            }

        } catch (\Exception $e) {
            \Log::warning('Some MySQL triggers could not be created: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        // Drop constraints (skip on SQLite)
        if ($driver !== 'sqlite') {
            DB::statement("ALTER TABLE journal_entry_details DROP CONSTRAINT IF EXISTS chk_debit_xor_credit");
            DB::statement("ALTER TABLE journal_entries DROP CONSTRAINT IF EXISTS chk_fx_rate_positive");
            DB::statement("ALTER TABLE account_types DROP CONSTRAINT IF EXISTS chk_report_group");

            // Restore old XOR constraint (allowing both zero)
            DB::statement("
                ALTER TABLE journal_entry_details
                ADD CONSTRAINT chk_debit_xor_credit
                CHECK (
                    (debit > 0 AND credit = 0) OR
                    (credit > 0 AND debit = 0) OR
                    (debit = 0 AND credit = 0)
                )
            ");
        }

        Schema::table('account_types', function (Blueprint $table) {
            $table->dropUnique(['type_name']);
        });

        // Drop triggers
        if ($driver === 'pgsql') {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_block_posted_journal_updates ON journal_entries");
            DB::unprepared("DROP TRIGGER IF EXISTS trg_block_posted_journal_deletes ON journal_entries");
            DB::unprepared("DROP TRIGGER IF EXISTS trg_block_posted_detail_updates ON journal_entry_details");
            DB::unprepared("DROP TRIGGER IF EXISTS trg_block_posted_detail_deletes ON journal_entry_details");
            DB::unprepared("DROP TRIGGER IF EXISTS trg_check_accounting_period_update ON journal_entries");
            DB::unprepared("DROP TRIGGER IF EXISTS trg_auto_set_accounting_period ON journal_entries");
            DB::unprepared("DROP FUNCTION IF EXISTS block_posted_journal_changes()");
            DB::unprepared("DROP FUNCTION IF EXISTS block_posted_journal_deletes()");
            DB::unprepared("DROP FUNCTION IF EXISTS block_posted_detail_changes()");
            DB::unprepared("DROP FUNCTION IF EXISTS auto_set_accounting_period()");
        } else {
            try {
                DB::unprepared("DROP TRIGGER IF EXISTS trg_block_posted_journal_updates");
                DB::unprepared("DROP TRIGGER IF EXISTS trg_block_posted_journal_deletes");
                DB::unprepared("DROP TRIGGER IF EXISTS trg_block_posted_detail_updates");
                DB::unprepared("DROP TRIGGER IF EXISTS trg_block_posted_detail_deletes");
                DB::unprepared("DROP TRIGGER IF EXISTS trg_check_accounting_period_update");
                DB::unprepared("DROP TRIGGER IF EXISTS trg_auto_set_accounting_period");
                DB::unprepared("DROP TRIGGER IF EXISTS trg_auto_set_accounting_period_update");
            } catch (\Exception $e) {
                // Ignore
            }
        }
    }
};
