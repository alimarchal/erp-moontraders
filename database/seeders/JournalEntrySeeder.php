<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JournalEntrySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        // Get the base currency (PKR) and first user
        $baseCurrency = DB::table('currencies')->where('is_base_currency', true)->first();
        $firstUser = DB::table('users')->first();

        // Get Q1 2025 period (it's closed, so we'll use Q4 2025 which is open)
        $openPeriod = DB::table('accounting_periods')
            ->where('status', 'open')
            ->where('start_date', '<=', '2025-10-30')
            ->where('end_date', '>=', '2025-10-30')
            ->first();

        DB::table('journal_entries')->insert([
            [
                'id' => 1,
                'currency_id' => $baseCurrency->id,
                'accounting_period_id' => $openPeriod->id,
                'fx_rate_to_base' => 1.000000,
                'entry_date' => '2025-10-02',
                'description' => 'Initial capital investment by owner',
                'reference' => 'INV-001',
                'status' => 'posted',
                'posted_at' => $now,
                'posted_by' => $firstUser->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'currency_id' => $baseCurrency->id,
                'accounting_period_id' => $openPeriod->id,
                'fx_rate_to_base' => 1.000000,
                'entry_date' => '2025-10-03',
                'description' => 'Purchase office equipment',
                'reference' => 'PO-001',
                'status' => 'posted',
                'posted_at' => $now,
                'posted_by' => $firstUser->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'currency_id' => $baseCurrency->id,
                'accounting_period_id' => $openPeriod->id,
                'fx_rate_to_base' => 1.000000,
                'entry_date' => '2025-10-05',
                'description' => 'Paid monthly rent',
                'reference' => 'CHK-001',
                'status' => 'posted',
                'posted_at' => $now,
                'posted_by' => $firstUser->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 4,
                'currency_id' => $baseCurrency->id,
                'accounting_period_id' => $openPeriod->id,
                'fx_rate_to_base' => 1.000000,
                'entry_date' => '2025-10-08',
                'description' => 'Service revenue from client ABC Corp',
                'reference' => 'INV-1001',
                'status' => 'posted',
                'posted_at' => $now,
                'posted_by' => $firstUser->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 5,
                'currency_id' => $baseCurrency->id,
                'accounting_period_id' => $openPeriod->id,
                'fx_rate_to_base' => 1.000000,
                'entry_date' => '2025-10-10',
                'description' => 'Purchase office supplies',
                'reference' => 'PO-002',
                'status' => 'posted',
                'posted_at' => $now,
                'posted_by' => $firstUser->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 6,
                'currency_id' => $baseCurrency->id,
                'accounting_period_id' => $openPeriod->id,
                'fx_rate_to_base' => 1.000000,
                'entry_date' => '2025-10-15',
                'description' => 'Received payment from client ABC Corp',
                'reference' => 'RCT-001',
                'status' => 'posted',
                'posted_at' => $now,
                'posted_by' => $firstUser->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 7,
                'currency_id' => $baseCurrency->id,
                'accounting_period_id' => $openPeriod->id,
                'fx_rate_to_base' => 1.000000,
                'entry_date' => '2025-10-20',
                'description' => 'Paid utility bills',
                'reference' => 'CHK-002',
                'status' => 'posted',
                'posted_at' => $now,
                'posted_by' => $firstUser->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 8,
                'currency_id' => $baseCurrency->id,
                'accounting_period_id' => $openPeriod->id,
                'fx_rate_to_base' => 1.000000,
                'entry_date' => '2025-10-25',
                'description' => 'Owner withdrawal',
                'reference' => 'CHK-003',
                'status' => 'posted',
                'posted_at' => $now,
                'posted_by' => $firstUser->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 9,
                'currency_id' => $baseCurrency->id,
                'accounting_period_id' => $openPeriod->id,
                'fx_rate_to_base' => 1.000000,
                'entry_date' => '2025-10-28',
                'description' => 'Bank loan received',
                'reference' => 'LOAN-001',
                'status' => 'posted',
                'posted_at' => $now,
                'posted_by' => $firstUser->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 10,
                'currency_id' => $baseCurrency->id,
                'accounting_period_id' => $openPeriod->id,
                'fx_rate_to_base' => 1.000000,
                'entry_date' => '2025-10-30',
                'description' => 'Monthly depreciation expense',
                'reference' => 'DEP-001',
                'status' => 'posted',
                'posted_at' => $now,
                'posted_by' => $firstUser->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("
                SELECT setval(
                    pg_get_serial_sequence('journal_entries', 'id'),
                    (SELECT COALESCE(MAX(id), 0) FROM journal_entries)
                )
            ");
        } elseif (in_array($driver, ['mysql', 'mariadb'])) {
            $nextId = DB::table('journal_entries')->max('id') + 1;

            if ($nextId < 1) {
                $nextId = 1;
            }

            DB::statement("ALTER TABLE journal_entries AUTO_INCREMENT = {$nextId}");
        }
    }
}
