<?php

use App\Models\AccountingPeriod;
use App\Models\AccountType;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\GoodsIssue;
use App\Models\Product;
use App\Models\SalesSettlement;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    config(['settlement_attachments.enabled' => true, 'settlement_attachments.disk' => 'local']);

    $this->user = User::factory()->create(['is_super_admin' => 'Yes']);

    AccountingPeriod::create([
        'name' => 'FY 2026',
        'start_date' => now()->startOfYear()->format('Y-m-d'),
        'end_date' => now()->endOfYear()->format('Y-m-d'),
        'status' => AccountingPeriod::STATUS_OPEN,
    ]);

    $currency = Currency::factory()->base()->create();
    $accountType = AccountType::create(['type_name' => 'Asset', 'report_group' => 'BalanceSheet']);
    $coa = ChartOfAccount::create([
        'account_code' => '1001',
        'account_name' => 'Bank',
        'account_type_id' => $accountType->id,
        'currency_id' => $currency->id,
        'normal_balance' => 'debit',
        'is_group' => false,
        'is_active' => true,
    ]);

    $this->bank = BankAccount::create([
        'account_name' => 'Main Account',
        'account_number' => '1234567890',
        'bank_name' => 'Bank',
        'chart_of_account_id' => $coa->id,
        'is_active' => true,
    ]);
    $this->customer = Customer::factory()->create();
    $this->goodsIssue = GoodsIssue::factory()->create([
        'status' => 'issued',
        'warehouse_id' => Warehouse::factory()->create()->id,
        'vehicle_id' => Vehicle::factory()->create()->id,
        'employee_id' => Employee::factory()->create()->id,
    ]);
    $this->product = Product::factory()->create();
});

function attachmentPayload(array $entries = [], array $files = []): array
{
    $test = test();

    return array_merge([
        'settlement_date' => now()->toDateString(),
        'goods_issue_id' => $test->goodsIssue->id,
        'items' => [[
            'product_id' => $test->product->id,
            'quantity_issued' => 10,
            'quantity_sold' => 5,
            'quantity_returned' => 5,
            'unit_cost' => 100,
            'selling_price' => 150,
            'batches' => [],
        ]],
        'cash_sales_amount' => 0, 'cheque_sales_amount' => 0, 'bank_transfer_amount' => 0,
        'credit_sales_amount' => 0, 'cash_collected' => 0, 'cheques_collected' => 0, 'expenses_claimed' => 0,
        'denom_5000' => 0, 'denom_1000' => 0, 'denom_500' => 0, 'denom_100' => 0,
        'denom_50' => 0, 'denom_20' => 0, 'denom_10' => 0, 'denom_coins' => 0,
        'attachment_files' => $files,
    ], $entries);
}

function allEntriesWithKeys(): array
{
    $test = test();

    return [
        'credit_sales' => json_encode([['customer_id' => $test->customer->id, 'sale_amount' => 100, 'attachment_key' => 'k1']]),
        'cheques' => [['cheque_number' => 'CH-1', 'amount' => 200, 'bank_name' => 'HBL', 'attachment_key' => 'k2']],
        'bank_transfers' => [['bank_account_id' => $test->bank->id, 'amount' => 300, 'attachment_key' => 'k3']],
        'bank_slips' => json_encode([['bank_account_id' => $test->bank->id, 'amount' => 400, 'attachment_key' => 'k4']]),
        'recoveries_entries' => json_encode([
            ['customer_id' => $test->customer->id, 'payment_method' => 'bank_transfer', 'bank_account_id' => $test->bank->id, 'amount' => 500, 'attachment_key' => 'k5'],
            ['customer_id' => $test->customer->id, 'payment_method' => 'cash', 'amount' => 50, 'attachment_key' => 'k6'],
        ]),
    ];
}

