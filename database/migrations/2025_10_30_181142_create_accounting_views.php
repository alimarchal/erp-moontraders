<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // View for Trial Balance
        DB::statement("
            CREATE OR REPLACE VIEW vw_trial_balance AS
            SELECT
                SUM(debit) AS total_debits,
                SUM(credit) AS total_credits,
                SUM(debit) - SUM(credit) AS difference
            FROM journal_entry_details jed
            JOIN journal_entries je ON je.id = jed.journal_entry_id
            WHERE je.status = 'posted'
        ");

        // View for Account Balances
        DB::statement("
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
                    WHEN a.normal_balance = 'debit' THEN COALESCE(SUM(d.debit - d.credit), 0)
                    WHEN a.normal_balance = 'credit' THEN COALESCE(SUM(d.credit - d.debit), 0)
                END AS balance,
                a.is_group,
                a.is_active
            FROM chart_of_accounts a
            JOIN account_types at ON at.id = a.account_type_id
            LEFT JOIN journal_entry_details d ON d.chart_of_account_id = a.id
            LEFT JOIN journal_entries je ON je.id = d.journal_entry_id AND je.status = 'posted'
            GROUP BY a.id, a.account_code, a.account_name, at.type_name, at.report_group, a.normal_balance, a.is_group, a.is_active
        ");

        // View for General Ledger (Account-wise detail)
        DB::statement("
            CREATE OR REPLACE VIEW vw_general_ledger AS
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
        ");

        // View for Balance Sheet accounts
        DB::statement("
            CREATE OR REPLACE VIEW vw_balance_sheet AS
            SELECT
                a.id AS account_id,
                a.account_code,
                a.account_name,
                at.type_name AS account_type,
                at.report_group,
                a.normal_balance,
                CASE
                    WHEN a.normal_balance = 'debit' THEN COALESCE(SUM(d.debit - d.credit), 0)
                    WHEN a.normal_balance = 'credit' THEN COALESCE(SUM(d.credit - d.debit), 0)
                END AS balance
            FROM chart_of_accounts a
            JOIN account_types at ON at.id = a.account_type_id
            LEFT JOIN journal_entry_details d ON d.chart_of_account_id = a.id
            LEFT JOIN journal_entries je ON je.id = d.journal_entry_id AND je.status = 'posted'
            WHERE at.report_group = 'BalanceSheet'
            AND a.is_active = true
            GROUP BY a.id, a.account_code, a.account_name, at.type_name, at.report_group, a.normal_balance
            HAVING COALESCE(SUM(d.debit), 0) <> 0 OR COALESCE(SUM(d.credit), 0) <> 0
        ");

        // View for Income Statement accounts
        DB::statement("
            CREATE OR REPLACE VIEW vw_income_statement AS
            SELECT
                a.id AS account_id,
                a.account_code,
                a.account_name,
                at.type_name AS account_type,
                at.report_group,
                a.normal_balance,
                CASE
                    WHEN a.normal_balance = 'debit' THEN COALESCE(SUM(d.debit - d.credit), 0)
                    WHEN a.normal_balance = 'credit' THEN COALESCE(SUM(d.credit - d.debit), 0)
                END AS balance
            FROM chart_of_accounts a
            JOIN account_types at ON at.id = a.account_type_id
            LEFT JOIN journal_entry_details d ON d.chart_of_account_id = a.id
            LEFT JOIN journal_entries je ON je.id = d.journal_entry_id AND je.status = 'posted'
            WHERE at.report_group = 'IncomeStatement'
            AND a.is_active = true
            GROUP BY a.id, a.account_code, a.account_name, at.type_name, at.report_group, a.normal_balance
            HAVING COALESCE(SUM(d.debit), 0) <> 0 OR COALESCE(SUM(d.credit), 0) <> 0
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS vw_income_statement");
        DB::statement("DROP VIEW IF EXISTS vw_balance_sheet");
        DB::statement("DROP VIEW IF EXISTS vw_general_ledger");
        DB::statement("DROP VIEW IF EXISTS vw_account_balances");
        DB::statement("DROP VIEW IF EXISTS vw_trial_balance");
    }
};
