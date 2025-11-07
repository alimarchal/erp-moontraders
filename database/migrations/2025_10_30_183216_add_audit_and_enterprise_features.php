<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Adds enterprise-grade features:
     * 1. Comprehensive audit trail for all accounting data changes
     * 2. Soft deletes for draft journal entries
     * 3. Period-end balance snapshots for performance
     * 4. Reversing entry reference tracking
     */
    public function up(): void
    {
        // 1. AUDIT TRAIL TABLE - Track all changes to accounting data
        Schema::create('accounting_audit_log', function (Blueprint $table) {
            $table->id();
            $table->string('table_name')->index()->comment('Table being audited');
            $table->unsignedBigInteger('record_id')->index()->comment('ID of the record changed');
            $table->string('action', 20)->comment('INSERT, UPDATE, DELETE');
            $table->json('old_values')->nullable()->comment('Previous values (for UPDATE/DELETE)');
            $table->json('new_values')->nullable()->comment('New values (for INSERT/UPDATE)');
            $table->json('changed_fields')->nullable()->comment('List of fields that changed');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null')->comment('User who made the change');
            $table->ipAddress('ip_address')->nullable()->comment('IP address of the user');
            $table->text('user_agent')->nullable()->comment('Browser/client information');
            $table->timestamp('created_at')->useCurrent()->comment('When the change occurred');

            $table->index(['table_name', 'record_id']);
            $table->index(['created_at']);
            $table->index(['user_id', 'created_at']);
        });

        // 2. SOFT DELETES - Add deleted_at to draft-capable tables
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at')->comment('Soft delete timestamp (only for draft entries)');
            $table->foreignId('deleted_by')->nullable()->after('deleted_at')
                ->constrained('users')->onDelete('set null')
                ->comment('User who deleted the entry');
        });

        // 3. BALANCE SNAPSHOTS - For performance with large datasets
        Schema::create('account_balance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chart_of_account_id')->constrained('chart_of_accounts')->onDelete('cascade');
            $table->foreignId('accounting_period_id')->constrained()->onDelete('cascade');
            $table->date('snapshot_date')->comment('Date of the snapshot');
            $table->decimal('opening_balance', 15, 2)->default(0)->comment('Balance at start of period');
            $table->decimal('period_debits', 15, 2)->default(0)->comment('Total debits in period');
            $table->decimal('period_credits', 15, 2)->default(0)->comment('Total credits in period');
            $table->decimal('closing_balance', 15, 2)->default(0)->comment('Balance at end of period');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['chart_of_account_id', 'accounting_period_id'], 'idx_uniq_acct_period');
            $table->index(['snapshot_date'], 'idx_snapshot_date');
            $table->index(['accounting_period_id', 'snapshot_date'], 'idx_period_snapshot_date');
        });

        // 4. REVERSING ENTRIES - Track reversal relationships
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->foreignId('reverses_entry_id')->nullable()->after('reference')
                ->constrained('journal_entries')->onDelete('restrict')
                ->comment('If this entry reverses another entry');
            $table->foreignId('reversed_by_entry_id')->nullable()->after('reverses_entry_id')
                ->constrained('journal_entries')->onDelete('restrict')
                ->comment('If this entry was reversed by another entry');
            $table->timestamp('reversed_at')->nullable()->after('reversed_by_entry_id')
                ->comment('When this entry was reversed');

            $table->index('reverses_entry_id');
            $table->index('reversed_by_entry_id');
        });

        // 5. ADD AUDIT TRIGGERS (PostgreSQL)
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            $this->createPostgreSQLAuditTriggers();
            $this->createPostgreSQLSoftDeleteProtection();
            $this->createPostgreSQLSnapshotHelpers();
        } elseif (in_array($driver, ['mysql', 'mariadb'])) {
            $this->createMySQLAuditTriggers();
            $this->createMySQLSoftDeleteProtection();
            $this->createMySQLSnapshotHelpers();
        }
    }

    /**
     * Create PostgreSQL audit triggers
     */
    private function createPostgreSQLAuditTriggers(): void
    {
        // Generic audit function that works for any table
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
                -- Get current user context from application
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
                    v_new_values := NULL;
                    v_changed_fields := NULL;
                    
                    INSERT INTO accounting_audit_log (
                        table_name, record_id, action, 
                        old_values, new_values, changed_fields, user_id, ip_address, user_agent
                    ) VALUES (
                        TG_TABLE_NAME, OLD.id, 'DELETE',
                        v_old_values, v_new_values, v_changed_fields, v_user_id, v_ip_address, v_user_agent
                    );
                    
                    RETURN OLD;
                    
                ELSIF TG_OP = 'UPDATE' THEN
                    v_old_values := row_to_json(OLD);
                    v_new_values := row_to_json(NEW);
                    
                    -- Build list of changed fields
                    v_changed_fields := (
                        SELECT json_agg(key)
                        FROM json_each_text(v_new_values) new_val
                        JOIN json_each_text(v_old_values) old_val USING (key)
                        WHERE new_val.value IS DISTINCT FROM old_val.value
                    );
                    
                    -- Only log if something actually changed
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
                    v_old_values := NULL;
                    v_new_values := row_to_json(NEW);
                    v_changed_fields := NULL;
                    
                    INSERT INTO accounting_audit_log (
                        table_name, record_id, action,
                        old_values, new_values, changed_fields, user_id, ip_address, user_agent
                    ) VALUES (
                        TG_TABLE_NAME, NEW.id, 'INSERT',
                        v_old_values, v_new_values, v_changed_fields, v_user_id, v_ip_address, v_user_agent
                    );
                    
                    RETURN NEW;
                END IF;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        // Apply audit triggers to key tables
        $auditTables = [
            'chart_of_accounts',
            'journal_entries',
            'journal_entry_details',
            'accounting_periods',
            'account_types',
            'cost_centers'
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

    /**
     * Create soft delete protection (can only delete drafts)
     */
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

        DB::unprepared("
            DROP TRIGGER IF EXISTS trg_prevent_hard_delete ON journal_entries;
            CREATE TRIGGER trg_prevent_hard_delete
            BEFORE DELETE ON journal_entries
            FOR EACH ROW
            EXECUTE FUNCTION prevent_hard_delete_posted();
        ");
    }

    /**
     * Create snapshot helper functions
     */
    private function createPostgreSQLSnapshotHelpers(): void
    {
        // Function to create snapshots for a period
        DB::unprepared("
            CREATE OR REPLACE FUNCTION sp_create_period_snapshots(p_period_id BIGINT)
            RETURNS TABLE (
                accounts_processed INT,
                snapshot_date DATE
            ) AS \$\$
            DECLARE
                v_period_start DATE;
                v_period_end DATE;
                v_accounts_processed INT := 0;
            BEGIN
                -- Get period dates
                SELECT start_date, end_date INTO v_period_start, v_period_end
                FROM accounting_periods
                WHERE id = p_period_id;

                -- Create snapshots for all active accounts
                INSERT INTO account_balance_snapshots (
                    chart_of_account_id,
                    accounting_period_id,
                    snapshot_date,
                    opening_balance,
                    period_debits,
                    period_credits,
                    closing_balance,
                    created_by
                )
                SELECT 
                    a.id,
                    p_period_id,
                    v_period_end,
                    COALESCE(prev.closing_balance, 0) as opening_balance,
                    COALESCE(SUM(jed.debit), 0) as period_debits,
                    COALESCE(SUM(jed.credit), 0) as period_credits,
                    COALESCE(prev.closing_balance, 0) + 
                    COALESCE(SUM(jed.debit), 0) - 
                    COALESCE(SUM(jed.credit), 0) as closing_balance,
                    current_setting('app.current_user_id', TRUE)::BIGINT
                FROM chart_of_accounts a
                LEFT JOIN account_balance_snapshots prev ON (
                    prev.chart_of_account_id = a.id 
                    AND prev.snapshot_date < v_period_start
                )
                LEFT JOIN journal_entry_details jed ON jed.chart_of_account_id = a.id
                LEFT JOIN journal_entries je ON (
                    je.id = jed.journal_entry_id 
                    AND je.status = 'posted'
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

        // Function to get balance using snapshots (fast)
        DB::unprepared("
            CREATE OR REPLACE FUNCTION fn_account_balance_fast(
                p_account_id BIGINT,
                p_as_of_date DATE DEFAULT CURRENT_DATE
            )
            RETURNS DECIMAL(15,2) AS \$\$
            DECLARE
                v_balance DECIMAL(15,2);
                v_snapshot_date DATE;
            BEGIN
                -- Get most recent snapshot before the date
                SELECT closing_balance, snapshot_date 
                INTO v_balance, v_snapshot_date
                FROM account_balance_snapshots
                WHERE chart_of_account_id = p_account_id
                AND snapshot_date <= p_as_of_date
                ORDER BY snapshot_date DESC
                LIMIT 1;

                -- If no snapshot, start from zero
                IF v_snapshot_date IS NULL THEN
                    v_balance := 0;
                    v_snapshot_date := '1900-01-01';
                END IF;

                -- Add transactions since snapshot
                SELECT v_balance + COALESCE(SUM(jed.debit - jed.credit), 0)
                INTO v_balance
                FROM journal_entry_details jed
                JOIN journal_entries je ON je.id = jed.journal_entry_id
                WHERE jed.chart_of_account_id = p_account_id
                AND je.status = 'posted'
                AND je.entry_date > v_snapshot_date
                AND je.entry_date <= p_as_of_date;

                RETURN v_balance;
            END;
            \$\$ LANGUAGE plpgsql;
        ");
    }

    /**
     * Create MySQL/MariaDB audit triggers
     */
    private function createMySQLAuditTriggers(): void
    {
        // MySQL/MariaDB: Create triggers for INSERT, UPDATE, DELETE
        $auditTables = [
            'chart_of_accounts',
            'journal_entries',
            'journal_entry_details',
            'accounting_periods',
            'account_types',
            'cost_centers'
        ];

        foreach ($auditTables as $table) {
            try {
                // INSERT trigger
                DB::unprepared("DROP TRIGGER IF EXISTS trg_audit_{$table}_insert");
                DB::unprepared("
                    CREATE TRIGGER trg_audit_{$table}_insert
                    AFTER INSERT ON {$table}
                    FOR EACH ROW
                    BEGIN
                        DECLARE v_user_id BIGINT;
                        DECLARE v_ip_address VARCHAR(45);
                        DECLARE v_user_agent TEXT;
                        
                        SET v_user_id = @current_user_id;
                        SET v_ip_address = @ip_address;
                        SET v_user_agent = @user_agent;
                        
                        INSERT INTO accounting_audit_log (
                            table_name, record_id, action, 
                            old_values, new_values, changed_fields,
                            user_id, ip_address, user_agent, created_at
                        ) VALUES (
                            '{$table}', 
                            NEW.id, 
                            'INSERT',
                            NULL,
                            JSON_OBJECT('id', NEW.id),
                            NULL,
                            v_user_id,
                            v_ip_address,
                            v_user_agent,
                            CURRENT_TIMESTAMP
                        );
                    END
                ");

                // UPDATE trigger
                DB::unprepared("DROP TRIGGER IF EXISTS trg_audit_{$table}_update");
                DB::unprepared("
                    CREATE TRIGGER trg_audit_{$table}_update
                    AFTER UPDATE ON {$table}
                    FOR EACH ROW
                    BEGIN
                        DECLARE v_user_id BIGINT;
                        DECLARE v_ip_address VARCHAR(45);
                        DECLARE v_user_agent TEXT;
                        
                        SET v_user_id = @current_user_id;
                        SET v_ip_address = @ip_address;
                        SET v_user_agent = @user_agent;
                        
                        INSERT INTO accounting_audit_log (
                            table_name, record_id, action, 
                            old_values, new_values, changed_fields,
                            user_id, ip_address, user_agent, created_at
                        ) VALUES (
                            '{$table}', 
                            NEW.id, 
                            'UPDATE',
                            JSON_OBJECT('id', OLD.id),
                            JSON_OBJECT('id', NEW.id),
                            JSON_ARRAY('updated'),
                            v_user_id,
                            v_ip_address,
                            v_user_agent,
                            CURRENT_TIMESTAMP
                        );
                    END
                ");

                // DELETE trigger
                DB::unprepared("DROP TRIGGER IF EXISTS trg_audit_{$table}_delete");
                DB::unprepared("
                    CREATE TRIGGER trg_audit_{$table}_delete
                    AFTER DELETE ON {$table}
                    FOR EACH ROW
                    BEGIN
                        DECLARE v_user_id BIGINT;
                        DECLARE v_ip_address VARCHAR(45);
                        DECLARE v_user_agent TEXT;
                        
                        SET v_user_id = @current_user_id;
                        SET v_ip_address = @ip_address;
                        SET v_user_agent = @user_agent;
                        
                        INSERT INTO accounting_audit_log (
                            table_name, record_id, action, 
                            old_values, new_values, changed_fields,
                            user_id, ip_address, user_agent, created_at
                        ) VALUES (
                            '{$table}', 
                            OLD.id, 
                            'DELETE',
                            JSON_OBJECT('id', OLD.id),
                            NULL,
                            NULL,
                            v_user_id,
                            v_ip_address,
                            v_user_agent,
                            CURRENT_TIMESTAMP
                        );
                    END
                ");
            } catch (\Exception $e) {
                \Log::warning("Skipped audit triggers for {$table}: " . $e->getMessage());
            }
        }
    }

    /**
     * MySQL/MariaDB soft delete protection
     */
    private function createMySQLSoftDeleteProtection(): void
    {
        try {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_mysql_prevent_hard_delete");
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
            \Log::warning('MySQL/MariaDB soft delete protection not created: ' . $e->getMessage());
        }
    }

    /**
     * Create MySQL/MariaDB snapshot helper stored procedures
     */
    private function createMySQLSnapshotHelpers(): void
    {
        try {
            // Stored procedure to create period snapshots
            DB::unprepared("DROP PROCEDURE IF EXISTS sp_create_period_snapshots");
            DB::unprepared("
                CREATE PROCEDURE sp_create_period_snapshots(IN p_period_id BIGINT)
                BEGIN
                    DECLARE v_period_start DATE;
                    DECLARE v_period_end DATE;
                    
                    -- Get period dates
                    SELECT start_date, end_date INTO v_period_start, v_period_end
                    FROM accounting_periods
                    WHERE id = p_period_id;
                    
                    -- Create or update snapshots for all active accounts
                    INSERT INTO account_balance_snapshots (
                        chart_of_account_id,
                        accounting_period_id,
                        snapshot_date,
                        opening_balance,
                        period_debits,
                        period_credits,
                        closing_balance,
                        created_by,
                        created_at,
                        updated_at
                    )
                    SELECT 
                        a.id,
                        p_period_id,
                        v_period_end,
                        COALESCE(prev.closing_balance, 0) as opening_balance,
                        COALESCE(SUM(jed.debit), 0) as period_debits,
                        COALESCE(SUM(jed.credit), 0) as period_credits,
                        COALESCE(prev.closing_balance, 0) + 
                        COALESCE(SUM(jed.debit), 0) - 
                        COALESCE(SUM(jed.credit), 0) as closing_balance,
                        @current_user_id,
                        CURRENT_TIMESTAMP,
                        CURRENT_TIMESTAMP
                    FROM chart_of_accounts a
                    LEFT JOIN (
                        SELECT chart_of_account_id, closing_balance
                        FROM account_balance_snapshots
                        WHERE snapshot_date < v_period_start
                        ORDER BY snapshot_date DESC
                        LIMIT 1
                    ) prev ON prev.chart_of_account_id = a.id
                    LEFT JOIN journal_entry_details jed ON jed.chart_of_account_id = a.id
                    LEFT JOIN journal_entries je ON (
                        je.id = jed.journal_entry_id 
                        AND je.status = 'posted'
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

            // Function to get account balance using snapshots
            DB::unprepared("DROP FUNCTION IF EXISTS fn_account_balance_fast");
            DB::unprepared("
                CREATE FUNCTION fn_account_balance_fast(
                    p_account_id BIGINT,
                    p_as_of_date DATE
                )
                RETURNS DECIMAL(15,2)
                READS SQL DATA
                BEGIN
                    DECLARE v_balance DECIMAL(15,2) DEFAULT 0;
                    DECLARE v_snapshot_date DATE DEFAULT '1900-01-01';
                    
                    -- Get most recent snapshot before the date
                    SELECT COALESCE(closing_balance, 0), COALESCE(snapshot_date, '1900-01-01')
                    INTO v_balance, v_snapshot_date
                    FROM account_balance_snapshots
                    WHERE chart_of_account_id = p_account_id
                    AND snapshot_date <= p_as_of_date
                    ORDER BY snapshot_date DESC
                    LIMIT 1;
                    
                    -- Add transactions since snapshot
                    SELECT v_balance + COALESCE(SUM(jed.debit - jed.credit), 0)
                    INTO v_balance
                    FROM journal_entry_details jed
                    JOIN journal_entries je ON je.id = jed.journal_entry_id
                    WHERE jed.chart_of_account_id = p_account_id
                    AND je.status = 'posted'
                    AND je.entry_date > v_snapshot_date
                    AND je.entry_date <= p_as_of_date;
                    
                    RETURN v_balance;
                END
            ");
        } catch (\Exception $e) {
            \Log::warning('MySQL/MariaDB snapshot helpers not created: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        // Drop triggers and functions based on database driver
        if ($driver === 'pgsql') {
            // PostgreSQL cleanup
            $tables = [
                'chart_of_accounts',
                'journal_entries',
                'journal_entry_details',
                'accounting_periods',
                'account_types',
                'cost_centers'
            ];
            foreach ($tables as $table) {
                DB::unprepared("DROP TRIGGER IF EXISTS trg_audit_{$table} ON {$table}");
            }
            DB::unprepared("DROP TRIGGER IF EXISTS trg_prevent_hard_delete ON journal_entries");
            DB::unprepared("DROP FUNCTION IF EXISTS audit_accounting_changes() CASCADE");
            DB::unprepared("DROP FUNCTION IF EXISTS prevent_hard_delete_posted() CASCADE");
            DB::unprepared("DROP FUNCTION IF EXISTS sp_create_period_snapshots(BIGINT) CASCADE");
            DB::unprepared("DROP FUNCTION IF EXISTS fn_account_balance_fast(BIGINT, DATE) CASCADE");
        } elseif (in_array($driver, ['mysql', 'mariadb'])) {
            // MySQL/MariaDB cleanup
            $tables = [
                'chart_of_accounts',
                'journal_entries',
                'journal_entry_details',
                'accounting_periods',
                'account_types',
                'cost_centers'
            ];

            foreach ($tables as $table) {
                try {
                    DB::unprepared("DROP TRIGGER IF EXISTS trg_audit_{$table}_insert");
                    DB::unprepared("DROP TRIGGER IF EXISTS trg_audit_{$table}_update");
                    DB::unprepared("DROP TRIGGER IF EXISTS trg_audit_{$table}_delete");
                } catch (\Exception $e) {
                    \Log::warning("Could not drop triggers for {$table}: " . $e->getMessage());
                }
            }

            try {
                DB::unprepared("DROP TRIGGER IF EXISTS trg_mysql_prevent_hard_delete");
                DB::unprepared("DROP PROCEDURE IF EXISTS sp_create_period_snapshots");
                DB::unprepared("DROP FUNCTION IF EXISTS fn_account_balance_fast");
            } catch (\Exception $e) {
                \Log::warning("Could not drop MySQL objects: " . $e->getMessage());
            }
        }

        // Drop columns from journal_entries
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropSoftDeletes();

            if (Schema::hasColumn('journal_entries', 'deleted_by')) {
                $table->dropForeign(['deleted_by']);
                $table->dropColumn('deleted_by');
            }

            if (Schema::hasColumn('journal_entries', 'reverses_entry_id')) {
                $table->dropForeign(['reverses_entry_id']);
                $table->dropColumn('reverses_entry_id');
            }

            if (Schema::hasColumn('journal_entries', 'reversed_by_entry_id')) {
                $table->dropForeign(['reversed_by_entry_id']);
                $table->dropColumn('reversed_by_entry_id');
            }

            if (Schema::hasColumn('journal_entries', 'reversed_at')) {
                $table->dropColumn('reversed_at');
            }
        });

        // Drop tables
        Schema::dropIfExists('account_balance_snapshots');
        Schema::dropIfExists('accounting_audit_log');
    }
};
