<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create test user
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Seed accounting data in the correct order
        $this->call([
            AccountTypeSeeder::class,
            CurrencySeeder::class,              // Add currencies before accounts
            AccountingPeriodSeeder::class,
            ChartOfAccountSeeder::class,
            CostCenterSeeder::class,            // Add cost centers before journal entries
            CompanySeeder::class,               // Add company after currencies and cost centers
            WarehouseTypeSeeder::class,         // Add warehouse types after companies
            SupplierSeeder::class,              // Add suppliers after currencies
            JournalEntrySeeder::class,
            JournalEntryDetailSeeder::class,
            AttachmentSeeder::class,            // Add attachments last
        ]);
    }
}
