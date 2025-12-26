<?php

use App\Models\ChartOfAccount;
use App\Models\GoodsReceiptNote;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    // Create default currency
    $currency = \App\Models\Currency::create([
        'currency_code' => 'PKR',
        'currency_name' => 'Pakistani Rupee',
        'currency_symbol' => 'Rs',
        'is_base_currency' => true,
    ]);

    // Create accounting period
    \App\Models\AccountingPeriod::create([
        'name' => now()->format('F Y'),
        'start_date' => now()->startOfMonth(),
        'end_date' => now()->endOfMonth(),
        'status' => 'open',
    ]);

    // Create cost centers
    $this->warehouseCostCenter = \App\Models\CostCenter::create([
        'code' => 'CC006',
        'name' => 'Warehouse & Inventory',
        'is_active' => true,
    ]);

    $this->procurementCostCenter = \App\Models\CostCenter::create([
        'code' => 'CC010',
        'name' => 'Procurement & Purchasing',
        'is_active' => true,
    ]);

    // Create account types required for COA
    $assetType = \App\Models\AccountType::create([
        'type_name' => 'Assets',
        'report_group' => 'BalanceSheet',
        'category' => 'Asset',
    ]);

    $liabilityType = \App\Models\AccountType::create([
        'type_name' => 'Liabilities',
        'report_group' => 'BalanceSheet',
        'category' => 'Liability',
    ]);

    $incomeType = \App\Models\AccountType::create([
        'type_name' => 'Income',
        'report_group' => 'IncomeStatement',
        'category' => 'Revenue',
    ]);

    // Create required chart of accounts (must match codes in InventoryService)
    $this->inventoryAccount = ChartOfAccount::create([
        'account_type_id' => $assetType->id,
        'currency_id' => $currency->id,
        'account_code' => '1151',
        'account_name' => 'Stock In Hand',
        'normal_balance' => 'debit',
        'is_active' => true,
    ]);

    $this->creditorsAccount = ChartOfAccount::create([
        'account_type_id' => $liabilityType->id,
        'currency_id' => $currency->id,
        'account_code' => '2110',
        'account_name' => 'Accounts Payable',
        'normal_balance' => 'credit',
        'is_active' => true,
    ]);

    $this->fmrAllowanceAccount = ChartOfAccount::create([
        'account_type_id' => $incomeType->id,
        'currency_id' => $currency->id,
        'account_code' => '4210',
        'account_name' => 'FMR Allowance',
        'normal_balance' => 'credit',
        'is_active' => true,
    ]);

    $this->gstAccount = ChartOfAccount::create([
        'account_type_id' => $liabilityType->id,
        'currency_id' => $currency->id,
        'account_code' => '2121',
        'account_name' => 'General Sales Tax (GST)',
        'normal_balance' => 'credit',
        'is_active' => true,
    ]);

    $this->advanceTaxAccount = ChartOfAccount::create([
        'account_type_id' => $assetType->id,
        'currency_id' => $currency->id,
        'account_code' => '1171',
        'account_name' => 'Advance Tax',
        'normal_balance' => 'debit',
        'is_active' => true,
    ]);

    $expenseType = \App\Models\AccountType::create([
        'type_name' => 'Expenses',
        'report_group' => 'IncomeStatement',
        'category' => 'Expense',
    ]);

    $this->roundOffAccount = ChartOfAccount::create([
        'account_type_id' => $expenseType->id,
        'currency_id' => $currency->id,
        'account_code' => '5271',
        'account_name' => 'Round Off',
        'normal_balance' => 'debit',
        'is_active' => true,
    ]);

    // Create supporting data
    $this->supplier = Supplier::factory()->create();
    $this->warehouse = Warehouse::factory()->create();
    $this->product = Product::factory()->create();
    $this->uom = Uom::factory()->create();
});

