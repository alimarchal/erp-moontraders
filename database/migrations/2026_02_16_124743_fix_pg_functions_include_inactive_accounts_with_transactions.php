<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix fn_balance_sheet and fn_income_statement to include inactive accounts
     * that have posted transactions, preventing balance sheet imbalances when
     * accounts are deactivated after journal entries are posted to them.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

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
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

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
                WHERE at.report_group = 'BalanceSheet' AND a.is_active = true
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
                WHERE at.report_group = 'IncomeStatement' AND a.is_active = true
                GROUP BY a.id, a.account_code, a.account_name, at.type_name, at.report_group, a.normal_balance
                HAVING COALESCE(SUM(d.debit), 0) <> 0 OR COALESCE(SUM(d.credit), 0) <> 0
                ORDER BY a.account_code;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_general_ledger(
                p_start_date DATE DEFAULT NULL, p_end_date DATE DEFAULT CURRENT_DATE,
                p_account_id BIGINT DEFAULT NULL
            )
            RETURNS TABLE (
                journal_entry_id BIGINT, entry_date DATE, reference VARCHAR,
                journal_description TEXT, status VARCHAR,
                account_id BIGINT, account_code VARCHAR, account_name VARCHAR,
                line_no INTEGER, debit NUMERIC, credit NUMERIC, line_description TEXT,
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
    }
};
