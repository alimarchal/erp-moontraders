<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class GoodsReceiptNoteItemsImport implements ToCollection, WithHeadingRow, WithValidation
{
    /** @var int */
    private const DEFAULT_PURCHASE_UOM_ID = 33; // Case

    /** @var int */
    private const DEFAULT_STOCK_UOM_ID = 24; // Piece

    private Supplier $supplier;

    private float $salesTaxRate;

    /** @var array<int, array<string, mixed>> */
    private array $processedItems = [];

    /** @var array<int, string> */
    private array $rowErrors = [];

    public function __construct(
        private int $supplierId,
    ) {
        $this->supplier = Supplier::findOrFail($supplierId);
        $this->salesTaxRate = (float) ($this->supplier->sales_tax ?? 18.00);
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 2; // +2 for 1-based index + heading row

            $productCode = trim((string) ($row['product_code'] ?? ''));
            if ($productCode === '') {
                $this->rowErrors[$rowNumber] = "Row {$rowNumber}: Product code is required.";

                continue;
            }

            $product = Product::where('product_code', $productCode)
                ->where('supplier_id', $this->supplierId)
                ->where('is_active', true)
                ->first();

            if (! $product) {
                $this->rowErrors[$rowNumber] = "Row {$rowNumber}: Product code '{$productCode}' not found or does not belong to this supplier.";

                continue;
            }

            $qtyInPurchaseUom = (float) ($row['qty_in_purchase_uom'] ?? 0);
            if ($qtyInPurchaseUom <= 0) {
                $this->rowErrors[$rowNumber] = "Row {$rowNumber}: Qty in purchase UOM must be greater than 0.";

                continue;
            }

            $unitPricePerCase = (float) ($row['unit_price_per_case'] ?? 0);
            if ($unitPricePerCase <= 0) {
                $this->rowErrors[$rowNumber] = "Row {$rowNumber}: Unit price per case must be greater than 0.";

                continue;
            }

            $uomConversionFactor = (float) ($product->uom_conversion_factor ?? 1);
            $discountValue = (float) ($row['discount_value'] ?? 0);
            $fmrAllowance = (float) ($row['fmr_allowance'] ?? 0);
            $exciseDuty = (float) ($row['excise_duty'] ?? 0);
            $advanceIncomeTax = (float) ($row['advance_income_tax'] ?? 0);

            // Calculations - exact same logic as the JS in create.blade.php
            $qtyInStockUom = round($qtyInPurchaseUom * $uomConversionFactor, 2);
            $extendedValue = round($qtyInPurchaseUom * $unitPricePerCase, 2);
            $discountedValueBeforeTax = round($extendedValue - $discountValue - $fmrAllowance, 2);

            // Sales tax: use provided value or auto-calculate from supplier rate
            $salesTaxRaw = $row['sales_tax_value'] ?? null;
            $salesTaxManuallyProvided = $salesTaxRaw !== null && $salesTaxRaw !== '' && is_numeric($salesTaxRaw);
            $salesTaxValue = $salesTaxManuallyProvided
                ? round((float) $salesTaxRaw, 2)
                : round(($discountedValueBeforeTax * $this->salesTaxRate) / 100, 2);

            $totalValueWithTaxes = round($discountedValueBeforeTax + $exciseDuty + $salesTaxValue + $advanceIncomeTax, 2);

            $quantityReceived = $qtyInStockUom;
            $quantityAccepted = $quantityReceived;

            // Unit cost = (total_value_with_taxes + fmr_allowance) / quantity_received
            $unitCost = $quantityReceived > 0
                ? round(($totalValueWithTaxes + $fmrAllowance) / $quantityReceived, 2)
                : 0;

            $sellingPrice = ($row['selling_price'] ?? null) !== null && $row['selling_price'] !== '' && is_numeric($row['selling_price'])
                ? round((float) $row['selling_price'], 2)
                : ($product->unit_sell_price ?? 0);

            $promotionalPrice = ($row['promotional_price'] ?? null) !== null && $row['promotional_price'] !== '' && is_numeric($row['promotional_price'])
                ? round((float) $row['promotional_price'], 2)
                : null;

            $priorityOrder = ($row['priority_order'] ?? null) !== null && $row['priority_order'] !== '' && is_numeric($row['priority_order'])
                ? (int) $row['priority_order']
                : 99;

            $this->processedItems[] = [
                'product_id' => $product->id,
                'product_code' => $product->product_code,
                'product_name' => $product->product_name,
                'is_powder' => (bool) $product->is_powder,
                'stock_uom_id' => self::DEFAULT_STOCK_UOM_ID,
                'purchase_uom_id' => self::DEFAULT_PURCHASE_UOM_ID,
                'qty_in_purchase_uom' => $qtyInPurchaseUom,
                'uom_conversion_factor' => $uomConversionFactor,
                'qty_in_stock_uom' => $qtyInStockUom,
                'unit_price_per_case' => $unitPricePerCase,
                'extended_value' => $extendedValue,
                'discount_value' => $discountValue,
                'fmr_allowance' => $fmrAllowance,
                'discounted_value_before_tax' => $discountedValueBeforeTax,
                'excise_duty' => $exciseDuty,
                'sales_tax_value' => $salesTaxValue,
                'advance_income_tax' => $advanceIncomeTax,
                'total_value_with_taxes' => $totalValueWithTaxes,
                'quantity_received' => $quantityReceived,
                'quantity_accepted' => $quantityAccepted,
                'unit_cost' => $unitCost,
                'selling_price' => $sellingPrice,
                'promotional_price' => $promotionalPrice,
                'priority_order' => $priorityOrder,
                'must_sell_before' => $this->parseDate($row['must_sell_before'] ?? null),
                'batch_number' => trim((string) ($row['batch_number'] ?? '')) ?: null,
                'manufacturing_date' => $this->parseDate($row['manufacturing_date'] ?? null),
                'expiry_date' => $this->parseDate($row['expiry_date'] ?? null),
            ];
        }
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'product_code' => 'required|string',
            'qty_in_purchase_uom' => 'required|numeric|min:0.01',
            'unit_price_per_case' => 'required|numeric|min:0.01',
            'discount_value' => 'nullable|numeric|min:0',
            'fmr_allowance' => 'nullable|numeric|min:0',
            'excise_duty' => 'nullable|numeric|min:0',
            'sales_tax_value' => 'nullable|numeric|min:0',
            'advance_income_tax' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
            'promotional_price' => 'nullable|numeric|min:0',
            'priority_order' => 'nullable|integer|min:1|max:99',
            'must_sell_before' => 'nullable',
            'batch_number' => 'nullable|string|max:100',
            'manufacturing_date' => 'nullable',
            'expiry_date' => 'nullable',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getProcessedItems(): array
    {
        return $this->processedItems;
    }

    /**
     * @return array<int, string>
     */
    public function getRowErrors(): array
    {
        return $this->rowErrors;
    }

    public function hasErrors(): bool
    {
        return count($this->rowErrors) > 0;
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Handle Excel numeric date serial numbers
        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((int) $value)->format('Y-m-d');
            } catch (\Exception) {
                return null;
            }
        }

        // Handle string dates
        try {
            return \Carbon\Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }
}
