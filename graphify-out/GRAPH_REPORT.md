# Graph Report - moontraders  (2026-09-16)

## Corpus Check
- cluster-only mode — file stats not available

## Summary
- 4588 nodes · 11203 edges · 649 communities (105 shown, 158 thin omitted)
- Extraction: 97% EXTRACTED · 3% INFERRED · 0% AMBIGUOUS · INFERRED: 321 edges (avg confidence: 0.83)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `9cd35217`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- Community 0
- Community 1
- Community 2
- Community 3
- Community 4
- Community 5
- Community 6
- Community 7
- Community 8
- Community 9
- Community 10
- Community 11
- Community 12
- Community 13
- Community 14
- Community 15
- Community 16
- Community 17
- Community 18
- Community 19
- Community 20
- Community 21
- Community 22
- Community 23
- Community 24
- Community 25
- Community 26
- Community 27
- Community 28
- Community 29
- Community 30
- Community 31
- Community 32
- Community 33
- Community 34
- Community 35
- Community 36
- Community 37
- Community 38
- Community 39
- Community 40
- Community 41
- Community 42
- Community 43
- Community 44
- Community 45
- Community 46
- Community 47
- Community 48
- Community 49
- Community 50
- Community 51
- Community 52
- Community 53
- Community 54
- Community 55
- Community 56
- Community 57
- Community 58
- Community 59
- Community 60
- Community 61
- Community 62
- Community 63
- Community 64
- Community 65
- Community 66
- Community 67
- Community 68
- Community 69
- Community 70
- Community 71
- Community 72
- Community 73
- Community 74
- Community 75
- Community 76
- Community 77
- Community 78
- Community 79
- Community 80
- Community 81
- Community 82
- Community 83
- Community 84
- Community 85
- Community 86
- Community 87
- Community 88
- Community 89
- Community 90
- Community 91
- Community 92
- Community 93
- Community 94
- Community 95
- Community 96
- Community 97
- Community 98
- Community 99
- Community 100
- Community 101
- Community 102
- Community 103
- Community 104
- Community 105
- Community 106
- Community 107
- Community 108
- Community 109
- Community 110
- Community 111
- Community 112
- Community 113
- Community 114
- Community 115
- Community 116
- Community 117
- Community 118
- Community 119
- Community 120
- Community 121
- Community 122
- Community 123
- Community 124
- Community 125
- Community 126
- Community 127
- Community 128
- Community 129
- Community 130
- Community 131
- Community 132
- Community 133
- Community 134
- Community 135
- Community 136
- Community 137
- Community 138
- Community 139
- Community 140
- Community 141
- Community 142
- Community 143
- Community 144
- Community 145
- Community 147
- Community 148
- Community 149
- Community 150
- Community 151
- Community 152
- Community 153
- Community 154
- Community 155
- Community 156
- Community 157
- Community 158
- Community 159
- Community 160
- Community 161
- Community 162
- Community 163
- Community 164
- Community 165
- Community 166
- Community 167
- Community 168
- Community 169
- Community 170
- Community 171
- Community 172
- Community 173
- Community 174
- Community 175
- Community 176
- Community 177
- Community 178
- Community 179
- Community 180
- Community 181
- Community 182
- Community 183
- Community 184
- Community 185
- Community 186
- Community 187
- Community 188
- Community 189
- Community 190
- Community 191
- Community 192
- Community 193
- Community 194
- Community 195
- Community 196
- Community 197
- Community 198
- Community 199
- Community 200
- Community 201
- Community 202
- Community 203
- Community 275
- Community 276
- Community 277
- Community 278
- Community 279
- Community 280
- Community 281
- Community 282
- Community 283
- Community 284
- Community 285
- Community 286
- Community 287
- Community 288
- Community 289
- Community 290
- Community 291
- Community 292
- Community 293
- Community 294
- Community 295
- Community 296
- Community 297
- Community 298
- Community 299
- Community 300
- Community 301
- Community 302
- Community 303
- Community 304
- Community 305
- Community 306
- Community 307
- Community 308
- Community 309
- Community 310
- Community 311
- Community 312
- Community 313
- Community 314
- Community 315
- Community 316
- Community 317
- Community 318
- Community 319
- Community 320
- Community 321
- Community 322
- Community 323
- Community 324
- Community 325
- Community 326
- Community 327
- Community 328
- Community 329
- Community 330
- Community 331
- Community 332
- Community 333
- Community 335

