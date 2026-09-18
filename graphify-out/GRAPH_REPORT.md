# Graph Report - moontraders  (2026-09-18)

## Corpus Check
- cluster-only mode — file stats not available

## Summary
- 4590 nodes · 11204 edges · 665 communities (103 shown, 175 thin omitted)
- Extraction: 97% EXTRACTED · 3% INFERRED · 0% AMBIGUOUS · INFERRED: 331 edges (avg confidence: 0.83)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `ae886b24`
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
- Community 146
- Community 147
- Community 148
- Community 149
- Community 150
- Community 151
- Community 152
- Community 153
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
- Community 204
- Community 205
- Community 206
- Community 207
- Community 208
- Community 209
- Community 210
- Community 211
- Community 212
- Community 213
- Community 214
- Community 215
- Community 216
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
- Community 334
- Community 335
- Community 336
- Community 337
- Community 338
- Community 339
- Community 340
- Community 341
- Community 342
- Community 343
- Community 344
- Community 345
- Community 346
- Community 347
- Community 348
- Community 349
- Community 351

## God Nodes (most connected - your core abstractions)
1. `User` - 448 edges
2. `Supplier` - 249 edges
3. `Employee` - 176 edges
4. `SalesSettlement` - 172 edges
5. `Product` - 152 edges
6. `ChartOfAccount` - 139 edges
7. `Controller` - 137 edges
8. `Warehouse` - 136 edges
9. `Vehicle` - 116 edges
10. `GoodsIssue` - 114 edges

## Surprising Connections (you probably didn't know these)
- `createDashboardPermissions()` --calls--> `Permission`  [INFERRED]
  tests/Feature/DashboardTest.php → app/Models/Permission.php
- `setupStockWithPromotionalBatches()` --calls--> `Permission`  [INFERRED]
  tests/Feature/GoodsIssueExcludePromotionalTest.php → app/Models/Permission.php
- `setupSingleBatchStockForMultiLineTest()` --calls--> `Permission`  [INFERRED]
  tests/Feature/GoodsIssueMultiLineSameProductTest.php → app/Models/Permission.php
- `setupWholeNumberUomStock()` --calls--> `Permission`  [INFERRED]
  tests/Feature/GoodsIssueWholeNumberQuantityTest.php → app/Models/Permission.php
- `createSuperAdminUser()` --calls--> `User`  [EXTRACTED]
  tests/Feature/DashboardTest.php → app/Models/User.php

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Supplier-Linked Seed Data** — database_seeders_data_ledger_registers_supplier_ledger, database_seeders_data_employee_list_employee, database_seeders_data_vehicles_delivery_vehicle, database_seeders_data_sku_product_sku, database_seeders_data_employee_list_nestle_pakistan [EXTRACTED 1.00]
- **Opening Balance Seeding Subsystem** — database_seeders_data_ledger_registers_supplier_ledger, database_seeders_data_transactions_customer_transaction, database_seeders_data_ledger_registers_ob_document_type, database_seeders_data_transactions_opening_balance_type [INFERRED 0.85]
- **Customer Accounts Receivable Subsystem** — database_seeders_data_customers_customer, database_seeders_data_accounts_customer_employee_account, database_seeders_data_transactions_customer_transaction, database_seeders_data_employee_list_employee [INFERRED 0.88]

## Communities (665 total, 175 thin omitted)

### Community 0 - "Community 0"
Cohesion: 0.02
Nodes (20): User, AccountingPeriodPolicy, AccountTypePolicy, ChartOfAccountPolicy, CurrencyPolicy, EmployeePolicy, JournalEntryPolicy, ProductPolicy (+12 more)

### Community 1 - "Community 1"
Cohesion: 0.03
Nodes (9): Company, ProductPriceChangeLog, ProductRecallItem, SalesSettlementBankSlip, SalesSettlementExcessAmount, StockAdjustmentItem, CompanyPolicy, CompanySeeder (+1 more)

### Community 2 - "Community 2"
Cohesion: 0.07
Nodes (24): DailySalesReportController, Employee, GoodsIssueItem, GoodsReceiptNoteItem, Product, Supplier, Uom, Vehicle (+16 more)

### Community 3 - "Community 3"
Cohesion: 0.06
Nodes (27): ChartOfAccountController, AccountingPeriod, AccountType, ChartOfAccount, Currency, JournalEntry, SalesSettlementExpense, ChartOfAccountFactory (+19 more)