function allFiles(): array
{
    return [
        'k1' => UploadedFile::fake()->create('invoice original.pdf', 50, 'application/pdf'),
        'k2' => UploadedFile::fake()->image('cheque scan.jpg'),
        'k3' => UploadedFile::fake()->image('transfer.png'),
        'k4' => UploadedFile::fake()->create('slip.pdf', 50, 'application/pdf'),
        'k5' => UploadedFile::fake()->image('receipt.webp'),
        'k6' => UploadedFile::fake()->image('cash.png'),
    ];
}

it('stores optional attachments in per-settlement section folders with hashed names', function () {
    $this->actingAs($this->user)
        ->post(route('sales-settlements.store'), attachmentPayload(allEntriesWithKeys(), allFiles()))
        ->assertRedirect();

    $settlement = SalesSettlement::latest('id')->firstOrFail();
    $base = 'sales-settlement/'.$settlement->settlement_number;

    $paths = [
        'credit-sales' => $settlement->creditSales->first()->attachment,
        'cheques' => $settlement->cheques->first()->attachment,
        'bank-transfers' => $settlement->bankTransfers->first()->attachment,
        'bank-slips' => $settlement->bankSlips->first()->attachment,
        'recoveries' => $settlement->recoveries->firstWhere('payment_method', 'bank_transfer')->attachment,
    ];

    foreach ($paths as $folder => $path) {
        expect($path)->toStartWith($base.'/'.$folder.'/')
            ->and($path)->not->toContain('original')->not->toContain('scan')
            ->and(Storage::disk('local')->exists($path))->toBeTrue();
    }

    expect($settlement->recoveries->firstWhere('payment_method', 'cash')->attachment)->toBeNull();
});

it('keeps rows without attachments null and does not require files', function () {
    $this->actingAs($this->user)
        ->post(route('sales-settlements.store'), attachmentPayload([
            'cheques' => [['cheque_number' => 'CH-9', 'amount' => 10, 'bank_name' => 'HBL']],
        ]))
        ->assertRedirect();

    expect(SalesSettlement::latest('id')->first()->cheques->first()->attachment)->toBeNull();
});

it('rejects files that are not pdf or images', function () {
    $this->actingAs($this->user)
        ->postJson(route('sales-settlements.store'), attachmentPayload(
            ['cheques' => [['cheque_number' => 'CH-1', 'amount' => 200, 'bank_name' => 'HBL', 'attachment_key' => 'k2']]],
            ['k2' => UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream')],
        ))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['attachment_files.k2']);
});

it('rejects files over the size limit', function () {
    config(['settlement_attachments.max_kb' => 5120]);

    $this->actingAs($this->user)
        ->postJson(route('sales-settlements.store'), attachmentPayload(
            ['cheques' => [['cheque_number' => 'CH-1', 'amount' => 200, 'bank_name' => 'HBL', 'attachment_key' => 'k2']]],
            ['k2' => UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf')],
        ))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['attachment_files.k2']);
});

it('ignores uploads when the feature is disabled', function () {
    config(['settlement_attachments.enabled' => false]);

    $this->actingAs($this->user)
        ->post(route('sales-settlements.store'), attachmentPayload(allEntriesWithKeys(), allFiles()))
        ->assertRedirect();

    expect(SalesSettlement::latest('id')->first()->cheques->first()->attachment)->toBeNull()
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();
});

function storeSettlementWithAttachments(): SalesSettlement
{
    test()->actingAs(test()->user)
        ->post(route('sales-settlements.store'), attachmentPayload(allEntriesWithKeys(), allFiles()))
        ->assertRedirect();

    return SalesSettlement::latest('id')->firstOrFail();
}

function updatePayload(SalesSettlement $settlement, array $overrides = [], array $files = []): array
{
    $cheque = $settlement->cheques->first();
    $entries = [
        'cheques' => [['cheque_number' => 'CH-1', 'amount' => 200, 'bank_name' => 'HBL', 'attachment' => $cheque->attachment]],
        'credit_sales' => json_encode([['customer_id' => test()->customer->id, 'sale_amount' => 100, 'attachment' => $settlement->creditSales->first()->attachment]]),
    ];

    return attachmentPayload(array_merge($entries, $overrides), $files);
}

