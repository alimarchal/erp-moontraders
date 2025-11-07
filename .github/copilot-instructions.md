# Moontrader ERP - AI Coding Agent Instructions

## Project Overview
Laravel 12 ERP system with **double-entry accounting core**, inventory, and basic business operations. Built with Livewire 3, Jetstream, TailwindCSS 3, and Pest testing framework. Uses Spatie packages for permissions (`spatie/laravel-permission`) and filtering (`spatie/laravel-query-builder`).

## Architecture

### Core Accounting Engine
- **Double-entry bookkeeping** enforced via database triggers (PostgreSQL/MySQL)
- Chart of accounts with hierarchical structure (`parent_id`, recursive `descendants()`)
- Journal entries flow: `draft` → `post()` → `posted` (immutable)
- **Never edit posted entries** - create reversing entries via `reverseJournalEntry()`
- Balance calculations use snapshots (`account_balance_snapshots`) + incremental updates for performance

### Service Layer Pattern
- `AccountingService` is the **single source of truth** for all journal entry operations
- Controllers delegate to service methods (e.g., `createJournalEntry()`, `postJournalEntry()`)
- Service returns `['success' => bool, 'data' => Model, 'message' => string]` format
- All accounting operations wrapped in DB transactions with automatic rollback

### Database Audit Trail
- Automatic audit logging via triggers (`accounting_audit_log` table)
- Middleware `SetDatabaseAuditContext` sets session variables (`@current_user_id`, IP, user agent)
- Triggers capture INSERT/UPDATE/DELETE with old/new values and changed fields
- Soft deletes allowed only for draft entries (enforced by `prevent_hard_delete_posted()` trigger)

## Key Conventions

### Models & Relationships
- `ChartOfAccount::children()` - direct children only
- `ChartOfAccount::childrenRecursive()` - full tree (use for tree views)
- `JournalEntry::details()` - line items sorted by `line_no`
- All financial models use `decimal(15,2)` for amounts

### Authorization
- Spatie Laravel Permission (`HasRoles` trait on `User`)
- "Super Admin" role bypassed via `Gate::before()` in `AppServiceProvider`
- Middleware aliases registered in `bootstrap/app.php`: `role`, `permission`, `role_or_permission`
- Controllers use `HasMiddleware` interface with attribute-based route middleware
- Policies auto-discovered (no manual registration needed)

### Validation Rules
- Journal entries require minimum 2 lines
- Total debits must equal total credits (±0.01 tolerance)
- Each line must have either debit XOR credit (not both, not neither)
- Posted entries immutable - update status checks in controller before service call

### File Storage
- `FileStorageHelper::storeFiles()` - public disk (direct URL access)
- `FileStorageHelper::storePrivateFiles()` - local disk (controlled access)
- Auto-generates UUID filenames, stores metadata in `attachments` table
- Attachments polymorphic (`attachmentable_id`, `attachmentable_type`)

## Development Workflows

### Running the Application
```bash
composer run dev  # Concurrent: server, queue, pail logs, vite
composer run setup  # Fresh install: deps, .env, migrate, npm
php artisan test  # Pest test suite
```

### Database Migrations
- Use artisan for new migrations: `php artisan make:migration`
- Accounting schema has **database triggers** - test on both PostgreSQL and MySQL if modifying
- Migration `2025_10_30_183216` has trigger creation logic - reference for patterns
- Never bypass service layer for journal entries (breaks audit trail)

### Creating Journal Entries
```php
// Always use service layer
$result = app(AccountingService::class)->createJournalEntry([
    'entry_date' => now()->toDateString(),
    'description' => 'Description',
    'lines' => [
        ['account_id' => 7, 'debit' => 1000, 'credit' => 0, 'description' => '...'],
        ['account_id' => 29, 'debit' => 0, 'credit' => 1000, 'description' => '...'],
    ],
    'auto_post' => true, // Optional: post immediately
]);
```

### Testing
- Pest configured with `RefreshDatabase` for Feature tests
- Test factories in `database/factories/`
- Run specific test: `php artisan test --filter=TestName`

## Common Pitfalls

1. **Never directly create/update journal entries** - always use `AccountingService` methods
2. **Check `status` before editing** - posted entries are immutable
3. **Balance validation** happens in service layer - don't duplicate in requests
4. **Soft deletes only work on drafts** - attempting to delete posted entries throws exception
5. **Audit context requires auth** - middleware only sets variables for authenticated users
6. **Account hierarchy queries** - use `childrenRecursive()` to avoid N+1, not manual recursion

## Front-End Stack
- Livewire 3 components for reactive features (API tokens, navigation)
- Alpine.js via `@entangle` for Livewire state binding
- TailwindCSS 3 utility classes (no custom CSS files)
- Blade components in `resources/views/components/`
- Vite for asset compilation (`npm run dev` for HMR)

## Database Specifics
- Supports PostgreSQL (preferred) and MySQL/MariaDB
- Stored procedures: `sp_create_period_snapshots(period_id)` for month-end
- Functions: `fn_account_balance_fast(account_id, date)` for optimized balance queries
- Views: `accounting_views` migration creates materialized views for reports

## External Dependencies
- `alimarchal/id-generator` - auto-incrementing reference numbers
- `spatie/laravel-activitylog` - User model activity logging
- `spatie/laravel-query-builder` - API filtering/sorting
- `barryvdh/laravel-dompdf` - PDF generation (vehicles export)

## Project Structure Notes
- `app/Helpers/FileStorageHelper.php` - file upload utilities
- `app/Services/AccountingService.php` - all accounting logic
- `routes/web.php` - RESTful resource routes grouped by auth middleware
- `routes/console.php` - scheduled commands (Laravel 11+ pattern)
- `bootstrap/app.php` - middleware registration, routing config
- `bootstrap/providers.php` - service provider registration (if needed)
- `config/permission.php` - Spatie permission tables/cache config

## Laravel 11+ Coding Standards

### Code Quality
- **No obvious comments** - Only document non-obvious logic or "why" decisions
- **No commented-out code** - Delete old code, rely on Git history
- **Use type hints** - Leverage PHP 8.2+ features throughout

### File Generation
- **Use Artisan commands** - `php artisan make:*` creates proper structure
  - `php artisan make:migration` - for migrations
  - `php artisan make:view` - for Blade files (not `touch` or `mkdir`)
  - `php artisan make:controller` - for controllers
  - `php artisan make:model` - for models
- **Pivot table naming** - Alphabetical order: `create_project_role_table` not `create_role_project_table`

### Laravel 11+ Architecture
- **Service Providers** - Only `AppServiceProvider` exists. Register new providers in `bootstrap/providers.php` (not `config/app.php`)
- **Event Listeners** - Auto-discovered via type-hinted constructor (no manual registration)
- **Scheduled Tasks** - Define in `routes/console.php` (no `app/Console/Kernel.php`)
- **Middleware** - Use class names in routes. Register aliases in `bootstrap/app.php` (not `app/Http/Kernel.php`)
- **Policies** - Auto-discovered by convention (no manual registration)

### Front-End Patterns
- **TailwindCSS only** - No Bootstrap unless explicitly requested
- **Vite** - Pre-configured for asset compilation (`npm run dev`)
- **Blade components** - Use `<x-*>` components in `resources/views/components/`

### Testing & Data
- **Pest PHP** - Functional testing with `RefreshDatabase` trait
- **Factories** - Use `fake()` helper, not `$this->faker`
- **Seeders** - Generate test data for development

### Package Management
- All packages must be Laravel 12 compatible
- Keep `spatie/laravel-permission` and `spatie/laravel-query-builder` updated
- Review breaking changes when updating major versions