### Community 4 - "Community 4"
Cohesion: 0.06
Nodes (13): AccountBalance, BalanceSheetAccount, IncomeStatementAccount, InvoiceSummary, PaymentGrnAllocation, SchemeReceived, UserTracking, Database\Factories\InvestmentOpeningBalanceFactory (+5 more)

### Community 5 - "Community 5"
Cohesion: 0.10
Nodes (13): OpeningStockController, Barryvdh\DomPDF\Facade\Pdf, Illuminate\Database\QueryException, Illuminate\Routing\Controllers\HasMiddleware, Illuminate\Routing\Controllers\Middleware, Illuminate\Support\Facades\DB, Illuminate\Support\Facades\Hash, Illuminate\Support\Facades\Log (+5 more)

### Community 6 - "Community 6"
Cohesion: 0.04
Nodes (25): AccountTypeController, Controller, App\Http\Controllers\EmployeeSalaryController, App\Http\Controllers\EmployeeSalaryTransactionController, AccountBalancesController, AdvanceTaxReportController, AdvanceTaxSalesRegisterController, BalanceSheetController (+17 more)

### Community 7 - "Community 7"
Cohesion: 0.07
Nodes (50): O(), ve(), v(), ya(), Ae(), B(), Be(), c() (+42 more)

### Community 8 - "Community 8"
Cohesion: 0.05
Nodes (12): CreditSalesReportController, JournalEntryController, CreditorsLedgerController, InvoiceSummaryReportController, ShopListController, SupplierLedgerReportController, VehicleReportController, TransactionController (+4 more)

### Community 9 - "Community 9"
Cohesion: 0.04
Nodes (3): SalesSettlement, SalesSettlementPolicy, Illuminate\Database\Eloquent\Relations\HasMany

### Community 10 - "Community 10"
Cohesion: 0.05
Nodes (22): AccountingPeriodSeeder, AccountTypeSeeder, AttachmentSeeder, ChartOfAccountSeeder, ClaimRegisterSeeder, CostCenterSeeder, CurrencySeeder, CustomerSeeder (+14 more)

### Community 11 - "Community 11"
Cohesion: 0.06
Nodes (7): ClaimRegisterController, ClaimRegisterReportController, StoreClaimRegisterRequest, UpdateClaimRegisterRequest, ClaimRegister, ClaimRegisterPolicy, ClaimRegisterService

### Community 12 - "Community 12"
Cohesion: 0.05
Nodes (44): de(), aa(), ab(), ba(), bb(), ca(), cd(), da() (+36 more)

### Community 13 - "Community 13"
Cohesion: 0.04
Nodes (16): AppendGoodsIssueItemsRequest, PostGoodsReceiptNoteRequest, StoreEmployeeSalaryRequest, StoreInvestmentOpeningBalanceRequest, StoreSaleRequest, StoreSchemeReceivedRequest, StoreStockReceiptRequest, UpdateCategoryRequest (+8 more)

### Community 14 - "Community 14"
Cohesion: 0.05
Nodes (20): PermissionController, RoleController, UserController, LogOptions, Permission, LogOptions, LogOptions, Role (+12 more)

### Community 15 - "Community 15"
Cohesion: 0.12
Nodes (54): ac(), b(), bc(), cc(), d(), db(), e(), eb() (+46 more)

### Community 16 - "Community 16"
Cohesion: 0.04
Nodes (11): StoreExpenseDetailRequest, StoreRevenueDetailRequest, UpdateBankAccountRequest, UpdateChartOfAccountRequest, UpdateCostCenterRequest, UpdateExpenseDetailRequest, UpdateLedgerRegisterRequest, UpdateProductRequest (+3 more)

### Community 17 - "Community 17"
Cohesion: 0.07
Nodes (5): ExpenseDetailController, ExpenseDetailReportController, PeriodClosingService, AccountingService, ExpenseDetailService

### Community 18 - "Community 18"
Cohesion: 0.07
Nodes (31): A(), at(), b(), be(), ce(), e(), Ee(), fe() (+23 more)

### Community 19 - "Community 19"
Cohesion: 0.04
Nodes (10): StoreCategoryRequest, StoreTaxCodeRequest, StoreTaxTransactionRequest, StoreVehicleRequest, UpdateAccountingPeriodRequest, UpdateTaxCodeRequest, UpdateTaxRateRequest, UpdateTaxTransactionRequest (+2 more)

