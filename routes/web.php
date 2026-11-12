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
use App\Http\Controllers\BankApprovalController;
use App\Http\Controllers\BankChequeController;
use App\Http\Controllers\BankEntryAttachmentController;
use App\Http\Controllers\BankEntryCategoryController;
use App\Http\Controllers\BankEntryRuleController;
use App\Http\Controllers\BankPartyAliasController;
use App\Http\Controllers\BankReceiptScanController;
use App\Http\Controllers\BankReconciliationController;
use App\Http\Controllers\BankRegisterHelpController;
use App\Http\Controllers\BankStatementEntryController;
use App\Http\Controllers\BankStatementImportController;
use App\Http\Controllers\BatchTransferController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CategoryRevenueController;
use App\Http\Controllers\ChartOfAccountController;
use App\Http\Controllers\ClaimRegisterController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanyTargetController;
use App\Http\Controllers\CostCenterController;
use App\Http\Controllers\CreditSalesReportController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\CurrentStockController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeSalaryController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseDetailController;
use App\Http\Controllers\ExpenseVendorController;
use App\Http\Controllers\ExpenseVendorPaymentController;
use App\Http\Controllers\ExpenseVoucherController;
use App\Http\Controllers\GoodsIssueController;
use App\Http\Controllers\GoodsReceiptNoteController;
use App\Http\Controllers\InvestmentOpeningBalanceController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\OpeningCustomerBalanceController;
use App\Http\Controllers\OpeningStockController;
use App\Http\Controllers\PayrollAdjustmentController;
use App\Http\Controllers\PayrollRunController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductRecallController;
use App\Http\Controllers\ProductTaxMappingController;
use App\Http\Controllers\ProfitCategoryController;
use App\Http\Controllers\PromotionalCampaignController;
use App\Http\Controllers\Reports\AccountBalancesController;
use App\Http\Controllers\Reports\AdvanceTaxCustomerSummaryController;
use App\Http\Controllers\Reports\AdvanceTaxReportController;
use App\Http\Controllers\Reports\AdvanceTaxSalesRegisterController;
use App\Http\Controllers\Reports\AmrDisposeRegisterController;
use App\Http\Controllers\Reports\BalanceSheetController;
use App\Http\Controllers\Reports\BankReportController;
use App\Http\Controllers\Reports\CashCollectionDistributorController;
use App\Http\Controllers\Reports\CashDetailController;
use App\Http\Controllers\Reports\ChequeRegisterController;
use App\Http\Controllers\Reports\ClaimRegisterReportController;
use App\Http\Controllers\Reports\CompanyTargetSummaryController;
use App\Http\Controllers\Reports\CreditorsLedgerController;
use App\Http\Controllers\Reports\CustomerAccountStatementController;
use App\Http\Controllers\Reports\CustomSettlementReportController;
use App\Http\Controllers\Reports\DailySalesReportController;
use App\Http\Controllers\Reports\DailyStockRegisterController;
use App\Http\Controllers\Reports\ExpenseDetailReportController;
use App\Http\Controllers\Reports\FmrAmrComparisonController;
use App\Http\Controllers\Reports\GeneralLedgerController;
use App\Http\Controllers\Reports\GoodsIssueReportController;
use App\Http\Controllers\Reports\IncomeStatementController;
use App\Http\Controllers\Reports\InventoryLedgerReportController;
use App\Http\Controllers\Reports\InvestmentSummaryController;
use App\Http\Controllers\Reports\InvoiceSummaryReportController;
use App\Http\Controllers\Reports\LedgerRegisterController;
use App\Http\Controllers\Reports\OpeningCustomerBalanceReportController;
use App\Http\Controllers\Reports\PercentageExpenseReportController;
use App\Http\Controllers\Reports\ProductPriceChangeLogController;
use App\Http\Controllers\Reports\ProfitAfterCategoryReportController;
use App\Http\Controllers\Reports\RevenueDetailReportController;
use App\Http\Controllers\Reports\RoiReportController;
use App\Http\Controllers\Reports\SalesmanStockRegisterController;
use App\Http\Controllers\Reports\SalesSettlementReportController;
use App\Http\Controllers\Reports\SchemeDiscountReportController;
use App\Http\Controllers\Reports\SchemeReceivedReportController;
use App\Http\Controllers\Reports\ShopListController;
use App\Http\Controllers\Reports\SkuFmrAmrController;
use App\Http\Controllers\Reports\SkuRatesController;
use App\Http\Controllers\Reports\StockAvailabilityReportController;
use App\Http\Controllers\Reports\SummaryRoiReportController;
use App\Http\Controllers\Reports\SupplierLedgerReportController;
use App\Http\Controllers\Reports\TrialBalanceController;
use App\Http\Controllers\Reports\TtsSummaryReportController;
use App\Http\Controllers\Reports\VanStockBatchReportController;
use App\Http\Controllers\Reports\VanStockLedgerController;
use App\Http\Controllers\Reports\VehicleReportController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalaryComponentController;
use App\Http\Controllers\SalesSettlementController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TaxCodeController;
use App\Http\Controllers\TaxRateController;
use App\Http\Controllers\TaxTransactionController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UomController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\WarehouseTypeController;
use App\Providers\AppServiceProvider;
use Illuminate\Http\Request;
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
    Route::get('goods-receipt-notes/help', [GoodsReceiptNoteController::class, 'help'])
        ->name('goods-receipt-notes.help');
    Route::get('goods-receipt-notes/import-template', [GoodsReceiptNoteController::class, 'downloadImportTemplate'])
        ->name('goods-receipt-notes.import-template');
    Route::post('goods-receipt-notes/import', [GoodsReceiptNoteController::class, 'importItems'])
        ->name('goods-receipt-notes.import');
    Route::resource('goods-receipt-notes', GoodsReceiptNoteController::class);
    Route::post('goods-receipt-notes/{goodsReceiptNote}/post', [GoodsReceiptNoteController::class, 'post'])
        ->name('goods-receipt-notes.post');
    Route::post('goods-receipt-notes/{goodsReceiptNote}/reverse', [GoodsReceiptNoteController::class, 'reverse'])
        ->name('goods-receipt-notes.reverse');
    Route::get('goods-receipt-notes/{goodsReceiptNote}/edit-special', [GoodsReceiptNoteController::class, 'editSpecial'])
        ->name('goods-receipt-notes.edit-special');
    Route::post('goods-receipt-notes/{goodsReceiptNote}/update-special', [GoodsReceiptNoteController::class, 'updateSpecial'])
        ->name('goods-receipt-notes.update-special');
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
    // Add or remove items on an issued goods issue (before settlement). The old /append-items address redirects here.
    Route::get('goods-issues/{goodsIssue}/items', [GoodsIssueController::class, 'appendItemsForm'])
        ->name('goods-issues.items');
    Route::get('goods-issues/{goodsIssue}/append-items', fn (Request $request, int $goodsIssue) => redirect()->to(
        route('goods-issues.items', $goodsIssue).($request->getQueryString() ? '?'.$request->getQueryString() : ''), 301
    ))->name('goods-issues.append-items');
    Route::post('goods-issues/{goodsIssue}/items/{item}/remove', [GoodsIssueController::class, 'removeItem'])
        ->scopeBindings()
        ->name('goods-issues.remove-item');
    Route::post('goods-issues/{goodsIssue}/append-items', [GoodsIssueController::class, 'appendItems'])
        ->name('goods-issues.store-appended-items');
    Route::resource('goods-issues', GoodsIssueController::class);
    Route::post('goods-issues/{goodsIssue}/post', [GoodsIssueController::class, 'post'])
        ->name('goods-issues.post');
    Route::post('goods-issues/{goodsIssue}/reverse', [GoodsIssueController::class, 'reverse'])
        ->name('goods-issues.reverse');
    Route::get('api/goods-issues/check-vehicle', [GoodsIssueController::class, 'checkVehicleGoodsIssue'])
        ->name('api.goods-issues.check-vehicle');
    Route::get('api/warehouses/{warehouse}/products/{product}/stock', [GoodsIssueController::class, 'getProductStock'])
        ->name('api.warehouses.products.stock');
    Route::get('api/employees/by-suppliers', [GoodsIssueController::class, 'getEmployeesBySuppliers'])
        ->name('api.employees.by-suppliers');
    Route::get('api/vehicles/by-suppliers', [GoodsIssueController::class, 'getVehiclesBySuppliers'])
        ->name('api.vehicles.by-suppliers');
    Route::get('api/products/by-suppliers', [GoodsIssueController::class, 'getProductsBySuppliers'])
        ->name('api.products.by-suppliers');

    /*
    |----------------------------------------------------------------------
    | Sales Settlements
    |----------------------------------------------------------------------
    | Reconcile goods issued vs. cash/credit returned by salesmen.
    | Permissions: sales-settlement-list, -create, -edit, -delete, -post, -revert
    */
    Route::get('sales-settlements/{salesSettlement}/attachments/{section}/{id}', [SalesSettlementController::class, 'attachment'])
        ->whereNumber('id')
        ->name('sales-settlements.attachments.show');
    Route::resource('sales-settlements', SalesSettlementController::class);
    Route::post('sales-settlements/{salesSettlement}/post', [SalesSettlementController::class, 'post'])
        ->name('sales-settlements.post');
    Route::post('sales-settlements/{salesSettlement}/revert', [SalesSettlementController::class, 'revert'])
        ->name('sales-settlements.revert');
    Route::get('sales-settlements/{salesSettlement}/edit-special', [SalesSettlementController::class, 'editSpecial'])
        ->name('sales-settlements.edit-special');
    Route::post('sales-settlements/{salesSettlement}/update-special', [SalesSettlementController::class, 'updateSpecial'])
        ->name('sales-settlements.update-special');
    Route::get('api/sales-settlements/goods-issues', [SalesSettlementController::class, 'fetchGoodsIssues'])
        ->name('api.sales-settlements.goods-issues');
    Route::get('api/sales-settlements/goods-issues/{id}/items', [SalesSettlementController::class, 'fetchGoodsIssueItems'])
        ->name('api.sales-settlements.goods-issues.items');
    Route::get('api/products/{product}/amr-batches', [SalesSettlementController::class, 'fetchBatchesForProduct'])
        ->name('api.products.amr-batches');

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
    | Expense Details
    |----------------------------------------------------------------------
    | Track category-based expenses (Stationary, TCS, Tonner & IT, Salaries,
    | Fuel, Van Work) with double-entry GL posting.
    | Permissions: expense-detail-list, -create, -edit, -delete, -post
    */
    Route::get('expense-vouchers/{expense_voucher}/attachments/{attachment}', [ExpenseVoucherController::class, 'attachment'])->name('expense-vouchers.attachment');
    Route::get('expense-vouchers/summary', [ExpenseVoucherController::class, 'summary'])->name('expense-vouchers.summary');
    Route::get('expense-vouchers/employees/search', [ExpenseVoucherController::class, 'employeeSearch'])->name('expense-vouchers.employees.search');
    Route::get('expense-vouchers/help', [ExpenseVoucherController::class, 'help'])->name('expense-vouchers.help');
    Route::resource('expense-vouchers', ExpenseVoucherController::class);
    Route::post('expense-vouchers/{expense_voucher}/submit', [ExpenseVoucherController::class, 'submit'])->name('expense-vouchers.submit');
    Route::post('expense-vouchers/{expense_voucher}/approve', [ExpenseVoucherController::class, 'approve'])->name('expense-vouchers.approve');
    Route::post('expense-vouchers/{expense_voucher}/reject', [ExpenseVoucherController::class, 'reject'])->name('expense-vouchers.reject');
    Route::get('expense-vouchers/{expense_voucher}/print', [ExpenseVoucherController::class, 'print'])->name('expense-vouchers.print');

    // Shops (daily-expense parties), month-end payments to them, and payroll with advance recovery.
    Route::get('expense-categories', [ExpenseCategoryController::class, 'index'])->name('expense-categories.index');
    Route::post('expense-categories', [ExpenseCategoryController::class, 'store'])->name('expense-categories.store');
    Route::put('expense-categories/{expense_category}', [ExpenseCategoryController::class, 'update'])->name('expense-categories.update');
    Route::get('expense-vendors/search', [ExpenseVendorController::class, 'search'])->name('expense-vendors.search');
    Route::resource('expense-vendors', ExpenseVendorController::class);
    Route::get('expense-vendor-payments/vendors/{expense_vendor}/vouchers', [ExpenseVendorPaymentController::class, 'vouchers'])->name('expense-vendor-payments.vouchers');
    Route::resource('expense-vendor-payments', ExpenseVendorPaymentController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('expense-vendor-payments/{expense_vendor_payment}/approve', [ExpenseVendorPaymentController::class, 'approve'])->name('expense-vendor-payments.approve');
    Route::post('expense-vendor-payments/{expense_vendor_payment}/reject', [ExpenseVendorPaymentController::class, 'reject'])->name('expense-vendor-payments.reject');

    Route::prefix('payroll')->name('payroll.')->group(function () {
        Route::get('help', [PayrollRunController::class, 'help'])->name('help');
        Route::get('adjustments', [PayrollAdjustmentController::class, 'index'])->name('adjustments.index');
        Route::post('adjustments', [PayrollAdjustmentController::class, 'store'])->name('adjustments.store');
        Route::get('adjustments/employees', [PayrollAdjustmentController::class, 'employees'])->name('adjustments.employees');
        Route::delete('adjustments/{adjustment}', [PayrollAdjustmentController::class, 'destroy'])->name('adjustments.destroy');
        Route::get('salaries', [EmployeeSalaryController::class, 'index'])->name('salaries.index');
        Route::post('salaries/bulk', [EmployeeSalaryController::class, 'bulkUpdate'])->name('salaries.bulk');
        Route::get('salaries/{employee}/edit', [EmployeeSalaryController::class, 'edit'])->name('salaries.edit');
        Route::put('salaries/{employee}', [EmployeeSalaryController::class, 'update'])->name('salaries.update');
        Route::get('components', [SalaryComponentController::class, 'index'])->name('components.index');
        Route::post('components', [SalaryComponentController::class, 'store'])->name('components.store');
        Route::put('components/{component}', [SalaryComponentController::class, 'update'])->name('components.update');

        Route::get('/', [PayrollRunController::class, 'index'])->name('index');
        Route::get('create', [PayrollRunController::class, 'create'])->name('create');
        Route::post('/', [PayrollRunController::class, 'store'])->name('store');
        Route::get('export/pdf', [PayrollRunController::class, 'listPdf'])->name('list.pdf');
        Route::get('export/excel', [PayrollRunController::class, 'listExcel'])->name('list.excel');
        Route::get('{payroll}', [PayrollRunController::class, 'show'])->name('show');
        Route::get('{payroll}/export/pdf', [PayrollRunController::class, 'exportPdf'])->name('export.pdf');
        Route::get('{payroll}/export/excel', [PayrollRunController::class, 'exportExcel'])->name('export.excel');
        Route::get('{payroll}/slips-pdf', [PayrollRunController::class, 'slipsPdf'])->name('slips.pdf');
        Route::get('{payroll}/slips/{slip}/pdf', [PayrollRunController::class, 'slipsPdf'])->name('slip.pdf');
        Route::delete('{payroll}', [PayrollRunController::class, 'destroy'])->name('destroy');
        Route::get('{payroll}/print', [PayrollRunController::class, 'printAll'])->name('print');
        Route::get('{payroll}/slips/{slip}', [PayrollRunController::class, 'slip'])->name('slips.show');
        Route::put('{payroll}/slips/{slip}', [PayrollRunController::class, 'updateSlip'])->name('slips.update');
        Route::post('{payroll}/slips/{slip}/lines', [PayrollRunController::class, 'addLine'])->name('lines.store');
        Route::delete('{payroll}/lines/{line}', [PayrollRunController::class, 'removeLine'])->name('lines.destroy');
        Route::post('{payroll}/add-employees', [PayrollRunController::class, 'addEmployees'])->name('add-employees');
        Route::post('{payroll}/recalculate', [PayrollRunController::class, 'recalculate'])->name('recalculate');
        Route::post('{payroll}/submit', [PayrollRunController::class, 'submit'])->name('submit');
        Route::post('{payroll}/approve', [PayrollRunController::class, 'approve'])->name('approve');
        Route::post('{payroll}/reject', [PayrollRunController::class, 'reject'])->name('reject');
        Route::post('{payroll}/pay', [PayrollRunController::class, 'pay'])->name('pay');
    });

    Route::resource('expense-details', ExpenseDetailController::class);
    Route::post('expense-details/{expenseDetail}/post', [ExpenseDetailController::class, 'post'])
        ->name('expense-details.post');

    /*
    |----------------------------------------------------------------------
    | Stock Adjustments
    |----------------------------------------------------------------------
    | Manual inventory adjustments (damage, theft, expiry, recall).
    | Posts to inventory ledger and creates GL journal entry.
    | Permissions: stock-adjustment-list, -create, -edit, -delete, -post
    */
    Route::get('stock-adjustments/help', [StockAdjustmentController::class, 'help'])->name('stock-adjustments.help');
    Route::resource('stock-adjustments', StockAdjustmentController::class);
    Route::post('stock-adjustments/{stockAdjustment}/post', [StockAdjustmentController::class, 'post'])
        ->name('stock-adjustments.post');
    Route::get('api/suppliers/{supplier}/stock-adjustment-products', [StockAdjustmentController::class, 'getProductsBySupplier'])
        ->name('api.suppliers.stock-adjustment-products');
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
    | Inventory Stock Views
    |----------------------------------------------------------------------
    | Read-only views of current warehouse stock, optionally by batch.
    | Permissions: inventory-view, report-inventory-* (per controller)
    */
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('current-stock', [CurrentStockController::class, 'index'])->name('current-stock.index');
        Route::get('current-stock/by-batch', [CurrentStockController::class, 'showByBatch'])->name('current-stock.by-batch');
        Route::get('current-stock/batch-transfer', [BatchTransferController::class, 'createFromStock'])
            ->name('current-stock.batch-transfer');
        Route::post('current-stock/batch-transfer', [BatchTransferController::class, 'storeFromStock'])
            ->name('current-stock.batch-transfer.store');
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
        Route::get('/product-wise/pdf', [DailySalesReportController::class, 'productWisePdf'])->name('product-wise.pdf');
        Route::get('/product-wise/excel', [DailySalesReportController::class, 'productWiseExcel'])->name('product-wise.excel');
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
    | Tickets — product change requests (own menu item, gated by ticket-* permissions)
    |----------------------------------------------------------------------
    */
    Route::get('tickets/products/{product}/batches', [TicketController::class, 'batches'])->name('tickets.product-batches');
    Route::get('tickets/customer-code/check', [TicketController::class, 'checkCustomerCode'])->name('tickets.customer-code.check');
    Route::get('tickets/help', [TicketController::class, 'help'])->name('tickets.help');
    Route::get('tickets/stock-adjustment/products/{product}/warehouses/{warehouse}/batches', [TicketController::class, 'adjustmentBatches'])->name('tickets.adjustment-batches');
    Route::post('tickets/{ticket}/approve', [TicketController::class, 'approve'])->name('tickets.approve');
    Route::post('tickets/{ticket}/customer-code', [TicketController::class, 'updateCustomerCode'])->name('tickets.customer-code');
    Route::post('tickets/{ticket}/reject', [TicketController::class, 'reject'])->name('tickets.reject');
    Route::resource('tickets', TicketController::class);

    /*
    |----------------------------------------------------------------------
    | Bank Statement Register — import, tag, approve (maker-checker), post, reconcile
    |----------------------------------------------------------------------
    */
    Route::get('bank-register/help', BankRegisterHelpController::class)->name('bank-register.help');

    Route::resource('bank-statement-imports', BankStatementImportController::class)->only(['create', 'store', 'show', 'destroy']);
    Route::post('bank-statement-imports/{bankStatementImport}/confirm', [BankStatementImportController::class, 'confirm'])->name('bank-statement-imports.confirm');

    Route::get('bank-statement-entries/export', [BankStatementEntryController::class, 'export'])->name('bank-statement-entries.export');
    Route::post('bank-statement-entries/bulk-classify', [BankStatementEntryController::class, 'bulkClassify'])->name('bank-statement-entries.bulk-classify');
    Route::post('bank-statement-entries/bulk-submit', [BankStatementEntryController::class, 'bulkSubmit'])->name('bank-statement-entries.bulk-submit');
    Route::post('bank-statement-entries/auto-classify', [BankStatementEntryController::class, 'autoClassify'])->name('bank-statement-entries.auto-classify');
    Route::get('bank-statement-entries/{bankStatementEntry}/drawer', [BankStatementEntryController::class, 'drawer'])->name('bank-statement-entries.drawer');
    Route::post('bank-statement-entries/{bankStatementEntry}/flag', [BankStatementEntryController::class, 'flag'])->name('bank-statement-entries.flag');
    Route::post('bank-statement-entries/{bankStatementEntry}/submit', [BankStatementEntryController::class, 'submit'])->name('bank-statement-entries.submit');
    Route::post('bank-statement-entries/{bankStatementEntry}/reverse', [BankStatementEntryController::class, 'reverse'])->name('bank-statement-entries.reverse');
    Route::post('bank-statement-entries/{bankStatementEntry}/link', [BankStatementEntryController::class, 'link'])->name('bank-statement-entries.link');
    Route::post('bank-statement-entries/{bankStatementEntry}/not-in-books', [BankStatementEntryController::class, 'notInBooks'])->name('bank-statement-entries.not-in-books');
    Route::post('bank-statement-entries/{bankStatementEntry}/attachments', [BankEntryAttachmentController::class, 'store'])->name('bank-statement-entries.attachments.store');
    Route::resource('bank-statement-entries', BankStatementEntryController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);

    Route::get('bank-attachments/{attachment}/download', [BankEntryAttachmentController::class, 'download'])->name('bank-attachments.download');
    Route::delete('bank-attachments/{attachment}', [BankEntryAttachmentController::class, 'destroy'])->name('bank-attachments.destroy');

    Route::get('bank-receipt-scans/{bankReceiptScan}/file', [BankReceiptScanController::class, 'file'])->name('bank-receipt-scans.file');
    Route::post('bank-receipt-scans/{bankReceiptScan}/link', [BankReceiptScanController::class, 'link'])->name('bank-receipt-scans.link');
    Route::resource('bank-receipt-scans', BankReceiptScanController::class)->only(['index', 'store', 'destroy'])->parameters(['bank-receipt-scans' => 'bankReceiptScan']);

    Route::post('bank-approvals/bulk-approve', [BankApprovalController::class, 'bulkApprove'])->name('bank-approvals.bulk-approve');
    Route::post('bank-approvals/{bankStatementEntry}/approve', [BankApprovalController::class, 'approve'])->name('bank-approvals.approve');
    Route::post('bank-approvals/{bankStatementEntry}/reject', [BankApprovalController::class, 'reject'])->name('bank-approvals.reject');

    Route::resource('bank-reconciliations', BankReconciliationController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('bank-reconciliations/{bankReconciliation}/auto-match', [BankReconciliationController::class, 'autoMatch'])->name('bank-reconciliations.auto-match');
    Route::post('bank-reconciliations/{bankReconciliation}/match', [BankReconciliationController::class, 'match'])->name('bank-reconciliations.match');
    Route::delete('bank-reconciliations/{bankReconciliation}/items/{item}', [BankReconciliationController::class, 'unmatch'])->name('bank-reconciliations.unmatch');
    Route::post('bank-reconciliations/{bankReconciliation}/complete', [BankReconciliationController::class, 'complete'])->name('bank-reconciliations.complete');
    Route::post('bank-reconciliations/{bankReconciliation}/cancel', [BankReconciliationController::class, 'cancel'])->name('bank-reconciliations.cancel');

    // The cheque register and the reports live under the Bank Register; its setup lists (categories, party aliases,
    // auto-classify rules) are master data under Settings. Route names are unchanged, old addresses redirect.
    Route::prefix('bank-register')->group(function () {
        Route::resource('bank-cheques', BankChequeController::class)->except(['show'])->parameters(['bank-cheques' => 'bankCheque']);
        Route::get('{setup}/{rest?}', fn (string $setup, ?string $rest = null) => redirect('/settings/'.$setup.($rest ? '/'.$rest : ''), 301))
            ->whereIn('setup', ['bank-entry-categories', 'bank-party-aliases', 'bank-entry-rules'])->where('rest', '.*');
    });

    Route::prefix('bank-register/reports')->name('reports.bank.')->group(function () {
        Route::get('/', [BankReportController::class, 'index'])->name('index');
        Route::get('{report}/export/excel', [BankReportController::class, 'exportExcel'])->name('export.excel');
        Route::get('{report}/export/pdf', [BankReportController::class, 'exportPdf'])->name('export.pdf');
        Route::get('{report}', [BankReportController::class, 'show'])->name('show');
    });

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
        Route::resource('investment-opening-balances', InvestmentOpeningBalanceController::class);
        Route::get('chart-of-accounts/manage-tree-structure', [ChartOfAccountController::class, 'tree'])->name('chart-of-accounts.tree');
        Route::resource('chart-of-accounts', ChartOfAccountController::class);
        Route::resource('currencies', CurrencyController::class);
        Route::resource('cost-centers', CostCenterController::class);
        Route::resource('bank-accounts', BankAccountController::class);

        /* Bank Register setup */
        Route::get('bank-entry-categories/export/{format}', [BankEntryCategoryController::class, 'export'])->name('bank-entry-categories.export');
        Route::resource('bank-entry-categories', BankEntryCategoryController::class)->except(['show'])->parameters(['bank-entry-categories' => 'bankEntryCategory']);
        Route::resource('bank-party-aliases', BankPartyAliasController::class)->except(['show'])->parameters(['bank-party-aliases' => 'bankPartyAlias']);
        Route::resource('bank-entry-rules', BankEntryRuleController::class)->except(['show'])->parameters(['bank-entry-rules' => 'bankEntryRule']);

        /* Tax Configuration */
        Route::resource('tax-codes', TaxCodeController::class);
        Route::resource('tax-rates', TaxRateController::class);
        Route::resource('product-tax-mappings', ProductTaxMappingController::class);
        Route::resource('tax-transactions', TaxTransactionController::class)->only(['index', 'show']);
        Route::resource('category-revenue', CategoryRevenueController::class)
            ->parameters(['category-revenue' => 'categoryRevenue']);
        Route::resource('profit-categories', ProfitCategoryController::class)
            ->parameters(['profit-categories' => 'profitCategory']);

        /* Organization & Logistics */
        Route::resource('companies', CompanyController::class);
        Route::resource('warehouses', WarehouseController::class);
        Route::resource('warehouse-types', WarehouseTypeController::class);
        Route::get('vehicles/export/excel', [VehicleController::class, 'exportExcel'])->name('vehicles.export.excel');
        Route::get('vehicles/export/pdf', [VehicleController::class, 'exportPdf'])->name('vehicles.export.pdf');
        Route::resource('vehicles', VehicleController::class);

        /* Products & Inventory */
        Route::get('products/export/excel', [ProductController::class, 'exportExcel'])->name('products.export.excel');
        Route::get('products/import', [ProductController::class, 'importForm'])->name('products.import.form');
        Route::get('products/import/template', [ProductController::class, 'importTemplate'])->name('products.import.template');
        Route::post('products/import', [ProductController::class, 'import'])->name('products.import');
        Route::get('products/help', [ProductController::class, 'help'])->name('products.help');
        Route::resource('products', ProductController::class);
        Route::resource('categories', CategoryController::class);
        Route::resource('uoms', UomController::class);

        /* Partners */
        Route::get('suppliers/help', [SupplierController::class, 'help'])->name('suppliers.help');
        Route::post('suppliers/{supplier}/toggle-active', [SupplierController::class, 'toggleActive'])->name('suppliers.toggle-active');
        Route::resource('suppliers', SupplierController::class)->except('destroy');
        Route::get('employees/export/excel', [EmployeeController::class, 'exportExcel'])->name('employees.export.excel');
        Route::post('employees/bulk-update', [EmployeeController::class, 'bulkUpdate'])->name('employees.bulk-update');
        Route::resource('employees', EmployeeController::class)->except('destroy');
        Route::get('company-targets/employees', [CompanyTargetController::class, 'employees'])->name('company-targets.employees');
        Route::get('company-targets/help', [CompanyTargetController::class, 'help'])->name('company-targets.help');
        Route::resource('company-targets', CompanyTargetController::class);
        Route::get('customers/export/excel', [CustomerController::class, 'exportExcel'])->name('customers.export.excel');
        Route::get('customers/import', [CustomerController::class, 'importForm'])->name('customers.import.form');
        Route::get('customers/import/template', [CustomerController::class, 'importTemplate'])->name('customers.import.template');
        Route::post('customers/import', [CustomerController::class, 'import'])->name('customers.import');
        Route::get('customers/help', [CustomerController::class, 'help'])->name('customers.help');
        Route::post('customers/{customer}/toggle-active', [CustomerController::class, 'toggleActive'])->name('customers.toggle-active');
        Route::resource('customers', CustomerController::class)->except('destroy');

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
        Route::get('cash-detail/export/pdf', [CashDetailController::class, 'exportPdf'])->name('cash-detail.export.pdf');
        Route::get('cash-collection-distributor/help', [CashCollectionDistributorController::class, 'help'])->name('cash-collection-distributor.help');
        Route::get('cash-collection-distributor', [CashCollectionDistributorController::class, 'index'])->name('cash-collection-distributor.index');
        Route::get('cash-collection-distributor/export/pdf', [CashCollectionDistributorController::class, 'exportPdf'])->name('cash-collection-distributor.export.pdf');
        Route::get('investment-summary', [InvestmentSummaryController::class, 'index'])->name('investment-summary.index');
        Route::get('company-target-summary/export/excel', [CompanyTargetSummaryController::class, 'exportExcel'])->name('company-target-summary.export.excel');
        Route::get('company-target-summary/export/pdf', [CompanyTargetSummaryController::class, 'exportPdf'])->name('company-target-summary.export.pdf');
        Route::get('company-target-summary', [CompanyTargetSummaryController::class, 'index'])->name('company-target-summary.index');
        Route::get('customer-account-statement', [CustomerAccountStatementController::class, 'index'])->name('customer-account-statement.index');
        Route::get('customer-account-statement/{customerEmployeeAccount}', [CustomerAccountStatementController::class, 'show'])->name('customer-account-statement.show');
        Route::get('sku-fmr-amr', [SkuFmrAmrController::class, 'index'])->name('sku-fmr-amr.index');

        /* Inventory & Stock Reports */
        Route::get('daily-stock-register', [DailyStockRegisterController::class, 'index'])->name('daily-stock-register.index');
        Route::get('salesman-stock-register', [SalesmanStockRegisterController::class, 'index'])->name('salesman-stock-register.index');
        Route::get('inventory-ledger', [InventoryLedgerReportController::class, 'index'])->name('inventory-ledger.index');
        Route::get('shop-list', [ShopListController::class, 'index'])->name('shop-list.index');
        Route::get('sku-rates', [SkuRatesController::class, 'index'])->name('sku-rates.index');
        Route::get('vehicle', [VehicleReportController::class, 'index'])->name('vehicle.index');
        Route::get('stock-availability/export/pdf', [StockAvailabilityReportController::class, 'exportPdf'])->name('stock-availability.export.pdf');
        Route::get('stock-availability', [StockAvailabilityReportController::class, 'index'])->name('stock-availability.index');
        Route::get('product-price-change-log', [ProductPriceChangeLogController::class, 'index'])->name('product-price-change-log.index');
        Route::get('supplier-ledger', [SupplierLedgerReportController::class, 'index'])->name('supplier-ledger.index');
        Route::get('supplier-ledger/{supplier}/salesmen', [SupplierLedgerReportController::class, 'salesmen'])->name('supplier-ledger.salesmen');
        Route::get('supplier-ledger/{supplier}/salesmen/{employee}/customers', [SupplierLedgerReportController::class, 'customers'])->name('supplier-ledger.customers');

        /* Claim Register */
        Route::prefix('claim-register')->name('claim-register.')->group(function () {
            Route::get('/', [ClaimRegisterReportController::class, 'index'])->name('index');
            Route::post('/', [ClaimRegisterReportController::class, 'store'])->name('store');
            Route::put('/{claimRegister}', [ClaimRegisterReportController::class, 'update'])->name('update');
            Route::post('/{claimRegister}/post', [ClaimRegisterReportController::class, 'post'])->name('post');
            Route::delete('/{claimRegister}', [ClaimRegisterReportController::class, 'destroy'])->name('destroy');
        });

        /* Expense Detail */
        Route::prefix('expense-detail')->name('expense-detail.')->group(function () {
            Route::get('/', [ExpenseDetailReportController::class, 'index'])->name('index');
            Route::post('/', [ExpenseDetailReportController::class, 'store'])->name('store');
            Route::put('/{expenseDetail}', [ExpenseDetailReportController::class, 'update'])->name('update');
            Route::post('/{expenseDetail}/post', [ExpenseDetailReportController::class, 'post'])->name('post');
            Route::delete('/{expenseDetail}', [ExpenseDetailReportController::class, 'destroy'])->name('destroy');
        });

        /* Revenue Detail */
        Route::prefix('revenue-detail')->name('revenue-detail.')->group(function () {
            Route::get('/', [RevenueDetailReportController::class, 'index'])->name('index');
            Route::post('/', [RevenueDetailReportController::class, 'store'])->name('store');
            Route::put('/{revenueDetail}', [RevenueDetailReportController::class, 'update'])->name('update');
            Route::post('/{revenueDetail}/post', [RevenueDetailReportController::class, 'post'])->name('post');
            Route::delete('/{revenueDetail}', [RevenueDetailReportController::class, 'destroy'])->name('destroy');
        });

        /* Profit After Category */
        Route::prefix('profit-after-category')->name('profit-after-category.')->group(function () {
            Route::get('/', [ProfitAfterCategoryReportController::class, 'index'])->name('index');
            Route::post('/', [ProfitAfterCategoryReportController::class, 'store'])->name('store');
            Route::put('/{profitCategoryDetail}', [ProfitAfterCategoryReportController::class, 'update'])->name('update');
            Route::post('/{profitCategoryDetail}/post', [ProfitAfterCategoryReportController::class, 'post'])->name('post');
            Route::delete('/{profitCategoryDetail}', [ProfitAfterCategoryReportController::class, 'destroy'])->name('destroy');
        });

        /* Scheme Received */
        Route::prefix('scheme-received')->name('scheme-received.')->group(function () {
            Route::get('/', [SchemeReceivedReportController::class, 'index'])->name('index');
            Route::post('/', [SchemeReceivedReportController::class, 'store'])->name('store');
            Route::put('/{schemeReceived}', [SchemeReceivedReportController::class, 'update'])->name('update');
            Route::delete('/{schemeReceived}', [SchemeReceivedReportController::class, 'destroy'])->name('destroy');
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
        Route::get('tts-summary', [TtsSummaryReportController::class, 'index'])->name('tts-summary.index');
        Route::get('roi', [RoiReportController::class, 'index'])->name('roi.index');
        Route::get('summary-roi', [SummaryRoiReportController::class, 'index'])->name('summary-roi.index');
        Route::get('summary-roi/help', [SummaryRoiReportController::class, 'help'])->name('summary-roi.help');
        Route::get('scheme-discount', [SchemeDiscountReportController::class, 'index'])->name('scheme-discount.index');
        Route::get('percentage-expense', [PercentageExpenseReportController::class, 'index'])->name('percentage-expense.index');
        Route::get('advance-tax', [AdvanceTaxReportController::class, 'index'])->name('advance-tax.index');
        Route::get('advance-tax-sales-register', [AdvanceTaxSalesRegisterController::class, 'index'])->name('advance-tax-sales-register.index');
        Route::get('advance-tax-customer-summary/export/pdf', [AdvanceTaxCustomerSummaryController::class, 'exportPdf'])->name('advance-tax-customer-summary.export.pdf');
        Route::get('advance-tax-customer-summary', [AdvanceTaxCustomerSummaryController::class, 'index'])->name('advance-tax-customer-summary.index');

        /* Cheque Register */
        Route::prefix('cheque-register')->name('cheque-register.')->group(function () {
            Route::get('/', [ChequeRegisterController::class, 'index'])->name('index');
            Route::post('/bulk-clear', [ChequeRegisterController::class, 'bulkClear'])->name('bulk-clear');
            Route::post('/{cheque}/update-status', [ChequeRegisterController::class, 'updateStatus'])->name('update-status');
            Route::get('/{cheque}/history', [ChequeRegisterController::class, 'history'])->name('history');
        });

        /* AMR Dispose Register */
        Route::prefix('amr-dispose-register')->name('amr-dispose-register.')->group(function () {
            Route::get('/', [AmrDisposeRegisterController::class, 'index'])->name('index');
            Route::post('/{type}/{id}/update-disposed', [AmrDisposeRegisterController::class, 'updateDisposed'])->name('update-disposed');
            Route::post('/bulk-update-disposed', [AmrDisposeRegisterController::class, 'bulkUpdateDisposed'])->name('bulk-update-disposed');
        });

        /* Supplier Ledger Register */
        // the old address keeps working for bookmarks
        Route::get('ledger-register', fn (Request $request) => redirect()->to('/reports/supplier-ledger-register'.($request->getQueryString() ? '?'.$request->getQueryString() : ''), 301));
        Route::prefix('supplier-ledger-register')->name('supplier-ledger-register.')->group(function () {
            Route::get('/', [LedgerRegisterController::class, 'index'])->name('index');
            Route::post('/', [LedgerRegisterController::class, 'store'])->name('store');
            Route::put('/{ledgerRegister}', [LedgerRegisterController::class, 'update'])->name('update');
            Route::delete('/{ledgerRegister}', [LedgerRegisterController::class, 'destroy'])->name('destroy');
            Route::post('/{ledgerRegister}/post', [LedgerRegisterController::class, 'post'])->name('post');
            Route::put('/opening-balance/{supplier}', [LedgerRegisterController::class, 'updateOpeningBalance'])->name('opening-balance.update');
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