it('creates journal entry with all accounts when posting GRN with taxes and allowances', function () {
    // Create GRN with all tax and allowance fields
    $grn = GoodsReceiptNote::factory()->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
        'receipt_date' => now(),
    ]);

    // Service calculates:
    // - Invoice value = extended - discount + GST + advance_tax = 10000 - 500 + 1710 + 92 = 11302
    // - Actual inventory = total_cost = 9200 (qty × unit_cost)
    // - Rounding difference = 11302 - 9200 = 2102 (Dr. Round Off)
    // - Creditors = 11302 - 300 (FMR) = 11002
    $grn->items()->create([
        'line_no' => 1,
        'product_id' => $this->product->id,
        'stock_uom_id' => $this->uom->id,
        'purchase_uom_id' => $this->uom->id,
        'qty_in_purchase_uom' => 100,
        'uom_conversion_factor' => 1,
        'qty_in_stock_uom' => 100,
        'extended_value' => 10000, // Base value
        'discount_value' => 500, // Discount
        'fmr_allowance' => 300, // FMR Allowance
        'sales_tax_value' => 1710, // GST (included in invoice value)
        'advance_income_tax' => 92, // Advance tax (included in invoice value)
        'quantity_received' => 100,
        'quantity_accepted' => 100,
        'unit_cost' => 92, // Unit cost per stock unit
        'total_cost' => 9200, // Actual inventory value: 100 × 92
    ]);

    // Post GRN
    $inventoryService = app(InventoryService::class);
    $result = $inventoryService->postGrnToInventory($grn->fresh());

    expect($result['success'])->toBeTrue();

    // Verify journal entry was created
    $grn = $grn->fresh();
    expect($grn->journal_entry_id)->not->toBeNull();
    expect($grn->status)->toBe('posted');

    $journalEntry = JournalEntry::find($grn->journal_entry_id);
    expect($journalEntry)->not->toBeNull();
    expect($journalEntry->status)->toBe('posted');

    // Verify journal entry details
    $details = $journalEntry->details;

    // Should have 4 lines: Inventory Dr, Round Off Dr, FMR Allowance Cr, Creditors Cr
    // (GST and advance tax are included in invoice value, difference goes to Round Off)
    expect($details)->toHaveCount(4);

    // Dr. Inventory = actual cost (9200), NOT invoice value
    $inventoryLine = $details->where('chart_of_account_id', $this->inventoryAccount->id)->first();
    expect((float) $inventoryLine->debit)->toBe(9200.00);
    expect((float) $inventoryLine->credit)->toBe(0.00);

    // Dr. Round Off = invoice value - actual = 11302 - 9200 = 2102
    $roundOffLine = $details->where('chart_of_account_id', $this->roundOffAccount->id)->first();
    expect((float) $roundOffLine->debit)->toBe(2102.00);
    expect((float) $roundOffLine->credit)->toBe(0.00);

    // Cr. FMR Allowance (300) - reduces amount payable
    $fmrLine = $details->where('chart_of_account_id', $this->fmrAllowanceAccount->id)->first();
    expect((float) $fmrLine->debit)->toBe(0.00);
    expect((float) $fmrLine->credit)->toBe(300.00);

    // Cr. Creditors (11302 - 300 = 11002)
    $creditorsLine = $details->where('chart_of_account_id', $this->creditorsAccount->id)->first();
    expect((float) $creditorsLine->debit)->toBe(0.00);
    expect((float) $creditorsLine->credit)->toBe(11002.00);

    // Verify debits = credits
    $totalDebits = $details->sum('debit');
    $totalCredits = $details->sum('credit');
    expect((float) $totalDebits)->toBe((float) $totalCredits);
    expect((float) $totalDebits)->toBe(11302.00); // 9200 + 2102
});

it('creates journal entry with only inventory and creditors when no taxes or allowances', function () {
    // Create GRN without taxes and allowances
    $grn = GoodsReceiptNote::factory()->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
        'receipt_date' => now(),
    ]);

    $grn->items()->create([
        'line_no' => 1,
        'product_id' => $this->product->id,
        'stock_uom_id' => $this->uom->id,
        'purchase_uom_id' => $this->uom->id,
        'qty_in_purchase_uom' => 100,
        'uom_conversion_factor' => 1,
        'qty_in_stock_uom' => 100,
        'extended_value' => 10000,
        'discount_value' => 0,
        'fmr_allowance' => 0,
        'sales_tax_value' => 0,
        'advance_income_tax' => 0,
        'quantity_received' => 100,
        'quantity_accepted' => 100,
        'unit_cost' => 100,
        'total_cost' => 10000,
    ]);

    // Post GRN
    $inventoryService = app(InventoryService::class);
    $result = $inventoryService->postGrnToInventory($grn->fresh());

    expect($result['success'])->toBeTrue();

    $journalEntry = JournalEntry::find($grn->fresh()->journal_entry_id);
    $details = $journalEntry->details;

    // Should have only 2 lines: Inventory Dr, Creditors Cr
    expect($details)->toHaveCount(2);

    // Dr. Inventory (10000)
    $inventoryLine = $details->where('chart_of_account_id', $this->inventoryAccount->id)->first();
    expect((float) $inventoryLine->debit)->toBe(10000.00);
    expect((float) $inventoryLine->credit)->toBe(0.00);

    // Cr. Creditors (10000)
    $creditorsLine = $details->where('chart_of_account_id', $this->creditorsAccount->id)->first();
    expect((float) $creditorsLine->debit)->toBe(0.00);
    expect((float) $creditorsLine->credit)->toBe(10000.00);
});

