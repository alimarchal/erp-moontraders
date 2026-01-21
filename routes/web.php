<?php

use App\Http\Controllers\AccountingPeriodController;
use App\Http\Controllers\AccountTypeController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\ChartOfAccountController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CostCenterController;
use App\Http\Controllers\CreditSalesReportController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\CurrentStockController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\GoodsIssueController;
use App\Http\Controllers\GoodsReceiptNoteController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductTaxMappingController;
use App\Http\Controllers\PromotionalCampaignController;
use App\Http\Controllers\Reports\AccountBalancesController;
use App\Http\Controllers\Reports\BalanceSheetController;
use App\Http\Controllers\Reports\CreditorsLedgerController;
use App\Http\Controllers\Reports\DailySalesReportController;
use App\Http\Controllers\Reports\FmrAmrComparisonController;
use App\Http\Controllers\Reports\GeneralLedgerController;
use App\Http\Controllers\Reports\IncomeStatementController;
use App\Http\Controllers\Reports\ShopListController;
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

Route::get('/', function () {
    return to_route('login');
    // view('welcome');
});

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // Journal Entry Routes
    Route::resource('journal-entries', JournalEntryController::class);

    // Additional journal entry actions
    Route::post('journal-entries/{journalEntry}/post', [JournalEntryController::class, 'post'])
        ->name('journal-entries.post');
    Route::post('journal-entries/{journalEntry}/reverse', [JournalEntryController::class, 'reverse'])
        ->name('journal-entries.reverse');

    // Quick transaction helpers
    Route::post('transactions/cash-receipt', [JournalEntryController::class, 'recordCashReceipt'])
        ->name('transactions.cash-receipt');
    Route::post('transactions/cash-payment', [JournalEntryController::class, 'recordCashPayment'])
        ->name('transactions.cash-payment');
    Route::post('transactions/opening-balance', [JournalEntryController::class, 'recordOpeningBalance'])
        ->name('transactions.opening-balance');

    // Inventory Routes
    Route::resource('goods-receipt-notes', GoodsReceiptNoteController::class);
    Route::post('goods-receipt-notes/{goodsReceiptNote}/post', [GoodsReceiptNoteController::class, 'post'])
        ->name('goods-receipt-notes.post');
    Route::post('goods-receipt-notes/{goodsReceiptNote}/reverse', [GoodsReceiptNoteController::class, 'reverse'])
        ->name('goods-receipt-notes.reverse');
    Route::get('api/suppliers/{supplier}/products', [GoodsReceiptNoteController::class, 'getProductsBySupplier'])
        ->name('api.suppliers.products');

    Route::resource('promotional-campaigns', PromotionalCampaignController::class);

    // Goods Issue Routes (Sales Distribution)
    Route::resource('goods-issues', GoodsIssueController::class);
    Route::post('goods-issues/{goodsIssue}/post', [GoodsIssueController::class, 'post'])
        ->name('goods-issues.post');
    Route::get('api/warehouses/{warehouse}/products/{product}/stock', [GoodsIssueController::class, 'getProductStock'])
        ->name('api.warehouses.products.stock');

    // Sales Settlement Routes
    Route::resource('sales-settlements', SalesSettlementController::class);
    Route::post('sales-settlements/{salesSettlement}/post', [SalesSettlementController::class, 'post'])
        ->name('sales-settlements.post');
    Route::get('api/sales-settlements/goods-issues', [SalesSettlementController::class, 'fetchGoodsIssues'])
        ->name('api.sales-settlements.goods-issues');
    Route::get('api/sales-settlements/goods-issues/{id}/items', [SalesSettlementController::class, 'fetchGoodsIssueItems'])
        ->name('api.sales-settlements.goods-issues.items');

    // Daily Sales Reports
    Route::prefix('reports/daily-sales')->name('reports.daily-sales.')->group(function () {
        Route::get('/', [DailySalesReportController::class, 'index'])->name('index');
        Route::get('/product-wise', [DailySalesReportController::class, 'productWise'])->name('product-wise');
        Route::get('/salesman-wise', [DailySalesReportController::class, 'salesmanWise'])->name('salesman-wise');
        Route::get('/van-stock', [DailySalesReportController::class, 'vanStock'])->name('van-stock');
    });

    // Credit Sales Reports
    Route::prefix('reports/credit-sales')->name('reports.credit-sales.')->group(function () {
        Route::get('/salesman-history', [CreditSalesReportController::class, 'salesmanCreditHistory'])->name('salesman-history');
        Route::get('/salesman/{employee}', [CreditSalesReportController::class, 'salesmanCreditDetails'])->name('salesman-details');
        Route::get('/customer-history', [CreditSalesReportController::class, 'customerCreditHistory'])->name('customer-history');
        Route::get('/customer/{customer}', [CreditSalesReportController::class, 'customerCreditDetails'])->name('customer-details');
    });

    // Creditors Ledger (Accounts Receivable) Reports
    Route::prefix('reports/creditors-ledger')->name('reports.creditors-ledger.')->group(function () {
        Route::get('/', [CreditorsLedgerController::class, 'index'])->name('index');
        Route::get('/customer/{customer}/ledger', [CreditorsLedgerController::class, 'customerLedger'])->name('customer-ledger');
        Route::get('/customer/{customer}/credit-sales', [CreditorsLedgerController::class, 'customerCreditSales'])->name('customer-credit-sales');
        Route::get('/salesman-creditors', [CreditorsLedgerController::class, 'salesmanCreditors'])->name('salesman-creditors');
        Route::get('/aging-report', [CreditorsLedgerController::class, 'agingReport'])->name('aging-report');
    });

    // Van Stock Ledger Reports
    Route::prefix('reports/van-stock-ledger')->name('reports.van-stock-ledger.')->group(function () {
        Route::get('/', [VanStockLedgerController::class, 'index'])->name('index');
        Route::get('/summary', [VanStockLedgerController::class, 'summary'])->name('summary');
        Route::get('/vehicle/{vehicle}', [VanStockLedgerController::class, 'vehicleLedger'])->name('vehicle-ledger');
    });

    // Van Stock by Batch Report
    Route::get('reports/van-stock-batch', [VanStockBatchReportController::class, 'index'])->name('reports.van-stock-batch.index');

    // Supplier Payment Routes
    Route::resource('supplier-payments', SupplierPaymentController::class);
    Route::post('supplier-payments/{supplierPayment}/post', [SupplierPaymentController::class, 'post'])
        ->name('supplier-payments.post');
    Route::post('supplier-payments/{supplierPayment}/reverse', [SupplierPaymentController::class, 'reverse'])
        ->name('supplier-payments.reverse');
    Route::get('supplier-payments/create/{supplier}', [SupplierPaymentController::class, 'createForSupplier'])
        ->name('supplier-payments.create-for-supplier');
    Route::get('api/suppliers/{supplier}/unpaid-grns', [SupplierPaymentController::class, 'getUnpaidGrns'])
        ->name('api.suppliers.unpaid-grns');

    // Inventory Stock Routes
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('current-stock', [CurrentStockController::class, 'index'])->name('current-stock.index');
        Route::get('current-stock/by-batch', [CurrentStockController::class, 'showByBatch'])->name('current-stock.by-batch');
    });

    // Settings Routes
    Route::prefix('settings')->group(function () {
        // Settings Index
        Route::get('/', [SettingsController::class, 'index'])->name('settings.index');

        // Account Types CRUD
        Route::resource('account-types', AccountTypeController::class);

        // Accounting Periods CRUD
        Route::resource('accounting-periods', AccountingPeriodController::class);

        // Tax Codes CRUD
        Route::resource('tax-codes', TaxCodeController::class);

        // Tax Rates CRUD
        Route::resource('tax-rates', TaxRateController::class);

        // Product Tax Mappings CRUD
        Route::resource('product-tax-mappings', ProductTaxMappingController::class);

        // Tax Transactions (Read-only)
        Route::resource('tax-transactions', TaxTransactionController::class)->only(['index', 'show']);

        // Currencies CRUD
        Route::resource('currencies', CurrencyController::class);

        // Cost Centers CRUD
        Route::resource('cost-centers', CostCenterController::class);

        // Chart of Accounts Tree View
        Route::get('chart-of-accounts/manage-tree-structure', [ChartOfAccountController::class, 'tree'])->name('chart-of-accounts.tree');

        // Chart of Accounts CRUD
        Route::resource('chart-of-accounts', ChartOfAccountController::class);

        // Company CRUD
        Route::resource('companies', CompanyController::class);

        // Warehouses CRUD
        Route::resource('warehouses', WarehouseController::class);

        // Warehouse Types CRUD
        Route::resource('warehouse-types', WarehouseTypeController::class);

        // Suppliers CRUD
        Route::resource('suppliers', SupplierController::class);

        // Employees CRUD
        Route::resource('employees', EmployeeController::class);

        // Products CRUD
        Route::resource('products', ProductController::class);

        // Customers CRUD
        Route::resource('customers', CustomerController::class);

        // Bank Accounts CRUD
        Route::resource('bank-accounts', BankAccountController::class);

        // Vehicles CRUD
        Route::get('vehicles/export/pdf', [VehicleController::class, 'exportPdf'])->name('vehicles.export.pdf');
        Route::resource('vehicles', VehicleController::class);

        // Units of Measure CRUD
        Route::resource('uoms', UomController::class);

        // RBAC Management
        Route::resource('users', UserController::class);
        Route::post('users/bulk-update', [UserController::class, 'bulkUpdate'])->name('users.bulk-update');
        Route::resource('roles', RoleController::class);
        Route::resource('permissions', PermissionController::class);
    });

    // Reports Routes
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportsController::class, 'index'])->name('index');
        Route::get('general-ledger', [GeneralLedgerController::class, 'index'])->name('general-ledger.index');
        Route::get('trial-balance', [TrialBalanceController::class, 'index'])->name('trial-balance.index');
        Route::get('account-balances', [AccountBalancesController::class, 'index'])->name('account-balances.index');
        Route::get('balance-sheet', [BalanceSheetController::class, 'index'])->name('balance-sheet.index');
        Route::get('income-statement', [IncomeStatementController::class, 'index'])->name('income-statement.index');
        Route::get('fmr-amr-comparison', [FmrAmrComparisonController::class, 'index'])->name('fmr-amr-comparison.index');
        Route::get('shop-list', [ShopListController::class, 'index'])->name('shop-list.index');
    });
});
