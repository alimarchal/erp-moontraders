<?php

namespace Database\Seeders;

use App\Models\InvoiceSummary;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class InvoiceSummarySeeder extends Seeder
{
    public function run(): void
    {
        $nestle = Supplier::where('short_name', 'Nestle')->first();

        if (! $nestle) {
            return;
        }

        // Seed exact data from the Excel "Invoice Summary - February 2026"
        $invoices = [
            ['date' => '2026-02-04', 'number' => '1073527810', 'cartons' => 2, 'value' => 20421.54, 'za' => 102.11, 'discount' => 738.78, 'fmr' => 51.22, 'disc_before_st' => 17289.10, 'excise' => 0, 'sales_tax' => 3112.04, 'adv_tax' => 20.40],
            ['date' => '2026-02-04', 'number' => '1073527810', 'cartons' => 1282, 'value' => 11199451.37, 'za' => 55997.26, 'discount' => 434172.24, 'fmr' => 36826.83, 'disc_before_st' => 9427051.79, 'excise' => 46485.32, 'sales_tax' => 1714725.96, 'adv_tax' => 11188.30],
            ['date' => '2026-02-05', 'number' => '1073528188', 'cartons' => 959, 'value' => 4917548.59, 'za' => 24587.74, 'discount' => 179324.61, 'fmr' => 18677.47, 'disc_before_st' => 4135182.40, 'excise' => 23299.02, 'sales_tax' => 754154.55, 'adv_tax' => 4912.62],
            ['date' => '2026-02-07', 'number' => '1073528354', 'cartons' => 906, 'value' => 449711.60, 'za' => 2248.56, 'discount' => 40044.00, 'fmr' => 1124.93, 'disc_before_st' => 370414.93, 'excise' => 0, 'sales_tax' => 78847.43, 'adv_tax' => 449.26],
            ['date' => '2026-02-09', 'number' => '1073528638', 'cartons' => 1720, 'value' => 897504.70, 'za' => 4487.52, 'discount' => 79835.31, 'fmr' => 2242.74, 'disc_before_st' => 738492.59, 'excise' => 0, 'sales_tax' => 158115.52, 'adv_tax' => 896.61],
            ['date' => '2026-02-10', 'number' => '1073529209', 'cartons' => 1956, 'value' => 5841379.50, 'za' => 29206.90, 'discount' => 194677.21, 'fmr' => 25037.13, 'disc_before_st' => 4869531.41, 'excise' => 67063.00, 'sales_tax' => 898949.35, 'adv_tax' => 5835.56],
            ['date' => '2026-02-13', 'number' => '1073529769', 'cartons' => 1700, 'value' => 4264998.62, 'za' => 21324.99, 'discount' => 118358.03, 'fmr' => 21437.15, 'disc_before_st' => 3610794.82, 'excise' => 0, 'sales_tax' => 649943.06, 'adv_tax' => 4260.74],
            ['date' => '2026-02-14', 'number' => '1073530164', 'cartons' => 1959, 'value' => 8673256.80, 'za' => 43366.28, 'discount' => 312859.20, 'fmr' => 36153.51, 'disc_before_st' => 7082759.04, 'excise' => 235003.03, 'sales_tax' => 1346830.11, 'adv_tax' => 8664.62],
            ['date' => '2026-02-14', 'number' => '1073530376', 'cartons' => 1118, 'value' => 3976055.01, 'za' => 19880.28, 'discount' => 133665.42, 'fmr' => 16674.09, 'disc_before_st' => 3350808.50, 'excise' => 12881.28, 'sales_tax' => 608393.12, 'adv_tax' => 3972.11],
            ['date' => '2026-02-17', 'number' => '1073530851', 'cartons' => 1288, 'value' => 9055137.29, 'za' => 45275.69, 'discount' => 382320.87, 'fmr' => 26879.79, 'disc_before_st' => 7480025.17, 'excise' => 166490.64, 'sales_tax' => 1399575.38, 'adv_tax' => 9046.10],
            ['date' => '2026-02-17', 'number' => '1073531110', 'cartons' => 1529, 'value' => 3912180.43, 'za' => 19560.90, 'discount' => 108566.98, 'fmr' => 19663.79, 'disc_before_st' => 3312095.06, 'excise' => 0, 'sales_tax' => 596177.11, 'adv_tax' => 3908.26],
            ['date' => '2026-02-18', 'number' => '1073529894', 'cartons' => 800, 'value' => 2932501.96, 'za' => 14662.51, 'discount' => 81379.90, 'fmr' => 14739.63, 'disc_before_st' => 2482688.47, 'excise' => 0, 'sales_tax' => 446883.92, 'adv_tax' => 2929.57],
        ];

        foreach ($invoices as $inv) {
            InvoiceSummary::create([
                'supplier_id' => $nestle->id,
                'invoice_date' => $inv['date'],
                'invoice_number' => $inv['number'],
                'cartons' => $inv['cartons'],
                'invoice_value' => $inv['value'],
                'za_on_invoices' => $inv['za'],
                'discount_value' => $inv['discount'],
                'fmr_allowance' => $inv['fmr'],
                'discount_before_sales_tax' => $inv['disc_before_st'],
                'excise_duty' => $inv['excise'],
                'sales_tax_value' => $inv['sales_tax'],
                'advance_tax' => $inv['adv_tax'],
                'total_value_with_tax' => round($inv['disc_before_st'] + $inv['excise'] + $inv['sales_tax'] + $inv['adv_tax'], 2),
            ]);
        }
    }
}