### Community 20 - "Community 20"
Cohesion: 0.06
Nodes (7): CostCenterController, EmployeeController, QueryBuilder, StoreCostCenterRequest, CostCenter, CostCenterPolicy, EmployeeSeeder

### Community 22 - "Community 22"
Cohesion: 0.06
Nodes (7): SalesSettlementAdvanceTax, SalesSettlementAdvanceTaxIncome, SalesSettlementBankTransfer, SalesSettlementCreditSale, SalesSettlementItemBatch, SalesSettlementPercentageExpense, SalesSettlementRecovery

### Community 23 - "Community 23"
Cohesion: 0.08
Nodes (4): SupplierPaymentController, SupplierPayment, SupplierPaymentPolicy, PaymentService

### Community 24 - "Community 24"
Cohesion: 0.06
Nodes (12): computeStyleTests(), dataAttr(), finalPropName(), getData(), Identity(), NOTE: This can be skipped if there are no unmatched elements (i.e.,…, TODO: Now that all calls to _data and _removeData have been replaced, TODO: identify versions (+4 more)

### Community 25 - "Community 25"
Cohesion: 0.07
Nodes (13): AttachmentFactory, CategoryFactory, CustomerFactory, GoodsIssueItemFactory, JournalEntryFactory, ProductFactory, ProductTaxMappingFactory, RevenueCategoryFactory (+5 more)

### Community 26 - "Community 26"
Cohesion: 0.09
Nodes (9): CustomerAccountStatementController, TtsSummaryReportController, ReportsController, SettingsController, SettingsController, AppLayout, GuestLayout, Illuminate\View\Component (+1 more)

### Community 27 - "Community 27"
Cohesion: 0.06
Nodes (30): devDependencies, autoprefixer, axios, concurrently, laravel-vite-plugin, postcss, tailwindcss, @tailwindcss/forms (+22 more)

### Community 28 - "Community 28"
Cohesion: 0.09
Nodes (6): CategoryRevenueController, RevenueDetailReportController, RevenueCategory, RevenueDetail, RevenueDetailService, RevenueDetailFactory

### Community 29 - "Community 29"
Cohesion: 0.09
Nodes (4): GoodsIssue, SalesSettlementItem, GoodsIssuePolicy, createRoiSettlement()

### Community 31 - "Community 31"
Cohesion: 0.23
Nodes (24): b(), g(), c(), d(), c(), e(), f(), g() (+16 more)

### Community 33 - "Community 33"
Cohesion: 0.09
Nodes (5): JournalEntryDetailController, StoreJournalEntryDetailRequest, UpdateJournalEntryDetailRequest, JournalEntryDetail, JournalEntryDetailPolicy

### Community 34 - "Community 34"
Cohesion: 0.11
Nodes (5): StockValuationLayer, VanStockBalance, VanStockBatch, DistributionService, createEditedGrnDriftStock()

### Community 35 - "Community 35"
Cohesion: 0.13
Nodes (7): CustomerExport, EmployeeExport, GeneralLedgerExport, VehicleExport, Illuminate\Database\Eloquent\Builder, Maatwebsite\Excel\Concerns\FromQuery, Maatwebsite\Excel\Concerns\WithMapping

### Community 36 - "Community 36"
Cohesion: 0.11
Nodes (9): FmrAmrComparisonController, GoodsIssueReportController, SchemeReceivedReportController, Carbon\Carbon, Carbon\CarbonPeriod, Illuminate\Http\RedirectResponse, Illuminate\Pagination\LengthAwarePaginator, Illuminate\Support\Carbon (+1 more)

### Community 37 - "Community 37"
Cohesion: 0.10
Nodes (5): AttachmentController, StoreAttachmentRequest, UpdateAttachmentRequest, Attachment, AttachmentPolicy

### Community 38 - "Community 38"
Cohesion: 0.11
Nodes (5): CategoryController, SkuRatesController, StockAvailabilityReportController, Category, CategorySeeder

### Community 39 - "Community 39"
Cohesion: 0.07
Nodes (5): StoreProfitCategoryRequest, StoreRevenueCategoryRequest, UpdateProfitCategoryRequest, UpdateRevenueCategoryRequest, Illuminate\Support\Str

### Community 40 - "Community 40"
Cohesion: 0.10
Nodes (5): WarehouseController, WarehouseTypeController, WarehouseType, WarehouseTypePolicy, WarehouseTypeSeeder

### Community 42 - "Community 42"
Cohesion: 0.09
Nodes (3): Customer, CustomerPolicy, createStatementAccount()

### Community 44 - "Community 44"
Cohesion: 0.08
Nodes (4): CustomerEmployeeAccount, SalesmanLedger, createReportTransaction(), createStatementTransaction()

### Community 46 - "Community 46"
Cohesion: 0.10
Nodes (15): CreateNewUser, PasswordValidationRules, ResetUserPassword, UpdateUserPassword, Illuminate\Cache\RateLimiting\Limit, Illuminate\Contracts\Validation\Rule, Illuminate\Support\Facades\RateLimiter, Illuminate\Support\Facades\Validator (+7 more)

### Community 48 - "Community 48"
Cohesion: 0.12
Nodes (3): TaxCodeController, TaxCode, TaxCodePolicy

### Community 49 - "Community 49"
Cohesion: 0.11
Nodes (3): ProductRecall, ProductRecallService, Illuminate\Database\Eloquent\Collection

### Community 50 - "Community 50"
Cohesion: 0.12
Nodes (3): BatchTransferController, StockBatch, BatchTransferService

### Community 54 - "Community 54"
Cohesion: 0.12
Nodes (3): OpeningCustomerBalanceController, QueryBuilder, CustomerEmployeeAccountTransaction

### Community 57 - "Community 57"
Cohesion: 0.16
Nodes (3): AmrDisposeRegisterController, SalesSettlementAmrLiquid, SalesSettlementAmrPowder

### Community 58 - "Community 58"
Cohesion: 0.12
Nodes (3): LedgerRegister, LedgerRegisterPolicy, LedgerRegisterSeeder

### Community 59 - "Community 59"
Cohesion: 0.14
Nodes (5): ProfitCategoryController, ProfitCategory, ProfitCategoryDetailFactory, ProfitCategoryFactory, createProfitCategoryFixture()

### Community 60 - "Community 60"
Cohesion: 0.13
Nodes (19): Blade Component: page-header, DB Table: customer_employee_account_transactions, DB Table: customer_employee_accounts, Migration: Add AMR Dispose Register Permission, Migration: Add Supplier Ledger Permission, Employee Model, Supplier Model, Pattern: Three-Level Drilldown Report (Supplier→Salesman→Customer) (+11 more)

### Community 61 - "Community 61"
Cohesion: 0.13
Nodes (5): BankAccount, SalesSettlementCashDenomination, BankAccountSeeder, CashDetailReportTest, makeDraftSettlement()

### Community 63 - "Community 63"
Cohesion: 0.14
Nodes (12): CheckUserStatus, SetDatabaseAuditContext, Closure, Illuminate\Foundation\Application, Illuminate\Foundation\Configuration\Exceptions, Illuminate\Foundation\Configuration\Middleware, Illuminate\Support\Facades\Auth, Illuminate\Support\Facades\Request (+4 more)

### Community 64 - "Community 64"
Cohesion: 0.14
Nodes (6): GoodsReceiptNoteItemsImport, OpeningStockImport, Maatwebsite\Excel\Concerns\ToCollection, Maatwebsite\Excel\Concerns\WithHeadingRow, Maatwebsite\Excel\Concerns\WithValidation, PhpOffice\PhpSpreadsheet\Shared\Date

### Community 65 - "Community 65"
Cohesion: 0.30
Nodes (16): a(), c(), d(), e(), f(), g(), h(), i() (+8 more)

### Community 67 - "Community 67"
Cohesion: 0.15
Nodes (3): ProductTaxMappingController, ProductTaxMapping, ProductTaxMappingPolicy

### Community 68 - "Community 68"
Cohesion: 0.22
Nodes (3): StockAdjustmentController, Controller, StockAdjustment

### Community 69 - "Community 69"
Cohesion: 0.15
Nodes (3): TaxTransactionController, TaxTransaction, TaxTransactionPolicy

### Community 70 - "Community 70"
Cohesion: 0.16
Nodes (3): TaxRateController, TaxRate, TaxRatePolicy

### Community 71 - "Community 71"
Cohesion: 0.15
Nodes (6): AppServiceProvider, FortifyServiceProvider, JetstreamServiceProvider, UserTrackingServiceProvider, Illuminate\Support\ServiceProvider, Laravel\Jetstream\Jetstream

### Community 72 - "Community 72"
Cohesion: 0.16
Nodes (3): ProfitAfterCategoryReportController, ProfitCategoryDetail, ProfitCategoryDetailService

### Community 73 - "Community 73"
Cohesion: 0.15
Nodes (8): Laravel\Jetstream\Features, Laravel\Jetstream\Http\Livewire\ApiTokenManager, Laravel\Jetstream\Http\Livewire\DeleteUserForm, Laravel\Jetstream\Http\Livewire\LogoutOtherBrowserSessionsForm, Laravel\Jetstream\Http\Livewire\UpdatePasswordForm, Laravel\Jetstream\Http\Livewire\UpdateProfileInformationForm, Laravel\Jetstream\Http\Middleware\AuthenticateSession, Livewire\Livewire

### Community 74 - "Community 74"
Cohesion: 0.22
Nodes (16): addCheckConstraints(), createMySQLAuditTriggers(), createMySQLSnapshotHelpers(), createMySQLSoftDeleteProtection(), createMySQLTriggers(), createPostgreSQLAccountingFunctions(), createPostgreSQLAuditTriggers(), createPostgreSQLImmutabilityTriggers() (+8 more)

### Community 77 - "Community 77"
Cohesion: 0.16
Nodes (16): Customer Employee Account, Customer Channel Type (General Store, Pharmacy, Wholesale), Customer Record, Employee Record, Nestlé Pakistan Supplier, Journal Entry Reference in Ledger, Opening Balance Document Type (OB), Supplier Ledger Register (+8 more)

### Community 78 - "Community 78"
Cohesion: 0.15
Nodes (8): SchedulerHeartbeat, SnapshotInventory, RebuildDailyInventorySnapshots, ResyncStockValues, Command, Illuminate\Console\Attributes\Description, Illuminate\Console\Attributes\Signature, Illuminate\Console\Command

### Community 84 - "Community 84"
Cohesion: 0.14
Nodes (14): require, barryvdh/laravel-dompdf, laravel/framework, laravel/jetstream, laravel/sanctum, laravel/tinker, livewire/livewire, maatwebsite/excel (+6 more)

### Community 85 - "Community 85"
Cohesion: 0.16
Nodes (14): buildFragment(), buildParams(), cloneCopyEvent(), disableScript(), DOMEval(), domManip(), getAll(), isArrayLike() (+6 more)

### Community 86 - "Community 86"
Cohesion: 0.22
Nodes (4): GoodsReceiptNoteTemplateExport, OpeningStockTemplateExport, Maatwebsite\Excel\Concerns\FromArray, Maatwebsite\Excel\Concerns\WithHeadings

### Community 90 - "Community 90"
Cohesion: 0.24
Nodes (9): Illuminate\Http\UploadedFile, PhpOffice\PhpSpreadsheet\Spreadsheet, PhpOffice\PhpSpreadsheet\Writer\Xlsx, createTestExcelFile(), UploadedFile, createOpeningStockExcel(), UploadedFile, makeOpeningStockFile() (+1 more)

### Community 98 - "Community 98"
Cohesion: 0.17
Nodes (11): autoload-dev, psr-4, description, keywords, license, minimum-stability, name, prefer-stable (+3 more)

### Community 99 - "Community 99"
Cohesion: 0.17
Nodes (11): Spatie\Backup\Notifications\Notifiable, Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification, Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification, Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification, Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification, Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification, Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification, Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy (+3 more)

### Community 100 - "Community 100"
Cohesion: 0.17
Nodes (7): Illuminate\Auth\Events\Verified, Illuminate\Auth\Notifications\ResetPassword, Illuminate\Support\Facades\Event, Illuminate\Support\Facades\Notification, Illuminate\Support\Facades\URL, Laravel\Fortify\Features, Laravel\Jetstream\Http\Livewire\TwoFactorAuthenticationForm

### Community 101 - "Community 101"
Cohesion: 0.20
Nodes (12): addCombinator(), condense(), createPositionalPseudo(), elementMatcher(), markFunction(), matcherFromGroupMatchers(), matcherFromTokens(), multipleContexts() (+4 more)

### Community 102 - "Community 102"
Cohesion: 0.18
Nodes (12): adoptValue(), ajaxConvert(), ajaxHandleResponses(), Animation(), createFxNow(), createTween(), defaultPrefilter(), done() (+4 more)

### Community 103 - "Community 103"
Cohesion: 0.24
Nodes (3): DocumentType, LedgerRegisterFactory, static

### Community 104 - "Community 104"
Cohesion: 0.18
Nodes (8): Google\Client, Google\Service\Drive, Illuminate\Console\Scheduling\Schedule, Illuminate\Filesystem\FilesystemAdapter, Illuminate\Support\Facades\Gate, Illuminate\Support\Facades\Storage, League\Flysystem\Filesystem, Masbug\Flysystem\GoogleDriveAdapter

### Community 110 - "Community 110"
Cohesion: 0.36
Nodes (3): GeneralLedgerController, QueryBuilder, GeneralLedgerEntry

### Community 112 - "Community 112"
Cohesion: 0.22
Nodes (9): require-dev, fakerphp/faker, laravel/boost, laravel/pail, laravel/pint, mockery/mockery, nunomaduro/collision, pestphp/pest (+1 more)

### Community 113 - "Community 113"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 126 - "Community 126"
Cohesion: 0.38
Nodes (3): App\Models\Team, static, UserFactory

### Community 127 - "Community 127"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 128 - "Community 128"
Cohesion: 0.43
Nodes (7): Branch, Report, Stationary, User, Branchwise Report, Stationary Report, User Management

### Community 129 - "Community 129"
Cohesion: 0.29
Nodes (6): confirmApiTokenDeletion({{ $token->id }}), deleteApiToken, manageApiTokenPermissions({{ $token->id }}), $toggle(, $set(, updateApiToken

### Community 133 - "Community 133"
Cohesion: 0.47
Nodes (3): UpdateUserProfileInformation, Illuminate\Contracts\Auth\MustVerifyEmail, Laravel\Fortify\Contracts\UpdatesUserProfileInformation

### Community 149 - "Community 149"
Cohesion: 0.40
Nodes (6): Moon Traders Application, Human Resources Module, User Management Module, HR Module Icon, Moon Traders Logo, Add Users Icon

### Community 172 - "Community 172"
Cohesion: 0.80
Nodes (5): Audit Document, Audit Review / Inspection, Audit Verification / Checkmark, Audits Functionality, Audits Icon

### Community 173 - "Community 173"
Cohesion: 0.60
Nodes (5): Bank Deposit, Branches, Permission / Access Control, Report (Financial), Stat Report (Pencil & Ruler)

### Community 174 - "Community 174"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 175 - "Community 175"
Cohesion: 0.40
Nodes (4): Monolog\Handler\NullHandler, Monolog\Handler\StreamHandler, Monolog\Handler\SyslogUdpHandler, Monolog\Processor\PsrLogMessageProcessor

### Community 176 - "Community 176"
Cohesion: 0.40
Nodes (4): Illuminate\Cookie\Middleware\EncryptCookies, Illuminate\Foundation\Http\Middleware\ValidateCsrfToken, Laravel\Sanctum\Http\Middleware\AuthenticateSession, Laravel\Sanctum\Sanctum

### Community 178 - "Community 178"
Cohesion: 0.50
Nodes (4): SITE_PATH, php, herd, laravel-boost

### Community 179 - "Community 179"
Cohesion: 0.40
Nodes (3): Illuminate\Foundation\Inspiring, Illuminate\Support\Facades\Artisan, Illuminate\Support\Facades\Schedule

### Community 192 - "Community 192"
Cohesion: 0.83
Nodes (4): Bank / Financial Institution, Cash / Money, Deposit Action, Deposit Icon

### Community 193 - "Community 193"
Cohesion: 0.50
Nodes (3): confirmLogout, logoutOtherBrowserSessions, $toggle(

### Community 194 - "Community 194"
Cohesion: 0.50
Nodes (3): confirmUserDeletion, deleteUser, $toggle(

### Community 196 - "Community 196"
Cohesion: 0.50
Nodes (4): expectSync(), leverageNative(), returnTrue(), safeActiveElement()

### Community 200 - "Community 200"
Cohesion: 1.00
Nodes (3): Bank of Azad Jammu & Kashmir (BAJK) Logo, Circular Refresh/Sync Icon, Manual Document Icon

### Community 201 - "Community 201"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 290 - "Community 290"
Cohesion: 1.00
Nodes (3): Account Holder (User with Location Pin), Complaint Module (Alert Chat Bubble), Employee / Representative (Business Person Icon)

### Community 291 - "Community 291"
Cohesion: 1.00
Nodes (3): Financial Background Image, Branch Target Reports Icon, HR Module Icon

### Community 292 - "Community 292"
Cohesion: 1.00
Nodes (3): Bank Module, Daily Bank Statement, Districts

### Community 293 - "Community 293"
Cohesion: 1.00
Nodes (3): Classification, Region Deposit, User Roles

### Community 294 - "Community 294"
Cohesion: 1.00
Nodes (3): Dispatch Module, Reports Module, Stationary Module

### Community 295 - "Community 295"
Cohesion: 0.67
Nodes (3): boxModelAdjustment(), curCSS(), getWidthOrHeight()

### Community 296 - "Community 296"
Cohesion: 0.67
Nodes (3): camelCase(), fcamelCase(), propFilter()

## Knowledge Gaps
- **150 isolated node(s):** `fakerphp/faker`, `laravel/boost`, `laravel/pail`, `laravel/pint`, `mockery/mockery` (+145 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 1521 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **175 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `Community 0` to `Community 1`, `Community 2`, `Community 3`, `Community 4`, `Community 5`, `Community 133`, `Community 8`, `Community 9`, `Community 11`, `Community 14`, `Community 20`, `Community 23`, `Community 152`, `Community 29`, `Community 32`, `Community 33`, `Community 37`, `Community 40`, `Community 42`, `Community 46`, `Community 48`, `Community 180`, `Community 58`, `Community 59`, `Community 61`, `Community 63`, `Community 67`, `Community 69`, `Community 70`, `Community 71`, `Community 73`, `Community 208`, `Community 90`, `Community 92`, `Community 100`, `Community 117`, `Community 123`, `Community 126`?**
  _High betweenness centrality (0.108) - this node is a cross-community bridge._
- **Why does `Supplier` connect `Community 2` to `Community 0`, `Community 1`, `Community 130`, `Community 3`, `Community 4`, `Community 5`, `Community 6`, `Community 8`, `Community 9`, `Community 11`, `Community 14`, `Community 143`, `Community 20`, `Community 22`, `Community 23`, `Community 150`, `Community 152`, `Community 26`, `Community 25`, `Community 28`, `Community 29`, `Community 289`, `Community 36`, `Community 38`, `Community 42`, `Community 51`, `Community 52`, `Community 54`, `Community 56`, `Community 57`, `Community 58`, `Community 59`, `Community 60`, `Community 61`, `Community 62`, `Community 64`, `Community 66`, `Community 68`, `Community 72`, `Community 80`, `Community 209`, `Community 82`, `Community 86`, `Community 87`, `Community 90`, `Community 96`, `Community 103`, `Community 105`, `Community 107`, `Community 116`, `Community 123`?**
  _High betweenness centrality (0.045) - this node is a cross-community bridge._
- **Why does `Employee` connect `Community 2` to `Community 0`, `Community 1`, `Community 3`, `Community 4`, `Community 5`, `Community 6`, `Community 8`, `Community 9`, `Community 139`, `Community 17`, `Community 20`, `Community 26`, `Community 35`, `Community 36`, `Community 42`, `Community 44`, `Community 51`, `Community 52`, `Community 54`, `Community 57`, `Community 60`, `Community 61`, `Community 207`, `Community 81`, `Community 93`, `Community 96`, `Community 116`?**
  _High betweenness centrality (0.036) - this node is a cross-community bridge._
- **What connects `fakerphp/faker`, `laravel/boost`, `laravel/pail` to the rest of the system?**
  _150 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Community 0` be split into smaller, more focused modules?**
  _Cohesion score 0.01890924630094546 - nodes in this community are weakly interconnected._
- **Should `Community 1` be split into smaller, more focused modules?**
  _Cohesion score 0.026596777324791303 - nodes in this community are weakly interconnected._
- **Should `Community 2` be split into smaller, more focused modules?**
  _Cohesion score 0.07478070175438596 - nodes in this community are weakly interconnected._