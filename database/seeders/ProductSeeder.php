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
        $json = file_get_contents(database_path('seeders/data/sku.json'));
        $products = json_decode($json, true);

        foreach ($products as $productData) {
            // Get the category ID by category code
            $category = DB::table('product_categories')
                ->where('category_code', $productData['category_code'])
                ->first();

            // Get the UOM ID by symbol
            $uom = DB::table('uoms')
                ->where('symbol', $productData['uom_symbol'])
                ->first();

            // Create the product
            Product::create([
                'product_code' => $productData['product_code'],
                'product_name' => $productData['product_name'],
                'description' => $productData['description'],
                'category_id' => $category?->id,
                'supplier_id' => $productData['supplier_id'], // Use supplier_id directly from JSON
                'uom_id' => $uom?->id,
                'pack_size' => $productData['pack_size'],
                'brand' => $productData['brand'],
                'weight' => $productData['weight'],
                'is_active' => true,
                'valuation_method' => 'FIFO',
                'reorder_level' => 10,
                'unit_price' => $productData['unit_price'] ?? 0,
                'cost_price' => $productData['cost_price'] ?? 0,
            ]);
        }
    }
}
