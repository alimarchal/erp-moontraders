<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $uoms = [
            // Length/Distance
            ['uom_name' => 'Meter', 'symbol' => 'm', 'description' => 'Base unit of length', 'enabled' => false],
            ['uom_name' => 'Centimeter', 'symbol' => 'cm', 'description' => 'One hundredth of a meter', 'enabled' => false],
            ['uom_name' => 'Millimeter', 'symbol' => 'mm', 'description' => 'One thousandth of a meter', 'enabled' => false],
            ['uom_name' => 'Kilometer', 'symbol' => 'km', 'description' => 'One thousand meters', 'enabled' => false],
            ['uom_name' => 'Inch', 'symbol' => 'in', 'description' => 'Imperial unit of length', 'enabled' => false],
            ['uom_name' => 'Foot', 'symbol' => 'ft', 'description' => 'Imperial unit = 12 inches', 'enabled' => false],
            ['uom_name' => 'Yard', 'symbol' => 'yd', 'description' => 'Imperial unit = 3 feet', 'enabled' => false],
            ['uom_name' => 'Mile', 'symbol' => 'mi', 'description' => 'Imperial unit = 1760 yards', 'enabled' => false],

            // Mass/Weight
            ['uom_name' => 'Kilogram', 'symbol' => 'kg', 'description' => 'Base unit of mass', 'enabled' => false],
            ['uom_name' => 'Gram', 'symbol' => 'g', 'description' => 'One thousandth of a kilogram', 'enabled' => false],
            ['uom_name' => 'Milligram', 'symbol' => 'mg', 'description' => 'One thousandth of a gram', 'enabled' => false],
            ['uom_name' => 'Ton', 'symbol' => 't', 'description' => 'Metric ton = 1000 kg', 'enabled' => false],
            ['uom_name' => 'Pound', 'symbol' => 'lb', 'description' => 'Imperial unit of mass', 'enabled' => false],
            ['uom_name' => 'Ounce', 'symbol' => 'oz', 'description' => 'Imperial unit = 1/16 pound', 'enabled' => false],

            // Volume
            ['uom_name' => 'Liter', 'symbol' => 'L', 'description' => 'Unit of volume', 'enabled' => false],
            ['uom_name' => 'Milliliter', 'symbol' => 'mL', 'description' => 'One thousandth of a liter', 'enabled' => false],
            ['uom_name' => 'Cubic Meter', 'symbol' => 'm³', 'description' => 'Cubic meter volume', 'enabled' => false],
            ['uom_name' => 'Gallon', 'symbol' => 'gal', 'description' => 'Imperial unit of volume', 'enabled' => false],

            // Area
            ['uom_name' => 'Square Meter', 'symbol' => 'm²', 'description' => 'Unit of area', 'enabled' => false],
            ['uom_name' => 'Square Foot', 'symbol' => 'ft²', 'description' => 'Imperial unit of area', 'enabled' => false],
            ['uom_name' => 'Acre', 'symbol' => 'ac', 'description' => 'Unit of area = 43,560 sq ft', 'enabled' => false],
            ['uom_name' => 'Hectare', 'symbol' => 'ha', 'description' => 'Unit of area = 10,000 m²', 'enabled' => false],

            // Count/Quantity
            ['uom_name' => 'Unit', 'symbol' => 'Unit', 'description' => 'Individual item', 'must_be_whole_number' => true, 'enabled' => false],
            ['uom_name' => 'Piece', 'symbol' => 'Pc', 'description' => 'Individual piece', 'must_be_whole_number' => true, 'enabled' => true],
            ['uom_name' => 'Nos', 'symbol' => 'Nos', 'description' => 'Number of items', 'must_be_whole_number' => true, 'enabled' => false],
            ['uom_name' => 'Dozen', 'symbol' => 'Dz', 'description' => '12 pieces', 'must_be_whole_number' => true, 'enabled' => false],
            ['uom_name' => 'Pair', 'symbol' => 'Pr', 'description' => '2 pieces', 'must_be_whole_number' => true, 'enabled' => false],
            ['uom_name' => 'Set', 'symbol' => 'Set', 'description' => 'Collection of items', 'must_be_whole_number' => true, 'enabled' => false],

            // Packaging
            ['uom_name' => 'Box', 'symbol' => 'Box', 'description' => 'Boxed quantity', 'must_be_whole_number' => true, 'enabled' => false],
            ['uom_name' => 'Carton', 'symbol' => 'Ctn', 'description' => 'Carton quantity', 'must_be_whole_number' => true, 'enabled' => false],
            ['uom_name' => 'Bag', 'symbol' => 'Bag', 'description' => 'Bag quantity', 'must_be_whole_number' => true, 'enabled' => false],
            ['uom_name' => 'Pack', 'symbol' => 'Pk', 'description' => 'Package quantity', 'must_be_whole_number' => true, 'enabled' => false],
            ['uom_name' => 'Case', 'symbol' => 'Case', 'description' => 'Case quantity', 'must_be_whole_number' => true, 'enabled' => true],
            ['uom_name' => 'Pallet', 'symbol' => 'Plt', 'description' => 'Pallet quantity', 'must_be_whole_number' => true, 'enabled' => false],
            ['uom_name' => 'Bundle', 'symbol' => 'Bdl', 'description' => 'Bundle quantity', 'must_be_whole_number' => true, 'enabled' => false],

            // Time
            ['uom_name' => 'Hour', 'symbol' => 'hr', 'description' => 'Unit of time', 'enabled' => false],
            ['uom_name' => 'Day', 'symbol' => 'day', 'description' => '24 hours', 'must_be_whole_number' => true, 'enabled' => false],
            ['uom_name' => 'Week', 'symbol' => 'wk', 'description' => '7 days', 'must_be_whole_number' => true, 'enabled' => false],
            ['uom_name' => 'Month', 'symbol' => 'mo', 'description' => 'Calendar month', 'must_be_whole_number' => true, 'enabled' => false],
            ['uom_name' => 'Year', 'symbol' => 'yr', 'description' => '12 months', 'must_be_whole_number' => true, 'enabled' => false],

            // Textile
            ['uom_name' => 'Meter (Fabric)', 'symbol' => 'm', 'description' => 'Fabric measurement', 'enabled' => false],
            ['uom_name' => 'Yard (Fabric)', 'symbol' => 'yd', 'description' => 'Fabric measurement', 'enabled' => false],

            // Temperature (for reference)
            ['uom_name' => 'Celsius', 'symbol' => '°C', 'description' => 'Temperature unit', 'enabled' => false],
            ['uom_name' => 'Fahrenheit', 'symbol' => '°F', 'description' => 'Temperature unit', 'enabled' => false],

            // Common Trade Units (Pakistan specific)
            ['uom_name' => 'Maund', 'symbol' => 'Md', 'description' => 'Traditional unit ≈ 40 kg', 'enabled' => false],
            ['uom_name' => 'Seer', 'symbol' => 'Sr', 'description' => 'Traditional unit ≈ 1 kg', 'enabled' => false],
        ];

        foreach ($uoms as $uom) {
            DB::table('uoms')->insert(array_merge($uom, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
