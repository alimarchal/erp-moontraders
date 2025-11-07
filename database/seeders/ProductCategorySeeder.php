<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            // Edible Oils & Ghee
            [
                'category_code' => 'BNSP',
                'category_name' => 'Banaspati & Ghee',
                'description' => 'Vegetable ghee, banaspati, and cooking fats',
                'is_active' => true,
            ],
            [
                'category_code' => 'COOK',
                'category_name' => 'Cooking Oil',
                'description' => 'Edible cooking oils - canola, sunflower, palm oil',
                'is_active' => true,
            ],

            // Dairy Products
            [
                'category_code' => 'MILK',
                'category_name' => 'Milk & Dairy',
                'description' => 'UHT milk, powdered milk, dairy products',
                'is_active' => true,
            ],
            [
                'category_code' => 'BUTTER',
                'category_name' => 'Butter',
                'description' => 'Butter and butter-based products',
                'is_active' => true,
            ],

            // Beverages
            [
                'category_code' => 'TEA',
                'category_name' => 'Tea',
                'description' => 'Tea leaves, tea bags, tea products',
                'is_active' => true,
            ],
            [
                'category_code' => 'COFFEE',
                'category_name' => 'Coffee',
                'description' => 'Coffee beans, instant coffee, coffee products',
                'is_active' => true,
            ],

            // Biscuits & Confectionery
            [
                'category_code' => 'BISCUIT',
                'category_name' => 'Biscuits',
                'description' => 'All types of biscuits and cookies',
                'is_active' => true,
            ],
            [
                'category_code' => 'WAFER',
                'category_name' => 'Wafers',
                'description' => 'Wafer biscuits and wafer-based products',
                'is_active' => true,
            ],
            [
                'category_code' => 'CAKE',
                'category_name' => 'Cakes',
                'description' => 'Cake products and cake-based items',
                'is_active' => true,
            ],

            // Spices & Condiments
            [
                'category_code' => 'SPICE',
                'category_name' => 'Spices',
                'description' => 'Ground spices, whole spices, spice mixes',
                'is_active' => true,
            ],
            [
                'category_code' => 'MASALA',
                'category_name' => 'Masala',
                'description' => 'Ready-made masala mixes for cooking',
                'is_active' => true,
            ],
            [
                'category_code' => 'PICKLE',
                'category_name' => 'Pickles & Sauces',
                'description' => 'Pickles, chutneys, and cooking sauces',
                'is_active' => true,
            ],

            // Tobacco
            [
                'category_code' => 'CIGRT',
                'category_name' => 'Cigarettes',
                'description' => 'Cigarette products',
                'is_active' => true,
            ],

            // Personal Care
            [
                'category_code' => 'SOAP',
                'category_name' => 'Soaps & Detergents',
                'description' => 'Bathing soaps, washing soaps, detergents',
                'is_active' => true,
            ],
            [
                'category_code' => 'SHAMPOO',
                'category_name' => 'Shampoo & Hair Care',
                'description' => 'Shampoos, conditioners, hair care products',
                'is_active' => true,
            ],

            // Miscellaneous
            [
                'category_code' => 'GENERAL',
                'category_name' => 'General Items',
                'description' => 'Miscellaneous products not fitting other categories',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            DB::table('product_categories')->insert(array_merge($category, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
