<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles and permissions first
        $this->call(RoleAndPermissionSeeder::class);

        // Seed users
        $this->call(UserSeeder::class);

        // Seed accounting data in the correct order
        $this->call([
            AccountTypeSeeder::class,
            CurrencySeeder::class,              // Add currencies before accounts
            AccountingPeriodSeeder::class,
            ChartOfAccountSeeder::class,
            TaxCodeSeeder::class,               // Add tax codes after chart of accounts
            TaxRateSeeder::class,               // Add tax rates after tax codes
            CostCenterSeeder::class,            // Add cost centers before journal entries
            CompanySeeder::class,               // Add company after currencies and cost centers
            WarehouseTypeSeeder::class,         // Add warehouse types after companies
            WarehouseSeeder::class,             // Add warehouses after warehouse types
            SupplierSeeder::class,              // Add suppliers before employees (FK reference)
            CustomerSeeder::class,              // Add customers from Shop List data
            EmployeeSeeder::class,              // Seed employees from master data
            VehicleSeeder::class,               // Seed vehicle master data
            UomSeeder::class,                   // Add units of measurement before products
            ProductSeeder::class,               // Add products from SKU data
            BankAccountSeeder::class,           // Add bank accounts after chart of accounts
            AttachmentSeeder::class,            // Add attachments last
        ]);
    }
}
