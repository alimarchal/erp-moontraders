# GRN CRUD - Quick Start Guide

## What's Been Completed ✅

### Database Layer
- ✅ 9 inventory tables migrated
- ✅ All relationships configured
- ✅ Indexes and constraints in place
- ✅ Optional batch tracking infrastructure

### Backend
- ✅ GoodsReceiptNoteController with full CRUD
- ✅ Routes registered in `web.php`
- ✅ Models with relationships and helper methods
- ✅ QueryBuilder filters configured
- ✅ Auto GRN number generation
- ✅ Transaction safety in all operations

### Frontend
- ✅ Index view with filters and pagination
- ✅ Create form with dynamic line items (Alpine.js)
- ✅ Edit form with pre-populated data
- ✅ Show view with full GRN details
- ✅ Status badges and conditional actions

## Testing the CRUD

### 1. Access the GRN Module

Navigate to:
```
http://moontrader.test/goods-receipt-notes
```

### 2. Create Your First GRN

1. Click **"New GRN"** button
2. Fill in header:
   - Receipt Date: Today's date
   - Supplier: Select from dropdown
   - Warehouse: Select from dropdown
   - Optional: Supplier invoice number/date

3. Add line items:
   - Click product dropdown, select a product
   - Select UOM (Unit of Measure)
   - Enter quantity received
   - Enter quantity accepted (can be less if some rejected)
   - Unit cost auto-fills from product (you can change it)
   - Selling price auto-fills (optional - for supplier MRP)
   - Click **"Add Line"** for more items

4. Add charges (optional):
   - Tax Amount
   - Freight Charges
   - Other Charges

5. Add notes (optional)

6. Click **"Create GRN"**

### 3. View GRN

After creation, you'll be redirected to the GRN details page showing:
- GRN number (auto-generated: GRN-2025-0001)
- Status badge (Draft)
- All line items
- Grand total with breakdown
- Edit button (only for draft)

### 4. Edit GRN (Draft Only)

1. From GRN details page, click **"Edit"**
2. Modify any fields
3. Add/remove line items as needed
4. Click **"Update GRN"**

### 5. Filter GRNs

On the index page, use filters:
- GRN Number (partial search)
- Supplier Invoice (partial search)
- Supplier (dropdown)
- Warehouse (dropdown)
- Status (draft/received/posted)
- Receipt Date From/To (date range)

Click **"Filter"** to apply, **"Reset"** to clear

## What's NOT Yet Working ⚠️

### GRN Posting
Currently GRNs can only be created/edited/deleted in **draft** status.

**To enable posting**, you need to:

1. **Create InventoryService**
   ```bash
   php artisan make:service InventoryService
   # Or manually create: app/Services/InventoryService.php
   ```

2. **Implement `postGoodsReceiptNote()` method** that:
   - Creates stock_movements records
   - Creates stock_valuation_layers
   - Updates stock_ledger_entries
   - Updates current_stock summary
   - Creates journal_entry for accounting
   - Updates GRN status to 'posted'
   - Sets posted_at timestamp

3. **Add post route** (routes/web.php):
   ```php
   Route::post('goods-receipt-notes/{goodsReceiptNote}/post', 
       [GoodsReceiptNoteController::class, 'post'])
       ->name('goods-receipt-notes.post');
   ```

4. **Add post() method** to GoodsReceiptNoteController

5. **Add "Post" button** to show.blade.php:
   ```blade
   @if($grn->status === 'draft')
       <form action="{{ route('goods-receipt-notes.post', $grn) }}" method="POST">
           @csrf
           <button type="submit" class="btn-primary">Post GRN</button>
       </form>
   @endif
   ```

### Navigation Menu
The GRN module is not yet in the sidebar menu.

**To add navigation**:

1. Find your navigation file (typically one of):
   - `resources/views/layouts/navigation.blade.php`
   - `resources/views/layouts/navigation-menu.blade.php`
   - `resources/views/components/nav-link.blade.php`

2. Add link:
   ```blade
   <x-nav-link href="{{ route('goods-receipt-notes.index') }}" 
       :active="request()->routeIs('goods-receipt-notes.*')">
       Goods Receipt Notes
   </x-nav-link>
   ```

