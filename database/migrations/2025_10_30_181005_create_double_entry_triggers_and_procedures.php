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
            $this->createPostgreSQLAccountingFunctions();
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
            $this->dropPostgreSQLAccountingFunctions();
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
                v_status VARCHAR(20);
            BEGIN
                -- Get the journal_entry_id from the operation
                IF TG_OP = 'DELETE' THEN
                    v_journal_id := OLD.journal_entry_id;
                ELSE
                    v_journal_id := NEW.journal_entry_id;
                END IF;

                -- Only check balance for posted journals or when posting
                SELECT status INTO v_status
                FROM journal_entries
                WHERE id = v_journal_id;

                -- Skip balance check for draft entries (allow temporary unbalance)
                IF v_status != 'posted' THEN
                    IF TG_OP = 'DELETE' THEN
                        RETURN OLD;
                    END IF;
                    RETURN NEW;
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

        // OPTIMIZED: Single constraint trigger instead of 3 separate triggers
        // Deferrable to end of transaction so temporary unbalance is allowed
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

    private function createPostgreSQLAccountingFunctions(): void
    {
        // Trial balance details
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_trial_balance(p_as_of_date DATE DEFAULT CURRENT_DATE)
            RETURNS TABLE (
                account_id BIGINT,
                account_code VARCHAR,
                account_name VARCHAR,
                account_type VARCHAR,
                normal_balance VARCHAR,
                total_debits NUMERIC,
                total_credits NUMERIC,
                balance NUMERIC
            ) AS $$
            BEGIN
                RETURN QUERY
                SELECT
                    a.id AS account_id,
                    a.account_code,
                    a.account_name,
                    at.type_name AS account_type,
                    a.normal_balance,
                    COALESCE(SUM(d.debit), 0) AS total_debits,
                    COALESCE(SUM(d.credit), 0) AS total_credits,
                    COALESCE(SUM(d.debit - d.credit), 0) AS balance
                FROM chart_of_accounts a
                JOIN account_types at ON at.id = a.account_type_id
                LEFT JOIN (
                    SELECT jed.chart_of_account_id, jed.debit, jed.credit
                    FROM journal_entry_details jed
                    JOIN journal_entries je ON je.id = jed.journal_entry_id
                    WHERE je.status = 'posted'
                    AND je.entry_date <= p_as_of_date
                ) d ON d.chart_of_account_id = a.id
                WHERE a.is_active = true
                GROUP BY a.id, a.account_code, a.account_name, at.type_name, a.normal_balance
                HAVING COALESCE(SUM(d.debit), 0) <> 0 OR COALESCE(SUM(d.credit), 0) <> 0
                ORDER BY a.account_code;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        // Trial balance summary
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_trial_balance_summary(p_as_of_date DATE DEFAULT CURRENT_DATE)
            RETURNS TABLE (
                total_debits NUMERIC,
                total_credits NUMERIC,
                difference NUMERIC
            ) AS $$
            BEGIN
                RETURN QUERY
                SELECT
                    COALESCE(SUM(debit), 0) AS total_debits,
                    COALESCE(SUM(credit), 0) AS total_credits,
                    COALESCE(SUM(debit) - SUM(credit), 0) AS difference
                FROM journal_entry_details jed
                JOIN journal_entries je ON je.id = jed.journal_entry_id
                WHERE je.status = 'posted'
                AND je.entry_date <= p_as_of_date;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        // Account balances
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_account_balances(
                p_start_date DATE DEFAULT NULL,
                p_end_date DATE DEFAULT CURRENT_DATE
            )
            RETURNS TABLE (
                account_id BIGINT,
                account_code VARCHAR,
                account_name VARCHAR,
                account_type VARCHAR,
                normal_balance VARCHAR,
                total_debits NUMERIC,
                total_credits NUMERIC,
                balance NUMERIC,
                is_group BOOLEAN,
                is_active BOOLEAN
            ) AS $$
            BEGIN
                RETURN QUERY
                SELECT
                    a.id AS account_id,
                    a.account_code,
                    a.account_name,
                    at.type_name AS account_type,
                    a.normal_balance,
                    COALESCE(SUM(d.debit), 0) AS total_debits,
                    COALESCE(SUM(d.credit), 0) AS total_credits,
                    COALESCE(SUM(d.debit - d.credit), 0) AS balance,
                    a.is_group,
                    a.is_active
                FROM chart_of_accounts a
                JOIN account_types at ON at.id = a.account_type_id
                LEFT JOIN (
                    SELECT jed.chart_of_account_id, jed.debit, jed.credit
                    FROM journal_entry_details jed
                    JOIN journal_entries je ON je.id = jed.journal_entry_id
                    WHERE je.status = 'posted'
                    AND (p_start_date IS NULL OR je.entry_date >= p_start_date)
                    AND je.entry_date <= p_end_date
                ) d ON d.chart_of_account_id = a.id
                GROUP BY a.id, a.account_code, a.account_name, at.type_name, a.normal_balance, a.is_group, a.is_active
                ORDER BY a.account_code;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        // General ledger
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_general_ledger(
                p_start_date DATE DEFAULT NULL,
                p_end_date DATE DEFAULT CURRENT_DATE,
                p_account_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                journal_entry_id BIGINT,
                entry_date DATE,
                reference VARCHAR,
                journal_description TEXT,
                status VARCHAR,
                account_id BIGINT,
                account_code VARCHAR,
                account_name VARCHAR,
                line_no INTEGER,
                debit NUMERIC,
                credit NUMERIC,
                line_description TEXT,
                cost_center_code VARCHAR,
                cost_center_name VARCHAR,
                currency_code VARCHAR,
                fx_rate_to_base NUMERIC
            ) AS $$
            BEGIN
                RETURN QUERY
                SELECT
                    je.id AS journal_entry_id,
                    je.entry_date,
                    je.reference,
                    je.description AS journal_description,
                    je.status,
                    a.id AS account_id,
                    a.account_code,
                    a.account_name,
                    jed.line_no,
                    jed.debit,
                    jed.credit,
                    jed.description AS line_description,
                    cc.code AS cost_center_code,
                    cc.name AS cost_center_name,
                    c.currency_code,
                    je.fx_rate_to_base
                FROM journal_entry_details jed
                JOIN journal_entries je ON je.id = jed.journal_entry_id
                JOIN chart_of_accounts a ON a.id = jed.chart_of_account_id
                LEFT JOIN cost_centers cc ON cc.id = jed.cost_center_id
                LEFT JOIN currencies c ON c.id = je.currency_id
                WHERE je.status = 'posted'
                AND (p_start_date IS NULL OR je.entry_date >= p_start_date)
                AND je.entry_date <= p_end_date
                AND (p_account_id IS NULL OR a.id = p_account_id)
                ORDER BY je.entry_date, je.id, jed.line_no;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        // Balance sheet
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_balance_sheet(p_as_of_date DATE DEFAULT CURRENT_DATE)
            RETURNS TABLE (
                account_id BIGINT,
                account_code VARCHAR,
                account_name VARCHAR,
                account_type VARCHAR,
                report_group VARCHAR,
                normal_balance VARCHAR,
                balance NUMERIC
            ) AS $$
            BEGIN
                RETURN QUERY
                SELECT
                    a.id AS account_id,
                    a.account_code,
                    a.account_name,
                    at.type_name AS account_type,
                    at.report_group,
                    a.normal_balance,
                    CASE
                        WHEN a.normal_balance = 'debit'
                        THEN COALESCE(SUM(d.debit - d.credit), 0)
                        WHEN a.normal_balance = 'credit'
                        THEN COALESCE(SUM(d.credit - d.debit), 0)
                        ELSE 0
                    END AS balance
                FROM chart_of_accounts a
                JOIN account_types at ON at.id = a.account_type_id
                LEFT JOIN (
                    SELECT jed.chart_of_account_id, jed.debit, jed.credit
                    FROM journal_entry_details jed
                    JOIN journal_entries je ON je.id = jed.journal_entry_id
                    WHERE je.status = 'posted'
                    AND je.entry_date <= p_as_of_date
                ) d ON d.chart_of_account_id = a.id
                WHERE at.report_group = 'BalanceSheet'
                AND a.is_active = true
                GROUP BY a.id, a.account_code, a.account_name, at.type_name, at.report_group, a.normal_balance
                HAVING COALESCE(SUM(d.debit), 0) <> 0 OR COALESCE(SUM(d.credit), 0) <> 0
                ORDER BY a.account_code;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        // Income statement
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_income_statement(
                p_start_date DATE DEFAULT NULL,
                p_end_date DATE DEFAULT CURRENT_DATE
            )
            RETURNS TABLE (
                account_id BIGINT,
                account_code VARCHAR,
                account_name VARCHAR,
                account_type VARCHAR,
                report_group VARCHAR,
                normal_balance VARCHAR,
                balance NUMERIC
            ) AS $$
            BEGIN
                RETURN QUERY
                SELECT
                    a.id AS account_id,
                    a.account_code,
                    a.account_name,
                    at.type_name AS account_type,
                    at.report_group,
                    a.normal_balance,
                    CASE
                        WHEN a.normal_balance = 'debit'
                        THEN COALESCE(SUM(d.debit - d.credit), 0)
                        WHEN a.normal_balance = 'credit'
                        THEN COALESCE(SUM(d.credit - d.debit), 0)
                        ELSE 0
                    END AS balance
                FROM chart_of_accounts a
                JOIN account_types at ON at.id = a.account_type_id
                LEFT JOIN (
                    SELECT jed.chart_of_account_id, jed.debit, jed.credit
                    FROM journal_entry_details jed
                    JOIN journal_entries je ON je.id = jed.journal_entry_id
                    WHERE je.status = 'posted'
                    AND (p_start_date IS NULL OR je.entry_date >= p_start_date)
                    AND je.entry_date <= p_end_date
                ) d ON d.chart_of_account_id = a.id
                WHERE at.report_group = 'IncomeStatement'
                AND a.is_active = true
                GROUP BY a.id, a.account_code, a.account_name, at.type_name, at.report_group, a.normal_balance
                HAVING COALESCE(SUM(d.debit), 0) <> 0 OR COALESCE(SUM(d.credit), 0) <> 0
                ORDER BY a.account_code;
            END;
            $$ LANGUAGE plpgsql;
        SQL);
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

        // OPTIMIZED: Only check balance when transitioning to 'posted' status
        // Draft entries can be temporarily unbalanced
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
            DB::unprepared("DROP TRIGGER IF EXISTS trg_leaf_account_only_insert");
        } catch (\Exception $e) {
            // Ignore errors on drop
        }
        DB::unprepared("
            CREATE TRIGGER trg_leaf_account_only_insert
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

        try {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_leaf_account_only_update");
        } catch (\Exception $e) {
            // Ignore errors on drop
        }
        DB::unprepared("
            CREATE TRIGGER trg_leaf_account_only_update
            BEFORE UPDATE ON journal_entry_details
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
            DB::unprepared("DROP TRIGGER IF EXISTS trg_check_accounting_period_insert");
        } catch (\Exception $e) {
            // Ignore errors on drop
        }
        DB::unprepared("
            CREATE TRIGGER trg_check_accounting_period_insert
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

        try {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_check_accounting_period_update");
        } catch (\Exception $e) {
            // Ignore errors on drop
        }
        DB::unprepared("
            CREATE TRIGGER trg_check_accounting_period_update
            BEFORE UPDATE ON journal_entries
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
        DB::unprepared("DROP TRIGGER IF EXISTS trg_journal_balance_insert ON journal_entry_details CASCADE");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_journal_balance_update ON journal_entry_details CASCADE");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_journal_balance_delete ON journal_entry_details CASCADE");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_journal_balance ON journal_entry_details CASCADE");
        DB::unprepared("DROP FUNCTION IF EXISTS check_journal_balance() CASCADE");

        DB::unprepared("DROP TRIGGER IF EXISTS trg_leaf_account_only ON journal_entry_details CASCADE");
        DB::unprepared("DROP FUNCTION IF EXISTS check_leaf_account_only() CASCADE");

        DB::unprepared("DROP TRIGGER IF EXISTS trg_check_accounting_period ON journal_entries CASCADE");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_check_accounting_period_update ON journal_entries CASCADE");
        DB::unprepared("DROP FUNCTION IF EXISTS check_accounting_period() CASCADE");

        DB::unprepared("DROP TRIGGER IF EXISTS trg_single_base_currency ON currencies CASCADE");
        DB::unprepared("DROP FUNCTION IF EXISTS check_single_base_currency() CASCADE");
    }

    private function dropPostgreSQLAccountingFunctions(): void
    {
        DB::unprepared("DROP FUNCTION IF EXISTS fn_income_statement(DATE, DATE) CASCADE");
        DB::unprepared("DROP FUNCTION IF EXISTS fn_balance_sheet(DATE) CASCADE");
        DB::unprepared("DROP FUNCTION IF EXISTS fn_general_ledger(DATE, DATE, BIGINT) CASCADE");
        DB::unprepared("DROP FUNCTION IF EXISTS fn_account_balances(DATE, DATE) CASCADE");
        DB::unprepared("DROP FUNCTION IF EXISTS fn_trial_balance_summary(DATE) CASCADE");
        DB::unprepared("DROP FUNCTION IF EXISTS fn_trial_balance(DATE) CASCADE");
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
