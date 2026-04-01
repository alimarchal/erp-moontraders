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
use App\Models\SalesSettlementCheque;
use App\Models\SalesSettlementItem;
use App\Models\StockMovement;
use App\Models\Uom;
use App\Models\User;
use App\Models\VanStockBalance;
use App\Services\AccountingService;
use App\Services\InventoryLedgerService;
use App\Services\SalesSettlementRevertService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ──────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────

function makeRevertUser(): User
{
    return User::factory()->create(['is_super_admin' => 'Yes']);
}

function makePostedSettlement(array $overrides = []): SalesSettlement
{
    // Ensure an open accounting period exists for the journal entry DB trigger
    AccountingPeriod::firstOrCreate(
        ['name' => now()->format('F Y')],
        [
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'status' => 'open',
        ]
    );

    $currency = Currency::firstOrCreate(
        ['currency_code' => 'PKR'],
        ['currency_name' => 'Pakistani Rupee', 'currency_symbol' => 'Rs', 'is_base_currency' => true, 'fx_rate_to_base' => 1]
    );

    $journalEntry = JournalEntry::create([
        'currency_id' => $currency->id,
        'entry_date' => now()->toDateString(),
        'reference' => 'SS-TEST-'.fake()->unique()->numerify('###'),
        'description' => 'Test settlement journal',
        'status' => 'posted',
    ]);

    return SalesSettlement::factory()->create(array_merge([
        'status' => 'posted',
        'posted_at' => now(),
        'journal_entry_id' => $journalEntry->id,
        'total_sales_amount' => 1500,
        'cash_sales_amount' => 1500,
        'gross_profit' => 500,
        'total_cogs' => 1000,
    ], $overrides));
}

// ──────────────────────────────────────────────────────
// Controller: Access Control
// ──────────────────────────────────────────────────────

it('redirects unauthenticated users to login on revert', function () {
    $settlement = makePostedSettlement();

    $this->post(route('sales-settlements.revert', $settlement->id), ['password' => 'secret'])
        ->assertRedirect(route('login'));
});

it('returns 403 when user lacks sales-settlement-revert permission', function () {
    $user = User::factory()->create(['is_super_admin' => 'No']);
    $settlement = makePostedSettlement();

    $this->actingAs($user)
        ->post(route('sales-settlements.revert', $settlement->id), ['password' => 'secret'])
        ->assertForbidden();
});

it('returns error when password is wrong', function () {
    $user = makeRevertUser();
    $settlement = makePostedSettlement(['created_by' => $user->id]);

    $this->actingAs($user)
        ->post(route('sales-settlements.revert', $settlement->id), ['password' => 'wrong-password'])
        ->assertRedirect()
        ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'Invalid password'));
});

it('returns validation error when password field is missing', function () {
    $user = makeRevertUser();
    $settlement = makePostedSettlement(['created_by' => $user->id]);

    $this->actingAs($user)
        ->post(route('sales-settlements.revert', $settlement->id), [])
        ->assertSessionHasErrors('password');
});

// ──────────────────────────────────────────────────────
// Service: Pre-checks
// ──────────────────────────────────────────────────────

it('blocks revert of a draft settlement', function () {
    $settlement = SalesSettlement::factory()->create(['status' => 'draft']);

    $service = app(SalesSettlementRevertService::class);
    $result = $service->performPreChecks($settlement);

    expect($result['ok'])->toBeFalse()
        ->and($result['message'])->toContain('posted');
});

it('blocks revert when journal_entry_id is missing', function () {
    $settlement = SalesSettlement::factory()->create([
        'status' => 'posted',
        'journal_entry_id' => null,
    ]);

    $service = app(SalesSettlementRevertService::class);
    $result = $service->performPreChecks($settlement);

    expect($result['ok'])->toBeFalse()
        ->and($result['message'])->toContain('GL journal entry');
});

it('blocks revert when a cleared cheque exists', function () {
    $settlement = makePostedSettlement();
    SalesSettlementCheque::create([
        'sales_settlement_id' => $settlement->id,
        'cheque_number' => 'CHQ-0001',
        'amount' => 500,
        'bank_name' => 'HBL',
        'cheque_date' => now()->toDateString(),
        'status' => 'cleared',
    ]);

    $service = app(SalesSettlementRevertService::class);
    $result = $service->performPreChecks($settlement->fresh());

    expect($result['ok'])->toBeFalse()
        ->and($result['message'])->toContain('cleared');
});

it('allows revert when cheque is pending', function () {
    $settlement = makePostedSettlement();
    SalesSettlementCheque::create([
        'sales_settlement_id' => $settlement->id,
        'cheque_number' => 'CHQ-0002',
        'amount' => 500,
        'bank_name' => 'HBL',
        'cheque_date' => now()->toDateString(),
        'status' => 'pending',
    ]);

    $service = app(SalesSettlementRevertService::class);
    $result = $service->performPreChecks($settlement->fresh());

    expect($result['ok'])->toBeTrue();
});

