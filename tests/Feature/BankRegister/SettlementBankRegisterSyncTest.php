<?php

use App\Models\AccountingPeriod;
use App\Models\AccountType;
use App\Models\BankAccount;
use App\Models\BankStatementEntry;
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
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    config(['settlement_attachments.enabled' => true, 'settlement_attachments.disk' => 'local', 'bank.attachments.disk' => 'local']);

    $this->user = User::factory()->create(['is_super_admin' => 'Yes']);

    AccountingPeriod::create([
        'name' => 'FY',
        'start_date' => now()->startOfYear()->format('Y-m-d'),
        'end_date' => now()->endOfYear()->format('Y-m-d'),
        'status' => AccountingPeriod::STATUS_OPEN,
    ]);

    $currency = Currency::factory()->base()->create();
    $accountType = AccountType::create(['type_name' => 'Asset', 'report_group' => 'BalanceSheet']);
    $coa = ChartOfAccount::create([
        'account_code' => '1001', 'account_name' => 'Bank', 'account_type_id' => $accountType->id,
        'currency_id' => $currency->id, 'normal_balance' => 'debit', 'is_group' => false, 'is_active' => true,
    ]);

    $this->bank = BankAccount::create([
        'account_name' => 'Main Collection', 'account_number' => '0012345', 'bank_name' => 'HBL',
        'chart_of_account_id' => $coa->id, 'is_active' => true,
    ]);
    $this->customer = Customer::factory()->create(['customer_name' => 'Al Madina Store']);
    $this->salesman = Employee::factory()->create(['name' => 'Imran Khan']);
    $this->goodsIssue = GoodsIssue::factory()->create([
        'status' => 'issued',
        'warehouse_id' => Warehouse::factory()->create()->id,
        'vehicle_id' => Vehicle::factory()->create()->id,
        'employee_id' => $this->salesman->id,
    ]);
    $this->product = Product::factory()->create();

    $this->payload = fn (array $entries = [], array $files = []): array => array_merge([
        'settlement_date' => now()->toDateString(),
        'goods_issue_id' => $this->goodsIssue->id,
        'items' => [[
            'product_id' => $this->product->id, 'quantity_issued' => 10, 'quantity_sold' => 5, 'quantity_returned' => 5,
            'unit_cost' => 100, 'selling_price' => 150, 'batches' => [],
        ]],
        'cash_sales_amount' => 0, 'cheque_sales_amount' => 0, 'bank_transfer_amount' => 0,
        'credit_sales_amount' => 0, 'cash_collected' => 0, 'cheques_collected' => 0, 'expenses_claimed' => 0,
        'denom_5000' => 0, 'denom_1000' => 0, 'denom_500' => 0, 'denom_100' => 0,
        'denom_50' => 0, 'denom_20' => 0, 'denom_10' => 0, 'denom_coins' => 0,
        'attachment_files' => $files,
    ], $entries);

    $this->bankMoney = fn (): array => [
        'bank_transfers' => [['bank_account_id' => $this->bank->id, 'customer_id' => $this->customer->id, 'amount' => 300, 'reference_number' => 'TRX-77', 'attachment_key' => 'k3']],
        'bank_slips' => json_encode([['bank_account_id' => $this->bank->id, 'amount' => 400, 'reference_number' => 'SLIP-9', 'attachment_key' => 'k4']]),
        'recoveries_entries' => json_encode([
            ['customer_id' => $this->customer->id, 'payment_method' => 'bank_transfer', 'bank_account_id' => $this->bank->id, 'amount' => 500, 'attachment_key' => 'k5'],
            ['customer_id' => $this->customer->id, 'payment_method' => 'cash', 'amount' => 50],
        ]),
    ];

    $this->files = fn (): array => [
        'k3' => UploadedFile::fake()->image('transfer.png'),
        'k4' => UploadedFile::fake()->create('slip.pdf', 50, 'application/pdf'),
        'k5' => UploadedFile::fake()->image('receipt.jpg'),
    ];

    $this->storeSettlement = function (): SalesSettlement {
        $this->actingAs($this->user)
            ->post(route('sales-settlements.store'), ($this->payload)(($this->bankMoney)(), ($this->files)()))
            ->assertRedirect();

        return SalesSettlement::latest('id')->firstOrFail();
    };
});