it('keeps an existing attachment on edit, and deletes it when replaced', function () {
    $settlement = storeSettlementWithAttachments();
    $oldCheque = $settlement->cheques->first()->attachment;
    $oldCredit = $settlement->creditSales->first()->attachment;

    // Keep the credit sale file; replace the cheque file with a new upload.
    $this->actingAs($this->user)
        ->put(route('sales-settlements.update', $settlement), updatePayload($settlement, [
            'cheques' => [['cheque_number' => 'CH-1', 'amount' => 200, 'bank_name' => 'HBL', 'attachment_key' => 'n1', 'attachment' => $oldCheque]],
        ], ['n1' => UploadedFile::fake()->image('new-cheque.png')]))
        ->assertRedirect();

    $settlement->refresh()->load('cheques', 'creditSales');
    $newCheque = $settlement->cheques->first()->attachment;

    expect($newCheque)->not->toBe($oldCheque)
        ->and(Storage::disk('local')->exists($newCheque))->toBeTrue()
        ->and(Storage::disk('local')->exists($oldCheque))->toBeFalse()
        ->and($settlement->creditSales->first()->attachment)->toBe($oldCredit)
        ->and(Storage::disk('local')->exists($oldCredit))->toBeTrue();
});

it('deletes the stored file when an attachment is removed on edit', function () {
    $settlement = storeSettlementWithAttachments();
    $oldCheque = $settlement->cheques->first()->attachment;

    $this->actingAs($this->user)
        ->put(route('sales-settlements.update', $settlement), updatePayload($settlement, [
            'cheques' => [['cheque_number' => 'CH-1', 'amount' => 200, 'bank_name' => 'HBL', 'attachment' => null]],
        ]))
        ->assertRedirect();

    expect($settlement->refresh()->cheques->first()->attachment)->toBeNull()
        ->and(Storage::disk('local')->exists($oldCheque))->toBeFalse();
});

it('does not let a submitted path point at another settlement file', function () {
    $settlement = storeSettlementWithAttachments();
    Storage::disk('local')->put('sales-settlement/OTHER-1/cheques/secret.pdf', 'x');

    $this->actingAs($this->user)
        ->put(route('sales-settlements.update', $settlement), updatePayload($settlement, [
            'cheques' => [['cheque_number' => 'CH-1', 'amount' => 200, 'bank_name' => 'HBL', 'attachment' => 'sales-settlement/OTHER-1/cheques/secret.pdf']],
        ]))
        ->assertRedirect();

    expect($settlement->refresh()->cheques->first()->attachment)->toBeNull()
        ->and(Storage::disk('local')->exists('sales-settlement/OTHER-1/cheques/secret.pdf'))->toBeTrue();
});

it('streams an attachment behind auth and 404s on a bad section', function () {
    $settlement = storeSettlementWithAttachments();
    $cheque = $settlement->cheques->first();

    $this->actingAs($this->user)
        ->get(route('sales-settlements.attachments.show', [$settlement, 'cheques', $cheque->id]))
        ->assertOk();

    $this->actingAs($this->user)
        ->get(route('sales-settlements.attachments.show', [$settlement, 'nope', $cheque->id]))
        ->assertNotFound();

    $middleware = Route::getRoutes()->getByName('sales-settlements.attachments.show')->gatherMiddleware();

    expect(collect($middleware)->contains(fn ($m) => str_starts_with((string) $m, 'auth')))->toBeTrue();
});

it('deletes attachment files when a draft settlement is deleted', function () {
    $settlement = storeSettlementWithAttachments();
    $path = $settlement->cheques->first()->attachment;

    $this->actingAs($this->user)->delete(route('sales-settlements.destroy', $settlement))->assertRedirect();

    expect(Storage::disk('local')->exists($path))->toBeFalse();
});
