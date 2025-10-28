<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class JournalEntrySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        DB::table('journal_entries')->insert([
            [
                'id' => 1,
                'entry_date' => '2025-01-02',
                'description' => 'Initial capital investment by owner',
                'reference' => 'INV-001',
                'status' => 'Posted',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'entry_date' => '2025-01-03',
                'description' => 'Purchase office equipment',
                'reference' => 'PO-001',
                'status' => 'Posted',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'entry_date' => '2025-01-05',
                'description' => 'Paid monthly rent',
                'reference' => 'CHK-001',
                'status' => 'Posted',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 4,
                'entry_date' => '2025-01-08',
                'description' => 'Service revenue from client ABC Corp',
                'reference' => 'INV-1001',
                'status' => 'Posted',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 5,
                'entry_date' => '2025-01-10',
                'description' => 'Purchase office supplies',
                'reference' => 'PO-002',
                'status' => 'Posted',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 6,
                'entry_date' => '2025-01-15',
                'description' => 'Received payment from client ABC Corp',
                'reference' => 'RCT-001',
                'status' => 'Posted',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 7,
                'entry_date' => '2025-01-20',
                'description' => 'Paid utility bills',
                'reference' => 'CHK-002',
                'status' => 'Posted',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 8,
                'entry_date' => '2025-01-25',
                'description' => 'Owner withdrawal',
                'reference' => 'CHK-003',
                'status' => 'Posted',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 9,
                'entry_date' => '2025-01-28',
                'description' => 'Bank loan received',
                'reference' => 'LOAN-001',
                'status' => 'Posted',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 10,
                'entry_date' => '2025-01-30',
                'description' => 'Monthly depreciation expense',
                'reference' => 'DEP-001',
                'status' => 'Posted',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