## God Nodes (most connected - your core abstractions)
1. `User` - 449 edges
2. `Supplier` - 251 edges
3. `Employee` - 176 edges
4. `SalesSettlement` - 172 edges
5. `Product` - 154 edges
6. `ChartOfAccount` - 140 edges
7. `Controller` - 138 edges
8. `Warehouse` - 138 edges
9. `Vehicle` - 116 edges
10. `GoodsIssue` - 114 edges

## Surprising Connections (you probably didn't know these)
- `makeRevertUser()` --calls--> `User`  [EXTRACTED]
  tests/Feature/SalesSettlementRevertTest.php → app/Models/User.php
- `createDashboardPermissions()` --calls--> `Permission`  [INFERRED]
  tests/Feature/DashboardTest.php → app/Models/Permission.php
- `setupStockWithPromotionalBatches()` --calls--> `Permission`  [INFERRED]
  tests/Feature/GoodsIssueExcludePromotionalTest.php → app/Models/Permission.php
- `setupSingleBatchStockForMultiLineTest()` --calls--> `Permission`  [INFERRED]
  tests/Feature/GoodsIssueMultiLineSameProductTest.php → app/Models/Permission.php
- `setupWholeNumberUomStock()` --calls--> `Permission`  [INFERRED]
  tests/Feature/GoodsIssueWholeNumberQuantityTest.php → app/Models/Permission.php

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Supplier-Linked Seed Data** — database_seeders_data_ledger_registers_supplier_ledger, database_seeders_data_employee_list_employee, database_seeders_data_vehicles_delivery_vehicle, database_seeders_data_sku_product_sku, database_seeders_data_employee_list_nestle_pakistan [EXTRACTED 1.00]
- **Opening Balance Seeding Subsystem** — database_seeders_data_ledger_registers_supplier_ledger, database_seeders_data_transactions_customer_transaction, database_seeders_data_ledger_registers_ob_document_type, database_seeders_data_transactions_opening_balance_type [INFERRED 0.85]
- **Customer Accounts Receivable Subsystem** — database_seeders_data_customers_customer, database_seeders_data_accounts_customer_employee_account, database_seeders_data_transactions_customer_transaction, database_seeders_data_employee_list_employee [INFERRED 0.88]

## Communities (649 total, 158 thin omitted)

### Community 0 - "Community 0"
Cohesion: 0.03
Nodes (28): ChartOfAccountController, AccountingPeriod, AccountType, ChartOfAccount, Currency, AccountingPeriodPolicy, AccountTypePolicy, ChartOfAccountPolicy (+20 more)

### Community 1 - "Community 1"
Cohesion: 0.04
Nodes (20): Supplier, User, RolePolicy, SupplierPolicy, UserPolicy, Illuminate\Foundation\Auth\User, Illuminate\Foundation\Http\Middleware\PreventRequestForgery, Illuminate\Notifications\Notifiable (+12 more)

### Community 2 - "Community 2"
Cohesion: 0.03
Nodes (8): ProductPriceChangeLog, ProductRecallItem, SalesSettlementAmrPowder, SalesSettlementBankSlip, SalesSettlementBankTransfer, SalesSettlementExcessAmount, StockAdjustmentItem, Illuminate\Database\Eloquent\Relations\BelongsTo

### Community 3 - "Community 3"
Cohesion: 0.04
Nodes (27): Carbon\Carbon, Carbon\CarbonPeriod, AccountingPeriodSeeder, AccountTypeSeeder, AttachmentSeeder, BankAccountSeeder, CategorySeeder, ChartOfAccountSeeder (+19 more)

### Community 4 - "Community 4"
Cohesion: 0.06
Nodes (12): AccountBalance, BalanceSheetAccount, IncomeStatementAccount, InvoiceSummary, PaymentGrnAllocation, SchemeReceived, UserTracking, Database\Factories\InvestmentOpeningBalanceFactory (+4 more)

### Community 5 - "Community 5"
Cohesion: 0.05
Nodes (16): DailySalesReportController, Employee, GoodsReceiptNoteItem, Vehicle, Warehouse, EmployeePolicy, VehiclePolicy, WarehousePolicy (+8 more)