it('allows revert when cheque is bounced', function () {
    $settlement = makePostedSettlement();
    SalesSettlementCheque::create([
        'sales_settlement_id' => $settlement->id,
        'cheque_number' => 'CHQ-0003',
        'amount' => 500,
        'bank_name' => 'MCB',
        'cheque_date' => now()->toDateString(),
        'status' => 'bounced',
    ]);

    $service = app(SalesSettlementRevertService::class);
    $result = $service->performPreChecks($settlement->fresh());

    expect($result['ok'])->toBeTrue();
});

// ──────────────────────────────────────────────────────
// Service: Revert operations
// ──────────────────────────────────────────────────────

it('resets settlement to draft and clears financial fields after revert', function () {
    $user = makeRevertUser();
    $settlement = makePostedSettlement(['created_by' => $user->id]);

    $this->actingAs($user);

    $mockAccounting = Mockery::mock(AccountingService::class);
    $mockAccounting->shouldReceive('reverseJournalEntry')
        ->once()
        ->andReturn(['success' => true, 'message' => 'Reversed']);

    $service = new SalesSettlementRevertService(
        $mockAccounting,
        app(InventoryLedgerService::class),
    );

    $result = $service->revert($settlement);

    expect($result['success'])->toBeTrue();

    $fresh = $settlement->fresh();
    expect($fresh->status)->toBe('draft')
        ->and($fresh->posted_at)->toBeNull()
        ->and($fresh->journal_entry_id)->toBeNull()
        ->and($fresh->gross_profit)->toBeNull()
        ->and($fresh->total_cogs)->toBeNull()
        ->and((float) $fresh->cash_sales_amount)->toBe(0.0)
        ->and($fresh->reverted_at)->not->toBeNull()
        ->and($fresh->reverted_by)->toBe($user->id);
});

it('creates reversing CustomerEmployeeAccountTransaction entries on revert', function () {
    $user = makeRevertUser();
    $settlement = makePostedSettlement(['created_by' => $user->id]);

    $customerAccount = CustomerEmployeeAccount::create([
        'account_number' => 'CA-'.fake()->unique()->numerify('####'),
        'customer_id' => Customer::factory()->create()->id,
        'employee_id' => $settlement->employee_id,
        'opened_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    CustomerEmployeeAccountTransaction::create([
        'customer_employee_account_id' => $customerAccount->id,
        'transaction_date' => now()->toDateString(),
        'transaction_type' => 'credit_sale',
        'reference_number' => 'INV-0001',
        'sales_settlement_id' => $settlement->id,
        'description' => 'Credit sale',
        'debit' => 2500,
        'credit' => 0,
        'payment_method' => 'credit',
    ]);

    $this->actingAs($user);

    $mockAccounting = Mockery::mock(AccountingService::class);
    $mockAccounting->shouldReceive('reverseJournalEntry')
        ->once()
        ->andReturn(['success' => true, 'message' => 'Reversed']);

    $service = new SalesSettlementRevertService(
        $mockAccounting,
        app(InventoryLedgerService::class),
    );

    $result = $service->revert($settlement);
    expect($result['success'])->toBeTrue($result['message'] ?? '');

    $reversalTxn = CustomerEmployeeAccountTransaction::where('sales_settlement_id', $settlement->id)
        ->where('transaction_type', 'adjustment')
        ->first();

    expect($reversalTxn)->not->toBeNull()
        ->and((float) $reversalTxn->debit)->toBe(0.0)
        ->and((float) $reversalTxn->credit)->toBe(2500.0)
        ->and($reversalTxn->reference_number)->toStartWith('REV-');
});

it('restores van stock balance after revert', function () {
    $user = makeRevertUser();
    $settlement = makePostedSettlement(['created_by' => $user->id]);

    $product = Product::factory()->create();

    SalesSettlementItem::create([
        'sales_settlement_id' => $settlement->id,
        'product_id' => $product->id,
        'quantity_issued' => 13,
        'quantity_sold' => 10,
        'quantity_returned' => 2,
        'quantity_shortage' => 1,
        'unit_selling_price' => 150,
        'total_sales_value' => 1500,
        'unit_cost' => 100,
        'total_cogs' => 1000,
    ]);

    // Van stock after posting would be 0
    VanStockBalance::create([
        'vehicle_id' => $settlement->vehicle_id,
        'product_id' => $product->id,
        'quantity_on_hand' => 0,
        'average_cost' => 100,
    ]);

    $this->actingAs($user);

    $mockAccounting = Mockery::mock(AccountingService::class);
    $mockAccounting->shouldReceive('reverseJournalEntry')
        ->once()
        ->andReturn(['success' => true, 'message' => 'Reversed']);

    $service = new SalesSettlementRevertService(
        $mockAccounting,
        app(InventoryLedgerService::class),
    );

    $service->revert($settlement);

    $vanStock = VanStockBalance::where('vehicle_id', $settlement->vehicle_id)
        ->where('product_id', $product->id)
        ->first();

    // 10 sold + 2 returned + 1 shortage = 13 restored
    expect((float) $vanStock->quantity_on_hand)->toBe(13.0);
});

