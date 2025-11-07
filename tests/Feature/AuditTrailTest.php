<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run the audit migration
        $this->artisan('migrate');
    }

    /**
     * Test that audit log table exists
     */
    public function test_audit_log_table_exists(): void
    {
        $this->assertTrue(
            DB::getSchemaBuilder()->hasTable('accounting_audit_log'),
            'Accounting audit log table should exist'
        );
    }

    /**
     * Test that account_balance_snapshots table exists
     */
    public function test_snapshots_table_exists(): void
    {
        $this->assertTrue(
            DB::getSchemaBuilder()->hasTable('account_balance_snapshots'),
            'Account balance snapshots table should exist'
        );
    }

    /**
     * Test manual audit logging
     */
    public function test_manual_audit_logging(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Insert a manual audit log entry
        DB::table('accounting_audit_log')->insert([
            'table_name' => 'test_table',
            'record_id' => 1,
            'action' => 'TEST',
            'old_values' => json_encode(['test' => 'old']),
            'new_values' => json_encode(['test' => 'new']),
            'changed_fields' => json_encode(['test']),
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('accounting_audit_log', [
            'table_name' => 'test_table',
            'record_id' => 1,
            'action' => 'TEST',
            'user_id' => $user->id,
        ]);
    }

    /**
     * Test database driver detection
     */
    public function test_database_driver_detection(): void
    {
        $driver = DB::connection()->getDriverName();

        $this->assertContains(
            $driver,
            ['pgsql', 'mysql', 'mariadb', 'sqlite'],
            "Database driver should be one of the supported types, got: {$driver}"
        );
    }

    /**
     * Test that stored procedures/functions exist based on driver
     */
    public function test_stored_procedures_exist(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // Check PostgreSQL functions exist
            $functions = DB::select("
                SELECT routine_name 
                FROM information_schema.routines 
                WHERE routine_schema = 'public' 
                AND routine_type = 'FUNCTION'
                AND routine_name IN ('audit_accounting_changes', 'prevent_hard_delete_posted', 'fn_account_balance_fast', 'sp_create_period_snapshots')
            ");

            $this->assertGreaterThanOrEqual(
                4,
                count($functions),
                'PostgreSQL should have at least 4 audit functions'
            );
        } elseif (in_array($driver, ['mysql', 'mariadb'])) {
            // Check MySQL/MariaDB procedures exist
            $procedures = DB::select("
                SHOW PROCEDURE STATUS 
                WHERE Db = DATABASE() 
                AND Name = 'sp_create_period_snapshots'
            ");

            $this->assertGreaterThanOrEqual(
                1,
                count($procedures),
                'MySQL/MariaDB should have snapshot procedure'
            );

            $functions = DB::select("
                SHOW FUNCTION STATUS 
                WHERE Db = DATABASE() 
                AND Name = 'fn_account_balance_fast'
            ");

            $this->assertGreaterThanOrEqual(
                1,
                count($functions),
                'MySQL/MariaDB should have balance function'
            );
        }

        $this->assertTrue(true, 'Stored procedures check passed for ' . $driver);
    }

    /**
     * Test audit context can be set
     */
    public function test_audit_context_setting(): void
    {
        $user = User::factory()->create();
        $driver = DB::connection()->getDriverName();

        try {
            if ($driver === 'pgsql') {
                DB::statement("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
                DB::statement("SELECT set_config('app.ip_address', ?, false)", ['192.168.1.1']);
                DB::statement("SELECT set_config('app.user_agent', ?, false)", ['Test Browser']);

                // Try to retrieve the values
                $userId = DB::selectOne("SELECT current_setting('app.current_user_id', true) as value");
                $this->assertEquals($user->id, $userId->value);
            } elseif (in_array($driver, ['mysql', 'mariadb'])) {
                DB::statement("SET @current_user_id = ?", [$user->id]);
                DB::statement("SET @ip_address = ?", ['192.168.1.1']);
                DB::statement("SET @user_agent = ?", ['Test Browser']);

                // Try to retrieve the values
                $result = DB::selectOne("SELECT @current_user_id as user_id");
                $this->assertEquals($user->id, $result->user_id);
            }

            $this->assertTrue(true, 'Audit context can be set for ' . $driver);
        } catch (\Exception $e) {
            $this->fail('Failed to set audit context: ' . $e->getMessage());
        }
    }
}
