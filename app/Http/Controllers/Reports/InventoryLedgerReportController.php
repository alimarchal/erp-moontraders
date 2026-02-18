<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\InventoryLedgerEntry;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class InventoryLedgerReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-inventory-inventory-ledger'),
        ];
    }

    public function index(Request $request)
    {
        try {
            // Check if table exists
            if (! Schema::hasTable('inventory_ledger_entries')) {
                return view('reports.inventory-ledger.index', [
                    'entries' => collect(),
                    'products' => Product::orderBy('product_name')->get(),
                    'warehouses' => Warehouse::orderBy('warehouse_name')->get(),
                    'vehicles' => Vehicle::orderBy('registration_number')->get(),
                    'employees' => Employee::orderBy('name')->get(),
                    'batches' => collect(),
                    'suppliers' => Supplier::orderBy('supplier_name')->get(),
                    'transactionTypes' => $this->getTransactionTypes(),
                    'filters' => [],
                    'openingBalance' => 0,
                    'closingBalance' => 0,
                    'totalDebits' => 0,
                    'totalCredits' => 0,
                    'error' => 'Inventory ledger table not yet created. Please run migrations.',
                ]);
            }

            $filters = $request->get('filter', []);

            // Default to current month
            $startDate = $filters['start_date'] ?? now()->startOfMonth()->toDateString();
            $endDate = $filters['end_date'] ?? now()->toDateString();

            $productId = $filters['product_id'] ?? null;
            $warehouseId = $filters['warehouse_id'] ?? null;
            $vehicleId = $filters['vehicle_id'] ?? null;
            $employeeId = $filters['employee_id'] ?? null;
            $batchId = $filters['batch_id'] ?? null;
            $supplierId = $filters['supplier_id'] ?? null;
            $transactionType = $filters['transaction_type'] ?? null;

            // Build query for ledger entries
            $query = InventoryLedgerEntry::with([
                'product',
                'warehouse',
                'vehicle',
                'employee',
                'stockBatch.supplier',
                'goodsReceiptNote',
                'goodsIssue',
                'salesSettlement',
            ])
                ->whereBetween('date', [$startDate, $endDate])
                ->orderBy('date', 'asc')
                ->orderBy('id', 'asc');

            // Apply filters
            if ($productId) {
                $query->where('product_id', $productId);
            }
            if ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            }
            if ($vehicleId) {
                $query->where('vehicle_id', $vehicleId);
            }
            if ($employeeId) {
                $query->where('employee_id', $employeeId);
            }
            if ($batchId) {
                $query->where('stock_batch_id', $batchId);
            }
            if ($supplierId) {
                $query->whereHas('stockBatch', function ($q) use ($supplierId) {
                    $q->where('supplier_id', $supplierId);
                });
            }
            if ($transactionType) {
                $query->where('transaction_type', $transactionType);
            }

            $entries = $query->get();

            // Calculate opening balance (before start date)
            $openingBalanceQuery = InventoryLedgerEntry::where('date', '<', $startDate);
            if ($productId) {
                $openingBalanceQuery->where('product_id', $productId);
            }
            if ($warehouseId) {
                $openingBalanceQuery->where('warehouse_id', $warehouseId);
            }
            if ($vehicleId) {
                $openingBalanceQuery->where('vehicle_id', $vehicleId);
            }
            if ($employeeId) {
                $openingBalanceQuery->where('employee_id', $employeeId);
            }
            if ($batchId) {
                $openingBalanceQuery->where('stock_batch_id', $batchId);
            }
            if ($supplierId) {
                $openingBalanceQuery->whereHas('stockBatch', function ($q) use ($supplierId) {
                    $q->where('supplier_id', $supplierId);
                });
            }

            $openingBalance = (float) $openingBalanceQuery
                ->selectRaw('COALESCE(SUM(debit_qty), 0) - COALESCE(SUM(credit_qty), 0) as balance')
                ->value('balance');

            // Calculate totals
            $totalDebits = $entries->sum('debit_qty');
            $totalCredits = $entries->sum('credit_qty');
            $closingBalance = $openingBalance + $totalDebits - $totalCredits;

            // Calculate running balance for each entry
            $runningBalance = $openingBalance;
            foreach ($entries as $entry) {
                $runningBalance += $entry->debit_qty - $entry->credit_qty;
                $entry->calculated_running_balance = $runningBalance;
            }

            // Get filter options
            $products = Product::orderBy('product_name')->get();
            $warehouses = Warehouse::orderBy('warehouse_name')->get();
            $vehicles = Vehicle::orderBy('registration_number')->get();
            $employees = Employee::select('id', 'name')->orderBy('name')->get();
            $batches = StockBatch::select('id', 'batch_code')->orderBy('batch_code')->get();
            $suppliers = Supplier::orderBy('supplier_name')->get();

            return view('reports.inventory-ledger.index', [
                'entries' => $entries,
                'products' => $products,
                'warehouses' => $warehouses,
                'vehicles' => $vehicles,
                'employees' => $employees,
                'batches' => $batches,
                'suppliers' => $suppliers,
                'transactionTypes' => $this->getTransactionTypes(),
                'filters' => $filters + ['start_date' => $startDate, 'end_date' => $endDate],
                'openingBalance' => $openingBalance,
                'closingBalance' => $closingBalance,
                'totalDebits' => $totalDebits,
                'totalCredits' => $totalCredits,
                'error' => null,
            ]);

        } catch (\Exception $e) {
            Log::error('Inventory Ledger Report Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return view('reports.inventory-ledger.index', [
                'entries' => collect(),
                'products' => Product::orderBy('product_name')->get(),
                'warehouses' => Warehouse::orderBy('warehouse_name')->get(),
                'vehicles' => Vehicle::orderBy('registration_number')->get(),
                'employees' => Employee::orderBy('name')->get(),
                'batches' => collect(),
                'suppliers' => Supplier::orderBy('supplier_name')->get(),
                'transactionTypes' => $this->getTransactionTypes(),
                'filters' => $request->get('filter', []),
                'openingBalance' => 0,
                'closingBalance' => 0,
                'totalDebits' => 0,
                'totalCredits' => 0,
                'error' => 'An error occurred while loading the report. Please try again. Error: '.$e->getMessage(),
            ]);
        }
    }

    protected function getTransactionTypes(): array
    {
        return [
            InventoryLedgerEntry::TYPE_PURCHASE => 'Purchase (GRN)',
            InventoryLedgerEntry::TYPE_TRANSFER_IN => 'Transfer In (to Van)',
            InventoryLedgerEntry::TYPE_TRANSFER_OUT => 'Transfer Out (from Warehouse)',
            InventoryLedgerEntry::TYPE_SALE => 'Sale',
            InventoryLedgerEntry::TYPE_RETURN => 'Return',
            InventoryLedgerEntry::TYPE_SHORTAGE => 'Shortage',
            InventoryLedgerEntry::TYPE_ADJUSTMENT => 'Adjustment',
        ];
    }
}
