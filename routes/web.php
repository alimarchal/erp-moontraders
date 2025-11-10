<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\CostCenterController;
use App\Http\Controllers\AccountTypeController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\ChartOfAccountController;
use App\Http\Controllers\AccountingPeriodController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\WarehouseTypeController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\UomController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\Reports\GeneralLedgerController;
use App\Http\Controllers\Reports\AccountBalancesController;
use App\Http\Controllers\Reports\BalanceSheetController;
use App\Http\Controllers\Reports\IncomeStatementController;
use App\Http\Controllers\Reports\TrialBalanceController;
use App\Http\Controllers\GoodsReceiptNoteController;
use App\Http\Controllers\CurrentStockController;
use App\Http\Controllers\PromotionalCampaignController;

Route::get('/', function () {
    return to_route('login');
    // view('welcome');
});

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified',])->group(function () {
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

    Route::resource('promotional-campaigns', PromotionalCampaignController::class);

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

        // Product Categories CRUD
        Route::resource('product-categories', ProductCategoryController::class);

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
    });

    // Reports Routes
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportsController::class, 'index'])->name('index');
        Route::get('general-ledger', [GeneralLedgerController::class, 'index'])->name('general-ledger.index');
        Route::get('trial-balance', [TrialBalanceController::class, 'index'])->name('trial-balance.index');
        Route::get('account-balances', [AccountBalancesController::class, 'index'])->name('account-balances.index');
        Route::get('balance-sheet', [BalanceSheetController::class, 'index'])->name('balance-sheet.index');
        Route::get('income-statement', [IncomeStatementController::class, 'index'])->name('income-statement.index');
    });
});
