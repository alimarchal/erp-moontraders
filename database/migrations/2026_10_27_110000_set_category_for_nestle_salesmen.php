<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SUPPLIER = 'Nestlé Pakistan';

    /** Salesman name => category, for employees of the Nestlé Pakistan supplier only. */
    private const CATEGORIES = [
        'Abbas Pharmacy' => 'Corporate',
        'Abbas Shah' => 'Powder',
        'Ahtasham Shah' => 'Powder',
        'Choudry Lateef' => 'Liquid',
        'Husnain Shah' => 'Corporate',
        'Khalid Khan' => 'Corporate',
        'Mujahid Shah' => 'Corporate',
        'Raja Safeer' => 'Corporate',
        'Shahid Mughal' => 'Liquid',
        'Tanveer Malik' => 'Corporate',
        'Waseem Choudry' => 'Liquid',
    ];

    /**
     * Matches on name (case-insensitive) within the exact supplier "Nestlé Pakistan" (not the HOD 19L one) and only fills
     * employees whose category is still empty, so manual edits are never overwritten.
     */
    public function up(): void
    {
        $supplierIds = DB::table('suppliers')->where('supplier_name', self::SUPPLIER)->pluck('id');

        foreach (self::CATEGORIES as $name => $category) {
            DB::table('employees')
                ->whereIn('supplier_id', $supplierIds)
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])
                ->whereNull('category')
                ->update(['category' => $category]);
        }
    }

    public function down(): void
    {
        $supplierIds = DB::table('suppliers')->where('supplier_name', self::SUPPLIER)->pluck('id');

        foreach (self::CATEGORIES as $name => $category) {
            DB::table('employees')
                ->whereIn('supplier_id', $supplierIds)
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])
                ->where('category', $category)
                ->update(['category' => null]);
        }
    }
};
