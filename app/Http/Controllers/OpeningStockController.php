<?php

namespace App\Http\Controllers;

use App\Exports\OpeningStockTemplateExport;
use App\Http\Requests\StoreOpeningStockRequest;
use App\Imports\OpeningStockImport;
use App\Models\GoodsReceiptNote;
use App\Models\Supplier;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class OpeningStockController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:opening-stock-create'),
        ];
    }

    public function downloadTemplate(Supplier $supplier)
    {
        return Excel::download(
            new OpeningStockTemplateExport($supplier->id),
            'opening_stock_'.str($supplier->supplier_name)->slug().'.xlsx'
        );
    }

    public function store(StoreOpeningStockRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $import = new OpeningStockImport($validated['supplier_id']);
            Excel::import($import, $request->file('import_file'));

            if ($import->hasErrors()) {
                DB::rollBack();

                return redirect()
                    ->route('goods-receipt-notes.create')
                    ->withInput()
                    ->withErrors(['opening_stock_file' => array_values($import->getRowErrors())]);
            }

            $processedItems = $import->getProcessedItems();

            if (empty($processedItems)) {
                DB::rollBack();

                return redirect()
                    ->route('goods-receipt-notes.create')
                    ->withInput()
                    ->withErrors(['opening_stock_file' => 'No valid items found in the Excel file.']);
            }

            $grnNumber = $this->generateGRNNumber();

            $totalQuantity = 0;
            $totalAmount = 0;

            foreach ($processedItems as $item) {
                $totalQuantity += $item['quantity_accepted'];
                $totalAmount += $item['total_cost'];
            }

            $grn = GoodsReceiptNote::create([
                'grn_number' => $grnNumber,
                'receipt_date' => $validated['receipt_date'],
                'supplier_id' => $validated['supplier_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'total_quantity' => $totalQuantity,
                'total_amount' => $totalAmount,
                'tax_amount' => 0,
                'freight_charges' => 0,
                'other_charges' => 0,
                'grand_total' => $totalAmount,
                'status' => 'draft',
                'received_by' => auth()->id(),
                'notes' => 'Opening Stock Import',
                'is_opening_stock' => true,
            ]);

            foreach ($processedItems as $index => $item) {
                $grn->items()->create([
                    'line_no' => $index + 1,
                    'product_id' => $item['product_id'],
                    'stock_uom_id' => $item['stock_uom_id'],
                    'purchase_uom_id' => $item['purchase_uom_id'],
                    'qty_in_purchase_uom' => $item['qty_in_purchase_uom'],
                    'uom_conversion_factor' => $item['uom_conversion_factor'],
                    'qty_in_stock_uom' => $item['qty_in_stock_uom'],
                    'unit_price_per_case' => $item['unit_price_per_case'],
                    'extended_value' => $item['extended_value'],
                    'discount_value' => 0,
                    'fmr_allowance' => 0,
                    'discounted_value_before_tax' => $item['discounted_value_before_tax'],
                    'excise_duty' => 0,
                    'sales_tax_value' => 0,
                    'advance_income_tax' => 0,
                    'other_charges' => 0,
                    'withholding_tax' => 0,
                    'total_value_with_taxes' => $item['total_value_with_taxes'],
                    'quantity_received' => $item['quantity_received'],
                    'quantity_accepted' => $item['quantity_accepted'],
                    'quantity_rejected' => 0,
                    'unit_cost' => $item['unit_cost'],
                    'total_cost' => $item['total_cost'],
                    'selling_price' => $item['selling_price'],
                    'is_promotional' => false,
                    'priority_order' => 99,
                    'quality_status' => 'approved',
                    'selling_strategy' => 'fifo',
                ]);
            }

            DB::commit();

            return redirect()
                ->route('goods-receipt-notes.show', $grn)
                ->with('success', "Opening stock GRN {$grn->grn_number} created as draft. Review and post when ready.");

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error importing opening stock', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            return redirect()
                ->route('goods-receipt-notes.create')
                ->withInput()
                ->with('error', 'Failed to import opening stock. '.$e->getMessage());
        }
    }

    private function generateGRNNumber(): string
    {
        $year = now()->year;
        $prefix = "GRN-{$year}-";

        $lastGRN = GoodsReceiptNote::where('grn_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastGRN ? ((int) str_replace($prefix, '', $lastGRN->grn_number)) + 1 : 1;

        return sprintf('%s%04d', $prefix, $sequence);
    }
}
