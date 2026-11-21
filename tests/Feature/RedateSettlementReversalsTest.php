<?php

use App\Models\AccountingPeriod;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerEmployeeAccount;
use App\Models\CustomerEmployeeAccountTransaction;
use App\Models\InventoryLedgerEntry;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\SalesSettlement;

/**
 * Reverts used to book their reversals on the day of the revert. The command moves the reversals
 * already booked that way onto the date of the posting they undo, and leaves the re-posting alone.
 */
beforeEach(function () {
    AccountingPeriod::create(['name' => 'November 2026', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'status' => 'open']);
    $currency = Currency::firstOrCreate(
        ['currency_code' => 'PKR'],
        ['currency_name' => 'Pakistani Rupee', 'currency_symbol' => 'Rs', 'is_base_currency' => true, 'fx_rate_to_base' => 1]
    );

    $this->settlement = SalesSettlement::factory()->create(['status' => 'posted', 'settlement_date' => '2026-11-19']);
    $this->product = Product::factory()->create();
    $account = CustomerEmployeeAccount::create([
        'account_number' => 'CA-REDATE',
        'customer_id' => Customer::factory()->create()->id,
        'employee_id' => $this->settlement->employee_id,
        'opened_date' => '2026-11-01',
        'status' => 'active',
    ]);

    $journal = fn (string $reference, string $date, ?int $reverses = null) => JournalEntry::forceCreate([
        'currency_id' => $currency->id,
        'entry_date' => $date,
        'reference' => $reference,
        'description' => $reference,
        'status' => 'posted',
        'reverses_entry_id' => $reverses,
    ]);
    $customerLine = fn (float $debit, float $credit, string $date, ?int $reverses = null) => CustomerEmployeeAccountTransaction::create([
        'customer_employee_account_id' => $account->id,
        'transaction_date' => $date,
        'transaction_type' => $reverses ? 'adjustment' : 'credit_sale',
        'sales_settlement_id' => $this->settlement->id,
        'reverses_transaction_id' => $reverses,
        'description' => 'Line',
        'debit' => $debit,
        'credit' => $credit,
    ]);
    $stockLine = fn (string $type, float $debit, float $credit, string $date) => InventoryLedgerEntry::create([
        'date' => $date,
        'transaction_type' => $type,
        'product_id' => $this->product->id,
        'vehicle_id' => $this->settlement->vehicle_id,
        'sales_settlement_id' => $this->settlement->id,
        'debit_qty' => $debit,
        'credit_qty' => $credit,
        'unit_cost' => 100,
        'running_balance' => 0,
    ]);

    $this->travelTo('2026-11-21 09:13:00');
    $original = $journal($this->settlement->settlement_number, '2026-11-19');
    $firstSale = $customerLine(95220, 0, '2026-11-19');
    $stockLine('sale', 0, 49, '2026-11-19');

    $this->travelTo('2026-11-21 09:24:55');
    $this->reversal = $journal('REV-'.$this->settlement->settlement_number, '2026-11-21', $original->id);
    $this->customerReversal = $customerLine(0, 95220, '2026-11-21', $firstSale->id);
    $this->stockReversal = $stockLine('adjustment', 49, 0, '2026-11-21');

    // Posted again 35 seconds later, on the settlement's own date.
    $this->travelTo('2026-11-21 09:25:30');
    $journal($this->settlement->settlement_number, '2026-11-19');
    $customerLine(95220, 0, '2026-11-19');
    $this->repostedSale = $stockLine('sale', 0, 49, '2026-11-19');
});

it('changes nothing on a dry run', function () {
    $this->artisan('sales-settlement:redate-reversals', ['--dry-run' => true])->assertSuccessful();

    expect($this->reversal->fresh()->entry_date->toDateString())->toBe('2026-11-21')
        ->and($this->customerReversal->fresh()->transaction_date->toDateString())->toBe('2026-11-21');
});

it('moves the reversals of a revert onto the date of the posting they undo', function () {
    $this->artisan('sales-settlement:redate-reversals', ['settlement' => $this->settlement->settlement_number])->assertSuccessful();

    expect($this->reversal->fresh()->entry_date->toDateString())->toBe('2026-11-19')
        ->and($this->customerReversal->fresh()->transaction_date->toDateString())->toBe('2026-11-19')
        ->and($this->stockReversal->fresh()->date->toDateString())->toBe('2026-11-19')
        ->and($this->repostedSale->fresh()->date->toDateString())->toBe('2026-11-19');

    // Run again: nothing is left to move.
    $this->artisan('sales-settlement:redate-reversals')
        ->expectsOutputToContain('nothing to do')
        ->assertSuccessful();
});
