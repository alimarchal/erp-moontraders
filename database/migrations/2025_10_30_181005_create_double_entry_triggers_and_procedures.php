<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            $this->createPostgreSQLTriggers();
        } elseif (in_array($driver, ['mysql', 'mariadb'])) {
            $this->createMySQLTriggers();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            $this->dropPostgreSQLTriggers();
        } elseif (in_array($driver, ['mysql', 'mariadb'])) {
            $this->dropMySQLTriggers();
        }
    }

    /**
     * Create PostgreSQL triggers and functions
     */
    private function createPostgreSQLTriggers(): void
    {
        // Function to check journal balance
        DB::unprepared("
            CREATE OR REPLACE FUNCTION check_journal_balance()
            RETURNS TRIGGER AS \$\$
            DECLARE
                v_debits DECIMAL(15,2);
                v_credits DECIMAL(15,2);
                v_journal_id BIGINT;
            BEGIN
                -- Get the journal_entry_id from the operation
                IF TG_OP = 'DELETE' THEN
                    v_journal_id := OLD.journal_entry_id;
                ELSE
                    v_journal_id := NEW.journal_entry_id;
                END IF;

                -- Calculate total debits and credits
                SELECT 
                    COALESCE(SUM(debit), 0), 
                    COALESCE(SUM(credit), 0)
                INTO v_debits, v_credits
                FROM journal_entry_details
                WHERE journal_entry_id = v_journal_id;

                -- Check if balanced
                IF v_debits <> v_credits THEN
                    RAISE EXCEPTION 'Journal entry % is unbalanced: Debits=%, Credits=%', 
                        v_journal_id, v_debits, v_credits;
                END IF;

                IF TG_OP = 'DELETE' THEN
                    RETURN OLD;
                END IF;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        // Constraint trigger to enforce balance (deferrable to end of transaction)
        DB::unprepared("
            DROP TRIGGER IF EXISTS trg_journal_balance_insert ON journal_entry_details;
            DROP TRIGGER IF EXISTS trg_journal_balance_update ON journal_entry_details;
            DROP TRIGGER IF EXISTS trg_journal_balance_delete ON journal_entry_details;
            DROP TRIGGER IF EXISTS trg_journal_balance ON journal_entry_details;
            CREATE CONSTRAINT TRIGGER trg_journal_balance
            AFTER INSERT OR UPDATE OR DELETE ON journal_entry_details
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW
            EXECUTE FUNCTION check_journal_balance();
        ");

        // Function to check leaf account only
        DB::unprepared("
            CREATE OR REPLACE FUNCTION check_leaf_account_only()
            RETURNS TRIGGER AS \$\$
            DECLARE
                v_is_group BOOLEAN;
            BEGIN
                SELECT is_group INTO v_is_group
                FROM chart_of_accounts
                WHERE id = NEW.chart_of_account_id;

                IF v_is_group THEN
                    RAISE EXCEPTION 'Cannot post to group account %', NEW.chart_of_account_id;
                END IF;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::unprepared("
            DROP TRIGGER IF EXISTS trg_leaf_account_only ON journal_entry_details;
            CREATE TRIGGER trg_leaf_account_only
            BEFORE INSERT OR UPDATE ON journal_entry_details
            FOR EACH ROW
            EXECUTE FUNCTION check_leaf_account_only();
        ");

        // Function to check accounting period
        DB::unprepared("
            CREATE OR REPLACE FUNCTION check_accounting_period()
            RETURNS TRIGGER AS \$\$
            DECLARE
                v_period_status VARCHAR(20);
            BEGIN
                IF NEW.status = 'posted' THEN
                    SELECT ap.status INTO v_period_status
                    FROM accounting_periods ap
                    WHERE NEW.entry_date BETWEEN ap.start_date AND ap.end_date
                    LIMIT 1;

                    IF v_period_status IS NULL THEN
                        RAISE EXCEPTION 'No accounting period found for date %', NEW.entry_date;
                    END IF;

                    IF v_period_status <> 'open' THEN
                        RAISE EXCEPTION 'Cannot post to % accounting period', v_period_status;
                    END IF;
                END IF;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::unprepared("
            DROP TRIGGER IF EXISTS trg_check_accounting_period ON journal_entries;
            CREATE TRIGGER trg_check_accounting_period
            BEFORE INSERT OR UPDATE ON journal_entries
            FOR EACH ROW
            EXECUTE FUNCTION check_accounting_period();
        ");

        // Function to enforce single base currency
        DB::unprepared("
            CREATE OR REPLACE FUNCTION check_single_base_currency()
            RETURNS TRIGGER AS \$\$
            DECLARE
                v_count INTEGER;
            BEGIN
                IF NEW.is_base_currency = TRUE THEN
                    SELECT COUNT(*) INTO v_count
                    FROM currencies
                    WHERE is_base_currency = TRUE AND id <> NEW.id;

                    IF v_count > 0 THEN
                        RAISE EXCEPTION 'Only one base currency is allowed';
                    END IF;
                END IF;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::unprepared("
            DROP TRIGGER IF EXISTS trg_single_base_currency ON currencies;
            CREATE TRIGGER trg_single_base_currency
            BEFORE INSERT OR UPDATE ON currencies
            FOR EACH ROW
            EXECUTE FUNCTION check_single_base_currency();
        ");
    }

    /**
     * Create MySQL/MariaDB triggers and procedures
     */
    private function createMySQLTriggers(): void
    {
        // Note: If MariaDB has system table issues (mysql.proc schema mismatch),
        // triggers and procedures will be skipped. Run mysql_upgrade to fix.

        // Stored procedure to check journal balance
        try {
            DB::unprepared("DROP PROCEDURE IF EXISTS sp_check_journal_balance");
        } catch (\Exception $e) {
            // Ignore errors on drop - may not exist or system table issue
        }

        try {
            DB::unprepared("
                CREATE PROCEDURE sp_check_journal_balance(IN p_journal_id BIGINT)
                BEGIN
                    DECLARE v_debits DECIMAL(15,2);
                    DECLARE v_credits DECIMAL(15,2);
                    DECLARE v_error_msg VARCHAR(500);

                    SELECT COALESCE(SUM(debit), 0), COALESCE(SUM(credit), 0)
                    INTO v_debits, v_credits
                    FROM journal_entry_details
                    WHERE journal_entry_id = p_journal_id;

                    IF v_debits <> v_credits THEN
                        SET v_error_msg = CONCAT('Journal entry ', p_journal_id, ' is unbalanced: Debits=', v_debits, ', Credits=', v_credits);
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = v_error_msg;
                    END IF;
                END
            ");
        } catch (\Exception $e) {
            // Skip procedure creation if system table issues exist
            \Log::warning('Skipped sp_check_journal_balance procedure creation: ' . $e->getMessage());
            return; // Skip all MySQL triggers if procedure creation fails
        }

        // Triggers for balance check
        try {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_journal_balance_insert");
        } catch (\Exception $e) {
            // Ignore errors on drop
        }
        DB::unprepared("
            CREATE TRIGGER trg_journal_balance_insert
            AFTER INSERT ON journal_entry_details
            FOR EACH ROW
            BEGIN
                DECLARE v_status VARCHAR(20);

                SELECT status INTO v_status
                FROM journal_entries
                WHERE id = NEW.journal_entry_id;

                IF v_status = 'posted' THEN
                    CALL sp_check_journal_balance(NEW.journal_entry_id);
                END IF;
            END
        ");

        try {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_journal_balance_update");
        } catch (\Exception $e) {
            // Ignore errors on drop
        }
        DB::unprepared("
            CREATE TRIGGER trg_journal_balance_update
            AFTER UPDATE ON journal_entry_details
            FOR EACH ROW
            BEGIN
                DECLARE v_status VARCHAR(20);

                SELECT status INTO v_status
                FROM journal_entries
                WHERE id = NEW.journal_entry_id;

                IF v_status = 'posted' THEN
                    CALL sp_check_journal_balance(NEW.journal_entry_id);
                END IF;
            END
        ");

        try {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_journal_balance_delete");
        } catch (\Exception $e) {
            // Ignore errors on drop
        }
        DB::unprepared("
            CREATE TRIGGER trg_journal_balance_delete
            AFTER DELETE ON journal_entry_details
            FOR EACH ROW
            BEGIN
                DECLARE v_status VARCHAR(20);

                SELECT status INTO v_status
                FROM journal_entries
                WHERE id = OLD.journal_entry_id;

                IF v_status = 'posted' THEN
                    CALL sp_check_journal_balance(OLD.journal_entry_id);
                END IF;
            END
        ");

        try {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_journal_balance_before_post");
        } catch (\Exception $e) {
            // Ignore errors on drop
        }
        DB::unprepared("
            CREATE TRIGGER trg_journal_balance_before_post
            BEFORE UPDATE ON journal_entries
            FOR EACH ROW
            BEGIN
                IF NEW.status = 'posted' AND OLD.status <> 'posted' THEN
                    CALL sp_check_journal_balance(NEW.id);
                END IF;
            END
        ");

        // Trigger for leaf account check
        try {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_leaf_account_only");
        } catch (\Exception $e) {
            // Ignore errors on drop
        }
        DB::unprepared("
            CREATE TRIGGER trg_leaf_account_only
            BEFORE INSERT ON journal_entry_details
            FOR EACH ROW
            BEGIN
                DECLARE v_is_group BOOLEAN;
                DECLARE v_error_msg VARCHAR(500);
                
                SELECT is_group INTO v_is_group
                FROM chart_of_accounts
                WHERE id = NEW.chart_of_account_id;

                IF v_is_group THEN
                    SET v_error_msg = CONCAT('Cannot post to group account ', NEW.chart_of_account_id);
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = v_error_msg;
                END IF;
            END
        ");

        // Trigger for accounting period check
        try {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_check_accounting_period");
        } catch (\Exception $e) {
            // Ignore errors on drop
        }
        DB::unprepared("
            CREATE TRIGGER trg_check_accounting_period
            BEFORE INSERT ON journal_entries
            FOR EACH ROW
            BEGIN
                DECLARE v_period_status VARCHAR(20);
                DECLARE v_error_msg VARCHAR(500);
                
                IF NEW.status = 'posted' THEN
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

        // Trigger for single base currency
        try {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_single_base_currency");
        } catch (\Exception $e) {
            // Ignore errors on drop
        }
        DB::unprepared("
            CREATE TRIGGER trg_single_base_currency
            BEFORE INSERT ON currencies
            FOR EACH ROW
            BEGIN
                DECLARE v_count INT;
                
                IF NEW.is_base_currency = TRUE THEN
                    SELECT COUNT(*) INTO v_count
                    FROM currencies
                    WHERE is_base_currency = TRUE;

                    IF v_count > 0 THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Only one base currency is allowed';
                    END IF;
                END IF;
            END
        ");

        try {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_single_base_currency_update");
        } catch (\Exception $e) {
            // Ignore errors on drop
        }
        DB::unprepared("
            CREATE TRIGGER trg_single_base_currency_update
            BEFORE UPDATE ON currencies
            FOR EACH ROW
            BEGIN
                DECLARE v_count INT;
                
                IF NEW.is_base_currency = TRUE AND OLD.is_base_currency = FALSE THEN
                    SELECT COUNT(*) INTO v_count
                    FROM currencies
                    WHERE is_base_currency = TRUE AND id <> NEW.id;

                    IF v_count > 0 THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Only one base currency is allowed';
                    END IF;
                END IF;
            END
        ");
    }

    /**
     * Drop PostgreSQL triggers and functions
     */
    private function dropPostgreSQLTriggers(): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS trg_journal_balance_insert ON journal_entry_details");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_journal_balance_update ON journal_entry_details");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_journal_balance_delete ON journal_entry_details");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_journal_balance ON journal_entry_details");
        DB::unprepared("DROP FUNCTION IF EXISTS check_journal_balance()");

        DB::unprepared("DROP TRIGGER IF EXISTS trg_leaf_account_only ON journal_entry_details");
        DB::unprepared("DROP FUNCTION IF EXISTS check_leaf_account_only()");

        DB::unprepared("DROP TRIGGER IF EXISTS trg_check_accounting_period ON journal_entries");
        DB::unprepared("DROP FUNCTION IF EXISTS check_accounting_period()");

        DB::unprepared("DROP TRIGGER IF EXISTS trg_single_base_currency ON currencies");
        DB::unprepared("DROP FUNCTION IF EXISTS check_single_base_currency()");
    }

    /**
     * Drop MySQL/MariaDB triggers and procedures
     */
    private function dropMySQLTriggers(): void
    {
        try {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_journal_balance_insert");
        } catch (\Exception $e) {
            // Ignore errors
        }
        try {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_journal_balance_update");
        } catch (\Exception $e) {
            // Ignore errors
        }
        try {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_journal_balance_delete");
        } catch (\Exception $e) {
            // Ignore errors
        }
        try {
            DB::unprepared("DROP PROCEDURE IF EXISTS sp_check_journal_balance");
        } catch (\Exception $e) {
            // Ignore errors
        }

        try {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_leaf_account_only");
        } catch (\Exception $e) {
            // Ignore errors
        }
        try {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_check_accounting_period");
        } catch (\Exception $e) {
            // Ignore errors
        }
        try {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_single_base_currency");
        } catch (\Exception $e) {
            // Ignore errors
        }
        try {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_single_base_currency_update");
        } catch (\Exception $e) {
            // Ignore errors
        }
        try {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_journal_balance_before_post");
        } catch (\Exception $e) {
            // Ignore errors
        }
    }
};
