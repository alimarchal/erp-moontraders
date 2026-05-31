<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();
        $categoryNames = ['Taxation', 'With Holding Tax H25'];
        $suppliers = DB::table('suppliers')->select('id')->get();

        foreach ($suppliers as $supplier) {
            foreach ($categoryNames as $categoryName) {
                DB::table('profit_categories')->updateOrInsert(
                    [
                        'supplier_id' => $supplier->id,
                        'slug' => Str::slug($categoryName),
                        'deleted_at' => null,
                    ],
                    [
                        'name' => $categoryName,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('profit_categories')
            ->whereIn('slug', ['taxation', 'with-holding-tax-h25'])
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('profit_category_details')
                    ->whereColumn('profit_category_details.profit_category_id', 'profit_categories.id');
            })
            ->delete();
    }
};