### Community 6 - "Community 6"
Cohesion: 0.06
Nodes (15): CustomerExport, EmployeeExport, GeneralLedgerExport, GoodsReceiptNoteTemplateExport, OpeningCustomerBalanceExport, OpeningStockTemplateExport, ProductExport, VehicleExport (+7 more)

### Community 7 - "Community 7"
Cohesion: 0.03
Nodes (21): AccountingPeriodController, AccountTypeController, App\Http\Controllers\EmployeeSalaryController, App\Http\Controllers\EmployeeSalaryTransactionController, OpeningStockController, AccountBalancesController, CashDetailController, CustomSettlementReportController (+13 more)

### Community 8 - "Community 8"
Cohesion: 0.07
Nodes (50): O(), ve(), v(), ya(), Ae(), B(), Be(), c() (+42 more)

### Community 9 - "Community 9"
Cohesion: 0.14
Nodes (13): Barryvdh\DomPDF\Facade\Pdf, Illuminate\Database\QueryException, Illuminate\Http\Request, Illuminate\Routing\Controllers\HasMiddleware, Illuminate\Routing\Controllers\Middleware, Illuminate\Support\Arr, Illuminate\Support\Facades\Hash, Illuminate\Support\Facades\Log (+5 more)

### Community 10 - "Community 10"
Cohesion: 0.06
Nodes (7): ClaimRegisterController, ClaimRegisterReportController, StoreClaimRegisterRequest, UpdateClaimRegisterRequest, ClaimRegister, ClaimRegisterPolicy, ClaimRegisterService

### Community 11 - "Community 11"
Cohesion: 0.05
Nodes (44): de(), aa(), ab(), ba(), bb(), ca(), cd(), da() (+36 more)

### Community 12 - "Community 12"
Cohesion: 0.05
Nodes (18): Controller, AdvanceTaxReportController, AdvanceTaxSalesRegisterController, BalanceSheetController, DailyStockRegisterController, IncomeStatementController, OpeningCustomerBalanceReportController, PercentageExpenseReportController (+10 more)

### Community 13 - "Community 13"
Cohesion: 0.05
Nodes (13): GoodsIssue, GoodsIssueItem, SalesSettlementCashDenomination, Uom, GoodsIssuePolicy, UomPolicy, GoodsReceiptNoteItemFactory, createAdvanceTaxIncomeAccount() (+5 more)

### Community 14 - "Community 14"
Cohesion: 0.06
Nodes (8): JournalEntryController, StoreJournalEntryRequest, UpdateJournalEntryRequest, JournalEntry, JournalEntryPolicy, PeriodClosingService, AccountingService, AttachmentFactory

### Community 15 - "Community 15"
Cohesion: 0.04
Nodes (15): AppendGoodsIssueItemsRequest, PostGoodsReceiptNoteRequest, StoreSaleItemRequest, StoreSchemeReceivedRequest, UpdateCategoryRequest, UpdateDeliveryNoteRequest, UpdateEmployeeSalaryRequest, UpdateGoodsIssueRequest (+7 more)

### Community 16 - "Community 16"
Cohesion: 0.06
Nodes (7): BankAccountController, SupplierPaymentController, BankAccount, SupplierPayment, SupplierPaymentPolicy, PaymentService, SalesSettlementBankTransferFactory

### Community 17 - "Community 17"
Cohesion: 0.12
Nodes (54): ac(), b(), bc(), cc(), d(), db(), e(), eb() (+46 more)

### Community 18 - "Community 18"
Cohesion: 0.06
Nodes (14): PermissionController, RoleController, UserController, LogOptions, Permission, LogOptions, LogOptions, Role (+6 more)

### Community 19 - "Community 19"
Cohesion: 0.08
Nodes (3): SalesSettlementController, SalesSettlement, SalesSettlementPolicy

### Community 20 - "Community 20"
Cohesion: 0.06
Nodes (7): CurrentStock, StockMovement, StockValuationLayer, VanStockBalance, VanStockBatch, DistributionService, createEditedGrnDriftStock()

### Community 21 - "Community 21"
Cohesion: 0.04
Nodes (11): StoreExpenseDetailRequest, StoreLedgerRegisterRequest, StoreRevenueDetailRequest, UpdateCostCenterRequest, UpdateExpenseDetailRequest, UpdateProductRequest, UpdateProfitCategoryDetailRequest, UpdateProfitCategoryRequest (+3 more)