it('creates reversing stock movement entries after revert', function () {
    $user = makeRevertUser();
    $settlement = makePostedSettlement(['created_by' => $user->id]);

    $product = Product::factory()->create();
    SalesSettlementItem::create([
        'sales_settlement_id' => $settlement->id,
        'product_id' => $product->id,
        'quantity_issued' => 10,
        'quantity_sold' => 10,
        'quantity_returned' => 0,
        'quantity_shortage' => 0,
        'unit_selling_price' => 150,
        'total_sales_value' => 1500,
        'unit_cost' => 100,
        'total_cogs' => 1000,
    ]);

    $uom = Uom::factory()->create();
    StockMovement::create([
        'movement_type' => 'sale',
        'reference_type' => 'App\\Models\\SalesSettlement',
        'reference_id' => $settlement->id,
        'movement_date' => now()->toDateString(),
        'product_id' => $product->id,
        'vehicle_id' => $settlement->vehicle_id,
        'uom_id' => $uom->id,
        'quantity' => -10,
        'unit_cost' => 100,
        'total_value' => 1000,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user);

    $mockAccounting = Mockery::mock(AccountingService::class);
    $mockAccounting->shouldReceive('reverseJournalEntry')
        ->once()
        ->andReturn(['success' => true, 'message' => 'Reversed']);

    $service = new SalesSettlementRevertService(
        $mockAccounting,
        app(InventoryLedgerService::class),
    );

    $service->revert($settlement);

    $reversalMovement = StockMovement::where('reference_type', 'App\\Models\\SalesSettlement')
        ->where('reference_id', $settlement->id)
        ->where('movement_type', 'adjustment')
        ->first();

    expect($reversalMovement)->not->toBeNull()
        ->and((float) $reversalMovement->quantity)->toBe(10.0); // negated from -10
});

it('returns error when GL reversal fails', function () {
    $user = makeRevertUser();
    $settlement = makePostedSettlement(['created_by' => $user->id]);

    $this->actingAs($user);

    $mockAccounting = Mockery::mock(AccountingService::class);
    $mockAccounting->shouldReceive('reverseJournalEntry')
        ->once()
        ->andReturn(['success' => false, 'message' => 'GL period closed']);

    $service = new SalesSettlementRevertService(
        $mockAccounting,
        app(InventoryLedgerService::class),
    );

    $result = $service->revert($settlement);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('GL period closed');

    // Settlement must NOT have been reset (transaction rolled back)
    expect($settlement->fresh()->status)->toBe('posted');
});

it('transaction rolls back on inventory ledger failure', function () {
    $user = makeRevertUser();
    $settlement = makePostedSettlement(['created_by' => $user->id]);

    $product = Product::factory()->create();
    SalesSettlementItem::create([
        'sales_settlement_id' => $settlement->id,
        'product_id' => $product->id,
        'quantity_issued' => 10,
        'quantity_sold' => 10,
        'quantity_returned' => 0,
        'quantity_shortage' => 0,
        'unit_selling_price' => 150,
        'total_sales_value' => 1500,
        'unit_cost' => 100,
        'total_cogs' => 1000,
    ]);

    VanStockBalance::create([
        'vehicle_id' => $settlement->vehicle_id,
        'product_id' => $product->id,
        'quantity_on_hand' => 0,
        'average_cost' => 100,
    ]);

    // Create an InventoryLedgerEntry so the mock's recordAdjustment gets called
    InventoryLedgerEntry::create([
        'date' => now()->toDateString(),
        'transaction_type' => 'sale',
        'product_id' => $product->id,
        'vehicle_id' => $settlement->vehicle_id,
        'sales_settlement_id' => $settlement->id,
        'debit_qty' => 0,
        'credit_qty' => 10,
        'unit_cost' => 100,
        'running_balance' => 0,
    ]);

    $this->actingAs($user);

    // GL succeeds but inventory ledger throws
    $mockAccounting = Mockery::mock(AccountingService::class);
    $mockAccounting->shouldReceive('reverseJournalEntry')
        ->once()
        ->andReturn(['success' => true, 'message' => 'Reversed']);

    $mockInventory = Mockery::mock(InventoryLedgerService::class);
    $mockInventory->shouldReceive('recordAdjustment')
        ->andThrow(new Exception('Inventory ledger failure'));

    $service = new SalesSettlementRevertService($mockAccounting, $mockInventory);
    $result = $service->revert($settlement);

    expect($result['success'])->toBeFalse();

    // VanStockBalance should NOT have changed (transaction rolled back)
    $vanStock = VanStockBalance::where('vehicle_id', $settlement->vehicle_id)
        ->where('product_id', $product->id)->first();
    expect((float) $vanStock->quantity_on_hand)->toBe(0.0);

    // Settlement should still be posted
    expect($settlement->fresh()->status)->toBe('posted');
});
