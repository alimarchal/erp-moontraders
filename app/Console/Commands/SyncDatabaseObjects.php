<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncDatabaseObjects extends Command
{
    protected $signature = 'db:sync-objects';

    protected $description = 'Re-creates all stored procedures, functions, triggers, and views (safe to run repeatedly)';

    /** @var array<string, string> */
    private array $results = [];

    public function handle(): int
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'])) {
            $this->syncMySQL();
        } elseif ($driver === 'pgsql') {
            $this->syncPostgreSQL();
        } else {
            $this->error("Unsupported driver: {$driver}. Supported: MySQL, MariaDB, PostgreSQL.");

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(['Object', 'Status'], collect($this->results)->map(
            fn ($status, $name) => [$name, $status]
        )->values()->toArray());

        return self::SUCCESS;
    }

    // ──────────────────────────────────────────
    // MySQL / MariaDB
    // ──────────────────────────────────────────

    private function syncMySQL(): void
    {
        $this->syncMySQLProcedures();
        $this->syncMySQLFunctions();
        $this->syncViews();
    }

    private function syncMySQLProcedures(): void
    {
        $this->syncMySQLObject('PROCEDURE', 'sp_check_journal_balance', function (): void {
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
                            SET v_error_msg = CONCAT('Journal entry ', p_journal_id,
                                ' is unbalanced: Debits=', v_debits, ', Credits=', v_credits);
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_msg;
                        END IF;
                    END IF;
                END
            ");
        });

        $this->syncMySQLObject('PROCEDURE', 'sp_create_period_snapshots', function (): void {
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
        });
    }

    private function syncMySQLFunctions(): void
    {
        $this->syncMySQLObject('FUNCTION', 'fn_account_balance_fast', function (): void {
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
        });
    }

    private function syncMySQLObject(string $type, string $name, callable $creator): void
    {
        try {
            $exists = $this->mysqlObjectExists($type, $name);
            DB::unprepared("DROP {$type} IF EXISTS `{$name}`");
            $creator();
            $this->results[$name] = $exists ? 'Recreated' : 'Created';
            $this->line("  <info>OK</info>  {$type} <comment>{$name}</comment>");
        } catch (\Throwable $e) {
            $this->results[$name] = 'FAILED: '.$e->getMessage();
            $this->line("  <error>FAIL</error> {$type} <comment>{$name}</comment>: {$e->getMessage()}");
        }
    }

    private function mysqlObjectExists(string $type, string $name): bool
    {
        $db = DB::connection()->getDatabaseName();
        $routineType = strtoupper($type);

        return match ($routineType) {
            'PROCEDURE', 'FUNCTION' => DB::table('information_schema.ROUTINES')
                ->where('ROUTINE_SCHEMA', $db)
                ->where('ROUTINE_TYPE', $routineType)
                ->where('ROUTINE_NAME', $name)
                ->exists(),
            'VIEW' => DB::table('information_schema.VIEWS')
                ->where('TABLE_SCHEMA', $db)
                ->where('TABLE_NAME', $name)
                ->exists(),
            default => false,
        };
    }

    // ──────────────────────────────────────────
    // PostgreSQL
    // ──────────────────────────────────────────

    private function syncPostgreSQL(): void
    {
        $this->syncPGFunctions();
        $this->syncPGTriggers();
        $this->syncViews();
    }

    private function syncPGFunctions(): void
    {
        // Trigger functions
        $this->syncPGObject('FUNCTION', 'check_journal_balance', fn () => DB::unprepared("
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
                SELECT status INTO v_status FROM journal_entries WHERE id = v_journal_id;
                IF v_status != 'posted' THEN
                    IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
                    RETURN NEW;
                END IF;
                SELECT COALESCE(SUM(debit), 0), COALESCE(SUM(credit), 0)
                INTO v_debits, v_credits
                FROM journal_entry_details WHERE journal_entry_id = v_journal_id;
                IF v_debits <> v_credits THEN
                    RAISE EXCEPTION 'Journal entry % is unbalanced: Debits=%, Credits=%',
                        v_journal_id, v_debits, v_credits;
                END IF;
                IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        "));

        $this->syncPGObject('FUNCTION', 'check_leaf_account_only', fn () => DB::unprepared("
            CREATE OR REPLACE FUNCTION check_leaf_account_only()
            RETURNS TRIGGER AS \$\$
            DECLARE v_is_group BOOLEAN;
            BEGIN
                SELECT is_group INTO v_is_group FROM chart_of_accounts WHERE id = NEW.chart_of_account_id;
                IF v_is_group THEN
                    RAISE EXCEPTION 'Cannot post to group account %', NEW.chart_of_account_id;
                END IF;
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        "));

        $this->syncPGObject('FUNCTION', 'check_accounting_period', fn () => DB::unprepared("
            CREATE OR REPLACE FUNCTION check_accounting_period()
            RETURNS TRIGGER AS \$\$
            DECLARE v_period_status VARCHAR(20);
            BEGIN
                IF NEW.status = 'posted' THEN
                    SELECT ap.status INTO v_period_status FROM accounting_periods ap
                    WHERE NEW.entry_date BETWEEN ap.start_date AND ap.end_date LIMIT 1;
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
        "));

        $this->syncPGObject('FUNCTION', 'check_single_base_currency', fn () => DB::unprepared("
            CREATE OR REPLACE FUNCTION check_single_base_currency()
            RETURNS TRIGGER AS \$\$
            DECLARE v_count INTEGER;
            BEGIN
                IF NEW.is_base_currency = TRUE THEN
                    SELECT COUNT(*) INTO v_count FROM currencies WHERE is_base_currency = TRUE AND id <> NEW.id;
                    IF v_count > 0 THEN
                        RAISE EXCEPTION 'Only one base currency is allowed';
                    END IF;
                END IF;
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        "));

        $this->syncPGObject('FUNCTION', 'block_posted_journal_changes', fn () => DB::unprepared("
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
        "));

        $this->syncPGObject('FUNCTION', 'block_posted_journal_deletes', fn () => DB::unprepared("
            CREATE OR REPLACE FUNCTION block_posted_journal_deletes()
            RETURNS TRIGGER AS \$\$
            BEGIN
                IF OLD.status = 'posted' THEN
                    RAISE EXCEPTION 'Cannot delete posted journal entries. Create a reversing entry instead.';
                END IF;
                RETURN OLD;
            END;
            \$\$ LANGUAGE plpgsql;
        "));

        $this->syncPGObject('FUNCTION', 'block_posted_detail_changes', fn () => DB::unprepared("
            CREATE OR REPLACE FUNCTION block_posted_detail_changes()
            RETURNS TRIGGER AS \$\$
            DECLARE v_status VARCHAR(20);
            BEGIN
                SELECT status INTO v_status FROM journal_entries WHERE id = OLD.journal_entry_id;
                IF v_status = 'posted' THEN
                    RAISE EXCEPTION 'Lines of a posted journal are immutable. Create a reversing entry instead.';
                END IF;
                IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        "));

        $this->syncPGObject('FUNCTION', 'auto_set_accounting_period', fn () => DB::unprepared("
            CREATE OR REPLACE FUNCTION auto_set_accounting_period()
            RETURNS TRIGGER AS \$\$
            DECLARE
                v_period_id BIGINT;
                v_period_status VARCHAR(20);
            BEGIN
                SELECT id, status INTO v_period_id, v_period_status
                FROM accounting_periods WHERE NEW.entry_date BETWEEN start_date AND end_date LIMIT 1;
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
        "));

        $this->syncPGObject('FUNCTION', 'audit_accounting_changes', fn () => DB::unprepared("
            CREATE OR REPLACE FUNCTION audit_accounting_changes()
            RETURNS TRIGGER AS \$\$
            DECLARE
                v_old_values JSON; v_new_values JSON; v_changed_fields JSON;
                v_user_id BIGINT; v_ip_address INET; v_user_agent TEXT;
            BEGIN
                BEGIN
                    v_user_id := current_setting('app.current_user_id', TRUE)::BIGINT;
                    v_ip_address := current_setting('app.ip_address', TRUE)::INET;
                    v_user_agent := current_setting('app.user_agent', TRUE);
                EXCEPTION WHEN OTHERS THEN
                    v_user_id := NULL; v_ip_address := NULL; v_user_agent := NULL;
                END;
                IF TG_OP = 'DELETE' THEN
                    INSERT INTO accounting_audit_log (table_name, record_id, action, old_values, new_values, changed_fields, user_id, ip_address, user_agent)
                    VALUES (TG_TABLE_NAME, OLD.id, 'DELETE', row_to_json(OLD), NULL, NULL, v_user_id, v_ip_address, v_user_agent);
                    RETURN OLD;
                ELSIF TG_OP = 'UPDATE' THEN
                    v_old_values := row_to_json(OLD); v_new_values := row_to_json(NEW);
                    v_changed_fields := (SELECT json_agg(key) FROM json_each_text(v_new_values) nv JOIN json_each_text(v_old_values) ov USING (key) WHERE nv.value IS DISTINCT FROM ov.value);
                    IF v_changed_fields IS NOT NULL THEN
                        INSERT INTO accounting_audit_log (table_name, record_id, action, old_values, new_values, changed_fields, user_id, ip_address, user_agent)
                        VALUES (TG_TABLE_NAME, NEW.id, 'UPDATE', v_old_values, v_new_values, v_changed_fields, v_user_id, v_ip_address, v_user_agent);
                    END IF;
                    RETURN NEW;
                ELSE
                    INSERT INTO accounting_audit_log (table_name, record_id, action, old_values, new_values, changed_fields, user_id, ip_address, user_agent)
                    VALUES (TG_TABLE_NAME, NEW.id, 'INSERT', NULL, row_to_json(NEW), NULL, v_user_id, v_ip_address, v_user_agent);
                    RETURN NEW;
                END IF;
            END;
            \$\$ LANGUAGE plpgsql;
        "));

        $this->syncPGObject('FUNCTION', 'prevent_hard_delete_posted', fn () => DB::unprepared("
            CREATE OR REPLACE FUNCTION prevent_hard_delete_posted()
            RETURNS TRIGGER AS \$\$
            BEGIN
                IF OLD.status = 'posted' AND OLD.deleted_at IS NULL THEN
                    RAISE EXCEPTION 'Cannot delete posted journal entries. Use reversing entry instead.';
                END IF;
                RETURN OLD;
            END;
            \$\$ LANGUAGE plpgsql;
        "));

        // Accounting functions
        $this->syncPGObject('FUNCTION', 'fn_trial_balance', fn () => DB::unprepared(<<<'SQL'
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
        SQL));

        $this->syncPGObject('FUNCTION', 'fn_trial_balance_summary', fn () => DB::unprepared(<<<'SQL'
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
        SQL));

        $this->syncPGObject('FUNCTION', 'fn_account_balances', fn () => DB::unprepared(<<<'SQL'
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
        SQL));

        $this->syncPGObject('FUNCTION', 'fn_general_ledger', function (): void {
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
        });

        $this->syncPGObject('FUNCTION', 'fn_balance_sheet', fn () => DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_balance_sheet(p_as_of_date DATE DEFAULT CURRENT_DATE)
            RETURNS TABLE (
                account_id BIGINT, account_code VARCHAR, account_name VARCHAR,
                account_type VARCHAR, report_group VARCHAR, normal_balance VARCHAR, balance NUMERIC
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
                WHERE at.report_group = 'BalanceSheet' AND a.is_active = true
                GROUP BY a.id, a.account_code, a.account_name, at.type_name, at.report_group, a.normal_balance
                HAVING COALESCE(SUM(d.debit), 0) <> 0 OR COALESCE(SUM(d.credit), 0) <> 0
                ORDER BY a.account_code;
            END;
            $$ LANGUAGE plpgsql;
        SQL));

        $this->syncPGObject('FUNCTION', 'fn_income_statement', fn () => DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_income_statement(
                p_start_date DATE DEFAULT NULL, p_end_date DATE DEFAULT CURRENT_DATE
            )
            RETURNS TABLE (
                account_id BIGINT, account_code VARCHAR, account_name VARCHAR,
                account_type VARCHAR, report_group VARCHAR, normal_balance VARCHAR, balance NUMERIC
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
                WHERE at.report_group = 'IncomeStatement' AND a.is_active = true
                GROUP BY a.id, a.account_code, a.account_name, at.type_name, at.report_group, a.normal_balance
                HAVING COALESCE(SUM(d.debit), 0) <> 0 OR COALESCE(SUM(d.credit), 0) <> 0
                ORDER BY a.account_code;
            END;
            $$ LANGUAGE plpgsql;
        SQL));

        $this->syncPGObject('FUNCTION', 'sp_create_period_snapshots', fn () => DB::unprepared("
            CREATE OR REPLACE FUNCTION sp_create_period_snapshots(p_period_id BIGINT)
            RETURNS TABLE (accounts_processed INT, snapshot_date DATE) AS \$\$
            DECLARE
                v_period_start DATE; v_period_end DATE;
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
                    COALESCE(SUM(jed.debit), 0), COALESCE(SUM(jed.credit), 0),
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
        "));

        $this->syncPGObject('FUNCTION', 'fn_account_balance_fast', fn () => DB::unprepared("
            CREATE OR REPLACE FUNCTION fn_account_balance_fast(
                p_account_id BIGINT, p_as_of_date DATE DEFAULT CURRENT_DATE
            )
            RETURNS DECIMAL(15,2) AS \$\$
            DECLARE
                v_balance DECIMAL(15,2); v_snapshot_date DATE;
            BEGIN
                SELECT closing_balance, snapshot_date INTO v_balance, v_snapshot_date
                FROM account_balance_snapshots
                WHERE chart_of_account_id = p_account_id AND snapshot_date <= p_as_of_date
                ORDER BY snapshot_date DESC LIMIT 1;
                IF v_snapshot_date IS NULL THEN
                    v_balance := 0; v_snapshot_date := '1900-01-01';
                END IF;
                SELECT v_balance + COALESCE(SUM(jed.debit - jed.credit), 0) INTO v_balance
                FROM journal_entry_details jed
                JOIN journal_entries je ON je.id = jed.journal_entry_id
                WHERE jed.chart_of_account_id = p_account_id AND je.status = 'posted'
                AND je.entry_date > v_snapshot_date AND je.entry_date <= p_as_of_date;
                RETURN v_balance;
            END;
            \$\$ LANGUAGE plpgsql;
        "));
    }

    private function syncPGTriggers(): void
    {
        /** @var array<string, array{table: string, sql: string}> $triggers */
        $triggers = [
            'trg_journal_balance' => [
                'table' => 'journal_entry_details',
                'sql' => '
                    DROP TRIGGER IF EXISTS trg_journal_balance ON journal_entry_details;
                    CREATE CONSTRAINT TRIGGER trg_journal_balance
                    AFTER INSERT OR UPDATE OR DELETE ON journal_entry_details
                    DEFERRABLE INITIALLY DEFERRED
                    FOR EACH ROW EXECUTE FUNCTION check_journal_balance();
                ',
            ],
            'trg_leaf_account_only' => [
                'table' => 'journal_entry_details',
                'sql' => '
                    DROP TRIGGER IF EXISTS trg_leaf_account_only ON journal_entry_details;
                    CREATE TRIGGER trg_leaf_account_only
                    BEFORE INSERT OR UPDATE ON journal_entry_details
                    FOR EACH ROW EXECUTE FUNCTION check_leaf_account_only();
                ',
            ],
            'trg_check_accounting_period' => [
                'table' => 'journal_entries',
                'sql' => '
                    DROP TRIGGER IF EXISTS trg_check_accounting_period ON journal_entries;
                    CREATE TRIGGER trg_check_accounting_period
                    BEFORE INSERT OR UPDATE ON journal_entries
                    FOR EACH ROW EXECUTE FUNCTION check_accounting_period();
                ',
            ],
            'trg_single_base_currency' => [
                'table' => 'currencies',
                'sql' => '
                    DROP TRIGGER IF EXISTS trg_single_base_currency ON currencies;
                    CREATE TRIGGER trg_single_base_currency
                    BEFORE INSERT OR UPDATE ON currencies
                    FOR EACH ROW EXECUTE FUNCTION check_single_base_currency();
                ',
            ],
            'trg_block_posted_journal_updates' => [
                'table' => 'journal_entries',
                'sql' => '
                    DROP TRIGGER IF EXISTS trg_block_posted_journal_updates ON journal_entries;
                    CREATE TRIGGER trg_block_posted_journal_updates
                    BEFORE UPDATE ON journal_entries
                    FOR EACH ROW EXECUTE FUNCTION block_posted_journal_changes();
                ',
            ],
            'trg_block_posted_journal_deletes' => [
                'table' => 'journal_entries',
                'sql' => '
                    DROP TRIGGER IF EXISTS trg_block_posted_journal_deletes ON journal_entries;
                    CREATE TRIGGER trg_block_posted_journal_deletes
                    BEFORE DELETE ON journal_entries
                    FOR EACH ROW EXECUTE FUNCTION block_posted_journal_deletes();
                ',
            ],
            'trg_block_posted_detail_updates' => [
                'table' => 'journal_entry_details',
                'sql' => '
                    DROP TRIGGER IF EXISTS trg_block_posted_detail_updates ON journal_entry_details;
                    CREATE TRIGGER trg_block_posted_detail_updates
                    BEFORE UPDATE ON journal_entry_details
                    FOR EACH ROW EXECUTE FUNCTION block_posted_detail_changes();
                ',
            ],
            'trg_block_posted_detail_deletes' => [
                'table' => 'journal_entry_details',
                'sql' => '
                    DROP TRIGGER IF EXISTS trg_block_posted_detail_deletes ON journal_entry_details;
                    CREATE TRIGGER trg_block_posted_detail_deletes
                    BEFORE DELETE ON journal_entry_details
                    FOR EACH ROW EXECUTE FUNCTION block_posted_detail_changes();
                ',
            ],
            'trg_auto_set_accounting_period' => [
                'table' => 'journal_entries',
                'sql' => '
                    DROP TRIGGER IF EXISTS trg_auto_set_accounting_period ON journal_entries;
                    CREATE TRIGGER trg_auto_set_accounting_period
                    BEFORE INSERT OR UPDATE ON journal_entries
                    FOR EACH ROW EXECUTE FUNCTION auto_set_accounting_period();
                ',
            ],
            'trg_prevent_hard_delete' => [
                'table' => 'journal_entries',
                'sql' => '
                    DROP TRIGGER IF EXISTS trg_prevent_hard_delete ON journal_entries;
                    CREATE TRIGGER trg_prevent_hard_delete
                    BEFORE DELETE ON journal_entries
                    FOR EACH ROW EXECUTE FUNCTION prevent_hard_delete_posted();
                ',
            ],
        ];

        // Audit triggers for each table
        $auditTables = [
            'chart_of_accounts', 'journal_entries', 'journal_entry_details',
            'accounting_periods', 'account_types', 'cost_centers',
        ];
        foreach ($auditTables as $auditTable) {
            $triggerName = "trg_audit_{$auditTable}";
            $triggers[$triggerName] = [
                'table' => $auditTable,
                'sql' => "
                    DROP TRIGGER IF EXISTS {$triggerName} ON {$auditTable};
                    CREATE TRIGGER {$triggerName}
                    AFTER INSERT OR UPDATE OR DELETE ON {$auditTable}
                    FOR EACH ROW EXECUTE FUNCTION audit_accounting_changes();
                ",
            ];
        }

        foreach ($triggers as $name => $def) {
            $this->syncPGObject('TRIGGER', $name, function () use ($def): void {
                DB::unprepared($def['sql']);
            }, $def['table']);
        }
    }

    private function syncPGObject(string $type, string $name, callable $creator, ?string $table = null): void
    {
        try {
            $exists = $this->pgObjectExists($type, $name, $table);
            $creator();
            $this->results[$name] = $exists ? 'Recreated' : 'Created';
            $this->line("  <info>OK</info>  {$type} <comment>{$name}</comment>");
        } catch (\Throwable $e) {
            $this->results[$name] = 'FAILED: '.$e->getMessage();
            $this->line("  <error>FAIL</error> {$type} <comment>{$name}</comment>: {$e->getMessage()}");
        }
    }

    private function pgObjectExists(string $type, string $name, ?string $table = null): bool
    {
        return match (strtoupper($type)) {
            'FUNCTION' => DB::selectOne(
                "SELECT 1 FROM pg_proc p JOIN pg_namespace n ON n.oid = p.pronamespace WHERE n.nspname = 'public' AND p.proname = ?",
                [$name]
            ) !== null,
            'TRIGGER' => $table !== null && DB::selectOne(
                "SELECT 1 FROM information_schema.triggers WHERE trigger_schema = 'public' AND trigger_name = ? AND event_object_table = ?",
                [$name, $table]
            ) !== null,
            'VIEW' => DB::selectOne(
                "SELECT 1 FROM information_schema.views WHERE table_schema = 'public' AND table_name = ?",
                [$name]
            ) !== null,
            default => false,
        };
    }

    // ──────────────────────────────────────────
    // Shared (both drivers)
    // ──────────────────────────────────────────

    private function syncViews(): void
    {
        foreach ($this->getViewDefinitions() as $name => $sql) {
            $driver = DB::connection()->getDriverName();

            if (in_array($driver, ['mysql', 'mariadb'])) {
                $this->syncMySQLObject('VIEW', $name, fn () => DB::statement($sql));
            } else {
                $this->syncPGObject('VIEW', $name, fn () => DB::statement($sql));
            }
        }
    }

    /** @return array<string, string> */
    private function getViewDefinitions(): array
    {
        return [
            'vw_trial_balance' => "
                CREATE OR REPLACE VIEW vw_trial_balance AS
                SELECT
                    COALESCE(SUM(jed.debit), 0) AS total_debits,
                    COALESCE(SUM(jed.credit), 0) AS total_credits,
                    COALESCE(SUM(jed.debit), 0) - COALESCE(SUM(jed.credit), 0) AS difference
                FROM journal_entry_details jed
                JOIN journal_entries je ON je.id = jed.journal_entry_id
                WHERE je.status = 'posted'
            ",
            'vw_account_balances' => "
                CREATE OR REPLACE VIEW vw_account_balances AS
                SELECT
                    a.id AS account_id,
                    a.account_code,
                    a.account_name,
                    at.type_name AS account_type,
                    at.report_group,
                    a.normal_balance,
                    COALESCE(SUM(d.debit), 0) AS total_debits,
                    COALESCE(SUM(d.credit), 0) AS total_credits,
                    CASE
                        WHEN a.normal_balance = 'debit' THEN COALESCE(SUM(d.debit), 0) - COALESCE(SUM(d.credit), 0)
                        WHEN a.normal_balance = 'credit' THEN COALESCE(SUM(d.credit), 0) - COALESCE(SUM(d.debit), 0)
                        ELSE 0
                    END AS balance,
                    a.is_group,
                    a.is_active
                FROM chart_of_accounts a
                JOIN account_types at ON at.id = a.account_type_id
                LEFT JOIN journal_entry_details d ON d.chart_of_account_id = a.id
                LEFT JOIN journal_entries je ON je.id = d.journal_entry_id AND je.status = 'posted'
                WHERE a.is_active = true
                GROUP BY a.id, a.account_code, a.account_name, at.type_name, at.report_group,
                         a.normal_balance, a.is_group, a.is_active
            ",
            'vw_general_ledger' => 'CREATE OR REPLACE VIEW vw_general_ledger AS
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
                ORDER BY je.entry_date, je.id, jed.line_no
            ',
            'vw_balance_sheet' => "
                CREATE OR REPLACE VIEW vw_balance_sheet AS
                SELECT
                    a.id AS account_id,
                    a.account_code,
                    a.account_name,
                    at.type_name AS account_type,
                    at.report_group,
                    a.normal_balance,
                    CASE
                        WHEN a.normal_balance = 'debit' THEN COALESCE(SUM(d.debit), 0) - COALESCE(SUM(d.credit), 0)
                        WHEN a.normal_balance = 'credit' THEN COALESCE(SUM(d.credit), 0) - COALESCE(SUM(d.debit), 0)
                        ELSE 0
                    END AS balance
                FROM chart_of_accounts a
                JOIN account_types at ON at.id = a.account_type_id
                LEFT JOIN journal_entry_details d ON d.chart_of_account_id = a.id
                LEFT JOIN journal_entries je ON je.id = d.journal_entry_id AND je.status = 'posted'
                WHERE at.report_group = 'BalanceSheet'
                AND a.is_active = true
                GROUP BY a.id, a.account_code, a.account_name, at.type_name, at.report_group, a.normal_balance
                HAVING COALESCE(SUM(d.debit), 0) <> 0 OR COALESCE(SUM(d.credit), 0) <> 0
            ",
            'vw_income_statement' => "
                CREATE OR REPLACE VIEW vw_income_statement AS
                SELECT
                    a.id AS account_id,
                    a.account_code,
                    a.account_name,
                    at.type_name AS account_type,
                    at.report_group,
                    a.normal_balance,
                    CASE
                        WHEN a.normal_balance = 'debit' THEN COALESCE(SUM(d.debit), 0) - COALESCE(SUM(d.credit), 0)
                        WHEN a.normal_balance = 'credit' THEN COALESCE(SUM(d.credit), 0) - COALESCE(SUM(d.debit), 0)
                        ELSE 0
                    END AS balance
                FROM chart_of_accounts a
                JOIN account_types at ON at.id = a.account_type_id
                LEFT JOIN journal_entry_details d ON d.chart_of_account_id = a.id
                LEFT JOIN journal_entries je ON je.id = d.journal_entry_id AND je.status = 'posted'
                WHERE at.report_group = 'IncomeStatement'
                AND a.is_active = true
                GROUP BY a.id, a.account_code, a.account_name, at.type_name, at.report_group, a.normal_balance
                HAVING COALESCE(SUM(d.debit), 0) <> 0 OR COALESCE(SUM(d.credit), 0) <> 0
            ",
        ];
    }

    /** @return array<string, string> */
    public function getResults(): array
    {
        return $this->results;
    }
}
