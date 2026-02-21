<?php

namespace Database\Seeders;

use App\Models\Product;
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

        if (! file_exists($jsonPath)) {
            $this->command->warn('⚠️  sku.json not found. Skipping product seeding.');

            return;
        }

        $json = file_get_contents($jsonPath);
        $products = json_decode($json, true);

        if (! $products || ! is_array($products)) {
            $this->command->error('❌ Invalid JSON format in sku.json');

            return;
        }

        $this->command->info('📦 Seeding '.count($products).' products from sku.json...');

        DB::beginTransaction();

        try {
            foreach ($products as $productData) {
                // Ensure required fields are present
                if (empty($productData['product_code']) || empty($productData['product_name'])) {
                    continue;
                }

                // Validate foreign keys exist before using them
                $supplierId = $productData['supplier_id'] ?? null;
                if ($supplierId && ! \App\Models\Supplier::find($supplierId)) {
                    $supplierId = null;
                }

                $uomId = $productData['uom_id'] ?? 1;
                if ($uomId && ! \App\Models\Uom::find($uomId)) {
                    $uomId = null;
                }

                $salesUomId = $productData['sales_uom_id'] ?? null;
                if ($salesUomId && ! \App\Models\Uom::find($salesUomId)) {
                    $salesUomId = null;
                }

                Product::create([
                    'product_code' => $productData['product_code'],
                    'product_name' => $productData['product_name'],
                    'description' => $productData['description'] ?? null,
                    'supplier_id' => $supplierId,
                    'brand' => $productData['brand'] ?? null,
                    'pack_size' => $productData['pack_size'] ?? null,
                    'uom_id' => $uomId,
                    'sales_uom_id' => $salesUomId,
                    'uom_conversion_factor' => $productData['uom_conversion_factor'] ?? 1,
                    'cost_price' => $productData['cost_price'] ?? 0,
                    'unit_sell_price' => $productData['unit_sell_price'] ?? $productData['unit_sell_price'] ?? 0,
                    'expiry_price' => $productData['expiry_price'] ??  $productData['unit_sell_price'] ?? 0,
                    'valuation_method' => $productData['valuation_method'] ?? 'FIFO',
                    'is_powder' => $productData['is_powder'] ?? false,
                    'is_active' => $productData['is_active'] ?? true,
                ]);
            }

            DB::commit();

            $this->command->info('✅ Successfully seeded '.count($products).' products');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Error seeding products: '.$e->getMessage());
            throw $e;
        }
    }
}
