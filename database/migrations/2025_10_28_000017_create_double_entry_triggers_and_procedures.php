<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Consolidated triggers, procedures, and constraints for double-entry accounting:
     * - Balance checks on journal entries
     * - Leaf account validation
     * - Accounting period checks
     * - Single base currency enforcement
     * - Immutability of posted entries
     * - Auto-set accounting period
     * - Audit trail triggers
     * - Soft delete protection
     * - Snapshot helper functions
     * - CHECK constraints (debit XOR credit, fx rate, report group)
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // 1. CHECK constraints (skip SQLite)
        $this->addCheckConstraints($driver);

        // 2. Core triggers and procedures
        if ($driver === 'pgsql') {
            $this->createPostgreSQLTriggers();
            $this->createPostgreSQLAccountingFunctions();
            $this->createPostgreSQLImmutabilityTriggers();
            $this->createPostgreSQLAuditTriggers();
            $this->createPostgreSQLSoftDeleteProtection();
            $this->createPostgreSQLSnapshotHelpers();
        } elseif (in_array($driver, ['mysql', 'mariadb'])) {
            $this->createMySQLTriggers();
            $this->createMySQLAuditTriggers();
            $this->createMySQLSoftDeleteProtection();
            $this->createMySQLSnapshotHelpers();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            $this->dropPostgreSQLAll();
        } elseif (in_array($driver, ['mysql', 'mariadb'])) {
            $this->dropMySQLAll();
        }

        $this->dropCheckConstraints($driver);
    }

    // ──────────────────────────────────────────
    // CHECK CONSTRAINTS
    // ──────────────────────────────────────────

    private function addCheckConstraints(string $driver): void
    {
        if ($driver === 'sqlite') {
            return;
        }

        // Debit XOR Credit - one side MUST be > 0
        try {
            DB::statement('
                ALTER TABLE journal_entry_details
                ADD CONSTRAINT chk_debit_xor_credit
                CHECK (
                    (debit > 0 AND credit = 0) OR
                    (credit > 0 AND debit = 0)
                )
            ');
        } catch (\Exception $e) {
            \Log::warning('Could not add chk_debit_xor_credit: '.$e->getMessage());
        }

        // Positive exchange rate
        try {
            DB::statement('
                ALTER TABLE journal_entries
                ADD CONSTRAINT chk_fx_rate_positive
                CHECK (fx_rate_to_base > 0)
            ');
        } catch (\Exception $e) {
            \Log::warning('Could not add chk_fx_rate_positive: '.$e->getMessage());
        }

        // Report group constraint
        try {
            DB::statement("
                ALTER TABLE account_types
                ADD CONSTRAINT chk_report_group
                CHECK (report_group IN ('BalanceSheet', 'IncomeStatement'))
            ");
        } catch (\Exception $e) {
            \Log::warning('Could not add chk_report_group: '.$e->getMessage());
        }
    }

    private function dropCheckConstraints(string $driver): void
    {
        if ($driver === 'sqlite') {
            return;
        }

        try {
            DB::statement('ALTER TABLE journal_entry_details DROP CONSTRAINT IF EXISTS chk_debit_xor_credit');
            DB::statement('ALTER TABLE journal_entries DROP CONSTRAINT IF EXISTS chk_fx_rate_positive');
            DB::statement('ALTER TABLE account_types DROP CONSTRAINT IF EXISTS chk_report_group');
        } catch (\Exception $e) {
            \Log::warning('Could not drop CHECK constraints: '.$e->getMessage());
        }
    }

    // ──────────────────────────────────────────
    // POSTGRESQL TRIGGERS
    // ──────────────────────────────────────────

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
                IF TG_OP = 'DELETE' THEN
                    v_journal_id := OLD.journal_entry_id;
                ELSE
                    v_journal_id := NEW.journal_entry_id;
                END IF;

                SELECT status INTO v_status
                FROM journal_entries
                WHERE id = v_journal_id;

                IF v_status != 'posted' THEN
                    IF TG_OP = 'DELETE' THEN
                        RETURN OLD;
                    END IF;
                    RETURN NEW;
                END IF;

                SELECT
                    COALESCE(SUM(debit), 0),
                    COALESCE(SUM(credit), 0)
                INTO v_debits, v_credits
                FROM journal_entry_details
                WHERE journal_entry_id = v_journal_id;

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

        DB::unprepared('
            DROP TRIGGER IF EXISTS trg_journal_balance ON journal_entry_details;
            CREATE CONSTRAINT TRIGGER trg_journal_balance
            AFTER INSERT OR UPDATE OR DELETE ON journal_entry_details
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW
            EXECUTE FUNCTION check_journal_balance();
        ');

        // Leaf account check
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

        DB::unprepared('
            DROP TRIGGER IF EXISTS trg_leaf_account_only ON journal_entry_details;
            CREATE TRIGGER trg_leaf_account_only
            BEFORE INSERT OR UPDATE ON journal_entry_details
            FOR EACH ROW
            EXECUTE FUNCTION check_leaf_account_only();
        ');

        // Accounting period check
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

        DB::unprepared('
            DROP TRIGGER IF EXISTS trg_check_accounting_period ON journal_entries;
            CREATE TRIGGER trg_check_accounting_period
            BEFORE INSERT OR UPDATE ON journal_entries
            FOR EACH ROW
            EXECUTE FUNCTION check_accounting_period();
        ');

        // Single base currency enforcement
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

        DB::unprepared('
            DROP TRIGGER IF EXISTS trg_single_base_currency ON currencies;
            CREATE TRIGGER trg_single_base_currency
            BEFORE INSERT OR UPDATE ON currencies
            FOR EACH ROW
            EXECUTE FUNCTION check_single_base_currency();
        ');
    }

    private function createPostgreSQLAccountingFunctions(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_trial_balance(p_as_of_date DATE DEFAULT CURRENT_DATE)
            RETURNS TABLE (
                account_id BIGINT, account_code VARCHAR, account_name VARCHAR,
                account_type VARCHAR, normal_balance VARCHAR,
                total_debits NUMERIC, total_credits NUMERIC, balance NUMERIC
            ) AS $$
            BEGIN
                RETURN QUERY
                SELECT a.id, a.account_code, a.account_name, at.type_name, a.normal_balance,
                    COALESCE(SUM(d.debit), 0), COALESCE(SUM(d.credit), 0),
                    COALESCE(SUM(d.debit - d.credit), 0)
                FROM chart_of_accounts a
                JOIN account_types at ON at.id = a.account_type_id
                LEFT JOIN (
                    SELECT jed.chart_of_account_id, jed.debit, jed.credit
                    FROM journal_entry_details jed
                    JOIN journal_entries je ON je.id = jed.journal_entry_id
                    WHERE je.status = 'posted' AND je.entry_date <= p_as_of_date
                ) d ON d.chart_of_account_id = a.id
                WHERE a.is_active = true
                GROUP BY a.id, a.account_code, a.account_name, at.type_name, a.normal_balance
                HAVING COALESCE(SUM(d.debit), 0) <> 0 OR COALESCE(SUM(d.credit), 0) <> 0
                ORDER BY a.account_code;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_trial_balance_summary(p_as_of_date DATE DEFAULT CURRENT_DATE)
            RETURNS TABLE (total_debits NUMERIC, total_credits NUMERIC, difference NUMERIC) AS $$
            BEGIN
                RETURN QUERY
                SELECT COALESCE(SUM(debit), 0), COALESCE(SUM(credit), 0),
                    COALESCE(SUM(debit) - SUM(credit), 0)
                FROM journal_entry_details jed
                JOIN journal_entries je ON je.id = jed.journal_entry_id
                WHERE je.status = 'posted' AND je.entry_date <= p_as_of_date;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_account_balances(
                p_start_date DATE DEFAULT NULL, p_end_date DATE DEFAULT CURRENT_DATE
            )
            RETURNS TABLE (
                account_id BIGINT, account_code VARCHAR, account_name VARCHAR,
                account_type VARCHAR, normal_balance VARCHAR,
                total_debits NUMERIC, total_credits NUMERIC, balance NUMERIC,
                is_group BOOLEAN, is_active BOOLEAN
            ) AS $$
            BEGIN
                RETURN QUERY
                SELECT a.id, a.account_code, a.account_name, at.type_name, a.normal_balance,
                    COALESCE(SUM(d.debit), 0), COALESCE(SUM(d.credit), 0),
                    COALESCE(SUM(d.debit - d.credit), 0), a.is_group, a.is_active
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

        DB::unprepared('DROP FUNCTION IF EXISTS fn_general_ledger(DATE, DATE, BIGINT) CASCADE');
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_general_ledger(
                p_start_date DATE DEFAULT NULL, p_end_date DATE DEFAULT CURRENT_DATE,
                p_account_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                journal_entry_id BIGINT, entry_date DATE, reference VARCHAR,
                journal_description TEXT, status VARCHAR,
                account_id BIGINT, account_code VARCHAR, account_name VARCHAR,
                line_no INTEGER, debit NUMERIC, credit NUMERIC, line_description VARCHAR,
                cost_center_code VARCHAR, cost_center_name VARCHAR,
                currency_code VARCHAR, fx_rate_to_base NUMERIC
            ) AS $$
            BEGIN
                RETURN QUERY
                SELECT je.id, je.entry_date, je.reference, je.description, je.status,
                    a.id, a.account_code, a.account_name,
                    jed.line_no, jed.debit, jed.credit, jed.description,
                    cc.code, cc.name, c.currency_code, je.fx_rate_to_base
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

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_balance_sheet(p_as_of_date DATE DEFAULT CURRENT_DATE)
            RETURNS TABLE (
                account_id BIGINT, account_code VARCHAR, account_name VARCHAR,
                account_type VARCHAR, report_group VARCHAR,
                normal_balance VARCHAR, balance NUMERIC
            ) AS $$
            BEGIN
                RETURN QUERY
                SELECT a.id, a.account_code, a.account_name, at.type_name, at.report_group, a.normal_balance,
                    CASE
                        WHEN a.normal_balance = 'debit' THEN COALESCE(SUM(d.debit - d.credit), 0)
                        WHEN a.normal_balance = 'credit' THEN COALESCE(SUM(d.credit - d.debit), 0)
                        ELSE 0
                    END
                FROM chart_of_accounts a
                JOIN account_types at ON at.id = a.account_type_id
                LEFT JOIN (
                    SELECT jed.chart_of_account_id, jed.debit, jed.credit
                    FROM journal_entry_details jed
                    JOIN journal_entries je ON je.id = jed.journal_entry_id
                    WHERE je.status = 'posted' AND je.entry_date <= p_as_of_date
                ) d ON d.chart_of_account_id = a.id
                WHERE at.report_group = 'BalanceSheet'
                AND (
                    a.is_active = true
                    OR EXISTS (
                        SELECT 1
                        FROM journal_entry_details jed2
                        JOIN journal_entries je2 ON je2.id = jed2.journal_entry_id
                        WHERE jed2.chart_of_account_id = a.id
                        AND je2.status = 'posted'
                    )
                )
                GROUP BY a.id, a.account_code, a.account_name, at.type_name, at.report_group, a.normal_balance
                HAVING COALESCE(SUM(d.debit), 0) <> 0 OR COALESCE(SUM(d.credit), 0) <> 0
                ORDER BY a.account_code;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_income_statement(
                p_start_date DATE DEFAULT NULL, p_end_date DATE DEFAULT CURRENT_DATE
            )
            RETURNS TABLE (
                account_id BIGINT, account_code VARCHAR, account_name VARCHAR,
                account_type VARCHAR, report_group VARCHAR,
                normal_balance VARCHAR, balance NUMERIC
            ) AS $$
            BEGIN
                RETURN QUERY
                SELECT a.id, a.account_code, a.account_name, at.type_name, at.report_group, a.normal_balance,
                    CASE
                        WHEN a.normal_balance = 'debit' THEN COALESCE(SUM(d.debit - d.credit), 0)
                        WHEN a.normal_balance = 'credit' THEN COALESCE(SUM(d.credit - d.debit), 0)
                        ELSE 0
                    END
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
                AND (
                    a.is_active = true
                    OR EXISTS (
                        SELECT 1
                        FROM journal_entry_details jed2
                        JOIN journal_entries je2 ON je2.id = jed2.journal_entry_id
                        WHERE jed2.chart_of_account_id = a.id
                        AND je2.status = 'posted'
                    )
                )
                GROUP BY a.id, a.account_code, a.account_name, at.type_name, at.report_group, a.normal_balance
                HAVING COALESCE(SUM(d.debit), 0) <> 0 OR COALESCE(SUM(d.credit), 0) <> 0
                ORDER BY a.account_code;
            END;
            $$ LANGUAGE plpgsql;
        SQL);
    }

    private function createPostgreSQLImmutabilityTriggers(): void
    {
        DB::unprepared("
            CREATE OR REPLACE FUNCTION block_posted_journal_changes()
            RETURNS TRIGGER AS \$\$
            BEGIN
                IF OLD.status = 'posted' THEN
                    IF NOT (
                        NEW.entry_date = OLD.entry_date
                        AND (NEW.description IS NOT DISTINCT FROM OLD.description)
                        AND (NEW.reference IS NOT DISTINCT FROM OLD.reference)
                        AND NEW.currency_id = OLD.currency_id
                        AND NEW.fx_rate_to_base = OLD.fx_rate_to_base
                        AND NEW.status = OLD.status
                    ) THEN
                        RAISE EXCEPTION 'Posted journal entries are immutable. Create a reversing entry instead.';
                    END IF;
                END IF;
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::unprepared('
            DROP TRIGGER IF EXISTS trg_block_posted_journal_updates ON journal_entries;
            CREATE TRIGGER trg_block_posted_journal_updates
            BEFORE UPDATE ON journal_entries
            FOR EACH ROW
            EXECUTE FUNCTION block_posted_journal_changes();
        ');

        DB::unprepared("
            CREATE OR REPLACE FUNCTION block_posted_journal_deletes()
            RETURNS TRIGGER AS \$\$
            BEGIN
                IF OLD.status = 'posted' THEN
                    RAISE EXCEPTION 'Cannot delete posted journal entries. Create a reversing entry instead.';
                END IF;
                RETURN OLD;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::unprepared('
            DROP TRIGGER IF EXISTS trg_block_posted_journal_deletes ON journal_entries;
            CREATE TRIGGER trg_block_posted_journal_deletes
            BEFORE DELETE ON journal_entries
            FOR EACH ROW
            EXECUTE FUNCTION block_posted_journal_deletes();
        ');

        DB::unprepared("
            CREATE OR REPLACE FUNCTION block_posted_detail_changes()
            RETURNS TRIGGER AS \$\$
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
            \$\$ LANGUAGE plpgsql;
        ");

        DB::unprepared('
            DROP TRIGGER IF EXISTS trg_block_posted_detail_updates ON journal_entry_details;
            CREATE TRIGGER trg_block_posted_detail_updates
            BEFORE UPDATE ON journal_entry_details
            FOR EACH ROW
            EXECUTE FUNCTION block_posted_detail_changes();
        ');

        DB::unprepared('
            DROP TRIGGER IF EXISTS trg_block_posted_detail_deletes ON journal_entry_details;
            CREATE TRIGGER trg_block_posted_detail_deletes
            BEFORE DELETE ON journal_entry_details
            FOR EACH ROW
            EXECUTE FUNCTION block_posted_detail_changes();
        ');

        DB::unprepared("
            DROP TRIGGER IF EXISTS trg_check_accounting_period_update ON journal_entries;
            CREATE TRIGGER trg_check_accounting_period_update
            BEFORE UPDATE ON journal_entries
            FOR EACH ROW
            WHEN (NEW.status = 'posted' AND OLD.status != 'posted')
            EXECUTE FUNCTION check_accounting_period();
        ");

        DB::unprepared("
            CREATE OR REPLACE FUNCTION auto_set_accounting_period()
            RETURNS TRIGGER AS \$\$
            DECLARE
                v_period_id BIGINT;
                v_period_status VARCHAR(20);
            BEGIN
                SELECT id, status INTO v_period_id, v_period_status
                FROM accounting_periods
                WHERE NEW.entry_date BETWEEN start_date AND end_date
                LIMIT 1;

                IF v_period_id IS NULL THEN
                    RAISE EXCEPTION 'No accounting period found for date %', NEW.entry_date;
                END IF;

                NEW.accounting_period_id := v_period_id;

                IF NEW.status = 'posted' AND v_period_status != 'open' THEN
                    RAISE EXCEPTION 'Cannot post to % accounting period', v_period_status;
                END IF;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::unprepared('
            DROP TRIGGER IF EXISTS trg_auto_set_accounting_period ON journal_entries;
            CREATE TRIGGER trg_auto_set_accounting_period
            BEFORE INSERT OR UPDATE ON journal_entries
            FOR EACH ROW
            EXECUTE FUNCTION auto_set_accounting_period();
        ');
    }

    private function createPostgreSQLAuditTriggers(): void
    {
        DB::unprepared("
            CREATE OR REPLACE FUNCTION audit_accounting_changes()
            RETURNS TRIGGER AS \$\$
            DECLARE
                v_old_values JSON;
                v_new_values JSON;
                v_changed_fields JSON;
                v_user_id BIGINT;
                v_ip_address INET;
                v_user_agent TEXT;
            BEGIN
                BEGIN
                    v_user_id := current_setting('app.current_user_id', TRUE)::BIGINT;
                    v_ip_address := current_setting('app.ip_address', TRUE)::INET;
                    v_user_agent := current_setting('app.user_agent', TRUE);
                EXCEPTION WHEN OTHERS THEN
                    v_user_id := NULL;
                    v_ip_address := NULL;
                    v_user_agent := NULL;
                END;

                IF TG_OP = 'DELETE' THEN
                    v_old_values := row_to_json(OLD);
                    INSERT INTO accounting_audit_log (
                        table_name, record_id, action,
                        old_values, new_values, changed_fields, user_id, ip_address, user_agent
                    ) VALUES (
                        TG_TABLE_NAME, OLD.id, 'DELETE',
                        v_old_values, NULL, NULL, v_user_id, v_ip_address, v_user_agent
                    );
                    RETURN OLD;
                ELSIF TG_OP = 'UPDATE' THEN
                    v_old_values := row_to_json(OLD);
                    v_new_values := row_to_json(NEW);
                    v_changed_fields := (
                        SELECT json_agg(key)
                        FROM json_each_text(v_new_values) new_val
                        JOIN json_each_text(v_old_values) old_val USING (key)
                        WHERE new_val.value IS DISTINCT FROM old_val.value
                    );

                    IF v_changed_fields IS NOT NULL THEN
                        INSERT INTO accounting_audit_log (
                            table_name, record_id, action,
                            old_values, new_values, changed_fields, user_id, ip_address, user_agent
                        ) VALUES (
                            TG_TABLE_NAME, NEW.id, 'UPDATE',
                            v_old_values, v_new_values, v_changed_fields, v_user_id, v_ip_address, v_user_agent
                        );
                    END IF;
                    RETURN NEW;
                ELSIF TG_OP = 'INSERT' THEN
                    v_new_values := row_to_json(NEW);
                    INSERT INTO accounting_audit_log (
                        table_name, record_id, action,
                        old_values, new_values, changed_fields, user_id, ip_address, user_agent
                    ) VALUES (
                        TG_TABLE_NAME, NEW.id, 'INSERT',
                        NULL, v_new_values, NULL, v_user_id, v_ip_address, v_user_agent
                    );
                    RETURN NEW;
                END IF;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        $auditTables = [
            'chart_of_accounts', 'journal_entries', 'journal_entry_details',
            'accounting_periods', 'account_types', 'cost_centers',
        ];

        foreach ($auditTables as $table) {
            DB::unprepared("
                DROP TRIGGER IF EXISTS trg_audit_{$table} ON {$table};
                CREATE TRIGGER trg_audit_{$table}
                AFTER INSERT OR UPDATE OR DELETE ON {$table}
                FOR EACH ROW
                EXECUTE FUNCTION audit_accounting_changes();
            ");
        }
    }

    private function createPostgreSQLSoftDeleteProtection(): void
    {
        DB::unprepared("
            CREATE OR REPLACE FUNCTION prevent_hard_delete_posted()
            RETURNS TRIGGER AS \$\$
            BEGIN
                IF OLD.status = 'posted' AND OLD.deleted_at IS NULL THEN
                    RAISE EXCEPTION 'Cannot delete posted journal entries. Use reversing entry instead.';
                END IF;
                RETURN OLD;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::unprepared('
            DROP TRIGGER IF EXISTS trg_prevent_hard_delete ON journal_entries;
            CREATE TRIGGER trg_prevent_hard_delete
            BEFORE DELETE ON journal_entries
            FOR EACH ROW
            EXECUTE FUNCTION prevent_hard_delete_posted();
        ');
    }

    private function createPostgreSQLSnapshotHelpers(): void
    {
        DB::unprepared("
            CREATE OR REPLACE FUNCTION sp_create_period_snapshots(p_period_id BIGINT)
            RETURNS TABLE (accounts_processed INT, snapshot_date DATE) AS \$\$
            DECLARE
                v_period_start DATE;
                v_period_end DATE;
                v_accounts_processed INT := 0;
            BEGIN
                SELECT start_date, end_date INTO v_period_start, v_period_end
                FROM accounting_periods WHERE id = p_period_id;

                INSERT INTO account_balance_snapshots (
                    chart_of_account_id, accounting_period_id, snapshot_date,
                    opening_balance, period_debits, period_credits, closing_balance, created_by
                )
                SELECT a.id, p_period_id, v_period_end,
                    COALESCE(prev.closing_balance, 0),
                    COALESCE(SUM(jed.debit), 0),
                    COALESCE(SUM(jed.credit), 0),
                    COALESCE(prev.closing_balance, 0) + COALESCE(SUM(jed.debit), 0) - COALESCE(SUM(jed.credit), 0),
                    current_setting('app.current_user_id', TRUE)::BIGINT
                FROM chart_of_accounts a
                LEFT JOIN account_balance_snapshots prev ON (
                    prev.chart_of_account_id = a.id AND prev.snapshot_date < v_period_start
                )
                LEFT JOIN journal_entry_details jed ON jed.chart_of_account_id = a.id
                LEFT JOIN journal_entries je ON (
                    je.id = jed.journal_entry_id AND je.status = 'posted'
                    AND je.entry_date BETWEEN v_period_start AND v_period_end
                )
                WHERE a.is_active = true
                GROUP BY a.id, prev.closing_balance
                ON CONFLICT (chart_of_account_id, accounting_period_id)
                DO UPDATE SET
                    period_debits = EXCLUDED.period_debits,
                    period_credits = EXCLUDED.period_credits,
                    closing_balance = EXCLUDED.closing_balance,
                    updated_at = CURRENT_TIMESTAMP;

                GET DIAGNOSTICS v_accounts_processed = ROW_COUNT;
                RETURN QUERY SELECT v_accounts_processed, v_period_end;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::unprepared("
            CREATE OR REPLACE FUNCTION fn_account_balance_fast(
                p_account_id BIGINT, p_as_of_date DATE DEFAULT CURRENT_DATE
            )
            RETURNS DECIMAL(15,2) AS \$\$
            DECLARE
                v_balance DECIMAL(15,2);
                v_snapshot_date DATE;
            BEGIN
                SELECT closing_balance, snapshot_date INTO v_balance, v_snapshot_date
                FROM account_balance_snapshots
                WHERE chart_of_account_id = p_account_id AND snapshot_date <= p_as_of_date
                ORDER BY snapshot_date DESC LIMIT 1;

                IF v_snapshot_date IS NULL THEN
                    v_balance := 0;
                    v_snapshot_date := '1900-01-01';
                END IF;

                SELECT v_balance + COALESCE(SUM(jed.debit - jed.credit), 0) INTO v_balance
                FROM journal_entry_details jed
                JOIN journal_entries je ON je.id = jed.journal_entry_id
                WHERE jed.chart_of_account_id = p_account_id
                AND je.status = 'posted'
                AND je.entry_date > v_snapshot_date AND je.entry_date <= p_as_of_date;

                RETURN v_balance;
            END;
            \$\$ LANGUAGE plpgsql;
        ");
    }

    // ──────────────────────────────────────────
    // MYSQL / MARIADB TRIGGERS
    // ──────────────────────────────────────────

    private function createMySQLTriggers(): void
    {
        try {
            DB::unprepared('DROP PROCEDURE IF EXISTS sp_check_journal_balance');
        } catch (\Exception $e) {
            // Ignore
        }

        try {
            DB::unprepared("
                CREATE PROCEDURE sp_check_journal_balance(IN p_journal_id BIGINT)
                BEGIN
                    DECLARE v_debits DECIMAL(15,2);
                    DECLARE v_credits DECIMAL(15,2);
                    DECLARE v_status VARCHAR(20);
                    DECLARE v_error_msg VARCHAR(500);

                    SELECT status INTO v_status FROM journal_entries WHERE id = p_journal_id;

                    IF v_status = 'posted' THEN
                        SELECT COALESCE(SUM(debit), 0), COALESCE(SUM(credit), 0)
                        INTO v_debits, v_credits
                        FROM journal_entry_details WHERE journal_entry_id = p_journal_id;

                        IF v_debits <> v_credits THEN
                            SET v_error_msg = CONCAT('Journal entry ', p_journal_id, ' is unbalanced: Debits=', v_debits, ', Credits=', v_credits);
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_msg;
                        END IF;
                    END IF;
                END
            ");
        } catch (\Exception $e) {
            \Log::warning('Skipped sp_check_journal_balance: '.$e->getMessage());

            return;
        }

        // Balance check on posting
        try {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_journal_balance_before_post');
        } catch (\Exception $e) {
            // Ignore
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

        // Leaf account check - INSERT
        try {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_leaf_account_only_insert');
        } catch (\Exception $e) {
            // Ignore
        }
        DB::unprepared("
            CREATE TRIGGER trg_leaf_account_only_insert
            BEFORE INSERT ON journal_entry_details
            FOR EACH ROW
            BEGIN
                DECLARE v_is_group BOOLEAN;
                DECLARE v_error_msg VARCHAR(500);
                SELECT is_group INTO v_is_group FROM chart_of_accounts WHERE id = NEW.chart_of_account_id;
                IF v_is_group THEN
                    SET v_error_msg = CONCAT('Cannot post to group account ', NEW.chart_of_account_id);
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_msg;
                END IF;
            END
        ");

        // Leaf account check - UPDATE
        try {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_leaf_account_only_update');
        } catch (\Exception $e) {
            // Ignore
        }
        DB::unprepared("
            CREATE TRIGGER trg_leaf_account_only_update
            BEFORE UPDATE ON journal_entry_details
            FOR EACH ROW
            BEGIN
                DECLARE v_is_group BOOLEAN;
                DECLARE v_error_msg VARCHAR(500);
                SELECT is_group INTO v_is_group FROM chart_of_accounts WHERE id = NEW.chart_of_account_id;
                IF v_is_group THEN
                    SET v_error_msg = CONCAT('Cannot post to group account ', NEW.chart_of_account_id);
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_msg;
                END IF;
            END
        ");

        // Accounting period check - INSERT
        try {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_check_accounting_period_insert');
        } catch (\Exception $e) {
            // Ignore
        }
        DB::unprepared("
            CREATE TRIGGER trg_check_accounting_period_insert
            BEFORE INSERT ON journal_entries
            FOR EACH ROW
            BEGIN
                DECLARE v_period_status VARCHAR(20);
                DECLARE v_error_msg VARCHAR(500);
                IF NEW.status = 'posted' THEN
                    SELECT ap.status INTO v_period_status FROM accounting_periods ap
                    WHERE NEW.entry_date BETWEEN ap.start_date AND ap.end_date LIMIT 1;
                    IF v_period_status IS NULL THEN
                        SET v_error_msg = CONCAT('No accounting period found for date ', NEW.entry_date);
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_msg;
                    END IF;
                    IF v_period_status <> 'open' THEN
                        SET v_error_msg = CONCAT('Cannot post to ', v_period_status, ' accounting period');
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_msg;
                    END IF;
                END IF;
            END
        ");

        // Single base currency - INSERT
        try {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_single_base_currency');
        } catch (\Exception $e) {
            // Ignore
        }
        DB::unprepared("
            CREATE TRIGGER trg_single_base_currency
            BEFORE INSERT ON currencies
            FOR EACH ROW
            BEGIN
                DECLARE v_count INT;
                IF NEW.is_base_currency = TRUE THEN
                    SELECT COUNT(*) INTO v_count FROM currencies WHERE is_base_currency = TRUE;
                    IF v_count > 0 THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only one base currency is allowed';
                    END IF;
                END IF;
            END
        ");

        // Single base currency - UPDATE
        try {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_single_base_currency_update');
        } catch (\Exception $e) {
            // Ignore
        }
        DB::unprepared("
            CREATE TRIGGER trg_single_base_currency_update
            BEFORE UPDATE ON currencies
            FOR EACH ROW
            BEGIN
                DECLARE v_count INT;
                IF NEW.is_base_currency = TRUE AND OLD.is_base_currency = FALSE THEN
                    SELECT COUNT(*) INTO v_count FROM currencies WHERE is_base_currency = TRUE AND id <> NEW.id;
                    IF v_count > 0 THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only one base currency is allowed';
                    END IF;
                END IF;
            END
        ");

        // Immutability: Block updates on posted entries
        try {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_block_posted_journal_updates');
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
            \Log::warning('Skipped trg_block_posted_journal_updates: '.$e->getMessage());
        }

        // Immutability: Block deletes on posted entries
        try {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_block_posted_journal_deletes');
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
            \Log::warning('Skipped trg_block_posted_journal_deletes: '.$e->getMessage());
        }

        // Immutability: Block detail updates on posted entries
        try {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_block_posted_detail_updates');
            DB::unprepared("
                CREATE TRIGGER trg_block_posted_detail_updates
                BEFORE UPDATE ON journal_entry_details
                FOR EACH ROW
                BEGIN
                    DECLARE v_status VARCHAR(20);
                    SELECT status INTO v_status FROM journal_entries WHERE id = OLD.journal_entry_id;
                    IF v_status = 'posted' THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Lines of a posted journal are immutable. Create a reversing entry instead.';
                    END IF;
                END
            ");
        } catch (\Exception $e) {
            \Log::warning('Skipped trg_block_posted_detail_updates: '.$e->getMessage());
        }

        // Immutability: Block detail deletes on posted entries
        try {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_block_posted_detail_deletes');
            DB::unprepared("
                CREATE TRIGGER trg_block_posted_detail_deletes
                BEFORE DELETE ON journal_entry_details
                FOR EACH ROW
                BEGIN
                    DECLARE v_status VARCHAR(20);
                    SELECT status INTO v_status FROM journal_entries WHERE id = OLD.journal_entry_id;
                    IF v_status = 'posted' THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Cannot delete lines of a posted journal. Create a reversing entry instead.';
                    END IF;
                END
            ");
        } catch (\Exception $e) {
            \Log::warning('Skipped trg_block_posted_detail_deletes: '.$e->getMessage());
        }

        // Period check on UPDATE
        try {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_check_accounting_period_update');
            DB::unprepared("
                CREATE TRIGGER trg_check_accounting_period_update
                BEFORE UPDATE ON journal_entries
                FOR EACH ROW
                BEGIN
                    DECLARE v_period_status VARCHAR(20);
                    DECLARE v_error_msg VARCHAR(500);
                    IF NEW.status = 'posted' AND OLD.status != 'posted' THEN
                        SELECT ap.status INTO v_period_status FROM accounting_periods ap
                        WHERE NEW.entry_date BETWEEN ap.start_date AND ap.end_date LIMIT 1;
                        IF v_period_status IS NULL THEN
                            SET v_error_msg = CONCAT('No accounting period found for date ', NEW.entry_date);
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_msg;
                        END IF;
                        IF v_period_status <> 'open' THEN
                            SET v_error_msg = CONCAT('Cannot post to ', v_period_status, ' accounting period');
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_msg;
                        END IF;
                    END IF;
                END
            ");
        } catch (\Exception $e) {
            \Log::warning('Skipped trg_check_accounting_period_update: '.$e->getMessage());
        }

        // Auto-set accounting_period_id on INSERT
        try {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_auto_set_accounting_period');
            DB::unprepared("
                CREATE TRIGGER trg_auto_set_accounting_period
                BEFORE INSERT ON journal_entries
                FOR EACH ROW
                BEGIN
                    DECLARE v_period_id BIGINT;
                    DECLARE v_period_status VARCHAR(20);
                    DECLARE v_error_msg VARCHAR(500);
                    SELECT id, status INTO v_period_id, v_period_status
                    FROM accounting_periods WHERE NEW.entry_date BETWEEN start_date AND end_date LIMIT 1;
                    IF v_period_id IS NULL THEN
                        SET v_error_msg = CONCAT('No accounting period found for date ', NEW.entry_date);
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_msg;
                    END IF;
                    SET NEW.accounting_period_id = v_period_id;
                    IF NEW.status = 'posted' AND v_period_status <> 'open' THEN
                        SET v_error_msg = CONCAT('Cannot post to ', v_period_status, ' accounting period');
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_msg;
                    END IF;
                END
            ");
        } catch (\Exception $e) {
            \Log::warning('Skipped trg_auto_set_accounting_period: '.$e->getMessage());
        }

        // Auto-set accounting_period_id on UPDATE
        try {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_auto_set_accounting_period_update');
            DB::unprepared("
                CREATE TRIGGER trg_auto_set_accounting_period_update
                BEFORE UPDATE ON journal_entries
                FOR EACH ROW
                BEGIN
                    DECLARE v_period_id BIGINT;
                    DECLARE v_period_status VARCHAR(20);
                    DECLARE v_error_msg VARCHAR(500);
                    SELECT id, status INTO v_period_id, v_period_status
                    FROM accounting_periods WHERE NEW.entry_date BETWEEN start_date AND end_date LIMIT 1;
                    IF v_period_id IS NULL THEN
                        SET v_error_msg = CONCAT('No accounting period found for date ', NEW.entry_date);
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_msg;
                    END IF;
                    SET NEW.accounting_period_id = v_period_id;
                    IF NEW.status = 'posted' AND v_period_status <> 'open' THEN
                        SET v_error_msg = CONCAT('Cannot post to ', v_period_status, ' accounting period');
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_msg;
                    END IF;
                END
            ");
        } catch (\Exception $e) {
            \Log::warning('Skipped trg_auto_set_accounting_period_update: '.$e->getMessage());
        }
    }

    private function createMySQLAuditTriggers(): void
    {
        $auditTables = [
            'chart_of_accounts', 'journal_entries', 'journal_entry_details',
            'accounting_periods', 'account_types', 'cost_centers',
        ];

        foreach ($auditTables as $table) {
            try {
                DB::unprepared("DROP TRIGGER IF EXISTS trg_audit_{$table}_insert");
                DB::unprepared("
                    CREATE TRIGGER trg_audit_{$table}_insert
                    AFTER INSERT ON {$table}
                    FOR EACH ROW
                    BEGIN
                        INSERT INTO accounting_audit_log (
                            table_name, record_id, action, old_values, new_values,
                            changed_fields, user_id, ip_address, user_agent, created_at
                        ) VALUES (
                            '{$table}', NEW.id, 'INSERT', NULL,
                            JSON_OBJECT('id', NEW.id), NULL,
                            @current_user_id, @ip_address, @user_agent, CURRENT_TIMESTAMP
                        );
                    END
                ");

                DB::unprepared("DROP TRIGGER IF EXISTS trg_audit_{$table}_update");
                DB::unprepared("
                    CREATE TRIGGER trg_audit_{$table}_update
                    AFTER UPDATE ON {$table}
                    FOR EACH ROW
                    BEGIN
                        INSERT INTO accounting_audit_log (
                            table_name, record_id, action, old_values, new_values,
                            changed_fields, user_id, ip_address, user_agent, created_at
                        ) VALUES (
                            '{$table}', NEW.id, 'UPDATE',
                            JSON_OBJECT('id', OLD.id), JSON_OBJECT('id', NEW.id),
                            JSON_ARRAY('updated'),
                            @current_user_id, @ip_address, @user_agent, CURRENT_TIMESTAMP
                        );
                    END
                ");

                DB::unprepared("DROP TRIGGER IF EXISTS trg_audit_{$table}_delete");
                DB::unprepared("
                    CREATE TRIGGER trg_audit_{$table}_delete
                    AFTER DELETE ON {$table}
                    FOR EACH ROW
                    BEGIN
                        INSERT INTO accounting_audit_log (
                            table_name, record_id, action, old_values, new_values,
                            changed_fields, user_id, ip_address, user_agent, created_at
                        ) VALUES (
                            '{$table}', OLD.id, 'DELETE',
                            JSON_OBJECT('id', OLD.id), NULL, NULL,
                            @current_user_id, @ip_address, @user_agent, CURRENT_TIMESTAMP
                        );
                    END
                ");
            } catch (\Exception $e) {
                \Log::warning("Skipped audit triggers for {$table}: ".$e->getMessage());
            }
        }
    }

    private function createMySQLSoftDeleteProtection(): void
    {
        try {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_mysql_prevent_hard_delete');
            DB::unprepared("
                CREATE TRIGGER trg_mysql_prevent_hard_delete
                BEFORE DELETE ON journal_entries
                FOR EACH ROW
                BEGIN
                    IF OLD.status = 'posted' AND (OLD.deleted_at IS NULL OR OLD.deleted_at = '0000-00-00 00:00:00') THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Cannot delete posted journal entries. Use reversing entry instead.';
                    END IF;
                END
            ");
        } catch (\Exception $e) {
            \Log::warning('MySQL soft delete protection not created: '.$e->getMessage());
        }
    }

    private function createMySQLSnapshotHelpers(): void
    {
        try {
            DB::unprepared('DROP PROCEDURE IF EXISTS sp_create_period_snapshots');
            DB::unprepared("
                CREATE PROCEDURE sp_create_period_snapshots(IN p_period_id BIGINT)
                BEGIN
                    DECLARE v_period_start DATE;
                    DECLARE v_period_end DATE;
                    SELECT start_date, end_date INTO v_period_start, v_period_end
                    FROM accounting_periods WHERE id = p_period_id;
                    INSERT INTO account_balance_snapshots (
                        chart_of_account_id, accounting_period_id, snapshot_date,
                        opening_balance, period_debits, period_credits, closing_balance,
                        created_by, created_at, updated_at
                    )
                    SELECT a.id, p_period_id, v_period_end,
                        COALESCE(prev.closing_balance, 0),
                        COALESCE(SUM(jed.debit), 0),
                        COALESCE(SUM(jed.credit), 0),
                        COALESCE(prev.closing_balance, 0) + COALESCE(SUM(jed.debit), 0) - COALESCE(SUM(jed.credit), 0),
                        @current_user_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                    FROM chart_of_accounts a
                    LEFT JOIN (
                        SELECT chart_of_account_id, closing_balance
                        FROM account_balance_snapshots
                        WHERE snapshot_date < v_period_start
                        ORDER BY snapshot_date DESC LIMIT 1
                    ) prev ON prev.chart_of_account_id = a.id
                    LEFT JOIN journal_entry_details jed ON jed.chart_of_account_id = a.id
                    LEFT JOIN journal_entries je ON (
                        je.id = jed.journal_entry_id AND je.status = 'posted'
                        AND je.entry_date BETWEEN v_period_start AND v_period_end
                    )
                    WHERE a.is_active = true
                    GROUP BY a.id, prev.closing_balance
                    ON DUPLICATE KEY UPDATE
                        period_debits = VALUES(period_debits),
                        period_credits = VALUES(period_credits),
                        closing_balance = VALUES(closing_balance),
                        updated_at = CURRENT_TIMESTAMP;
                    SELECT ROW_COUNT() as accounts_processed, v_period_end as snapshot_date;
                END
            ");

            DB::unprepared('DROP FUNCTION IF EXISTS fn_account_balance_fast');
            DB::unprepared("
                CREATE FUNCTION fn_account_balance_fast(p_account_id BIGINT, p_as_of_date DATE)
                RETURNS DECIMAL(15,2) READS SQL DATA
                BEGIN
                    DECLARE v_balance DECIMAL(15,2) DEFAULT 0;
                    DECLARE v_snapshot_date DATE DEFAULT '1900-01-01';
                    SELECT COALESCE(closing_balance, 0), COALESCE(snapshot_date, '1900-01-01')
                    INTO v_balance, v_snapshot_date
                    FROM account_balance_snapshots
                    WHERE chart_of_account_id = p_account_id AND snapshot_date <= p_as_of_date
                    ORDER BY snapshot_date DESC LIMIT 1;
                    SELECT v_balance + COALESCE(SUM(jed.debit - jed.credit), 0) INTO v_balance
                    FROM journal_entry_details jed
                    JOIN journal_entries je ON je.id = jed.journal_entry_id
                    WHERE jed.chart_of_account_id = p_account_id
                    AND je.status = 'posted'
                    AND je.entry_date > v_snapshot_date AND je.entry_date <= p_as_of_date;
                    RETURN v_balance;
                END
            ");
        } catch (\Exception $e) {
            \Log::warning('MySQL snapshot helpers not created: '.$e->getMessage());
        }
    }

    // ──────────────────────────────────────────
    // DROP ALL (reverse)
    // ──────────────────────────────────────────

    private function dropPostgreSQLAll(): void
    {
        $auditTables = [
            'chart_of_accounts', 'journal_entries', 'journal_entry_details',
            'accounting_periods', 'account_types', 'cost_centers',
        ];
        foreach ($auditTables as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_audit_{$table} ON {$table}");
        }

        DB::unprepared('DROP TRIGGER IF EXISTS trg_block_posted_journal_updates ON journal_entries');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_block_posted_journal_deletes ON journal_entries');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_block_posted_detail_updates ON journal_entry_details');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_block_posted_detail_deletes ON journal_entry_details');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_check_accounting_period_update ON journal_entries');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_auto_set_accounting_period ON journal_entries');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_journal_balance ON journal_entry_details CASCADE');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_leaf_account_only ON journal_entry_details CASCADE');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_check_accounting_period ON journal_entries CASCADE');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_single_base_currency ON currencies CASCADE');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_prevent_hard_delete ON journal_entries');

        DB::unprepared('DROP FUNCTION IF EXISTS check_journal_balance() CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS check_leaf_account_only() CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS check_accounting_period() CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS check_single_base_currency() CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS block_posted_journal_changes() CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS block_posted_journal_deletes() CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS block_posted_detail_changes() CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS auto_set_accounting_period() CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS audit_accounting_changes() CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS prevent_hard_delete_posted() CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS sp_create_period_snapshots(BIGINT) CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS fn_account_balance_fast(BIGINT, DATE) CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS fn_income_statement(DATE, DATE) CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS fn_balance_sheet(DATE) CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS fn_general_ledger(DATE, DATE, BIGINT) CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS fn_account_balances(DATE, DATE) CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS fn_trial_balance_summary(DATE) CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS fn_trial_balance(DATE) CASCADE');
    }

    private function dropMySQLAll(): void
    {
        $auditTables = [
            'chart_of_accounts', 'journal_entries', 'journal_entry_details',
            'accounting_periods', 'account_types', 'cost_centers',
        ];
        foreach ($auditTables as $table) {
            try {
                DB::unprepared("DROP TRIGGER IF EXISTS trg_audit_{$table}_insert");
                DB::unprepared("DROP TRIGGER IF EXISTS trg_audit_{$table}_update");
                DB::unprepared("DROP TRIGGER IF EXISTS trg_audit_{$table}_delete");
            } catch (\Exception $e) {
                // Ignore
            }
        }

        try {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_block_posted_journal_updates');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_block_posted_journal_deletes');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_block_posted_detail_updates');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_block_posted_detail_deletes');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_check_accounting_period_update');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_auto_set_accounting_period');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_auto_set_accounting_period_update');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_journal_balance_before_post');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_leaf_account_only_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_leaf_account_only_update');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_check_accounting_period_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_single_base_currency');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_single_base_currency_update');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_mysql_prevent_hard_delete');
            DB::unprepared('DROP PROCEDURE IF EXISTS sp_check_journal_balance');
            DB::unprepared('DROP PROCEDURE IF EXISTS sp_create_period_snapshots');
            DB::unprepared('DROP FUNCTION IF EXISTS fn_account_balance_fast');
        } catch (\Exception $e) {
            \Log::warning('Error dropping MySQL objects: '.$e->getMessage());
        }
    }
};
