<?php

/**
 * Web Routes
 *
 * All routes require authentication via Sanctum + Jetstream session guard.
 * Authorization is enforced at the controller level using Spatie permission
 * middleware (HasMiddleware interface) — not in this file.
 *
 * @see AppServiceProvider  Gate::before() bypass for "Super Admin"
 */

use App\Http\Controllers\AccountingPeriodController;
use App\Http\Controllers\AccountTypeController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChartOfAccountController;
use App\Http\Controllers\ClaimRegisterController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CostCenterController;
use App\Http\Controllers\CreditSalesReportController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\CurrentStockController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeSalaryController;
use App\Http\Controllers\EmployeeSalaryTransactionController;
use App\Http\Controllers\GoodsIssueController;
use App\Http\Controllers\GoodsReceiptNoteController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\OpeningCustomerBalanceController;
use App\Http\Controllers\OpeningStockController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductRecallController;
use App\Http\Controllers\ProductTaxMappingController;
use App\Http\Controllers\PromotionalCampaignController;
use App\Http\Controllers\Reports\AccountBalancesController;
use App\Http\Controllers\Reports\AdvanceTaxReportController;
use App\Http\Controllers\Reports\BalanceSheetController;
use App\Http\Controllers\Reports\CashDetailController;
use App\Http\Controllers\Reports\ClaimRegisterReportController;
use App\Http\Controllers\Reports\CreditorsLedgerController;
use App\Http\Controllers\Reports\CustomSettlementReportController;
use App\Http\Controllers\Reports\DailySalesReportController;
use App\Http\Controllers\Reports\DailyStockRegisterController;
use App\Http\Controllers\Reports\FmrAmrComparisonController;
use App\Http\Controllers\Reports\GeneralLedgerController;
use App\Http\Controllers\Reports\GoodsIssueReportController;
use App\Http\Controllers\Reports\IncomeStatementController;
use App\Http\Controllers\Reports\InventoryLedgerReportController;
use App\Http\Controllers\Reports\InvestmentSummaryController;
use App\Http\Controllers\Reports\InvoiceSummaryReportController;
use App\Http\Controllers\Reports\LegerRegisterController;
use App\Http\Controllers\Reports\OpeningCustomerBalanceReportController;
use App\Http\Controllers\Reports\PercentageExpenseReportController;
use App\Http\Controllers\Reports\RoiReportController;
use App\Http\Controllers\Reports\SalesmanStockRegisterController;
use App\Http\Controllers\Reports\SalesSettlementReportController;
use App\Http\Controllers\Reports\SchemeDiscountReportController;
use App\Http\Controllers\Reports\ShopListController;
use App\Http\Controllers\Reports\SkuRatesController;
use App\Http\Controllers\Reports\TrialBalanceController;
use App\Http\Controllers\Reports\VanStockBatchReportController;
use App\Http\Controllers\Reports\VanStockLedgerController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalesSettlementController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierPaymentController;
use App\Http\Controllers\TaxCodeController;
use App\Http\Controllers\TaxRateController;
use App\Http\Controllers\TaxTransactionController;
use App\Http\Controllers\UomController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\WarehouseTypeController;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => to_route('login'));

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
|
| Routes below require: Sanctum auth, Jetstream session, and email verified.
| Per-action permission checks are enforced in each controller via the
| HasMiddleware interface using Spatie `permission:` middleware.
|
*/
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {
    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');

    /*
    |----------------------------------------------------------------------
    | Database Object Sync — import-fun-pro
    |----------------------------------------------------------------------
    | Re-creates all missing stored procedures, functions, triggers, and views.
    | Safe to run repeatedly (idempotent). Supports MySQL/MariaDB and PostgreSQL.
    | Super-admin only.
    */
    Route::get('/import-fun-pro', function () {
        abort_unless(auth()->user()?->is_super_admin === 'Yes', 403);

        $driver = DB::connection()->getDriverName();
        $results = [];
        $hasErrors = false;

        if (! in_array($driver, ['mysql', 'mariadb', 'pgsql'])) {
            $results['error'] = "Unsupported database driver: {$driver}. Supported: MySQL, MariaDB, PostgreSQL.";
            $hasErrors = true;
        } else {
            Artisan::call('db:sync-objects');

            $db = DB::connection()->getDatabaseName();

            if (in_array($driver, ['mysql', 'mariadb'])) {
                $procedures = DB::table('information_schema.ROUTINES')
                    ->where('ROUTINE_SCHEMA', $db)->where('ROUTINE_TYPE', 'PROCEDURE')
                    ->pluck('ROUTINE_NAME')->map('strtolower')->toArray();

                $functions = DB::table('information_schema.ROUTINES')
                    ->where('ROUTINE_SCHEMA', $db)->where('ROUTINE_TYPE', 'FUNCTION')
                    ->pluck('ROUTINE_NAME')->map('strtolower')->toArray();

                $views = DB::table('information_schema.VIEWS')
                    ->where('TABLE_SCHEMA', $db)->pluck('TABLE_NAME')->map('strtolower')->toArray();

                $results = [
                    'sp_check_journal_balance' => in_array('sp_check_journal_balance', $procedures) ? 'OK' : 'MISSING',
                    'sp_create_period_snapshots' => in_array('sp_create_period_snapshots', $procedures) ? 'OK' : 'MISSING',
                    'fn_account_balance_fast' => in_array('fn_account_balance_fast', $functions) ? 'OK' : 'MISSING',
                    'vw_trial_balance' => in_array('vw_trial_balance', $views) ? 'OK' : 'MISSING',
                    'vw_account_balances' => in_array('vw_account_balances', $views) ? 'OK' : 'MISSING',
                    'vw_general_ledger' => in_array('vw_general_ledger', $views) ? 'OK' : 'MISSING',
                    'vw_balance_sheet' => in_array('vw_balance_sheet', $views) ? 'OK' : 'MISSING',
                    'vw_income_statement' => in_array('vw_income_statement', $views) ? 'OK' : 'MISSING',
                ];

                $triggers = DB::select(
                    'SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = ?', [$db]
                );
                $triggerNames = collect($triggers)->pluck('TRIGGER_NAME')->map('strtolower')->toArray();
                $results['trg_block_posted_claim_updates'] = in_array('trg_block_posted_claim_updates', $triggerNames) ? 'OK' : 'MISSING';
                $results['trg_block_posted_claim_deletes'] = in_array('trg_block_posted_claim_deletes', $triggerNames) ? 'OK' : 'MISSING';
            } else {
                // PostgreSQL — check via pg_proc and information_schema
                $pgFunctions = DB::select(
                    "SELECT p.proname FROM pg_proc p JOIN pg_namespace n ON n.oid = p.pronamespace WHERE n.nspname = 'public'"
                );
                $pgFunctionNames = collect($pgFunctions)->pluck('proname')->toArray();

                $pgTriggers = DB::select(
                    "SELECT trigger_name FROM information_schema.triggers WHERE trigger_schema = 'public'"
                );
                $pgTriggerNames = collect($pgTriggers)->pluck('trigger_name')->toArray();

                $pgViews = DB::select(
                    "SELECT table_name FROM information_schema.views WHERE table_schema = 'public'"
                );
                $pgViewNames = collect($pgViews)->pluck('table_name')->toArray();

                $expectedFunctions = [
                    'check_journal_balance', 'check_leaf_account_only', 'check_accounting_period',
                    'check_single_base_currency', 'block_posted_journal_changes', 'block_posted_journal_deletes',
                    'block_posted_detail_changes', 'auto_set_accounting_period', 'audit_accounting_changes',
                    'prevent_hard_delete_posted', 'fn_trial_balance', 'fn_trial_balance_summary',
                    'fn_account_balances', 'fn_general_ledger', 'fn_balance_sheet', 'fn_income_statement',
                    'sp_create_period_snapshots', 'fn_account_balance_fast',
                    'fn_block_posted_claim_updates', 'fn_block_posted_claim_deletes',
                ];

                $auditTables = [
                    'chart_of_accounts', 'journal_entries', 'journal_entry_details',
                    'accounting_periods', 'account_types', 'cost_centers',
                ];
                $expectedTriggers = array_merge(
                    ['trg_journal_balance', 'trg_leaf_account_only', 'trg_check_accounting_period',
                        'trg_single_base_currency', 'trg_block_posted_journal_updates', 'trg_block_posted_journal_deletes',
                        'trg_block_posted_detail_updates', 'trg_block_posted_detail_deletes',
                        'trg_auto_set_accounting_period', 'trg_prevent_hard_delete',
                        'trg_block_posted_claim_updates', 'trg_block_posted_claim_deletes'],
                    array_map(fn ($t) => "trg_audit_{$t}", $auditTables)
                );

                $expectedViews = ['vw_trial_balance', 'vw_account_balances', 'vw_general_ledger', 'vw_balance_sheet', 'vw_income_statement'];

                foreach ($expectedFunctions as $name) {
                    $results[$name] = in_array($name, $pgFunctionNames) ? 'OK' : 'MISSING';
                }
                foreach ($expectedTriggers as $name) {
                    $results[$name] = in_array($name, $pgTriggerNames) ? 'OK' : 'MISSING';
                }
                foreach ($expectedViews as $name) {
                    $results[$name] = in_array($name, $pgViewNames) ? 'OK' : 'MISSING';
                }
            }

            $hasErrors = in_array('MISSING', $results);
        }

        $rows = collect($results)->map(fn ($status, $name) => compact('name', 'status'))->values();

        return response()->view('import-fun-pro', compact('rows', 'hasErrors', 'driver'));
    })->name('import-fun-pro');

    /*
    |----------------------------------------------------------------------
    | Van Stock Reconciliation — vrs-fix
    |----------------------------------------------------------------------
    | Diagnoses and auto-repairs discrepancies between van_stock_batches /
    | van_stock_balances and the authoritative inventory_ledger_entries.
    | Idempotent — safe to run repeatedly. Super-admin only.
    */
    Route::get('/vrs-fix', function () {
        abort_unless(auth()->user()?->is_super_admin === 'Yes', 403);

        $rows = [];
        $fixedCount = 0;
        $okCount = 0;

        DB::beginTransaction();
        try {
            // All (vehicle_id, product_id) pairs across both denormalized tables
            $pairs = DB::table('van_stock_batches')
                ->select('vehicle_id', 'product_id')
                ->union(DB::table('van_stock_balances')->select('vehicle_id', 'product_id'))
                ->distinct()
                ->get();

            foreach ($pairs as $pair) {
                $vehicleId = $pair->vehicle_id;
                $productId = $pair->product_id;

                // Authoritative balance from ledger
                $ledgerBalance = (float) DB::table('inventory_ledger_entries')
                    ->where('vehicle_id', $vehicleId)
                    ->where('product_id', $productId)
                    ->sum(DB::raw('debit_qty - credit_qty'));
                $ledgerBalance = max(0.0, $ledgerBalance);

                // Current denormalized totals
                $vsbTotal = (float) DB::table('van_stock_batches')
                    ->where('vehicle_id', $vehicleId)
                    ->where('product_id', $productId)
                    ->sum('quantity_on_hand');

                $aggRecord = DB::table('van_stock_balances')
                    ->where('vehicle_id', $vehicleId)
                    ->where('product_id', $productId)
                    ->first();
                $vsbAgg = $aggRecord ? (float) $aggRecord->quantity_on_hand : null;

                $vehicleReg = DB::table('vehicles')->where('id', $vehicleId)->value('registration_number') ?? "Vehicle #{$vehicleId}";
                $productName = DB::table('products')->where('id', $productId)->value('product_name') ?? "Product #{$productId}";

                $discrepancy = round($vsbTotal - $ledgerBalance, 3);
                $aggDiscrepancy = $vsbAgg !== null ? round($vsbAgg - $ledgerBalance, 3) : null;

                if (abs($discrepancy) > 0.001 || ($aggDiscrepancy !== null && abs($aggDiscrepancy) > 0.001)) {
                    // Rebuild van_stock_batches FIFO from ledger balance
                    $batches = DB::table('van_stock_batches')
                        ->where('vehicle_id', $vehicleId)
                        ->where('product_id', $productId)
                        ->orderBy('created_at')
                        ->get();

                    $remaining = $ledgerBalance;
                    foreach ($batches as $batch) {
                        $maxCap = $batch->goods_issue_item_id
                            ? (float) (DB::table('goods_issue_items')->where('id', $batch->goods_issue_item_id)->value('quantity_issued') ?? $batch->quantity_on_hand)
                            : (float) $batch->quantity_on_hand;
                        $newQty = min($maxCap, $remaining);
                        DB::table('van_stock_batches')->where('id', $batch->id)->update(['quantity_on_hand' => $newQty]);
                        $remaining -= $newQty;
                    }
                    // Ledger shows more than all batches can absorb — add remainder to last batch
                    if ($remaining > 0.001 && $batches->isNotEmpty()) {
                        DB::table('van_stock_batches')
                            ->where('id', $batches->last()->id)
                            ->increment('quantity_on_hand', $remaining);
                    }

                    // Update or create aggregate van_stock_balances record
                    if ($aggRecord) {
                        DB::table('van_stock_balances')
                            ->where('vehicle_id', $vehicleId)
                            ->where('product_id', $productId)
                            ->update(['quantity_on_hand' => $ledgerBalance, 'last_updated' => now(), 'updated_at' => now()]);
                    } else {
                        DB::table('van_stock_balances')->insert([
                            'vehicle_id' => $vehicleId,
                            'product_id' => $productId,
                            'quantity_on_hand' => $ledgerBalance,
                            'opening_balance' => 0,
                            'average_cost' => 0,
                            'last_unit_cost' => 0,
                            'last_selling_price' => 0,
                            'last_updated' => now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    $rows[] = [
                        'vehicle' => $vehicleReg,
                        'product' => $productName,
                        'ledger' => $ledgerBalance,
                        'was_vsb' => $vsbTotal,
                        'was_agg' => $vsbAgg,
                        'status' => 'FIXED',
                    ];
                    $fixedCount++;
                } else {
                    $rows[] = [
                        'vehicle' => $vehicleReg,
                        'product' => $productName,
                        'ledger' => $ledgerBalance,
                        'was_vsb' => $vsbTotal,
                        'was_agg' => $vsbAgg,
                        'status' => 'OK',
                    ];
                    $okCount++;
                }
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            return back()->with('error', 'VRS Fix failed: '.$e->getMessage());
        }

        return response()->view('vrs-fix', compact('rows', 'fixedCount', 'okCount'));
    })->name('vrs-fix');

    /*
    |----------------------------------------------------------------------
    | Accounting — Journal Entries
    |----------------------------------------------------------------------
    | CRUD + post/reverse workflow. Quick helpers for common transactions.
    | Permissions: journal-entry-list, -create, -edit, -delete, -post, -reverse
    */
    Route::resource('journal-entries', JournalEntryController::class);
    Route::post('journal-entries/{journalEntry}/post', [JournalEntryController::class, 'post'])
        ->name('journal-entries.post');
    Route::post('journal-entries/{journalEntry}/reverse', [JournalEntryController::class, 'reverse'])
        ->name('journal-entries.reverse');

    Route::post('transactions/cash-receipt', [JournalEntryController::class, 'recordCashReceipt'])
        ->name('transactions.cash-receipt');
    Route::post('transactions/cash-payment', [JournalEntryController::class, 'recordCashPayment'])
        ->name('transactions.cash-payment');
    Route::post('transactions/opening-balance', [JournalEntryController::class, 'recordOpeningBalance'])
        ->name('transactions.opening-balance');

    /*
    |----------------------------------------------------------------------
    | Inventory — Opening Stock (one-time import)
    |----------------------------------------------------------------------
    | Import existing inventory as opening stock per supplier.
    | Creates a GRN with is_opening_stock=true, posts to inventory,
    | JE: Dr Inventory / Cr Opening Balance Equity, no payment created.
    | Permission: opening-stock-create
    */
    Route::post('opening-stock', [OpeningStockController::class, 'store'])
        ->name('opening-stock.store');
    Route::get('opening-stock/template/{supplier}', [OpeningStockController::class, 'downloadTemplate'])
        ->name('opening-stock.template');

    /*
    |----------------------------------------------------------------------
    | Opening Customer Balances (per salesman)
    |----------------------------------------------------------------------
    | CRUD for opening customer balances per employee/salesman.
    | Creates CustomerEmployeeAccount + opening_balance transaction via LedgerService.
    | Permissions: opening-customer-balance-list, -create, -edit, -delete
    */
    Route::post('opening-customer-balances/manual', [OpeningCustomerBalanceController::class, 'storeManual'])
        ->name('opening-customer-balances.store-manual');
    Route::post('opening-customer-balances/{opening_customer_balance}/post', [OpeningCustomerBalanceController::class, 'post'])
        ->name('opening-customer-balances.post');
    Route::resource('opening-customer-balances', OpeningCustomerBalanceController::class)
        ->except(['store']);

    /*
    |----------------------------------------------------------------------
    | Inventory — Goods Receipt Notes (GRN)
    |----------------------------------------------------------------------
    | Inbound inventory from suppliers. Post creates journal entries and
    | updates stock. Reverse creates a correcting entry.
    | Permissions: goods-receipt-note-list, -create, -edit, -delete, -post, -reverse, -import
    */
    Route::get('goods-receipt-notes/import-template', [GoodsReceiptNoteController::class, 'downloadImportTemplate'])
        ->name('goods-receipt-notes.import-template');
    Route::post('goods-receipt-notes/import', [GoodsReceiptNoteController::class, 'importItems'])
        ->name('goods-receipt-notes.import');
    Route::resource('goods-receipt-notes', GoodsReceiptNoteController::class);
    Route::post('goods-receipt-notes/{goodsReceiptNote}/post', [GoodsReceiptNoteController::class, 'post'])
        ->name('goods-receipt-notes.post');
    Route::post('goods-receipt-notes/{goodsReceiptNote}/reverse', [GoodsReceiptNoteController::class, 'reverse'])
        ->name('goods-receipt-notes.reverse');
    Route::get('api/suppliers/{supplier}/products', [GoodsReceiptNoteController::class, 'getProductsBySupplier'])
        ->name('api.suppliers.products');

    /*
    |----------------------------------------------------------------------
    | Inventory — Promotional Campaigns
    |----------------------------------------------------------------------
    */
    Route::resource('promotional-campaigns', PromotionalCampaignController::class);

    /*
    |----------------------------------------------------------------------
    | Inventory — Goods Issues (Sales Distribution)
    |----------------------------------------------------------------------
    | Outbound stock to salesmen/vehicles. Post deducts warehouse stock.
    | API endpoints supply form dropdowns (stock levels, employees, vehicles).
    | Permissions: goods-issue-list, -create, -edit, -delete, -post
    */
    Route::resource('goods-issues', GoodsIssueController::class);
    Route::post('goods-issues/{goodsIssue}/post', [GoodsIssueController::class, 'post'])
        ->name('goods-issues.post');
    Route::get('api/warehouses/{warehouse}/products/{product}/stock', [GoodsIssueController::class, 'getProductStock'])
        ->name('api.warehouses.products.stock');
    Route::get('api/employees/by-suppliers', [GoodsIssueController::class, 'getEmployeesBySuppliers'])
        ->name('api.employees.by-suppliers');
    Route::get('api/employees/{employee}/vehicles', [GoodsIssueController::class, 'getVehiclesByEmployee'])
        ->name('api.employees.vehicles');
    Route::get('api/products/by-suppliers', [GoodsIssueController::class, 'getProductsBySuppliers'])
        ->name('api.products.by-suppliers');

    /*
    |----------------------------------------------------------------------
    | Sales Settlements
    |----------------------------------------------------------------------
    | Reconcile goods issued vs. cash/credit returned by salesmen.
    | Permissions: sales-settlement-list, -create, -edit, -delete, -post
    */
    Route::resource('sales-settlements', SalesSettlementController::class);
    Route::post('sales-settlements/{salesSettlement}/post', [SalesSettlementController::class, 'post'])
        ->name('sales-settlements.post');
    Route::get('api/sales-settlements/goods-issues', [SalesSettlementController::class, 'fetchGoodsIssues'])
        ->name('api.sales-settlements.goods-issues');
    Route::get('api/sales-settlements/goods-issues/{id}/items', [SalesSettlementController::class, 'fetchGoodsIssueItems'])
        ->name('api.sales-settlements.goods-issues.items');
    Route::get('api/products/{product}/amr-batches', [SalesSettlementController::class, 'fetchBatchesForProduct'])
        ->name('api.products.amr-batches');

    /*
    |----------------------------------------------------------------------
    | Supplier Payments
    |----------------------------------------------------------------------
    | Record payments against GRNs. Post creates accounting entries.
    | Permissions: supplier-payment-list, -create, -edit, -delete, -post, -reverse
    */
    Route::resource('supplier-payments', SupplierPaymentController::class);
    Route::post('supplier-payments/{supplierPayment}/post', [SupplierPaymentController::class, 'post'])
        ->name('supplier-payments.post');
    Route::post('supplier-payments/{supplierPayment}/reverse', [SupplierPaymentController::class, 'reverse'])
        ->name('supplier-payments.reverse');
    Route::get('supplier-payments/create/{supplier}', [SupplierPaymentController::class, 'createForSupplier'])
        ->name('supplier-payments.create-for-supplier');
    Route::get('api/suppliers/{supplier}/unpaid-grns', [SupplierPaymentController::class, 'getUnpaidGrns'])
        ->name('api.suppliers.unpaid-grns');

    /*
    |----------------------------------------------------------------------
    | Claim Register
    |----------------------------------------------------------------------
    | Track supplier claims (debit) and recoveries (credit) with GL posting.
    | Permissions: claim-register-list, -create, -edit, -delete, -post
    */
    Route::resource('claim-registers', ClaimRegisterController::class);
    Route::post('claim-registers/{claimRegister}/post', [ClaimRegisterController::class, 'post'])
        ->name('claim-registers.post');

    /*
    |----------------------------------------------------------------------
    | Stock Adjustments
    |----------------------------------------------------------------------
    | Manual inventory adjustments (damage, theft, expiry, recall).
    | Posts to inventory ledger and creates GL journal entry.
    | Permissions: stock-adjustment-list, -create, -edit, -delete, -post
    */
    Route::resource('stock-adjustments', StockAdjustmentController::class);
    Route::post('stock-adjustments/{stockAdjustment}/post', [StockAdjustmentController::class, 'post'])
        ->name('stock-adjustments.post');
    Route::get('api/products/{product}/batches/{warehouse}', [StockAdjustmentController::class, 'getBatchesForProduct'])
        ->name('api.products.batches');

    /*
    |----------------------------------------------------------------------
    | Product Recalls
    |----------------------------------------------------------------------
    | Supplier-initiated product recalls with batch tracking.
    | Creates stock adjustment on posting, can generate claim register.
    | Permissions: product-recall-list, -create, -edit, -delete, -post, -cancel
    */
    Route::resource('product-recalls', ProductRecallController::class);
    Route::post('product-recalls/{productRecall}/post', [ProductRecallController::class, 'post'])
        ->name('product-recalls.post');
    Route::post('product-recalls/{productRecall}/cancel', [ProductRecallController::class, 'cancel'])
        ->name('product-recalls.cancel');
    Route::post('product-recalls/{productRecall}/create-claim', [ProductRecallController::class, 'createClaim'])
        ->name('product-recalls.create-claim');
    Route::get('api/suppliers/{supplier}/batches', [ProductRecallController::class, 'getBatchesForSupplier'])
        ->name('api.suppliers.batches');

    /*
    |----------------------------------------------------------------------
    | Employee Salary Management
    |----------------------------------------------------------------------
    | Salary structures and double-entry salary transactions ledger.
    | Post creates GL journal entry via SalaryService.
    | Permissions: employee-salary-list, -create, -edit, -delete
    |              employee-salary-transaction-list, -create, -edit, -delete, -post
    */
    Route::resource('employee-salaries', EmployeeSalaryController::class);
    Route::resource('employee-salary-transactions', EmployeeSalaryTransactionController::class);
    Route::post('employee-salary-transactions/{employeeSalaryTransaction}/post',
        [EmployeeSalaryTransactionController::class, 'post'])
        ->name('employee-salary-transactions.post');

    /*
    |----------------------------------------------------------------------
    | Inventory Stock Views
    |----------------------------------------------------------------------
    | Read-only views of current warehouse stock, optionally by batch.
    | Permissions: inventory-view, report-inventory-* (per controller)
    */
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('current-stock', [CurrentStockController::class, 'index'])->name('current-stock.index');
        Route::get('current-stock/by-batch', [CurrentStockController::class, 'showByBatch'])->name('current-stock.by-batch');
    });

    /*
    |----------------------------------------------------------------------
    | Sales & Distribution Reports
    |----------------------------------------------------------------------
    | Grouped report routes for daily sales, credit sales, creditors
    | ledger, van stock ledger, and van stock batch reports.
    */
    Route::prefix('reports/daily-sales')->name('reports.daily-sales.')->group(function () {
        Route::get('/', [DailySalesReportController::class, 'index'])->name('index');
        Route::get('/product-wise', [DailySalesReportController::class, 'productWise'])->name('product-wise');
        Route::get('/salesman-wise', [DailySalesReportController::class, 'salesmanWise'])->name('salesman-wise');
        Route::get('/van-stock', [DailySalesReportController::class, 'vanStock'])->name('van-stock');
    });

    Route::prefix('reports/credit-sales')->name('reports.credit-sales.')->group(function () {
        Route::get('/salesman-history', [CreditSalesReportController::class, 'salesmanCreditHistory'])->name('salesman-history');
        Route::get('/salesman/{employee}', [CreditSalesReportController::class, 'salesmanCreditDetails'])->name('salesman-details');
    });

    Route::prefix('reports/creditors-ledger')->name('reports.creditors-ledger.')->group(function () {
        Route::get('/', [CreditorsLedgerController::class, 'index'])->name('index');
        Route::get('/customer/{customer}/ledger', [CreditorsLedgerController::class, 'customerLedger'])->name('customer-ledger');
        Route::get('/customer/{customer}/credit-sales', [CreditorsLedgerController::class, 'customerCreditSales'])->name('customer-credit-sales');
        Route::get('/salesman-creditors', [CreditorsLedgerController::class, 'salesmanCreditors'])->name('salesman-creditors');
        Route::get('/aging-report', [CreditorsLedgerController::class, 'agingReport'])->name('aging-report');
    });

    Route::prefix('reports/van-stock-ledger')->name('reports.van-stock-ledger.')->group(function () {
        Route::get('/', [VanStockLedgerController::class, 'index'])->name('index');
        Route::get('/summary', [VanStockLedgerController::class, 'summary'])->name('summary');
        Route::get('/vehicle/{vehicle}', [VanStockLedgerController::class, 'vehicleLedger'])->name('vehicle-ledger');
    });

    Route::get('reports/van-stock-batch', [VanStockBatchReportController::class, 'index'])->name('reports.van-stock-batch.index');

    /*
    |----------------------------------------------------------------------
    | Settings — Master Data Management
    |----------------------------------------------------------------------
    | All system configuration and master data CRUD resources.
    | Individual controller permissions enforce access per resource.
    */
    Route::prefix('settings')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('settings.index');

        /* Accounting Configuration */
        Route::resource('account-types', AccountTypeController::class);
        Route::resource('accounting-periods', AccountingPeriodController::class);
        Route::get('chart-of-accounts/manage-tree-structure', [ChartOfAccountController::class, 'tree'])->name('chart-of-accounts.tree');
        Route::resource('chart-of-accounts', ChartOfAccountController::class);
        Route::resource('currencies', CurrencyController::class);
        Route::resource('cost-centers', CostCenterController::class);
        Route::resource('bank-accounts', BankAccountController::class);

        /* Tax Configuration */
        Route::resource('tax-codes', TaxCodeController::class);
        Route::resource('tax-rates', TaxRateController::class);
        Route::resource('product-tax-mappings', ProductTaxMappingController::class);
        Route::resource('tax-transactions', TaxTransactionController::class)->only(['index', 'show']);

        /* Organization & Logistics */
        Route::resource('companies', CompanyController::class);
        Route::resource('warehouses', WarehouseController::class);
        Route::resource('warehouse-types', WarehouseTypeController::class);
        Route::get('vehicles/export/excel', [VehicleController::class, 'exportExcel'])->name('vehicles.export.excel');
        Route::get('vehicles/export/pdf', [VehicleController::class, 'exportPdf'])->name('vehicles.export.pdf');
        Route::resource('vehicles', VehicleController::class);

        /* Products & Inventory */
        Route::get('products/export/excel', [ProductController::class, 'exportExcel'])->name('products.export.excel');
        Route::resource('products', ProductController::class);
        Route::resource('categories', CategoryController::class);
        Route::resource('uoms', UomController::class);

        /* Partners */
        Route::resource('suppliers', SupplierController::class);
        Route::get('employees/export/excel', [EmployeeController::class, 'exportExcel'])->name('employees.export.excel');
        Route::resource('employees', EmployeeController::class);
        Route::get('customers/export/excel', [CustomerController::class, 'exportExcel'])->name('customers.export.excel');
        Route::resource('customers', CustomerController::class);

        /* RBAC — Users, Roles & Permissions */
        Route::resource('users', UserController::class);
        Route::post('users/bulk-update', [UserController::class, 'bulkUpdate'])->name('users.bulk-update');
        Route::resource('roles', RoleController::class);
        Route::resource('permissions', PermissionController::class);
    });

    /*
    |----------------------------------------------------------------------
    | Financial & Operational Reports
    |----------------------------------------------------------------------
    | Centralized reporting: financial statements, settlement reports,
    | stock registers, and operational analytics.
    | Permissions: report-financial-*, report-inventory-*, report-sales-*, report-audit-* (per controller)
    */
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportsController::class, 'index'])->name('index');

        /* Financial Statements */
        Route::get('general-ledger/export/excel', [GeneralLedgerController::class, 'exportExcel'])->name('general-ledger.export.excel');
        Route::get('general-ledger', [GeneralLedgerController::class, 'index'])->name('general-ledger.index');
        Route::get('trial-balance', [TrialBalanceController::class, 'index'])->name('trial-balance.index');
        Route::get('account-balances', [AccountBalancesController::class, 'index'])->name('account-balances.index');
        Route::get('balance-sheet', [BalanceSheetController::class, 'index'])->name('balance-sheet.index');
        Route::get('income-statement', [IncomeStatementController::class, 'index'])->name('income-statement.index');
        Route::get('fmr-amr-comparison', [FmrAmrComparisonController::class, 'index'])->name('fmr-amr-comparison.index');

        /* Sales & Settlement Reports */
        Route::get('sales-settlement/{salesSettlement}/print', [SalesSettlementReportController::class, 'print'])->name('sales-settlement.print');
        Route::get('sales-settlement', [SalesSettlementReportController::class, 'index'])->name('sales-settlement.index');
        Route::get('custom-settlement', [CustomSettlementReportController::class, 'index'])->name('custom-settlement.index');
        Route::get('goods-issue', [GoodsIssueReportController::class, 'index'])->name('goods-issue.index');
        Route::get('cash-detail', [CashDetailController::class, 'index'])->name('cash-detail.index');
        Route::get('investment-summary', [InvestmentSummaryController::class, 'index'])->name('investment-summary.index');

        /* Inventory & Stock Reports */
        Route::get('daily-stock-register', [DailyStockRegisterController::class, 'index'])->name('daily-stock-register.index');
        Route::get('salesman-stock-register', [SalesmanStockRegisterController::class, 'index'])->name('salesman-stock-register.index');
        Route::get('inventory-ledger', [InventoryLedgerReportController::class, 'index'])->name('inventory-ledger.index');
        Route::get('shop-list', [ShopListController::class, 'index'])->name('shop-list.index');
        Route::get('sku-rates', [SkuRatesController::class, 'index'])->name('sku-rates.index');

        /* Claim Register */
        Route::prefix('claim-register')->name('claim-register.')->group(function () {
            Route::get('/', [ClaimRegisterReportController::class, 'index'])->name('index');
            Route::post('/', [ClaimRegisterReportController::class, 'store'])->name('store');
            Route::put('/{claimRegister}', [ClaimRegisterReportController::class, 'update'])->name('update');
            Route::delete('/{claimRegister}', [ClaimRegisterReportController::class, 'destroy'])->name('destroy');
        });

        /* Opening Customer Balance */
        Route::prefix('opening-customer-balance')->name('opening-customer-balance.')->group(function () {
            Route::get('/', [OpeningCustomerBalanceReportController::class, 'index'])->name('index');
            Route::get('/export/excel', [OpeningCustomerBalanceReportController::class, 'exportExcel'])->name('export.excel');
            Route::post('/', [OpeningCustomerBalanceReportController::class, 'store'])->name('store');
            Route::put('/{openingCustomerBalance}', [OpeningCustomerBalanceReportController::class, 'update'])->name('update');
            Route::post('/{openingCustomerBalance}/post', [OpeningCustomerBalanceReportController::class, 'post'])->name('post');
            Route::delete('/{openingCustomerBalance}', [OpeningCustomerBalanceReportController::class, 'destroy'])->name('destroy');
        });

        /* Analytics & Tax */
        Route::get('roi', [RoiReportController::class, 'index'])->name('roi.index');
        Route::get('scheme-discount', [SchemeDiscountReportController::class, 'index'])->name('scheme-discount.index');
        Route::get('percentage-expense', [PercentageExpenseReportController::class, 'index'])->name('percentage-expense.index');
        Route::get('advance-tax', [AdvanceTaxReportController::class, 'index'])->name('advance-tax.index');

        /* Supplier Ledger Register */
        Route::prefix('leger-register')->name('leger-register.')->group(function () {
            Route::get('/', [LegerRegisterController::class, 'index'])->name('index');
            Route::post('/', [LegerRegisterController::class, 'store'])->name('store');
            Route::put('/{legerRegister}', [LegerRegisterController::class, 'update'])->name('update');
            Route::delete('/{legerRegister}', [LegerRegisterController::class, 'destroy'])->name('destroy');
        });

        /* Invoice Summary */
        Route::prefix('invoice-summary')->name('invoice-summary.')->group(function () {
            Route::get('/', [InvoiceSummaryReportController::class, 'index'])->name('index');
            Route::post('/', [InvoiceSummaryReportController::class, 'store'])->name('store');
            Route::put('/{invoiceSummary}', [InvoiceSummaryReportController::class, 'update'])->name('update');
            Route::delete('/{invoiceSummary}', [InvoiceSummaryReportController::class, 'destroy'])->name('destroy');
        });
    });
});
