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

    // Create required chart of accounts
    $this->inventoryAccount = ChartOfAccount::create([
        'account_type_id' => $assetType->id,
        'currency_id' => $currency->id,
        'account_code' => '1161',
        'account_name' => 'Stock In Hand',
        'normal_balance' => 'debit',
        'is_active' => true,
    ]);

    $this->creditorsAccount = ChartOfAccount::create([
        'account_type_id' => $liabilityType->id,
        'currency_id' => $currency->id,
        'account_code' => '2111',
        'account_name' => 'Creditors',
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
        'sales_tax_value' => 1710, // 18% GST on (10000-500-300) = 9200 * 0.18 = 1656 (approx 1710)
        'advance_income_tax' => 92, // 1% advance tax on 9200
        'quantity_received' => 100,
        'quantity_accepted' => 100,
        'unit_cost' => 92, // (10000-500-300)/100 = 9200/100 = 92
        'total_cost' => 9200,
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

    // Should have 5 lines: Inventory Dr, GST Dr, Advance Tax Dr, FMR Allowance Cr, Creditors Cr
    expect($details)->toHaveCount(5);

    // Dr. Inventory (9500 = 10000 - 500)
    $inventoryLine = $details->where('chart_of_account_id', $this->inventoryAccount->id)->first();
    expect((float) $inventoryLine->debit)->toBe(9500.00);
    expect((float) $inventoryLine->credit)->toBe(0.00);

    // Dr. GST / Input Tax Credit (1710)
    $gstLine = $details->where('chart_of_account_id', $this->gstAccount->id)->first();
    expect((float) $gstLine->debit)->toBe(1710.00);
    expect((float) $gstLine->credit)->toBe(0.00);

    // Dr. Advance Tax (92)
    $advanceTaxLine = $details->where('chart_of_account_id', $this->advanceTaxAccount->id)->first();
    expect((float) $advanceTaxLine->debit)->toBe(92.00);
    expect((float) $advanceTaxLine->credit)->toBe(0.00);

    // Cr. FMR Allowance (300) - reduces amount payable
    $fmrLine = $details->where('chart_of_account_id', $this->fmrAllowanceAccount->id)->first();
    expect((float) $fmrLine->debit)->toBe(0.00);
    expect((float) $fmrLine->credit)->toBe(300.00);

    // Cr. Creditors (9500 + 1710 + 92 - 300 = 11002)
    $creditorsLine = $details->where('chart_of_account_id', $this->creditorsAccount->id)->first();
    expect((float) $creditorsLine->debit)->toBe(0.00);
    expect((float) $creditorsLine->credit)->toBe(11002.00);

    // Verify debits = credits
    $totalDebits = $details->sum('debit');
    $totalCredits = $details->sum('credit');
    expect((float) $totalDebits)->toBe((float) $totalCredits);
    expect((float) $totalDebits)->toBe(11302.00); // 9500 + 1710 + 92
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

    // Should have 5 lines (opposite of posting)
    expect($details)->toHaveCount(5);

    // Dr. Creditors (11002 - reverse the credit)
    $creditorsLine = $details->where('chart_of_account_id', $this->creditorsAccount->id)->first();
    expect((float) $creditorsLine->debit)->toBe(11002.00);
    expect((float) $creditorsLine->credit)->toBe(0.00);

    // Dr. FMR Allowance (300 - reverse the credit)
    $fmrLine = $details->where('chart_of_account_id', $this->fmrAllowanceAccount->id)->first();
    expect((float) $fmrLine->debit)->toBe(300.00);
    expect((float) $fmrLine->credit)->toBe(0.00);

    // Cr. GST / Input Tax Credit (1710 - reverse the debit)
    $gstLine = $details->where('chart_of_account_id', $this->gstAccount->id)->first();
    expect((float) $gstLine->debit)->toBe(0.00);
    expect((float) $gstLine->credit)->toBe(1710.00);

    // Cr. Advance Tax (92 - reverse the debit)
    $advanceTaxLine = $details->where('chart_of_account_id', $this->advanceTaxAccount->id)->first();
    expect((float) $advanceTaxLine->debit)->toBe(0.00);
    expect((float) $advanceTaxLine->credit)->toBe(92.00);

    // Cr. Inventory (9500 - reverse the debit)
    $inventoryLine = $details->where('chart_of_account_id', $this->inventoryAccount->id)->first();
    expect((float) $inventoryLine->debit)->toBe(0.00);
    expect((float) $inventoryLine->credit)->toBe(9500.00);

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
