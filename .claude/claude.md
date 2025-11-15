# MoonTrader - Claude Code Context

## Project Overview

**MoonTrader** is a comprehensive inventory management system built with Laravel 12xxx use backend language of PHP Laravel, designed for businesses to manage stock, suppliers, goods receipt notes (GRN), promotional campaigns, and integrated accounting. It implements sophisticated inventory tracking with FIFO/LIFO costing, batch tracking, and multi-warehouse support.

### User Requirements for ERP
Our process starts with the Delivery Note: multiple suppliers send goods directly to our warehouse (Moon Traders) without any purchase orders. When the goods arrive, we record them in the system based on the Delivery Note and add the stock to inventory. The supplier already provides the maximum selling price, and we enter our per-unit cost in the system. Each morning we issue inventory to our salesmen, whose vehicles are already registered in the ERP, and they sell to retailers, shops and marts throughout the day. In the evening they report what they sold; cash sales are recorded only as totals, while credit sales are logged retailer-wise. Any remaining inventory is either returned to the warehouse or kept in the vehicle as the next day’s opening balance. The system must generate a daily product-wise report for each salesman showing opening stock, issued quantity, sales, returns, and outstanding credit. Since a single retailer may interact with multiple salesmen, the ERP must track credit by salesman as well as by retailer.



### Core Business Domain
- **Primary Focus**: Inventory and stock management with integrated accounting
- **Key Features**: Goods receipt processing, batch tracking, promotional campaigns, supplier payments, and financial journal entries
- **Target Users**: Businesses requiring detailed inventory control with accounting integration

---

## Technology Stack

### Backend
- **Framework**: Laravel 12
- **PHP Version**: 8.2+
- **Database**: MariaDB/MySQL/PostgreSQL (We Use All Database Psql/MariaDB/Mysql)
- **ORM**: Eloquent
- **Authentication**: Laravel Jetstream with Sanctum
- **Queue System**: Laravel Queues (database driver)

### Frontend
- **UI Framework**: Livewire 3 (reactive server-side components)
- **JavaScript**: Alpine.js (lightweight interactions)
- **CSS**: TailwindCSS 4 (utility-first styling)
- **Build Tool**: Vite 7

### Key Laravel Packages
- **spatie/laravel-activity-log**: Complete audit trail for all models
- **spatie/laravel-permission**: Role-based access control (RBAC)
- **spatie/laravel-query-builder**: Advanced query filtering and sorting
- **barryvdh/laravel-dompdf**: PDF generation for reports
- **alimarchal/id-generator**: Custom ID generation with prefixes
- **Laravel Pail**: Real-time log monitoring
- **Pest PHP**: Testing framework

### Development Tools
- **Code Style**: Laravel Pint (PHP CS Fixer)
- **Process Management**: Concurrently (runs multiple dev processes)
- **Local Server**: Laravel Herd/Valet or `php artisan serve`

---

## Architecture Overview

### Design Pattern
MoonTrader follows a **Service-Oriented MVC** architecture:

```
Request → Controller → Service Layer → Models → Database
                          ↓
                    Journal Entries
                    Inventory Updates
                    Business Logic
```

### Service Layer (app/Services/)
Core business logic is encapsulated in dedicated service classes:

1. **InventoryService** (`app/Services/InventoryService.php`)
   - Posts GRN to inventory
   - Creates stock batches with FIFO/LIFO support
   - Manages stock movements and ledger entries
   - Updates current stock summaries
   - Handles promotional item prioritization

2. **AccountingService** (`app/Services/AccountingService.php`)
   - Creates journal entries for inventory transactions
   - Manages double-entry bookkeeping
   - Handles cost center allocations
   - Validates accounting periods

3. **PaymentService** (`app/Services/PaymentService.php`)
   - Processes supplier payments
   - Allocates payments to GRNs
   - Tracks payment history
   - Updates outstanding balances

### Controller Pattern
Controllers in `app/Http/Controllers/` are thin and delegate business logic to services:
- Handle HTTP requests/responses
- Validate input via Form Requests (`app/Http/Requests/`)
- Authorize actions via Policies (`app/Policies/`)
- Return views or JSON responses