### Permissions
No permission checks are implemented yet.

**To add authorization**:

1. Create policy:
   ```bash
   php artisan make:policy GoodsReceiptNotePolicy --model=GoodsReceiptNote
   ```

2. Add middleware to controller:
   ```php
   public static function middleware(): array
   {
       return [
           'permission:view goods receipt notes' => ['only' => ['index', 'show']],
           'permission:create goods receipt notes' => ['only' => ['create', 'store']],
           'permission:edit goods receipt notes' => ['only' => ['edit', 'update']],
           'permission:delete goods receipt notes' => ['only' => ['destroy']],
       ];
   }
   ```

## Current Workflow

```
User → Create GRN (Draft) → View GRN → Edit GRN → Delete GRN
                                ↓
                           [Post GRN - NOT IMPLEMENTED]
                                ↓
                         Status: Posted
                         (Immutable - no edit/delete)
```

## Files Modified/Created

### Created
1. `app/Http/Controllers/GoodsReceiptNoteController.php` - Full resource controller
2. `resources/views/goods-receipt-notes/index.blade.php` - List view
3. `resources/views/goods-receipt-notes/create.blade.php` - Create form
4. `resources/views/goods-receipt-notes/edit.blade.php` - Edit form
5. `resources/views/goods-receipt-notes/show.blade.php` - Details view
6. `INVENTORY_IMPLEMENTATION_SUMMARY.md` - Full documentation
7. `GRN_QUICK_START.md` - This file

### Modified
1. `routes/web.php` - Added GRN resource routes
2. `app/Models/GoodsReceiptNote.php` - Added scope methods for filters
3. `app/Models/GoodsReceiptNoteItem.php` - Already had pricing methods

## Testing Checklist

- [ ] Can access `/goods-receipt-notes` without errors
- [ ] Index page loads with empty state
- [ ] Create form opens successfully
- [ ] Can add/remove dynamic line items
- [ ] Product selection auto-fills unit cost
- [ ] Line totals calculate correctly
- [ ] Grand total updates in real-time
- [ ] Can save GRN successfully
- [ ] GRN number auto-generates (GRN-YYYY-NNNN format)
- [ ] View page displays all GRN details
- [ ] Can edit draft GRN
- [ ] Can delete draft GRN
- [ ] Posted GRN shows no edit/delete buttons
- [ ] Filters work on index page
- [ ] Pagination works
- [ ] Validation prevents saving without required fields

## Troubleshooting

### "Route not found" error
- Clear route cache: `php artisan route:clear`
- Regenerate routes: `php artisan route:cache`

### "View not found" error
- Check file paths match exactly (case-sensitive)
- Clear view cache: `php artisan view:clear`

### "Column not found" error
- Run migrations: `php artisan migrate`
- If already migrated: `php artisan migrate:fresh --seed` (WARNING: deletes all data)

### Alpine.js not working
- Check browser console for JavaScript errors
- Ensure Alpine.js is loaded (should be in your layout)
- Verify `x-data` attribute on form element

### Dropdowns empty
Make sure you have data:
```bash
php artisan db:seed
# Or manually create suppliers, warehouses, products via their CRUD interfaces
```

## Next Steps Priority

1. **Add to navigation menu** (5 minutes) - Makes module easily accessible
2. **Test basic CRUD** (15 minutes) - Ensure everything works
3. **Create test suppliers/products** (10 minutes) - Needed for testing
4. **Implement InventoryService** (2-3 hours) - Enables posting
5. **Add permissions** (30 minutes) - Security layer
6. **Write tests** (1-2 hours) - Ensure reliability

## Contact/Support

Refer to `INVENTORY_IMPLEMENTATION_SUMMARY.md` for:
- Complete database schema
- Business flow examples
- Accounting integration details
- Performance optimization tips
- Pending implementation details

---

**Status**: ✅ CRUD Interface 100% Complete  
**Last Updated**: {{ now()->format('Y-m-d H:i:s') }}  
**Developer**: GitHub Copilot  
**Framework**: Laravel 12 + Livewire 3 + Alpine.js + TailwindCSS 3