it('creates correct reversing journal entry when GRN is reversed', function () {
    // Create and post GRN with all components
    $grn = GoodsReceiptNote::factory()->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
        'receipt_date' => now(),
    ]);

    // Same data as posting test
    // - Invoice value = 10000 - 500 + 1710 + 92 = 11302
    // - Actual = 9200, Rounding = 2102
    $grn->items()->create([
        'line_no' => 1,
        'product_id' => $this->product->id,
        'stock_uom_id' => $this->uom->id,
        'purchase_uom_id' => $this->uom->id,
        'qty_in_purchase_uom' => 100,
        'uom_conversion_factor' => 1,
        'qty_in_stock_uom' => 100,
        'extended_value' => 10000,
        'discount_value' => 500,
        'fmr_allowance' => 300,
        'sales_tax_value' => 1710,
        'advance_income_tax' => 92,
        'quantity_received' => 100,
        'quantity_accepted' => 100,
        'unit_cost' => 92,
        'total_cost' => 9200,
    ]);

    // Post GRN
    $inventoryService = app(InventoryService::class);
    $inventoryService->postGrnToInventory($grn->fresh());

    $originalJournalEntryId = $grn->fresh()->journal_entry_id;

    // Reverse GRN
    $result = $inventoryService->reverseGrnInventory($grn->fresh());

    expect($result['success'])->toBeTrue();
    expect($grn->fresh()->status)->toBe('reversed');

    // Find the reversing journal entry (should be the most recent one with REVERSAL in description)
    $reversingEntry = JournalEntry::where('description', 'like', '%REVERSAL%')
        ->where('id', '!=', $originalJournalEntryId)
        ->where('status', 'posted')
        ->latest()
        ->first();

    expect($reversingEntry)->not->toBeNull();

    $details = $reversingEntry->details;

    // Should have 4 lines (opposite of posting):
    // Dr. Creditors, Dr. FMR, Cr. Inventory, Cr. Round Off
    expect($details)->toHaveCount(4);

    // Dr. Creditors (11002 - reverse the credit)
    $creditorsLine = $details->where('chart_of_account_id', $this->creditorsAccount->id)->first();
    expect((float) $creditorsLine->debit)->toBe(11002.00);
    expect((float) $creditorsLine->credit)->toBe(0.00);

    // Dr. FMR Allowance (300 - reverse the credit)
    $fmrLine = $details->where('chart_of_account_id', $this->fmrAllowanceAccount->id)->first();
    expect((float) $fmrLine->debit)->toBe(300.00);
    expect((float) $fmrLine->credit)->toBe(0.00);

    // Cr. Inventory (9200 - reverse the debit, actual cost)
    $inventoryLine = $details->where('chart_of_account_id', $this->inventoryAccount->id)->first();
    expect((float) $inventoryLine->debit)->toBe(0.00);
    expect((float) $inventoryLine->credit)->toBe(9200.00);

    // Cr. Round Off (2102 - reverse the debit)
    $roundOffLine = $details->where('chart_of_account_id', $this->roundOffAccount->id)->first();
    expect((float) $roundOffLine->debit)->toBe(0.00);
    expect((float) $roundOffLine->credit)->toBe(2102.00);

    // Verify debits = credits
    $totalDebits = $details->sum('debit');
    $totalCredits = $details->sum('credit');
    expect((float) $totalDebits)->toBe((float) $totalCredits);
});