### Form Requests
All input validation is handled by dedicated Form Request classes:
- Located in `app/Http/Requests/`
- Pattern: `Store{Model}Request` and `Update{Model}Request`
- Includes authorization and validation rules

### Authorization
Policy-based authorization for all resources:
- Located in `app/Policies/`
- Integrated with Spatie Permission package
- Granular permissions per model action (view, create, update, delete)

---

## Project Structure

```
moontrader/
├── app/
│   ├── Actions/              # Jetstream actions (user management)
│   ├── Helpers/              # Global helper functions
│   ├── Http/
│   │   ├── Controllers/      # Application controllers (31 controllers)
│   │   ├── Middleware/       # Custom middleware (e.g., SetDatabaseAuditContext)
│   │   └── Requests/         # Form request validation (56+ requests)
│   ├── Models/               # Eloquent models (38+ models)
│   ├── Policies/             # Authorization policies (19 policies)
│   ├── Providers/            # Service providers
│   ├── Services/             # Business logic services
│   ├── Traits/               # Reusable model traits
│   └── View/                 # View composers
│
├── database/
│   ├── migrations/           # Database schema (57 migrations)
│   ├── seeders/              # Database seeders (23 seeders)
│   │   └── data/             # Seed data files
│   └── factories/            # Model factories
│
├── resources/
│   ├── views/                # Blade templates
│   │   ├── goods-receipt-notes/
│   │   ├── inventory/
│   │   ├── accounting/
│   │   ├── customers/
│   │   ├── suppliers/
│   │   └── ...
│   ├── js/                   # JavaScript assets
│   └── css/                  # CSS/Tailwind files
│
├── routes/
│   ├── web.php               # Web routes (main routing file)
│   ├── api.php               # API routes
│   └── console.php           # Console commands
│
├── public/                   # Public assets and compiled files
├── scripts/                  # Utility scripts
├── storage/                  # Logs, cache, uploaded files
├── tests/                    # Pest PHP tests
│   ├── Feature/              # Feature tests
│   └── Unit/                 # Unit tests
└── vendor/                   # Composer dependencies
```

---

## Core Models & Relationships

### Inventory Models

#### GoodsReceiptNote
- **Purpose**: Header for goods received from suppliers
- **Key Fields**: `grn_number`, `supplier_id`, `warehouse_id`, `receipt_date`, `status`
- **Statuses**: draft, posted, cancelled
- **Relationships**:
  - `belongsTo` Supplier
  - `belongsTo` Warehouse
  - `hasMany` GoodsReceiptNoteItem
  - `hasOne` JournalEntry
- **Immutability**: Cannot be edited/deleted after posting
- **Location**: `app/Models/GoodsReceiptNote.php`

#### GoodsReceiptNoteItem
- **Purpose**: Line items for each product in a GRN
- **Key Fields**: `product_id`, `quantity_ordered`, `quantity_accepted`, `unit_cost`, `promotional_price`, `priority_order`
- **Relationships**:
  - `belongsTo` GoodsReceiptNote
  - `belongsTo` Product
  - `belongsTo` Uom (Unit of Measure)
  - `belongsTo` PromotionalCampaign (nullable)
- **Location**: `app/Models/GoodsReceiptNoteItem.php`

#### StockBatch
- **Purpose**: Tracks individual batches of inventory
- **Key Fields**: `batch_code`, `product_id`, `supplier_id`, `expiry_date`, `promotional_selling_price`, `priority_order`, `selling_strategy`
- **Selling Strategies**: fifo (default), lifo, priority
- **Promotional Support**: `is_promotional`, `must_sell_before`, promotional pricing
- **Relationships**:
  - `belongsTo` Product
  - `belongsTo` Supplier
  - `hasMany` StockMovement
  - `belongsTo` PromotionalCampaign (nullable)
- **Location**: `app/Models/StockBatch.php`

#### StockMovement
- **Purpose**: Records all inventory transactions
- **Movement Types**: grn (goods receipt), sale, adjustment, transfer, return
- **Key Fields**: `movement_type`, `reference_type`, `reference_id`, `product_id`, `warehouse_id`, `quantity`, `unit_cost`
- **Polymorphic**: Uses `reference_type` and `reference_id` to link to source document
- **Relationships**:
  - `morphTo` Reference (GRN, Sale, etc.)
  - `belongsTo` Product
  - `belongsTo` Warehouse
  - `belongsTo` StockBatch