it('raises one unposted bank register line for each online recovery, bank transfer and bank slip', function () {
    $settlement = ($this->storeSettlement)();

    $entries = BankStatementEntry::where('sales_settlement_id', $settlement->id)->get()->keyBy('settlement_section');

    expect($settlement->status)->toBe('draft')
        ->and($entries)->toHaveCount(3)
        ->and((float) $entries['recoveries']->credit)->toBe(500.0)
        ->and((float) $entries['bank_transfers']->credit)->toBe(300.0)
        ->and((float) $entries['bank_slips']->credit)->toBe(400.0);

    foreach ($entries as $entry) {
        expect($entry->bank_account_id)->toBe($this->bank->id)
            ->and((float) $entry->debit)->toBe(0.0)
            ->and($entry->journal_entry_id)->toBeNull()
            ->and($entry->isLocked())->toBeFalse()
            ->and($entry->source_statement)->toBe("Sales Settlement {$settlement->settlement_number}")
            ->and($entry->posting_date->toDateString())->toBe(now()->toDateString());
    }
});

it('writes a narration naming the party, bank account, reference, settlement and salesman', function () {
    $settlement = ($this->storeSettlement)();
    $entries = BankStatementEntry::where('sales_settlement_id', $settlement->id)->get()->keyBy('settlement_section');

    expect($entries['bank_transfers']->narration)
        ->toContain('Online bank transfer received from Al Madina Store')
        ->toContain('Credited to HBL - Main Collection (A/C No. 0012345)')
        ->toContain('Transaction Ref. TRX-77')
        ->toContain("Sales Settlement {$settlement->settlement_number} dated ".now()->format('d-M-Y'))
        ->toContain("Goods Issue {$this->goodsIssue->issue_number}")
        ->toContain('Salesman Imran Khan')
        ->and($entries['bank_transfers']->reference)->toBe('TRX-77')
        ->and($entries['bank_transfers']->party_name_in_statement)->toBe('Al Madina Store');

    $recoveryNumber = $settlement->recoveries->firstWhere('payment_method', 'bank_transfer')->recovery_number;
    expect($entries['recoveries']->narration)
        ->toContain('Online recovery of credit balance received from Al Madina Store')
        ->toContain("Recovery No. {$recoveryNumber}")
        ->toContain('Credited to HBL - Main Collection (A/C No. 0012345)')
        ->and($entries['recoveries']->reference)->toBe($recoveryNumber);

    expect($entries['bank_slips']->narration)
        ->toContain('Cash deposited into bank by salesman Imran Khan')
        ->toContain('Deposit Slip No. SLIP-9')
        ->toContain('Credited to HBL - Main Collection (A/C No. 0012345)')
        ->and($entries['bank_slips']->party_name_in_statement)->toBe('Imran Khan');
});

it('raises no register line for a cash recovery', function () {
    $this->actingAs($this->user)
        ->post(route('sales-settlements.store'), ($this->payload)([
            'recoveries_entries' => json_encode([['customer_id' => $this->customer->id, 'payment_method' => 'cash', 'amount' => 50]]),
        ]))
        ->assertRedirect();

    expect(BankStatementEntry::count())->toBe(0);
});

it('links each settlement proof to its register line and opens it from the register', function () {
    $settlement = ($this->storeSettlement)();
    $entry = BankStatementEntry::where('sales_settlement_id', $settlement->id)->where('settlement_section', 'bank_slips')->firstOrFail();
    $slipPath = $settlement->bankSlips->first()->attachment;

    expect(BankStatementEntry::where('sales_settlement_id', $settlement->id)->withCount('attachments')->pluck('attachments_count')->all())->toBe([1, 1, 1])
        ->and($entry->attachments->first()->file_path)->toBe($slipPath)
        ->and($entry->attachments->first()->label)->toBe('slip');

    $this->actingAs($this->user)
        ->get(route('bank-attachments.download', ['attachment' => $entry->attachments->first(), 'inline' => 1]))
        ->assertOk();
});

