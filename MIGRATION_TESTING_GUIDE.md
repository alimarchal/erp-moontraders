# Migration and Testing Guide

## For Fresh Installation (migrate:fresh --seed)

This implementation is fully compatible with `php artisan migrate:fresh --seed`. All components are properly integrated into the seeding flow.

### What Happens During migrate:fresh --seed

1. **DatabaseSeeder** runs all seeders in this order:
   - RoleAndPermissionSeeder (creates basic roles)
   - UserSeeder
   - AccountTypeSeeder
   - CurrencySeeder
   - AccountingPeriodSeeder
   - ChartOfAccountSeeder (auto-generates account codes)
   - **RecallAccountsSeeder** (appends 5 stock loss accounts dynamically)
   - TaxCodeSeeder
   - ... (other seeders)
   - **RecallPermissionsSeeder** (adds 11 permissions at the end)

2. **Account Codes Are Auto-Generated**
   - The RecallAccountsSeeder NO LONGER uses hardcoded codes (5280-5284)
   - Instead, it finds the "Indirect Expenses" parent account
   - Calculates next available codes dynamically based on existing children
   - Appends the 5 stock loss accounts: Stock Loss on Recalls, Stock Loss - Damage, Stock Loss - Theft, Stock Loss - Expiry, Stock Loss - Other
   - This ensures no conflicts even if the COA structure changes

3. **Services Use Account Names**
   - StockAdjustmentService queries by account_name instead of account_code
   - This makes it resilient to code changes

### Running Fresh Migration with Seed

```bash
# Fresh installation
php artisan migrate:fresh --seed

# Verify accounts were created
php artisan tinker
>>> App\Models\ChartOfAccount::where('account_name', 'LIKE', 'Stock Loss%')->pluck('account_code', 'account_name');

# Verify permissions were created
>>> Spatie\Permission\Models\Permission::where('name', 'LIKE', '%stock-adjustment%')->orWhere('name', 'LIKE', '%product-recall%')->pluck('name');
```

### Expected Output

The seeder will output:
```
Created account XXXX - Stock Loss on Recalls
Created account XXXX - Stock Loss - Damage
Created account XXXX - Stock Loss - Theft
Created account XXXX - Stock Loss - Expiry
Created account XXXX - Stock Loss - Other
```

(Where XXXX is the dynamically generated code, likely 5280-5284 but may vary)

## Testing the Implementation

### 1. Run Automated Tests

```bash
# Run stock adjustment tests (13 tests)
php artisan test --filter=StockAdjustment

# Run product recall tests (12 tests)
php artisan test --filter=ProductRecall

# Run all tests
php artisan test
```

### 2. Validate Installation

```bash
# Run validation script
bash validate-implementation.sh
```

Expected output: All ✓ green checkmarks

### 3. Manual Testing Checklist

#### Test Stock Adjustments
1. Navigate to Stock Adjustments (link in main nav if you have permission)
2. Create a draft adjustment
3. Post the adjustment (requires password)
4. Verify:
   - Journal entry created
   - Inventory reduced
   - Batch status updated

#### Test Product Recalls
1. Navigate to Product Recalls (link in main nav if you have permission)
2. Create a draft recall
3. Post the recall (requires password)
4. Verify:
   - Stock adjustment auto-created
   - Journal entry posted
   - Batch status = 'recalled'
5. Create claim from posted recall
6. Verify claim register created

## Navigation & Permissions

### Navigation Links Added

**Main Navigation:**
- Stock Adjustments (requires `stock-adjustment-list` permission)
- Product Recalls (requires `product-recall-list` permission)

Located after "Inventory" and before "Goods Issue"

### Permissions Added (11 total)

**Stock Adjustments (5):**
- `stock-adjustment-list`
- `stock-adjustment-create`
- `stock-adjustment-edit`
- `stock-adjustment-delete`
- `stock-adjustment-post`

**Product Recalls (6):**
- `product-recall-list`
- `product-recall-create`
- `product-recall-edit`
- `product-recall-delete`
- `product-recall-post`
- `product-recall-cancel`

### Default Role Assignments

**Super Admin:** All 11 permissions (auto-assigned by RecallPermissionsSeeder)

**Warehouse Manager:** 
- `stock-adjustment-list`
- `stock-adjustment-create`
- `stock-adjustment-edit`
- `product-recall-list`
- `product-recall-create`
- `product-recall-edit`

(Auto-assigned if role exists)

### Assigning Permissions to Other Roles

```bash
php artisan tinker
>>> $role = Spatie\Permission\Models\Role::findByName('YourRoleName');
>>> $role->givePermissionTo(['stock-adjustment-list', 'product-recall-list']);
```

Or use the admin panel to assign permissions via UI.

## Database Compatibility Verification

### Test on MySQL

```bash
# In .env
DB_CONNECTION=mysql

php artisan migrate:fresh --seed
php artisan test
```

### Test on PostgreSQL

```bash
# In .env
DB_CONNECTION=pgsql

php artisan migrate:fresh --seed
php artisan test
```

### Test on MariaDB

```bash
# In .env
DB_CONNECTION=mysql
DB_PORT=3306  # MariaDB default

php artisan migrate:fresh --seed
php artisan test
```

## Rollback Plan

If you need to rollback:

```bash
# Rollback the 4 new migrations
php artisan migrate:rollback --step=4

# Remove permissions (optional)
php artisan tinker
>>> Spatie\Permission\Models\Permission::whereIn('name', ['stock-adjustment-list', 'stock-adjustment-create', 'stock-adjustment-edit', 'stock-adjustment-delete', 'stock-adjustment-post', 'product-recall-list', 'product-recall-create', 'product-recall-edit', 'product-recall-delete', 'product-recall-post', 'product-recall-cancel'])->delete();
```

## Troubleshooting

### Issue: Accounts not created during seed

**Solution:** Run the seeder manually:
```bash
php artisan db:seed --class=RecallAccountsSeeder
```

### Issue: Permissions not assigned

**Solution:** Run the permissions seeder manually:
```bash
php artisan db:seed --class=RecallPermissionsSeeder
```

### Issue: Navigation links not showing

**Cause:** User doesn't have required permissions

**Solution:** Assign permissions to user's role:
```bash
php artisan tinker
>>> $user = App\Models\User::find(1);
>>> $user->givePermissionTo(['stock-adjustment-list', 'product-recall-list']);
```

### Issue: Cannot find stock loss accounts

**Cause:** Service looks for accounts by name, which must match exactly

**Solution:** Verify account names:
```bash
php artisan tinker
>>> App\Models\ChartOfAccount::where('account_name', 'LIKE', 'Stock Loss%')->get(['account_code', 'account_name']);
```

Names should be:
- Stock Loss on Recalls
- Stock Loss - Damage
- Stock Loss - Theft
- Stock Loss - Expiry
- Stock Loss - Other

## Summary

✅ **Safe for migrate:fresh --seed** - All components properly integrated
✅ **No hardcoded account codes** - Dynamically appends to COA
✅ **Permissions auto-seeded** - Assigned to Super Admin by default
✅ **Navigation added** - With proper permission checks
✅ **All tests pass** - 25 automated tests
✅ **Multi-database compatible** - MySQL, MariaDB, PostgreSQL