- **Location**: `app/Models/StockMovement.php`

#### StockLedgerEntry
- **Purpose**: Running balance audit trail for inventory
- **Key Fields**: `product_id`, `warehouse_id`, `batch_id`, `quantity_in`, `quantity_out`, `running_balance`
- **Pattern**: Maintains chronological transaction history with running totals
- **Location**: `app/Models/StockLedgerEntry.php`

#### StockValuationLayer
- **Purpose**: FIFO costing and inventory valuation
- **Key Fields**: `product_id`, `batch_id`, `quantity_remaining`, `unit_cost`, `remaining_value`
- **Pattern**: Tracks remaining quantity and value per batch for FIFO cost calculation
- **Location**: `app/Models/StockValuationLayer.php`

#### CurrentStock
- **Purpose**: Real-time inventory summary by product and warehouse
- **Key Fields**: `product_id`, `warehouse_id`, `total_quantity`, `total_value`, `average_cost`
- **Updated By**: InventoryService when stock movements occur
- **Location**: `app/Models/CurrentStock.php`

#### CurrentStockByBatch
- **Purpose**: Real-time inventory by batch
- **Key Fields**: `batch_id`, `product_id`, `warehouse_id`, `available_quantity`, `batch_value`
- **Location**: `app/Models/CurrentStockByBatch.php`

### Product & Supplier Models

#### Product
- **Purpose**: Product catalog
- **Key Fields**: `product_code`, `name`, `description`, `category`, `base_uom_id`, `status`
- **Relationships**:
  - `belongsTo` Uom (base unit of measure)
  - `hasMany` StockBatch
  - `hasMany` CurrentStock
- **Location**: `app/Models/Product.php`

#### Supplier
- **Purpose**: Supplier database
- **Key Fields**: `supplier_code`, `name`, `contact_person`, `email`, `phone`, `payment_terms`
- **Relationships**:
  - `hasMany` GoodsReceiptNote
  - `hasMany` SupplierPayment
- **Location**: `app/Models/Supplier.php`

#### SupplierPayment
- **Purpose**: Tracks payments made to suppliers
- **Key Fields**: `supplier_id`, `payment_date`, `amount`, `payment_method`, `reference_number`
- **Relationships**:
  - `belongsTo` Supplier
  - `hasMany` PaymentGrnAllocation
- **Location**: `app/Models/SupplierPayment.php`

#### PaymentGrnAllocation
- **Purpose**: Links payments to specific GRNs
- **Key Fields**: `payment_id`, `grn_id`, `allocated_amount`
- **Location**: `app/Models/PaymentGrnAllocation.php`

### Promotional Models

#### PromotionalCampaign
- **Purpose**: Manages time-bound promotional offers
- **Key Fields**: `name`, `start_date`, `end_date`, `discount_percent`, `is_active`
- **Relationships**:
  - `hasMany` StockBatch
  - `hasMany` GoodsReceiptNoteItem
- **Location**: `app/Models/PromotionalCampaign.php`

### Accounting Models

#### JournalEntry
- **Purpose**: Double-entry accounting journal headers
- **Key Fields**: `entry_number`, `entry_date`, `description`, `status`, `total_debit`, `total_credit`
- **Statuses**: draft, posted, reversed
- **Relationships**:
  - `hasMany` JournalEntryDetail
  - `belongsTo` CostCenter (nullable)
- **Location**: `app/Models/JournalEntry.php`

#### JournalEntryDetail
- **Purpose**: Journal entry line items (debit/credit lines)
- **Key Fields**: `account_id`, `debit_amount`, `credit_amount`, `description`
- **Validation**: Total debits must equal total credits
- **Relationships**:
  - `belongsTo` JournalEntry
  - `belongsTo` ChartOfAccount
- **Location**: `app/Models/JournalEntryDetail.php`

#### ChartOfAccount
- **Purpose**: Chart of accounts (nested hierarchical structure)
- **Account Types**: Asset, Liability, Equity, Revenue, Expense
- **Key Fields**: `account_code`, `account_name`, `account_type_id`, `parent_id`, `is_active`
- **Relationships**:
  - `belongsTo` AccountType
  - `belongsTo` Parent (self-referencing)
  - `hasMany` Children (self-referencing)
