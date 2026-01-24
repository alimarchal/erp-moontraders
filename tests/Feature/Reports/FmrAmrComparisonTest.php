<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();

    // Create accounting periods for the test years
    \App\Models\AccountingPeriod::factory()->create([
        'name' => 'FY 2025',
        'start_date' => '2025-01-01',
        'end_date' => '2025-12-31',
        'status' => 'open',
    ]);

    \App\Models\AccountingPeriod::factory()->create([
        'name' => 'FY 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'status' => 'open',
    ]);

    $this->currency = \App\Models\Currency::factory()->create();
    $this->accountType = \App\Models\AccountType::factory()->create([
        'type_name' => 'Expense',
        'report_group' => 'BalanceSheet',
    ]);

    // Seed Chart of Accounts
    \App\Models\ChartOfAccount::factory()->create(['account_code' => '4210', 'account_name' => 'FMR Liquid', 'account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);
    \App\Models\ChartOfAccount::factory()->create(['account_code' => '4220', 'account_name' => 'FMR Powder', 'account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);
    \App\Models\ChartOfAccount::factory()->create(['account_code' => '5262', 'account_name' => 'AMR Liquid', 'account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);
    \App\Models\ChartOfAccount::factory()->create(['account_code' => '5252', 'account_name' => 'AMR Powder', 'account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);
    \App\Models\ChartOfAccount::factory()->create(['account_code' => '1110', 'account_name' => 'Cash', 'account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);
});

test('fmr amr comparison report page loads for authenticated user', function () {
    $this->actingAs($this->user)
        ->get(route('reports.fmr-amr-comparison.index'))
        ->assertSuccessful();
});

test('fmr amr comparison report loads with date filters', function () {
    $this->actingAs($this->user)
        ->get(route('reports.fmr-amr-comparison.index', [
            'filter' => [
                'start_date' => '2025-01-01',
                'end_date' => '2025-12-31',
            ],
        ]))
        ->assertSuccessful();
});

test('fmr amr comparison report requires authentication', function () {
    $this->get(route('reports.fmr-amr-comparison.index'))
        ->assertRedirect(route('login'));
});

test('fmr amr comparison calculates difference as fmr minus amr', function () {
    $this->actingAs($this->user);

    // Get the account IDs
    $fmrLiquid = \App\Models\ChartOfAccount::where('account_code', '4210')->first();
    $fmrPowder = \App\Models\ChartOfAccount::where('account_code', '4220')->first();
    $amrLiquid = \App\Models\ChartOfAccount::where('account_code', '5262')->first();
    $amrPowder = \App\Models\ChartOfAccount::where('account_code', '5252')->first();

    expect($fmrLiquid)->not->toBeNull()
        ->and($fmrPowder)->not->toBeNull()
        ->and($amrLiquid)->not->toBeNull()
        ->and($amrPowder)->not->toBeNull();

    // Create a journal entry with all four accounts
    $journalEntry = \App\Models\JournalEntry::create([
        'entry_date' => '2025-06-15',
        'description' => 'Test FMR vs AMR entry',
        'status' => 'posted',
        'reference_no' => 'TEST-001',
        'currency_id' => $this->currency->id,
    ]);

    // Create a supplier
    $supplier = \App\Models\Supplier::factory()->create();

    // Create a GRN for this JE to satisfy the content of the report query
    \App\Models\GoodsReceiptNote::factory()->create([
        'journal_entry_id' => $journalEntry->id,
        'supplier_id' => $supplier->id,
    ]);

    // FMR accounts are income (credit increases income)
    // FMR Liquid: 1000 credit
    \App\Models\JournalEntryDetail::create([
        'journal_entry_id' => $journalEntry->id,
        'chart_of_account_id' => $fmrLiquid->id,
        'line_no' => 1,
        'debit' => 0,
        'credit' => 1000,
        'description' => 'FMR Liquid allowance',
    ]);

    // FMR Powder: 500 credit
    \App\Models\JournalEntryDetail::create([
        'journal_entry_id' => $journalEntry->id,
        'chart_of_account_id' => $fmrPowder->id,
        'line_no' => 2,
        'debit' => 0,
        'credit' => 500,
        'description' => 'FMR Powder allowance',
    ]);

    // AMR accounts are expenses (debit increases expense)
    // AMR Liquid: 700 debit
    \App\Models\JournalEntryDetail::create([
        'journal_entry_id' => $journalEntry->id,
        'chart_of_account_id' => $amrLiquid->id,
        'line_no' => 3,
        'debit' => 700,
        'credit' => 0,
        'description' => 'AMR Liquid expense',
    ]);

    // AMR Powder: 300 debit
    \App\Models\JournalEntryDetail::create([
        'journal_entry_id' => $journalEntry->id,
        'chart_of_account_id' => $amrPowder->id,
        'line_no' => 4,
        'debit' => 300,
        'credit' => 0,
        'description' => 'AMR Powder expense',
    ]);

    // Balancing entry (debit cash to balance the entry)
    $cashAccount = \App\Models\ChartOfAccount::where('account_code', '1110')->first();
    \App\Models\JournalEntryDetail::create([
        'journal_entry_id' => $journalEntry->id,
        'chart_of_account_id' => $cashAccount->id,
        'line_no' => 5,
        'debit' => 500,
        'credit' => 0,
        'description' => 'Balancing entry',
    ]);

    // Get the report
    $response = $this->get(route('reports.fmr-amr-comparison.index', [
        'filter' => [
            'start_date' => '2025-06-01',
            'end_date' => '2025-06-30',
        ],
    ]));

    $response->assertSuccessful();

    // Expected calculation:
    // FMR Total = 1000 (liquid) + 500 (powder) = 1500
    // AMR Total = 700 (liquid) + 300 (powder) = 1000
    // Difference = 1500 - 1000 = 500 (positive = net benefit)

    $reportData = $response->viewData('reportData');
    $juneData = $reportData->first();

    expect($juneData->fmr_liquid_total)->toBe(1000.0)
        ->and($juneData->fmr_powder_total)->toBe(500.0)
        ->and($juneData->amr_liquid_total)->toBe(700.0)
        ->and($juneData->amr_powder_total)->toBe(300.0)
        ->and($juneData->difference)->toBe(500.0);

    // Check grand totals
    $grandTotals = $response->viewData('grandTotals');
    expect($grandTotals->fmr_liquid_total)->toBe(1000.0)
        ->and($grandTotals->fmr_powder_total)->toBe(500.0)
        ->and($grandTotals->amr_liquid_total)->toBe(700.0)
        ->and($grandTotals->amr_powder_total)->toBe(300.0)
        ->and($grandTotals->difference)->toBe(500.0);
});

test('fmr amr comparison shows negative difference when amr exceeds fmr', function () {
    $this->actingAs($this->user);

    // Get the account IDs
    $fmrLiquid = \App\Models\ChartOfAccount::where('account_code', '4210')->first();
    $amrLiquid = \App\Models\ChartOfAccount::where('account_code', '5262')->first();

    // Create a journal entry where AMR > FMR (net cost)
    $journalEntry = \App\Models\JournalEntry::create([
        'entry_date' => '2025-07-15',
        'description' => 'Test AMR exceeds FMR',
        'status' => 'posted',
        'reference_no' => 'TEST-002',
        'currency_id' => $this->currency->id,
    ]);

    // Create a supplier
    $supplier = \App\Models\Supplier::factory()->create();

    // Create a GRN for this JE to satisfy the report requirements
    \App\Models\GoodsReceiptNote::factory()->create([
        'journal_entry_id' => $journalEntry->id,
        'supplier_id' => $supplier->id,
    ]);

    // FMR Liquid: 300 credit
    \App\Models\JournalEntryDetail::create([
        'journal_entry_id' => $journalEntry->id,
        'chart_of_account_id' => $fmrLiquid->id,
        'line_no' => 1,
        'debit' => 0,
        'credit' => 300,
        'description' => 'FMR Liquid allowance',
    ]);

    // AMR Liquid: 800 debit
    \App\Models\JournalEntryDetail::create([
        'journal_entry_id' => $journalEntry->id,
        'chart_of_account_id' => $amrLiquid->id,
        'line_no' => 2,
        'debit' => 800,
        'credit' => 0,
        'description' => 'AMR Liquid expense',
    ]);

    // Balancing entry
    $cashAccount = \App\Models\ChartOfAccount::where('account_code', '1110')->first();
    \App\Models\JournalEntryDetail::create([
        'journal_entry_id' => $journalEntry->id,
        'chart_of_account_id' => $cashAccount->id,
        'line_no' => 3,
        'debit' => 0,
        'credit' => 500,
        'description' => 'Balancing entry',
    ]);

    // Get the report
    $response = $this->get(route('reports.fmr-amr-comparison.index', [
        'filter' => [
            'start_date' => '2025-07-01',
            'end_date' => '2025-07-31',
        ],
    ]));

    $response->assertSuccessful();

    // Expected calculation:
    // FMR Total = 300
    // AMR Total = 800
    // Difference = 300 - 800 = -500 (negative = net cost)

    $reportData = $response->viewData('reportData');
    $julyData = $reportData->first();

    expect($julyData->difference)->toBe(-500.0)
        ->and($julyData->difference)->toBeLessThan(0);
});

test('fmr amr comparison report handles complex filters without binding errors', function () {
    // This test reproduces the binding mismatch error by exercising the path where
    // multiple unions and whereIn clauses are used together.
    $this->actingAs($this->user);

    // Create a supplier and some data to ensure the query builds fully
    $supplier = \App\Models\Supplier::factory()->create();

    $response = $this->get(route('reports.fmr-amr-comparison.index', [
        'filter' => [
            'source' => 'all',
            'supplier_ids' => [$supplier->id],
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ],
    ]));

    $response->assertSuccessful();
});
