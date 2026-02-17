<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvPath = database_path('seeders/data/product_categories.csv');

        if (! file_exists($csvPath)) {
            $this->command->warn('⚠️  product_categories.csv not found. Skipping category seeding.');

            return;
        }

        $this->command->info('📦 Seeding categories from product_categories.csv...');

        $file = fopen($csvPath, 'r');
        $header = fgetcsv($file); // Skip header row

        DB::beginTransaction();

        try {
            $count = 0;
            $updatedProducts = 0;

            while (($data = fgetcsv($file)) !== false) {
                // Determine column indices based on header or assume order: Category, SKU
                // data[0] = Category Name
                // data[1] = Product Name / SKU

                $categoryName = trim($data[0]);
                $productName = trim($data[1]);

                if (empty($categoryName) || empty($productName)) {
                    continue;
                }

                // 1. Create or Find Category
                $category = Category::firstOrCreate(
                    ['name' => $categoryName],
                    ['slug' => Str::slug($categoryName), 'is_active' => true]
                );

                // 2. Find Product by Name or Code (SKU)
                // The CSV contains "SKU" column which seems to be the Product Name based on sample data
                // "Nido Powder 12×900g" looks like a name. "DBP 2.5 KG Tin" is code/name.
                $product = Product::where('product_name', $productName)
                    ->orWhere('product_code', $productName)
                    ->first();

                if ($product) {
                    $product->update([
                        'category_id' => $category->id,
                        'is_powder' => strtolower($categoryName) === 'powder',
                    ]);
                    $updatedProducts++;
                } else {
                    // Optional: Log missing products
                    // $this->command->warn("Product not found: $productName");
                }

                $count++;
            }

            fclose($file);
            DB::commit();

            $this->command->info("✅ Processed $count rows.");
            $this->command->info("✅ Updated $updatedProducts products with categories.");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Error seeding categories: '.$e->getMessage());
            if (isset($file) && is_resource($file)) {
                fclose($file);
            }
            throw $e;
        }
    }
}