- **Location**: `app/Models/ChartOfAccount.php`

#### AccountingPeriod
- **Purpose**: Financial period management
- **Key Fields**: `period_name`, `start_date`, `end_date`, `is_closed`
- **Location**: `app/Models/AccountingPeriod.php`

#### CostCenter
- **Purpose**: Cost center tracking for departmental accounting
- **Key Fields**: `code`, `name`, `description`, `is_active`
- **Location**: `app/Models/CostCenter.php`

### Supporting Models

#### Warehouse
- **Purpose**: Storage locations
- **Key Fields**: `warehouse_code`, `name`, `location`, `warehouse_type_id`
- **Relationships**:
  - `belongsTo` WarehouseType
  - `hasMany` CurrentStock
- **Location**: `app/Models/Warehouse.php`

#### Uom (Unit of Measure)
- **Purpose**: Measurement units (kg, pcs, box, etc.)
- **Key Fields**: `name`, `abbreviation`, `is_base_unit`
- **Location**: `app/Models/Uom.php`

#### Employee
- **Purpose**: Employee records
- **Key Fields**: `employee_code`, `name`, `designation`, `cost_center_id`, `user_id`
- **Relationships**:
  - `belongsTo` User (nullable)
  - `belongsTo` CostCenter (nullable)
- **Location**: `app/Models/Employee.php`

#### Customer
- **Purpose**: Customer database
- **Key Fields**: `customer_code`, `name`, `contact_person`, `email`, `phone`
- **Location**: `app/Models/Customer.php`

---

## Database Schema Highlights

### Key Tables

**Inventory Core:**
- `goods_receipt_notes` - GRN headers with supplier and warehouse
- `goods_receipt_note_items` - GRN line items with pricing and promotional data
- `stock_batches` - Batch tracking with FIFO/LIFO and promotional support
- `stock_movements` - All inventory transactions (polymorphic references)
- `stock_ledger_entries` - Running balance audit trail
- `stock_valuation_layers` - FIFO cost tracking
- `current_stock` - Real-time summary by product+warehouse
- `current_stock_by_batch` - Real-time summary by batch

**Accounting:**
- `journal_entries` - Journal entry headers
- `journal_entry_details` - Journal entry lines (debits/credits)
- `chart_of_accounts` - Hierarchical account structure
- `account_types` - Account type definitions
- `accounting_periods` - Financial periods
- `cost_centers` - Cost center tracking

**Master Data:**
- `products` - Product catalog
- `suppliers` - Supplier database
- `customers` - Customer database
- `warehouses` - Storage locations
- `uoms` - Units of measure
- `employees` - Employee records
- `promotional_campaigns` - Promotional offers

**Payments:**
- `supplier_payments` - Payments to suppliers
- `payment_grn_allocations` - Payment allocation to GRNs

**System:**
- `users` - User authentication
- `permissions` - Spatie permissions
- `roles` - Spatie roles
- `activity_log` - Spatie activity log
- `id_prefixes` - Custom ID generation

### Important Constraints

1. **Double-Entry Validation**: Journal entries enforce debit = credit balance
2. **Immutability**: Posted GRNs cannot be edited or deleted
3. **Foreign Key Constraints**: Extensive use of foreign keys for referential integrity
4. **Unique Constraints**: Codes and numbers are unique (e.g., `grn_number`, `product_code`)
5. **Soft Deletes**: Most models use soft deletes for data preservation

---

## Business Logic Flow

### Goods Receipt Note (GRN) Processing

**Creating a GRN (Draft):**
1. User creates GRN via `GoodsReceiptNoteController@create`
2. Selects supplier and warehouse
3. Adds products with quantities, costs, and optional promotional data
4. GRN saved with status 'draft'
5. Can be edited/deleted while in draft status

