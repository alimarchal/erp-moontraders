<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = database_path('seeders/data/sku.json');

        if (!file_exists($jsonPath)) {
            $this->command->warn('⚠️  sku.json not found. Skipping product seeding.');
            return;
        }

        $json = file_get_contents($jsonPath);
        $products = json_decode($json, true);

        if (!$products || !is_array($products)) {
            $this->command->error('❌ Invalid JSON format in sku.json');
            return;
        }

        $this->command->info('📦 Seeding ' . count($products) . ' products from sku.json...');

        DB::beginTransaction();

        try {
            foreach ($products as $productData) {
                // Ensure required fields are present
                if (empty($productData['product_code']) || empty($productData['product_name'])) {
                    continue;
                }

                Product::create([
                    'product_code' => $productData['product_code'],
                    'product_name' => $productData['product_name'],
                    'description' => $productData['description'] ?? null,
                    'supplier_id' => $productData['supplier_id'] ?? null,
                    'brand' => $productData['brand'] ?? null,
                    'pack_size' => $productData['pack_size'] ?? null,
                    'uom_id' => $productData['uom_id'] ?? 1,
                    'sales_uom_id' => $productData['sales_uom_id'] ?? null,
                    'uom_conversion_factor' => $productData['uom_conversion_factor'] ?? 1,
                    'cost_price' => $productData['cost_price'] ?? 0,
                    'unit_sell_price' => $productData['unit_sell_price'] ?? $productData['unit_price'] ?? 0,
                    'valuation_method' => $productData['valuation_method'] ?? 'FIFO',
                    'is_active' => $productData['is_active'] ?? true,
                ]);
            }

            DB::commit();

            $this->command->info('✅ Successfully seeded ' . count($products) . ' products');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Error seeding products: ' . $e->getMessage());
            throw $e;
        }
    }
}