it('correctly handles discount reducing inventory value', function () {
    // Create GRN with discount only
    $grn = GoodsReceiptNote::factory()->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
        'receipt_date' => now(),
    ]);

    $grn->items()->create([
        'line_no' => 1,
        'product_id' => $this->product->id,
        'stock_uom_id' => $this->uom->id,
        'purchase_uom_id' => $this->uom->id,
        'qty_in_purchase_uom' => 100,
        'uom_conversion_factor' => 1,
        'qty_in_stock_uom' => 100,
        'extended_value' => 10000,
        'discount_value' => 1000, // 10% discount
        'fmr_allowance' => 0,
        'sales_tax_value' => 0,
        'advance_income_tax' => 0,
        'quantity_received' => 100,
        'quantity_accepted' => 100,
        'unit_cost' => 90, // (10000-1000)/100
        'total_cost' => 9000,
    ]);

    // Post GRN
    $inventoryService = app(InventoryService::class);
    $result = $inventoryService->postGrnToInventory($grn->fresh());

    expect($result['success'])->toBeTrue();

    $journalEntry = JournalEntry::find($grn->fresh()->journal_entry_id);
    $details = $journalEntry->details;

    // Dr. Inventory should be net of discount (9000)
    $inventoryLine = $details->where('chart_of_account_id', $this->inventoryAccount->id)->first();
    expect((float) $inventoryLine->debit)->toBe(9000.00);

    // Cr. Creditors should also be 9000 (no taxes)
    $creditorsLine = $details->where('chart_of_account_id', $this->creditorsAccount->id)->first();
    expect((float) $creditorsLine->credit)->toBe(9000.00);
});

it('ensures books remain balanced after posting and reversing', function () {
    // Create and post GRN
    $grn = GoodsReceiptNote::factory()->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
        'receipt_date' => now(),
    ]);

    $grn->items()->create([
        'line_no' => 1,
        'product_id' => $this->product->id,
        'stock_uom_id' => $this->uom->id,
        'purchase_uom_id' => $this->uom->id,
        'qty_in_purchase_uom' => 100,
        'uom_conversion_factor' => 1,
        'qty_in_stock_uom' => 100,
        'extended_value' => 10000,
        'discount_value' => 500,
        'fmr_allowance' => 300,
        'sales_tax_value' => 1710,
        'advance_income_tax' => 92,
        'quantity_received' => 100,
        'quantity_accepted' => 100,
        'unit_cost' => 92,
        'total_cost' => 9200,
    ]);

    // Post GRN
    $inventoryService = app(InventoryService::class);
    $inventoryService->postGrnToInventory($grn->fresh());

    // Get balances after posting
    $postingEntry = JournalEntry::find($grn->fresh()->journal_entry_id);
    $postingDebits = $postingEntry->details->sum('debit');
    $postingCredits = $postingEntry->details->sum('credit');

    // Books should be balanced after posting
    expect((float) $postingDebits)->toBe((float) $postingCredits);

    // Reverse GRN
    $inventoryService->reverseGrnInventory($grn->fresh());

    // Find reversing entry (should have REVERSAL in description)
    $reversingEntry = JournalEntry::where('description', 'like', '%REVERSAL%')
        ->where('id', '!=', $postingEntry->id)
        ->where('status', 'posted')
        ->latest()
        ->first();

    $reversingDebits = $reversingEntry->details->sum('debit');
    $reversingCredits = $reversingEntry->details->sum('credit');

    // Books should be balanced after reversal
    expect((float) $reversingDebits)->toBe((float) $reversingCredits);

    // Posting and reversing amounts should match
    expect($postingDebits)->toBe($reversingDebits);
    expect($postingCredits)->toBe($reversingCredits);
});