**Posting a GRN:**
1. User clicks "Post to Inventory" button
2. `GoodsReceiptNoteController@post` calls `InventoryService@postGrnToInventory()`
3. Within database transaction:

   **For each GRN item:**
   - Generate unique batch code
   - Create `StockBatch` record with batch details and promotional info
   - Create `StockMovement` record (type: 'grn')
   - Create `StockLedgerEntry` with running balance
   - Create `StockValuationLayer` for FIFO costing
   - Update `CurrentStock` summary
   - Update `CurrentStockByBatch` summary

   **Accounting Integration:**
   - Call `AccountingService@createGrnJournalEntry()`
   - Debit: Inventory account (total cost)
   - Credit: Accounts Payable (supplier liability)
   - Create `JournalEntry` and `JournalEntryDetail` records

4. Update GRN status to 'posted' and set `posted_at` timestamp
5. Link GRN to created journal entry
6. Commit transaction or rollback on error

**Result:**
- Inventory is updated with new stock
- Accounting reflects the purchase liability
- Complete audit trail created
- GRN becomes immutable

### Promotional Item Priority

**Priority System:**
- Priority values: 1-10 (urgent), 99 (normal)
- Lower number = higher priority
- Promotional items typically get priority 1-10
- Regular items default to 99

**Selling Strategy:**
- `fifo`: First In, First Out (default)
- `lifo`: Last In, First Out
- `priority`: Sell by priority order regardless of receipt date

**Must-Sell-Before Dates:**
- Optional field on stock batches
- Used to track items that need urgent sale
- Typically set for promotional or near-expiry items

### Supplier Payment Processing

**Recording a Payment:**
1. Create `SupplierPayment` record
2. Optionally allocate to specific GRNs via `PaymentGrnAllocation`
3. Updates supplier outstanding balance
4. Creates accounting journal entry (debit AP, credit cash/bank)

---

## Routing Structure

Routes are defined in `routes/web.php` with authentication middleware.

### Resource Routes
Most entities use Laravel resource controllers:
```php
Route::resource('goods-receipt-notes', GoodsReceiptNoteController::class);
Route::resource('products', ProductController::class);
Route::resource('suppliers', SupplierController::class);
Route::resource('journal-entries', JournalEntryController::class);
// ... etc
```

### Custom Routes
Additional actions beyond standard CRUD:
```php
// GRN posting
Route::post('goods-receipt-notes/{goodsReceiptNote}/post',
    [GoodsReceiptNoteController::class, 'post'])->name('goods-receipt-notes.post');

// Journal entry posting
Route::post('journal-entries/{journalEntry}/post',
    [JournalEntryController::class, 'post'])->name('journal-entries.post');

// View current stock
Route::get('inventory/current-stock',
    [CurrentStockController::class, 'index'])->name('inventory.current-stock');
```

### Route Organization
- All routes require authentication (`auth:sanctum` middleware)
- Jetstream auth session middleware
- Email verification required (`verified` middleware)

---

## Development Workflow

### Initial Setup
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
```

**Quick Setup (One Command):**
```bash
composer setup
```

### Development Server
```bash
composer dev
```
This runs concurrently:
- Laravel server (port 8000)
- Queue worker
- Log monitoring (Pail)
- Vite dev server (HMR)

**Manual Start:**
```bash
php artisan serve
npm run dev
```

### Database Management
```bash
# Run migrations
php artisan migrate

# Rollback last migration
php artisan migrate:rollback

# Fresh migration with seed data
php artisan migrate:fresh --seed

# Clear all caches
php artisan optimize:clear
```

### Testing
```bash
composer test
# or
php artisan test
```

### Code Quality
```bash
# Fix code style with Laravel Pint
./vendor/bin/pint

# Check without fixing
./vendor/bin/pint --test
```

### Monitoring
```bash
# Watch logs in real-time
php artisan pail

# Queue worker
php artisan queue:listen

