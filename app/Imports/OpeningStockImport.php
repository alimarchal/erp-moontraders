<?php

namespace App\Imports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class OpeningStockImport implements ToCollection, WithHeadingRow
{
    private const STOCK_UOM_ID = 24; // Piece

    /** @var array<int, array<string, mixed>> */
    private array $processedItems = [];

    /** @var array<int, string> */
    private array $rowErrors = [];

    public function __construct(private int $supplierId) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 2;

            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '') {
                $this->rowErrors[$rowNumber] = "Row {$rowNumber}: SKU is required.";

                continue;
            }

            $product = Product::where('product_code', $sku)
                ->where('supplier_id', $this->supplierId)
                ->where('is_active', true)
                ->first();

            if (! $product) {
                $this->rowErrors[$rowNumber] = "Row {$rowNumber}: SKU '{$sku}' not found or does not belong to this supplier.";

                continue;
            }

            $invoicePrice = round((float) ($row['invoice_price'] ?? 0), 2);
            if ($invoicePrice <= 0) {
                $this->rowErrors[$rowNumber] = "Row {$rowNumber}: Invoice Price must be greater than 0.";

                continue;
            }

            $retailPrice = round((float) ($row['retail_price'] ?? 0), 2);
            if ($retailPrice <= 0) {
                $this->rowErrors[$rowNumber] = "Row {$rowNumber}: Retail Price must be greater than 0.";

                continue;
            }

            $quantity = (float) ($row['total_inventory_in_pieces'] ?? 0);
            if ($quantity <= 0) {
                $this->rowErrors[$rowNumber] = "Row {$rowNumber}: Total Inventory in Pieces must be greater than 0.";

                continue;
            }

            $extendedValue = round($invoicePrice * $quantity, 2);

            $this->processedItems[] = [
                'product_id' => $product->id,
                'product_code' => $product->product_code,
                'product_name' => $product->product_name,
                'is_powder' => (bool) $product->is_powder,
                'stock_uom_id' => self::STOCK_UOM_ID,
                'purchase_uom_id' => self::STOCK_UOM_ID,
                'qty_in_purchase_uom' => $quantity,
                'uom_conversion_factor' => 1,
                'qty_in_stock_uom' => $quantity,
                'unit_price_per_case' => $invoicePrice,
                'extended_value' => $extendedValue,
                'discount_value' => 0,
                'fmr_allowance' => 0,
                'discounted_value_before_tax' => $extendedValue,
                'excise_duty' => 0,
                'sales_tax_value' => 0,
                'advance_income_tax' => 0,
                'other_charges' => 0,
                'withholding_tax' => 0,
                'total_value_with_taxes' => $extendedValue,
                'quantity_received' => $quantity,
                'quantity_accepted' => $quantity,
                'unit_cost' => $invoicePrice,
                'selling_price' => $retailPrice,
                'total_cost' => $extendedValue,
                'promotional_price' => null,
                'priority_order' => 99,
                'must_sell_before' => null,
                'batch_number' => null,
                'manufacturing_date' => null,
                'expiry_date' => null,
            ];
        }
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
}