it('posts inventory at actual cost (qty × unit_cost) with rounding difference to Round Off account', function () {
    // Simulate scenario where accounting value differs from actual inventory value
    // This happens due to rounding in unit cost calculation
    $grn = GoodsReceiptNote::factory()->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
        'receipt_date' => now(),
    ]);

    // Create GRN item where:
    // - Accounting value (extended - discount + GST) = 10000 - 500 + 1700 = 11200
    // - Actual inventory value (total_cost = qty × unit_cost) = 100 × 111.86 = 11186
    // - Rounding difference = 11200 - 11186 = 14 (should go to Round Off)
    $grn->items()->create([
        'line_no' => 1,
        'product_id' => $this->product->id,
        'stock_uom_id' => $this->uom->id,
        'purchase_uom_id' => $this->uom->id,
        'qty_in_purchase_uom' => 100,
        'uom_conversion_factor' => 1,
        'qty_in_stock_uom' => 100,
        'extended_value' => 10000, // Base value
        'discount_value' => 500, // Discount
        'fmr_allowance' => 0, // No FMR for simpler test
        'sales_tax_value' => 1700, // GST (capitalized into inventory)
        'advance_income_tax' => 0, // No advance tax for simpler test
        'quantity_received' => 100,
        'quantity_accepted' => 100,
        'unit_cost' => 111.86, // Actual unit cost (slightly different due to rounding)
        'total_cost' => 11186, // Actual inventory value: 100 × 111.86
    ]);

    // Post GRN
    $inventoryService = app(InventoryService::class);
    $result = $inventoryService->postGrnToInventory($grn->fresh());

    expect($result['success'])->toBeTrue();

    $journalEntry = JournalEntry::find($grn->fresh()->journal_entry_id);
    $details = $journalEntry->details;

    // Should have 3 lines: Inventory Dr, Round Off Dr, Creditors Cr
    expect($details)->toHaveCount(3);

    // Dr. Inventory should be ACTUAL cost (11186), not accounting value (11200)
    $inventoryLine = $details->where('chart_of_account_id', $this->inventoryAccount->id)->first();
    expect((float) $inventoryLine->debit)->toBe(11186.00);
    expect((float) $inventoryLine->credit)->toBe(0.00);

    // Dr. Round Off should be the difference (11200 - 11186 = 14)
    $roundOffLine = $details->where('chart_of_account_id', $this->roundOffAccount->id)->first();
    expect((float) $roundOffLine->debit)->toBe(14.00);
    expect((float) $roundOffLine->credit)->toBe(0.00);

    // Cr. Creditors should be full accounting value (11200)
    $creditorsLine = $details->where('chart_of_account_id', $this->creditorsAccount->id)->first();
    expect((float) $creditorsLine->debit)->toBe(0.00);
    expect((float) $creditorsLine->credit)->toBe(11200.00);

    // Verify debits = credits (books balanced)
    $totalDebits = $details->sum('debit');
    $totalCredits = $details->sum('credit');
    expect((float) $totalDebits)->toBe((float) $totalCredits);
    expect((float) $totalDebits)->toBe(11200.00); // 11186 + 14 = 11200
});

it('handles negative rounding difference (actual > accounting) by crediting Round Off', function () {
    // Scenario where actual inventory cost is higher than accounting value
    $grn = GoodsReceiptNote::factory()->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
        'receipt_date' => now(),
    ]);

    // Create GRN item where:
    // - Accounting value = 10000 - 500 + 1700 = 11200
    // - Actual inventory value = 100 × 112.10 = 11210
    // - Rounding difference = 11200 - 11210 = -10 (negative, credit to Round Off)
    $grn->items()->create([
        'line_no' => 1,
        'product_id' => $this->product->id,
        'stock_uom_id' => $this->uom->id,
        'purchase_uom_id' => $this->uom->id,
        'qty_in_purchase_uom' => 100,
        'uom_conversion_factor' => 1,
        'qty_in_stock_uom' => 100,
        'extended_value' => 10000,
        'discount_value' => 500,
        'fmr_allowance' => 0,
        'sales_tax_value' => 1700,
        'advance_income_tax' => 0,
        'quantity_received' => 100,
        'quantity_accepted' => 100,
        'unit_cost' => 112.10, // Higher unit cost
        'total_cost' => 11210, // 100 × 112.10
    ]);

    // Post GRN
    $inventoryService = app(InventoryService::class);
    $result = $inventoryService->postGrnToInventory($grn->fresh());

    expect($result['success'])->toBeTrue();

    $journalEntry = JournalEntry::find($grn->fresh()->journal_entry_id);
    $details = $journalEntry->details;

    // Should have 3 lines: Inventory Dr, Round Off Cr, Creditors Cr
    expect($details)->toHaveCount(3);

    // Dr. Inventory should be ACTUAL cost (11210)
    $inventoryLine = $details->where('chart_of_account_id', $this->inventoryAccount->id)->first();
    expect((float) $inventoryLine->debit)->toBe(11210.00);

    // Cr. Round Off should be 10 (negative difference means credit)
    $roundOffLine = $details->where('chart_of_account_id', $this->roundOffAccount->id)->first();
    expect((float) $roundOffLine->debit)->toBe(0.00);
    expect((float) $roundOffLine->credit)->toBe(10.00);

    // Cr. Creditors = 11200
    $creditorsLine = $details->where('chart_of_account_id', $this->creditorsAccount->id)->first();
    expect((float) $creditorsLine->credit)->toBe(11200.00);

    // Verify books balanced
    $totalDebits = $details->sum('debit');
    $totalCredits = $details->sum('credit');
    expect((float) $totalDebits)->toBe((float) $totalCredits);
});
