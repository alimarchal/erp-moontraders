<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Creates database functions/views for accounting reports.
     * PostgreSQL: Creates functions that return result sets
     * MySQL: Skips function creation - controllers use direct queries
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // Only create functions for PostgreSQL
        // MySQL doesn't support table-returning functions like PostgreSQL
        if ($driver === 'pgsql') {
            $this->createPostgresqlFunctions();
        }

        // For MySQL, controllers will use direct Eloquent queries with proper date filtering
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("DROP FUNCTION IF EXISTS fn_income_statement(DATE, DATE)");
            DB::statement("DROP FUNCTION IF EXISTS fn_balance_sheet(DATE)");
            DB::statement("DROP FUNCTION IF EXISTS fn_general_ledger(DATE, DATE, BIGINT)");
            DB::statement("DROP FUNCTION IF EXISTS fn_account_balances(DATE, DATE)");
            DB::statement("DROP FUNCTION IF EXISTS fn_trial_balance_summary(DATE)");
            DB::statement("DROP FUNCTION IF EXISTS fn_trial_balance(DATE)");
        }
    }

    private function createPostgresqlFunctions(): void
    {
        // Function for Trial Balance with date parameter
        DB::statement("
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
            ) AS \$\$
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
                LEFT JOIN journal_entry_details d ON d.chart_of_account_id = a.id
                LEFT JOIN journal_entries je ON je.id = d.journal_entry_id 
                    AND je.status = 'posted'
                    AND je.entry_date <= p_as_of_date
                WHERE a.is_active = true
                GROUP BY a.id, a.account_code, a.account_name, at.type_name, a.normal_balance
                HAVING COALESCE(SUM(d.debit), 0) <> 0 OR COALESCE(SUM(d.credit), 0) <> 0
                ORDER BY a.account_code;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        // Function for Trial Balance Summary
        DB::statement("
            CREATE OR REPLACE FUNCTION fn_trial_balance_summary(p_as_of_date DATE DEFAULT CURRENT_DATE)
            RETURNS TABLE (
                total_debits NUMERIC,
                total_credits NUMERIC,
                difference NUMERIC
            ) AS \$\$
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
            \$\$ LANGUAGE plpgsql;
        ");

        // Function for Account Balances with date range
        DB::statement("
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
            ) AS \$\$
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
                LEFT JOIN journal_entry_details d ON d.chart_of_account_id = a.id
                LEFT JOIN journal_entries je ON je.id = d.journal_entry_id 
                    AND je.status = 'posted'
                    AND (p_start_date IS NULL OR je.entry_date >= p_start_date)
                    AND je.entry_date <= p_end_date
                GROUP BY a.id, a.account_code, a.account_name, at.type_name, a.normal_balance, a.is_group, a.is_active
                ORDER BY a.account_code;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        // Function for General Ledger with date range
        DB::statement("
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
            ) AS \$\$
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
            \$\$ LANGUAGE plpgsql;
        ");

        // Function for Balance Sheet with as-of-date
        DB::statement("
            CREATE OR REPLACE FUNCTION fn_balance_sheet(p_as_of_date DATE DEFAULT CURRENT_DATE)
            RETURNS TABLE (
                account_id BIGINT,
                account_code VARCHAR,
                account_name VARCHAR,
                account_type VARCHAR,
                report_group VARCHAR,
                normal_balance VARCHAR,
                balance NUMERIC
            ) AS \$\$
            BEGIN
                RETURN QUERY
                SELECT
                    a.id AS account_id,
                    a.account_code,
                    a.account_name,
                    at.type_name AS account_type,
                    at.report_group,
                    a.normal_balance,
                    COALESCE(SUM(d.debit - d.credit), 0) AS balance
                FROM chart_of_accounts a
                JOIN account_types at ON at.id = a.account_type_id
                LEFT JOIN journal_entry_details d ON d.chart_of_account_id = a.id
                LEFT JOIN journal_entries je ON je.id = d.journal_entry_id 
                    AND je.status = 'posted'
                    AND je.entry_date <= p_as_of_date
                WHERE at.report_group = 'BalanceSheet'
                AND a.is_active = true
                GROUP BY a.id, a.account_code, a.account_name, at.type_name, at.report_group, a.normal_balance
                HAVING COALESCE(SUM(d.debit), 0) <> 0 OR COALESCE(SUM(d.credit), 0) <> 0
                ORDER BY a.account_code;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        // Function for Income Statement with date range
        DB::statement("
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
            ) AS \$\$
            BEGIN
                RETURN QUERY
                SELECT
                    a.id AS account_id,
                    a.account_code,
                    a.account_name,
                    at.type_name AS account_type,
                    at.report_group,
                    a.normal_balance,
                    COALESCE(SUM(d.debit - d.credit), 0) AS balance
                FROM chart_of_accounts a
                JOIN account_types at ON at.id = a.account_type_id
                LEFT JOIN journal_entry_details d ON d.chart_of_account_id = a.id
                LEFT JOIN journal_entries je ON je.id = d.journal_entry_id 
                    AND je.status = 'posted'
                    AND (p_start_date IS NULL OR je.entry_date >= p_start_date)
                    AND je.entry_date <= p_end_date
                WHERE at.report_group = 'IncomeStatement'
                AND a.is_active = true
                GROUP BY a.id, a.account_code, a.account_name, at.type_name, at.report_group, a.normal_balance
                HAVING COALESCE(SUM(d.debit), 0) <> 0 OR COALESCE(SUM(d.credit), 0) <> 0
                ORDER BY a.account_code;
            END;
            \$\$ LANGUAGE plpgsql;
        ");
    }
};