### Community 22 - "Community 22"
Cohesion: 0.07
Nodes (31): A(), at(), b(), be(), ce(), e(), Ee(), fe() (+23 more)

### Community 23 - "Community 23"
Cohesion: 0.06
Nodes (7): CostCenterController, EmployeeController, QueryBuilder, StoreCostCenterRequest, CostCenter, CostCenterPolicy, EmployeeSeeder

### Community 24 - "Community 24"
Cohesion: 0.07
Nodes (4): ExpenseDetailController, ExpenseDetailReportController, ExpenseDetail, ExpenseDetailService

### Community 25 - "Community 25"
Cohesion: 0.05
Nodes (10): StoreCategoryRequest, StoreEmployeeSalaryRequest, StoreSalesSettlementRequest, StoreStockReceiptItemRequest, StoreStockReceiptRequest, UpdateAccountingPeriodRequest, UpdatePaymentRequest, UpdateStockReceiptItemRequest (+2 more)

### Community 26 - "Community 26"
Cohesion: 0.07
Nodes (4): CompanyController, Company, CompanyPolicy, CompanySeeder

### Community 27 - "Community 27"
Cohesion: 0.07
Nodes (14): AccountingPeriodFactory, AccountTypeFactory, CategoryFactory, CompanyFactory, EmployeeFactory, GoodsIssueItemFactory, InvoiceSummaryFactory, JournalEntryDetailFactory (+6 more)

### Community 29 - "Community 29"
Cohesion: 0.07
Nodes (5): CustomerEmployeeAccount, SalesmanLedger, LedgerService, createReportTransaction(), createStatementTransaction()

