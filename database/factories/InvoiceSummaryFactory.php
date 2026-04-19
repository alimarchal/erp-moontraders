<?php

namespace Database\Factories;

use App\Models\InvoiceSummary;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceSummary>
 */
class InvoiceSummaryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $invoiceValue = fake()->randomFloat(2, 10000, 12000000);
        $zaOnInvoices = round($invoiceValue * 0.005, 2);
        $discountValue = round($invoiceValue * fake()->randomFloat(4, 0.01, 0.05), 2);
        $fmrAllowance = round($invoiceValue * fake()->randomFloat(4, 0.001, 0.005), 2);
        $discountBeforeSalesTax = round($invoiceValue - $discountValue - $fmrAllowance - $zaOnInvoices, 2);
        $exciseDuty = fake()->boolean(40) ? round($discountBeforeSalesTax * 0.005, 2) : 0;
        $salesTaxValue = round($discountBeforeSalesTax * 0.18, 2);
        $advanceTax = round($invoiceValue * 0.001, 2);
        $totalValueWithTax = round($discountBeforeSalesTax + $exciseDuty + $salesTaxValue + $advanceTax, 2);

        return [
            'supplier_id' => Supplier::inRandomOrder()->value('id') ?? Supplier::factory(),
            'invoice_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'invoice_number' => (string) fake()->numerify('107352####'),
            'cartons' => fake()->numberBetween(1, 2000),
            'invoice_value' => $invoiceValue,
            'za_on_invoices' => $zaOnInvoices,
            'discount_value' => $discountValue,
            'fmr_allowance' => $fmrAllowance,
            'discount_before_sales_tax' => $discountBeforeSalesTax,
            'excise_duty' => $exciseDuty,
            'sales_tax_value' => $salesTaxValue,
            'advance_tax' => $advanceTax,
            'total_value_with_tax' => $totalValueWithTax,
            'remarks' => fake()->optional(0.1)->sentence(),
        ];
    }
}