it('keeps the settlement file when its link is removed from the register', function () {
    $settlement = ($this->storeSettlement)();
    $entry = BankStatementEntry::where('sales_settlement_id', $settlement->id)->where('settlement_section', 'bank_transfers')->firstOrFail();
    $attachment = $entry->attachments->first();

    $this->actingAs($this->user)->delete(route('bank-attachments.destroy', $attachment))->assertRedirect();

    expect(Storage::disk('local')->exists($attachment->file_path))->toBeTrue()
        ->and($entry->attachments()->count())->toBe(0);
});

it('follows an edit: unchanged lines keep their entry, a changed amount replaces it, a removed line is withdrawn', function () {
    $settlement = ($this->storeSettlement)();
    $before = BankStatementEntry::where('sales_settlement_id', $settlement->id)->get()->keyBy('settlement_section');

    $this->actingAs($this->user)
        ->put(route('sales-settlements.update', $settlement), ($this->payload)([
            'bank_transfers' => [['bank_account_id' => $this->bank->id, 'customer_id' => $this->customer->id, 'amount' => 300, 'reference_number' => 'TRX-77', 'attachment' => $settlement->bankTransfers->first()->attachment]],
            'bank_slips' => json_encode([['bank_account_id' => $this->bank->id, 'amount' => 450, 'reference_number' => 'SLIP-9']]),
        ]))
        ->assertRedirect();

    $after = BankStatementEntry::where('sales_settlement_id', $settlement->id)->get()->keyBy('settlement_section');

    expect($after)->toHaveCount(2)
        ->and($after['bank_transfers']->id)->toBe($before['bank_transfers']->id)
        ->and($after['bank_transfers']->settlement_row_id)->toBe($settlement->bankTransfers()->first()->id)
        ->and($after['bank_transfers']->attachments()->count())->toBe(1)
        ->and($after['bank_slips']->id)->not->toBe($before['bank_slips']->id)
        ->and((float) $after['bank_slips']->credit)->toBe(450.0)
        ->and(BankStatementEntry::withTrashed()->find($before['bank_slips']->id)->trashed())->toBeTrue()
        ->and(BankStatementEntry::withTrashed()->find($before['recoveries']->id)->trashed())->toBeTrue();
});

it('keeps a register line that was already posted and flags it for review when its settlement line is removed', function () {
    $settlement = ($this->storeSettlement)();
    $posted = BankStatementEntry::where('sales_settlement_id', $settlement->id)->where('settlement_section', 'bank_slips')->firstOrFail();
    $posted->forceFill(['status' => BankStatementEntry::STATUS_POSTED])->save();

    $this->actingAs($this->user)
        ->put(route('sales-settlements.update', $settlement), ($this->payload)())
        ->assertRedirect();

    expect($posted->refresh())
        ->trashed()->toBeFalse()
        ->needs_review->toBeTrue()
        ->status->toBe(BankStatementEntry::STATUS_POSTED)
        ->and(BankStatementEntry::where('sales_settlement_id', $settlement->id)->count())->toBe(1);
});

it('withdraws the register lines when the draft settlement is deleted', function () {
    $settlement = ($this->storeSettlement)();

    $this->actingAs($this->user)->delete(route('sales-settlements.destroy', $settlement))->assertRedirect();

    expect(BankStatementEntry::count())->toBe(0)
        ->and(BankStatementEntry::onlyTrashed()->count())->toBe(3);
});

it('shows the source settlement on the register entry', function () {
    $settlement = ($this->storeSettlement)();
    $entry = BankStatementEntry::where('sales_settlement_id', $settlement->id)->firstOrFail();

    $this->actingAs($this->user)->get(route('bank-statement-entries.show', $entry))
        ->assertOk()
        ->assertSee("Sales Settlement {$settlement->settlement_number}")
        ->assertSee(route('sales-settlements.show', $settlement), false);
});
