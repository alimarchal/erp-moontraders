<?php

/**
 * Web Routes
 *
 * All routes require authentication via Sanctum + Jetstream session guard.
 * Authorization is enforced at the controller level using Spatie permission
 * middleware (HasMiddleware interface) — not in this file.
 *
 * @see \App\Providers\AppServiceProvider  Gate::before() bypass for "Super Admin"
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
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProductController;
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
    | Inventory — Goods Receipt Notes (GRN)
    |----------------------------------------------------------------------
    | Inbound inventory from suppliers. Post creates journal entries and
    | updates stock. Reverse creates a correcting entry.
    | Permissions: goods-receipt-note-list, -create, -edit, -delete, -post, -reverse
    */
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
    | Permissions: report-view-inventory
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
        Route::get('/customer-history', [CreditSalesReportController::class, 'customerCreditHistory'])->name('customer-history');
        Route::get('/customer/{customer}', [CreditSalesReportController::class, 'customerCreditDetails'])->name('customer-details');
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
        Route::get('vehicles/export/pdf', [VehicleController::class, 'exportPdf'])->name('vehicles.export.pdf');
        Route::resource('vehicles', VehicleController::class);

        /* Products & Inventory */
        Route::resource('products', ProductController::class);
        Route::resource('categories', CategoryController::class);
        Route::resource('uoms', UomController::class);

        /* Partners */
        Route::resource('suppliers', SupplierController::class);
        Route::resource('employees', EmployeeController::class);
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
    | Permissions: report-view-financial, report-view-inventory (per controller)
    */
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportsController::class, 'index'])->name('index');

        /* Financial Statements */
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

        /* Inventory & Stock Reports */
        Route::get('daily-stock-register', [DailyStockRegisterController::class, 'index'])->name('daily-stock-register.index');
        Route::get('salesman-stock-register', [SalesmanStockRegisterController::class, 'index'])->name('salesman-stock-register.index');
        Route::get('inventory-ledger', [InventoryLedgerReportController::class, 'index'])->name('inventory-ledger.index');
        Route::get('shop-list', [ShopListController::class, 'index'])->name('shop-list.index');
        Route::get('sku-rates', [SkuRatesController::class, 'index'])->name('sku-rates.index');

        /* Claim Register */
        Route::get('claim-register', [ClaimRegisterReportController::class, 'index'])->name('claim-register.index');

        /* Analytics & Tax */
        Route::get('roi', [RoiReportController::class, 'index'])->name('roi.index');
        Route::get('scheme-discount', [SchemeDiscountReportController::class, 'index'])->name('scheme-discount.index');
        Route::get('percentage-expense', [PercentageExpenseReportController::class, 'index'])->name('percentage-expense.index');
        Route::get('advance-tax', [AdvanceTaxReportController::class, 'index'])->name('advance-tax.index');
    });
});