### Community 30 - "Community 30"
Cohesion: 0.06
Nodes (12): computeStyleTests(), dataAttr(), finalPropName(), getData(), Identity(), NOTE: This can be skipped if there are no unmatched elements (i.e.,…, TODO: Now that all calls to _data and _removeData have been replaced, TODO: identify versions (+4 more)

### Community 31 - "Community 31"
Cohesion: 0.06
Nodes (6): SalesSettlementAmrLiquid, SalesSettlementCheque, SalesSettlementCreditSale, SalesSettlementItemBatch, SalesSettlementPercentageExpense, SalesSettlementRecovery

### Community 32 - "Community 32"
Cohesion: 0.10
Nodes (3): StockAdjustmentController, StockAdjustment, StockAdjustmentService

### Community 33 - "Community 33"
Cohesion: 0.09
Nodes (9): CustomerAccountStatementController, TtsSummaryReportController, ReportsController, SettingsController, SettingsController, AppLayout, GuestLayout, Illuminate\View\Component (+1 more)

### Community 34 - "Community 34"
Cohesion: 0.06
Nodes (30): devDependencies, autoprefixer, axios, concurrently, laravel-vite-plugin, postcss, tailwindcss, @tailwindcss/forms (+22 more)

### Community 35 - "Community 35"
Cohesion: 0.10
Nodes (8): Product, SalesSettlementItem, ProductPolicy, ProductSeeder, createAmrLiquidForSupplier(), createAmrPowderForSupplier(), createRoiSettlement(), VanStockBatchConsumptionTest

### Community 36 - "Community 36"
Cohesion: 0.09
Nodes (6): CategoryRevenueController, RevenueDetailReportController, RevenueCategory, RevenueDetail, RevenueDetailService, RevenueDetailFactory

### Community 37 - "Community 37"
Cohesion: 0.09
Nodes (6): ProfitCategoryController, ProfitAfterCategoryReportController, ProfitCategory, ProfitCategoryDetail, ProfitCategoryDetailService, ProfitCategoryDetailFactory

### Community 38 - "Community 38"
Cohesion: 0.09
Nodes (6): CategoryController, SalesmanStockRegisterController, SkuRatesController, StockAvailabilityReportController, SummaryRoiReportController, Category

### Community 39 - "Community 39"
Cohesion: 0.09
Nodes (6): WarehouseController, WarehouseTypeController, WarehouseType, WarehouseTypePolicy, WarehouseSeeder, WarehouseTypeSeeder

### Community 42 - "Community 42"
Cohesion: 0.23
Nodes (24): b(), g(), c(), d(), c(), e(), f(), g() (+16 more)

### Community 44 - "Community 44"
Cohesion: 0.09
Nodes (5): JournalEntryDetailController, StoreJournalEntryDetailRequest, UpdateJournalEntryDetailRequest, JournalEntryDetail, JournalEntryDetailPolicy

### Community 45 - "Community 45"
Cohesion: 0.09
Nodes (5): CreditSalesReportController, ChequeRegisterController, CreditorsLedgerController, InvoiceSummaryReportController, LengthAwarePaginator

### Community 46 - "Community 46"
Cohesion: 0.10
Nodes (5): AttachmentController, StoreAttachmentRequest, UpdateAttachmentRequest, Attachment, AttachmentPolicy

### Community 47 - "Community 47"
Cohesion: 0.09
Nodes (3): Customer, CustomerPolicy, Illuminate\Database\Eloquent\Relations\HasManyThrough

### Community 51 - "Community 51"
Cohesion: 0.12
Nodes (3): TaxCodeController, TaxCode, TaxCodePolicy

### Community 53 - "Community 53"
Cohesion: 0.13
Nodes (3): BatchTransferController, StockBatch, BatchTransferService

### Community 54 - "Community 54"
Cohesion: 0.16
Nodes (4): AmrDisposeRegisterController, FmrAmrComparisonController, Illuminate\Support\Collection, Illuminate\Support\Facades\Request

### Community 57 - "Community 57"
Cohesion: 0.12
Nodes (3): LedgerRegister, LedgerRegisterPolicy, LedgerRegisterSeeder

### Community 58 - "Community 58"
Cohesion: 0.14
Nodes (3): OpeningCustomerBalanceController, QueryBuilder, CustomerEmployeeAccountTransaction

### Community 59 - "Community 59"
Cohesion: 0.13
Nodes (19): Blade Component: page-header, DB Table: customer_employee_account_transactions, DB Table: customer_employee_accounts, Migration: Add AMR Dispose Register Permission, Migration: Add Supplier Ledger Permission, Employee Model, Supplier Model, Pattern: Three-Level Drilldown Report (Supplier→Salesman→Customer) (+11 more)

### Community 60 - "Community 60"
Cohesion: 0.14
Nodes (6): GoodsReceiptNoteItemsImport, OpeningStockImport, Maatwebsite\Excel\Concerns\ToCollection, Maatwebsite\Excel\Concerns\WithHeadingRow, Maatwebsite\Excel\Concerns\WithValidation, PhpOffice\PhpSpreadsheet\Shared\Date

### Community 61 - "Community 61"
Cohesion: 0.30
Nodes (16): a(), c(), d(), e(), f(), g(), h(), i() (+8 more)

### Community 62 - "Community 62"
Cohesion: 0.11
Nodes (3): CurrencyController, StoreCurrencyRequest, UpdateCurrencyRequest

### Community 65 - "Community 65"
Cohesion: 0.15
Nodes (3): ProductTaxMappingController, ProductTaxMapping, ProductTaxMappingPolicy

### Community 66 - "Community 66"
Cohesion: 0.15
Nodes (3): TaxRateController, TaxRate, TaxRatePolicy

### Community 67 - "Community 67"
Cohesion: 0.15
Nodes (3): TaxTransactionController, TaxTransaction, TaxTransactionPolicy

### Community 68 - "Community 68"
Cohesion: 0.16
Nodes (11): CheckUserStatus, SetDatabaseAuditContext, Closure, Illuminate\Foundation\Application, Illuminate\Foundation\Configuration\Exceptions, Illuminate\Foundation\Configuration\Middleware, Illuminate\Support\Facades\Auth, Spatie\Permission\Middleware\PermissionMiddleware (+3 more)

### Community 70 - "Community 70"
Cohesion: 0.15
Nodes (8): Laravel\Jetstream\Features, Laravel\Jetstream\Http\Livewire\ApiTokenManager, Laravel\Jetstream\Http\Livewire\DeleteUserForm, Laravel\Jetstream\Http\Livewire\LogoutOtherBrowserSessionsForm, Laravel\Jetstream\Http\Livewire\UpdatePasswordForm, Laravel\Jetstream\Http\Livewire\UpdateProfileInformationForm, Laravel\Jetstream\Http\Middleware\AuthenticateSession, Livewire\Livewire

### Community 71 - "Community 71"
Cohesion: 0.22
Nodes (16): addCheckConstraints(), createMySQLAuditTriggers(), createMySQLSnapshotHelpers(), createMySQLSoftDeleteProtection(), createMySQLTriggers(), createPostgreSQLAccountingFunctions(), createPostgreSQLAuditTriggers(), createPostgreSQLImmutabilityTriggers() (+8 more)

### Community 75 - "Community 75"
Cohesion: 0.17
Nodes (5): AppServiceProvider, FortifyServiceProvider, JetstreamServiceProvider, UserTrackingServiceProvider, Illuminate\Support\ServiceProvider

### Community 76 - "Community 76"
Cohesion: 0.16
Nodes (16): Customer Employee Account, Customer Channel Type (General Store, Pharmacy, Wholesale), Customer Record, Employee Record, Nestlé Pakistan Supplier, Journal Entry Reference in Ledger, Opening Balance Document Type (OB), Supplier Ledger Register (+8 more)

### Community 77 - "Community 77"
Cohesion: 0.16
Nodes (8): PasswordValidationRules, ResetUserPassword, UpdateUserPassword, Illuminate\Contracts\Validation\Rule, Illuminate\Support\Facades\Validator, Illuminate\Validation\Rules\Password, Laravel\Fortify\Contracts\ResetsUserPasswords, Laravel\Fortify\Contracts\UpdatesUserPasswords

### Community 78 - "Community 78"
Cohesion: 0.15
Nodes (8): SchedulerHeartbeat, SnapshotInventory, RebuildDailyInventorySnapshots, ResyncStockValues, Command, Illuminate\Console\Attributes\Description, Illuminate\Console\Attributes\Signature, Illuminate\Console\Command

### Community 80 - "Community 80"
Cohesion: 0.18
Nodes (3): CurrentStockByBatch, ProductRecallService, Illuminate\Database\Eloquent\Collection

### Community 83 - "Community 83"
Cohesion: 0.14
Nodes (14): require, barryvdh/laravel-dompdf, laravel/framework, laravel/jetstream, laravel/sanctum, laravel/tinker, livewire/livewire, maatwebsite/excel (+6 more)

### Community 84 - "Community 84"
Cohesion: 0.16
Nodes (14): buildFragment(), buildParams(), cloneCopyEvent(), disableScript(), DOMEval(), domManip(), getAll(), isArrayLike() (+6 more)

### Community 86 - "Community 86"
Cohesion: 0.15
Nodes (3): ProfitCategoryFactory, RevenueCategoryFactory, Illuminate\Support\Str

### Community 87 - "Community 87"
Cohesion: 0.24
Nodes (9): Illuminate\Http\UploadedFile, PhpOffice\PhpSpreadsheet\Spreadsheet, PhpOffice\PhpSpreadsheet\Writer\Xlsx, createTestExcelFile(), UploadedFile, createOpeningStockExcel(), UploadedFile, makeOpeningStockFile() (+1 more)

### Community 93 - "Community 93"
Cohesion: 0.17
Nodes (11): autoload-dev, psr-4, description, keywords, license, minimum-stability, name, prefer-stable (+3 more)

### Community 94 - "Community 94"
Cohesion: 0.17
Nodes (11): Spatie\Backup\Notifications\Notifiable, Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification, Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification, Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification, Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification, Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification, Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification, Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy (+3 more)

### Community 95 - "Community 95"
Cohesion: 0.17
Nodes (7): Illuminate\Auth\Events\Verified, Illuminate\Auth\Notifications\ResetPassword, Illuminate\Support\Facades\Event, Illuminate\Support\Facades\Notification, Illuminate\Support\Facades\URL, Laravel\Fortify\Features, Laravel\Jetstream\Http\Livewire\TwoFactorAuthenticationForm

### Community 96 - "Community 96"
Cohesion: 0.20
Nodes (12): addCombinator(), condense(), createPositionalPseudo(), elementMatcher(), markFunction(), matcherFromGroupMatchers(), matcherFromTokens(), multipleContexts() (+4 more)

### Community 97 - "Community 97"
Cohesion: 0.18
Nodes (12): adoptValue(), ajaxConvert(), ajaxHandleResponses(), Animation(), createFxNow(), createTween(), defaultPrefilter(), done() (+4 more)

### Community 98 - "Community 98"
Cohesion: 0.24
Nodes (3): DocumentType, LedgerRegisterFactory, static

### Community 99 - "Community 99"
Cohesion: 0.18
Nodes (8): Google\Client, Google\Service\Drive, Illuminate\Console\Scheduling\Schedule, Illuminate\Filesystem\FilesystemAdapter, Illuminate\Support\Facades\Gate, Illuminate\Support\Facades\Storage, League\Flysystem\Filesystem, Masbug\Flysystem\GoogleDriveAdapter

### Community 102 - "Community 102"
Cohesion: 0.24
Nodes (5): CreateNewUser, DeleteUser, Laravel\Fortify\Contracts\CreatesNewUsers, Laravel\Jetstream\Contracts\DeletesUsers, Laravel\Jetstream\Jetstream

### Community 105 - "Community 105"
Cohesion: 0.36
Nodes (3): GeneralLedgerController, QueryBuilder, GeneralLedgerEntry

### Community 108 - "Community 108"
Cohesion: 0.22
Nodes (9): require-dev, fakerphp/faker, laravel/boost, laravel/pail, laravel/pint, mockery/mockery, nunomaduro/collision, pestphp/pest (+1 more)

### Community 109 - "Community 109"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 117 - "Community 117"
Cohesion: 0.38
Nodes (3): App\Models\Team, static, UserFactory

### Community 118 - "Community 118"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 119 - "Community 119"
Cohesion: 0.43
Nodes (7): Branch, Report, Stationary, User, Branchwise Report, Stationary Report, User Management

### Community 120 - "Community 120"
Cohesion: 0.29
Nodes (6): confirmApiTokenDeletion({{ $token->id }}), deleteApiToken, manageApiTokenPermissions({{ $token->id }}), $toggle(, $set(, updateApiToken

### Community 124 - "Community 124"
Cohesion: 0.47
Nodes (3): UpdateUserProfileInformation, Illuminate\Contracts\Auth\MustVerifyEmail, Laravel\Fortify\Contracts\UpdatesUserProfileInformation

### Community 140 - "Community 140"
Cohesion: 0.40
Nodes (6): Moon Traders Application, Human Resources Module, User Management Module, HR Module Icon, Moon Traders Logo, Add Users Icon

### Community 141 - "Community 141"
Cohesion: 0.33
Nodes (5): Illuminate\Cache\RateLimiting\Limit, Illuminate\Support\Facades\RateLimiter, Illuminate\Validation\ValidationException, Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable, Laravel\Fortify\Fortify

### Community 170 - "Community 170"
Cohesion: 0.80
Nodes (5): Audit Document, Audit Review / Inspection, Audit Verification / Checkmark, Audits Functionality, Audits Icon

### Community 171 - "Community 171"
Cohesion: 0.60
Nodes (5): Bank Deposit, Branches, Permission / Access Control, Report (Financial), Stat Report (Pencil & Ruler)

### Community 172 - "Community 172"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 173 - "Community 173"
Cohesion: 0.40
Nodes (4): Monolog\Handler\NullHandler, Monolog\Handler\StreamHandler, Monolog\Handler\SyslogUdpHandler, Monolog\Processor\PsrLogMessageProcessor

### Community 174 - "Community 174"
Cohesion: 0.40
Nodes (4): Illuminate\Cookie\Middleware\EncryptCookies, Illuminate\Foundation\Http\Middleware\ValidateCsrfToken, Laravel\Sanctum\Http\Middleware\AuthenticateSession, Laravel\Sanctum\Sanctum

### Community 176 - "Community 176"
Cohesion: 0.50
Nodes (4): SITE_PATH, php, herd, laravel-boost

### Community 184 - "Community 184"
Cohesion: 0.83
Nodes (4): Bank / Financial Institution, Cash / Money, Deposit Action, Deposit Icon

### Community 185 - "Community 185"
Cohesion: 0.50
Nodes (3): confirmLogout, logoutOtherBrowserSessions, $toggle(

### Community 186 - "Community 186"
Cohesion: 0.50
Nodes (3): confirmUserDeletion, deleteUser, $toggle(

### Community 188 - "Community 188"
Cohesion: 0.50
Nodes (4): expectSync(), leverageNative(), returnTrue(), safeActiveElement()

### Community 192 - "Community 192"
Cohesion: 1.00
Nodes (3): Bank of Azad Jammu & Kashmir (BAJK) Logo, Circular Refresh/Sync Icon, Manual Document Icon

### Community 193 - "Community 193"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 275 - "Community 275"
Cohesion: 1.00
Nodes (3): Account Holder (User with Location Pin), Complaint Module (Alert Chat Bubble), Employee / Representative (Business Person Icon)

### Community 276 - "Community 276"
Cohesion: 1.00
Nodes (3): Financial Background Image, Branch Target Reports Icon, HR Module Icon

### Community 277 - "Community 277"
Cohesion: 1.00
Nodes (3): Bank Module, Daily Bank Statement, Districts

### Community 278 - "Community 278"
Cohesion: 1.00
Nodes (3): Classification, Region Deposit, User Roles

### Community 279 - "Community 279"
Cohesion: 1.00
Nodes (3): Dispatch Module, Reports Module, Stationary Module

### Community 280 - "Community 280"
Cohesion: 0.67
Nodes (3): boxModelAdjustment(), curCSS(), getWidthOrHeight()

### Community 281 - "Community 281"
Cohesion: 0.67
Nodes (3): camelCase(), fcamelCase(), propFilter()

## Knowledge Gaps
- **150 isolated node(s):** `fakerphp/faker`, `laravel/boost`, `laravel/pail`, `laravel/pint`, `mockery/mockery` (+145 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 1519 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **158 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `Community 1` to `Community 0`, `Community 2`, `Community 4`, `Community 5`, `Community 7`, `Community 9`, `Community 10`, `Community 13`, `Community 14`, `Community 141`, `Community 16`, `Community 144`, `Community 18`, `Community 19`, `Community 23`, `Community 26`, `Community 35`, `Community 39`, `Community 43`, `Community 44`, `Community 46`, `Community 47`, `Community 51`, `Community 57`, `Community 65`, `Community 66`, `Community 67`, `Community 68`, `Community 70`, `Community 75`, `Community 77`, `Community 87`, `Community 88`, `Community 95`, `Community 102`, `Community 103`, `Community 117`, `Community 124`?**
  _High betweenness centrality (0.097) - this node is a cross-community bridge._
- **Why does `Supplier` connect `Community 1` to `Community 0`, `Community 2`, `Community 3`, `Community 4`, `Community 5`, `Community 6`, `Community 7`, `Community 133`, `Community 9`, `Community 10`, `Community 12`, `Community 13`, `Community 142`, `Community 16`, `Community 144`, `Community 18`, `Community 19`, `Community 23`, `Community 27`, `Community 31`, `Community 32`, `Community 33`, `Community 35`, `Community 36`, `Community 37`, `Community 38`, `Community 40`, `Community 45`, `Community 54`, `Community 55`, `Community 56`, `Community 57`, `Community 58`, `Community 59`, `Community 60`, `Community 64`, `Community 69`, `Community 81`, `Community 86`, `Community 87`, `Community 98`, `Community 100`, `Community 103`, `Community 107`, `Community 110`, `Community 121`?**
  _High betweenness centrality (0.050) - this node is a cross-community bridge._
- **Why does `Product` connect `Community 35` to `Community 0`, `Community 1`, `Community 130`, `Community 2`, `Community 3`, `Community 5`, `Community 6`, `Community 7`, `Community 4`, `Community 9`, `Community 12`, `Community 13`, `Community 145`, `Community 19`, `Community 21`, `Community 32`, `Community 33`, `Community 38`, `Community 43`, `Community 52`, `Community 55`, `Community 60`, `Community 63`, `Community 64`, `Community 65`, `Community 198`, `Community 87`, `Community 103`, `Community 110`, `Community 113`?**
  _High betweenness centrality (0.030) - this node is a cross-community bridge._
- **What connects `fakerphp/faker`, `laravel/boost`, `laravel/pail` to the rest of the system?**
  _150 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Community 0` be split into smaller, more focused modules?**
  _Cohesion score 0.03403585271317829 - nodes in this community are weakly interconnected._
- **Should `Community 1` be split into smaller, more focused modules?**
  _Cohesion score 0.03535483870967742 - nodes in this community are weakly interconnected._
- **Should `Community 2` be split into smaller, more focused modules?**
  _Cohesion score 0.027233635929288104 - nodes in this community are weakly interconnected._