# List failed jobs
php artisan queue:failed
```

---

## Code Conventions & Patterns

### Naming Conventions

**Models:**
- Singular, PascalCase: `GoodsReceiptNote`, `StockBatch`
- Located in `app/Models/`

**Controllers:**
- Singular resource name + Controller: `GoodsReceiptNoteController`
- Located in `app/Http/Controllers/`

**Services:**
- Descriptive name + Service: `InventoryService`, `AccountingService`
- Located in `app/Services/`

**Form Requests:**
- Action + Model + Request: `StoreGoodsReceiptNoteRequest`, `UpdateProductRequest`
- Located in `app/Http/Requests/`

**Policies:**
- Model name + Policy: `GoodsReceiptNotePolicy`
- Located in `app/Policies/`

**Database Tables:**
- Plural, snake_case: `goods_receipt_notes`, `stock_batches`
- Foreign keys: `{table}_id` (e.g., `supplier_id`)

**Routes:**
- Kebab-case: `goods-receipt-notes`, `current-stock`

### Model Traits

**Common Traits Used:**
- `HasFactory` - Factory support for testing
- `SoftDeletes` - Soft delete functionality
- `LogsActivity` (Spatie) - Automatic activity logging
- `HasRoles` (Spatie) - Role and permission support

**Custom ID Generation:**
Many models use `alimarchal/id-generator` for prefixed IDs:
```php
use AliMarchal\IdGenerator\IdGenerator;

protected static function boot()
{
    parent::boot();

    static::creating(function ($model) {
        $model->grn_number = IdGenerator::generate([
            'table' => 'goods_receipt_notes',
            'field' => 'grn_number',
            'prefix' => 'GRN-',
            'length' => 10
        ]);
    });
}
```

### Service Pattern

Services encapsulate complex business logic:

```php
namespace App\Services;

class InventoryService
{
    public function postGrnToInventory(GoodsReceiptNote $grn): array
    {
        DB::beginTransaction();

        try {
            // Validation
            // Create stock batches
            // Record movements
            // Update accounting

            DB::commit();

            return ['success' => true, 'data' => $grn];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
```

**Key Principles:**
1. Always use database transactions for multi-step operations
2. Return consistent array format: `['success' => bool, 'message' => string, 'data' => mixed]`
3. Log errors for debugging
4. Validate state before operations

### Controller Pattern

Controllers are kept thin:

```php
public function post(GoodsReceiptNote $grn, InventoryService $inventoryService)
{
    $this->authorize('update', $grn);

    $result = $inventoryService->postGrnToInventory($grn);

    if ($result['success']) {
        return redirect()
            ->route('goods-receipt-notes.show', $grn)
            ->with('success', $result['message']);
    }

    return back()->with('error', $result['message']);
}
```

### Form Request Validation

Example validation rules:

```php
public function rules(): array
{
    return [
        'supplier_id' => ['required', 'exists:suppliers,id'],
        'warehouse_id' => ['required', 'exists:warehouses,id'],
        'receipt_date' => ['required', 'date'],
        'items' => ['required', 'array', 'min:1'],
        'items.*.product_id' => ['required', 'exists:products,id'],
        'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
        'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
    ];
}
```

### Authorization Pattern

Policies define access control:

```php
public function update(User $user, GoodsReceiptNote $grn): bool
{
    // Can't update posted or cancelled GRNs
    if (in_array($grn->status, ['posted', 'cancelled'])) {
        return false;
    }

    // Check permission
    return $user->can('update goods_receipt_notes');
}
```

---

## View Layer (Blade Templates)

### Layout Structure
- Base layout: `resources/views/layouts/app.blade.php` (Jetstream)
- Navigation: Handled by Jetstream components
- TailwindCSS for styling

### View Organization
Views are organized by resource:
```
resources/views/
├── goods-receipt-notes/
│   ├── index.blade.php
│   ├── create.blade.php
│   ├── edit.blade.php
│   ├── show.blade.php
│   └── partials/
│       └── form-fields.blade.php
├── inventory/
│   └── current-stock.blade.php
└── accounting/
    └── chart-of-accounts/
        ├── index.blade.php
        └── tree.blade.php
```

### Common Blade Patterns

**Form with Validation Errors:**
```blade
@if ($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
```

**Flash Messages:**
```blade
@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
```

**Authorization in Views:**
```blade
@can('update', $grn)
    <a href="{{ route('goods-receipt-notes.edit', $grn) }}">Edit</a>
@endcan
```

---

## Testing Strategy

### Test Organization
- **Feature Tests**: `tests/Feature/` - Test HTTP requests and responses
- **Unit Tests**: `tests/Unit/` - Test individual classes/methods

### Using Pest PHP
Tests use Pest syntax:

```php
it('can create a goods receipt note', function () {
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $response = $this->post(route('goods-receipt-notes.store'), [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'receipt_date' => now()->toDateString(),
        // ...
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('goods_receipt_notes', [
        'supplier_id' => $supplier->id,
    ]);
});
```

### Existing Tests
- Authentication tests (Jetstream)
- Feature tests for core functionality
- Located in `tests/Feature/`

---

## Common Operations

### Creating a New Resource

**1. Generate scaffolding:**
```bash
php artisan make:model Product -mfsc
# -m: migration
# -f: factory
# -s: seeder
# -c: controller
```

**2. Create policy:**
```bash
php artisan make:policy ProductPolicy --model=Product
```

**3. Create form requests:**
```bash
php artisan make:request StoreProductRequest
php artisan make:request UpdateProductRequest
```

**4. Define routes in `routes/web.php`:**
```php
Route::resource('products', ProductController::class);
```

**5. Create views:**
- `resources/views/products/index.blade.php`
- `resources/views/products/create.blade.php`
- `resources/views/products/edit.blade.php`
- `resources/views/products/show.blade.php`

### Adding a Migration

```bash
php artisan make:migration add_field_to_table --table=table_name
```

**Example migration:**
```php
public function up()
{
    Schema::table('products', function (Blueprint $table) {
        $table->string('new_field')->nullable()->after('existing_field');
    });
}
```

### Debugging

**View SQL Queries:**
```php
DB::enableQueryLog();
// ... run queries
dd(DB::getQueryLog());
```

**Log to Laravel Log:**
```php
Log::info('Message', ['context' => $data]);
Log::error('Error occurred', ['exception' => $e->getMessage()]);
```

**Watch Logs:**
```bash
php artisan pail
# or
tail -f storage/logs/laravel.log
```

---

## Important Notes & Gotchas

### Inventory Management

1. **GRN Immutability**: Once a GRN is posted, it CANNOT be edited or deleted. This ensures inventory integrity.

2. **Transaction Safety**: All inventory operations use database transactions. If any part fails, everything rolls back.

3. **FIFO Costing**: The system automatically calculates FIFO cost using `StockValuationLayer`. Don't manually modify these records.

4. **Batch Codes**: Automatically generated. Each GRN item creates a unique batch.

5. **Promotional Priority**: Lower priority number = higher urgency. Priority 1 items sell before priority 99.

### Accounting

1. **Double-Entry**: Journal entries must have balanced debits and credits. This is enforced at the database and application level.

2. **Period Locking**: Once an accounting period is closed, transactions in that period cannot be modified.

3. **Journal Entry Posting**: Similar to GRNs, posted journal entries become immutable.

### Database

1. **Soft Deletes**: Most models use soft deletes. Use `->withTrashed()` to include deleted records in queries.

2. **Foreign Key Constraints**: Extensive FK constraints mean you can't delete records with dependencies. Soft delete instead.

3. **ID Generation**: Many tables use custom ID generators with prefixes (GRN-xxxx, etc.). Don't create these manually.

### Performance

1. **Eager Loading**: Always eager load relationships to avoid N+1 queries:
   ```php
   $grns = GoodsReceiptNote::with(['supplier', 'warehouse', 'items.product'])->get();
   ```

2. **Current Stock Tables**: `current_stock` and `current_stock_by_batch` are summary tables updated by the service layer. Don't query `stock_movements` directly for current inventory.

3. **Activity Log**: Spatie Activity Log tracks all model changes. This can grow large over time. Consider archiving old logs.

### Security

1. **Authorization**: Always authorize actions in controllers using policies or gates.

2. **Mass Assignment**: All models define `$fillable` or `$guarded` to prevent mass assignment vulnerabilities.

3. **Input Validation**: All user input is validated via Form Requests before reaching the controller.

4. **CSRF Protection**: All forms must include `@csrf` blade directive.

### Development

1. **Queue Worker**: Some operations use queues. Run `php artisan queue:listen` during development.

2. **Asset Building**: After changing JS/CSS, rebuild assets with `npm run build` (production) or `npm run dev` (development with HMR).

3. **Cache Clearing**: After config changes, clear cache with `php artisan optimize:clear`.

4. **Migrations in Production**: Always backup database before running migrations in production.

---

## Environment Configuration

### Key .env Variables

**Database:**
```env
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=moontrader
DB_USERNAME=root
DB_PASSWORD=
```

**Application:**
```env
APP_NAME=MoonTrader
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost
```

**Queue:**
```env
QUEUE_CONNECTION=database
```

**Session:**
```env
SESSION_DRIVER=database
```

---

## Useful Artisan Commands

### Development
```bash
php artisan serve              # Start development server
php artisan queue:listen       # Start queue worker
php artisan pail              # Monitor logs
php artisan optimize:clear    # Clear all caches
php artisan route:list        # List all routes
php artisan migrate:status    # Check migration status
```

### Code Generation
```bash
php artisan make:model ModelName -mfsc
php artisan make:controller ControllerName
php artisan make:request RequestName
php artisan make:policy PolicyName
php artisan make:migration create_table_name
php artisan make:seeder SeederName
```

### Database
```bash
php artisan migrate                    # Run migrations
php artisan migrate:fresh --seed       # Fresh DB with seed data
php artisan db:seed                    # Run seeders
php artisan migrate:rollback          # Rollback last migration
```

---

## Key Files to Reference

### Core Services
- `app/Services/InventoryService.php` - All inventory operations
- `app/Services/AccountingService.php` - Accounting integration
- `app/Services/PaymentService.php` - Payment processing

### Key Controllers
- `app/Http/Controllers/GoodsReceiptNoteController.php` - GRN CRUD and posting
- `app/Http/Controllers/CurrentStockController.php` - View current inventory
- `app/Http/Controllers/JournalEntryController.php` - Accounting entries

### Important Models
- `app/Models/GoodsReceiptNote.php`
- `app/Models/StockBatch.php`
- `app/Models/StockMovement.php`
- `app/Models/Product.php`
- `app/Models/JournalEntry.php`

### Database Seeders
- `database/seeders/DatabaseSeeder.php` - Main seeder orchestration
- `database/seeders/ProductSeeder.php`
- `database/seeders/SupplierSeeder.php`
- `database/seeders/ChartOfAccountSeeder.php`

### Configuration
- `config/app.php` - Application configuration
- `config/database.php` - Database configuration
- `config/permission.php` - Spatie permission config
- `config/activitylog.php` - Spatie activity log config

---

## Additional Documentation

The project includes comprehensive documentation:

- **README.md** - Project overview and quick start
- **QUICKSTART.md** - Quick start guide for inventory system
- **GRN_QUICK_START.md** - GRN CRUD implementation guide
- **INVENTORY_GUIDE.md** - Detailed inventory user guide
- **INVENTORY_IMPLEMENTATION_SUMMARY.md** - Complete implementation details

---

## Context for AI Assistance

When working on this project with Claude Code:

### Best Practices
1. **Always check authorization**: Verify policies before making changes to controllers
2. **Use transactions**: Wrap multi-step operations in DB transactions
3. **Follow service pattern**: Business logic goes in services, not controllers
4. **Validate input**: Use Form Requests for all user input
5. **Maintain audit trail**: Spatie Activity Log is configured - it logs automatically
6. **Test thoroughly**: Write Pest tests for new features
7. **Keep consistency**: Follow existing naming conventions and patterns

### When Adding Features
1. Check if similar functionality exists elsewhere in the codebase
2. Use the same service pattern approach
3. Create policies for authorization
4. Add form request validation
5. Update routes in `web.php`
6. Create Blade views following the existing structure
7. Consider accounting implications (does it need a journal entry?)
8. Update seeders if needed for test data

### When Debugging
1. Check `storage/logs/laravel.log`
2. Use `php artisan pail` for real-time logs
3. Verify database transactions committed successfully
4. Check authorization policies
5. Review activity log for audit trail
6. Verify foreign key relationships

### Architecture Principles
- **Separation of Concerns**: Controllers → Services → Models
- **Single Responsibility**: Each service/class has one clear purpose
- **DRY (Don't Repeat Yourself)**: Reuse services and components
- **SOLID Principles**: Follow Laravel best practices
- **Security First**: Always authorize and validate

---

## License
[Specify your license here]

## Credits
Built with Laravel 12 and modern web technologies.
