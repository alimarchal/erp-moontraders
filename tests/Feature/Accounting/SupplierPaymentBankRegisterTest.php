<?php

use App\Enums\DocumentType;
use App\Models\AccountType;
use App\Models\BankAccount;
use App\Models\BankStatementEntry;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\LedgerRegister;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('report-audit-ledger-register');
    Permission::findOrCreate('report-audit-ledger-register-post');

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['report-audit-ledger-register', 'report-audit-ledger-register-post']);
    $this->actingAs($this->user);

    // The ledger register posts against cost center 1.
    if (! CostCenter::whereKey(1)->exists()) {
        DB::table('cost_centers')->insert(['id' => 1, 'code' => 'CC001', 'name' => 'Administration', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval('cost_centers_id_seq', GREATEST((SELECT MAX(id) FROM cost_centers), (SELECT last_value FROM cost_centers_id_seq)))");
        }
    }

    seedGrnPostingAccounts();

    $currencyId = DB::table('currencies')->where('is_base_currency', true)->value('id');
    $accounts = [
        '1112' => ['Pending Claims Debtors', 'Assets', 'debit'],
        '1171' => ['HBL Main Account', 'Assets', 'debit'],
        '1172' => ['Meezan Account', 'Assets', 'debit'],
        '4240' => ['ZA 0.5% Incentive Income', 'Income', 'credit'],
        '5210' => ['Administrative Expenses', 'Expenses', 'debit'],
    ];
    foreach ($accounts as $code => [$name, $typeName, $normalBalance]) {
        ChartOfAccount::firstOrCreate(['account_code' => $code], [
            'account_type_id' => AccountType::where('type_name', $typeName)->value('id')
                ?? AccountType::create(['type_name' => $typeName, 'report_group' => 'BalanceSheet', 'category' => 'Asset'])->id,
            'currency_id' => $currencyId,
            'account_name' => $name,
            'normal_balance' => $normalBalance,
            'is_active' => true,
        ]);
    }

    $this->hbl = BankAccount::factory()->create([
        'account_name' => 'HBL Main Account',
        'chart_of_account_id' => ChartOfAccount::where('account_code', '1171')->value('id'),
        'is_active' => true,
    ]);
    $this->meezan = BankAccount::factory()->create([
        'account_name' => 'Meezan Account',
        'chart_of_account_id' => ChartOfAccount::where('account_code', '1172')->value('id'),
        'is_active' => true,
    ]);

    $this->supplier = Supplier::factory()->create(['disabled' => false]);
});

function supplierPayment(object $test, float $amount): LedgerRegister
{
    return LedgerRegister::factory()->create([
        'supplier_id' => $test->supplier->id,
        'transaction_date' => now()->toDateString(),
        'document_type' => DocumentType::Dz,
        'document_number' => 'PAY-'.fake()->unique()->numerify('#####'),
        'online_amount' => $amount,
        'invoice_amount' => 0,
        'expenses_amount' => 0,
        'za_point_five_percent_amount' => 0,
        'claim_adjust_amount' => 0,
    ]);
}

function bankGlBalance(string $code): float
{
    return round((float) DB::table('journal_entry_details as line')
        ->join('journal_entries as je', 'je.id', '=', 'line.journal_entry_id')
        ->join('chart_of_accounts as account', 'account.id', '=', 'line.chart_of_account_id')
        ->where('je.status', 'posted')
        ->where('account.account_code', $code)
        ->selectRaw('COALESCE(SUM(line.debit - line.credit), 0) as balance')
        ->value('balance'), 2);
}

test('an online payment is paid from the chosen bank and recorded in the bank register', function () {
    $entry = supplierPayment($this, 5000000);

    $this->post(route('reports.supplier-ledger-register.post', $entry), ['bank_account_id' => $this->meezan->id])
        ->assertSessionHas('success');

    $entry->refresh();
    expect($entry->isPosted())->toBeTrue()
        ->and($entry->bank_account_id)->toBe($this->meezan->id)
        ->and(bankGlBalance('1172'))->toBe(-5000000.0)
        ->and(bankGlBalance('1171'))->toBe(0.0)
        ->and(bankGlBalance('2111'))->toBe(5000000.0);

    $line = BankStatementEntry::where('supplier_ledger_register_id', $entry->id)->sole();
    expect($line->bank_account_id)->toBe($this->meezan->id)
        ->and((float) $line->debit)->toBe(5000000.0)
        ->and((float) $line->credit)->toBe(0.0)
        ->and($line->status)->toBe(BankStatementEntry::STATUS_POSTED)
        ->and($line->journal_entry_id)->toBe($entry->journal_entry_id)
        ->and($line->journal_entry_detail_id)->not->toBeNull()
        ->and($line->company_id)->toBe($this->supplier->id)
        ->and($line->bookingLabel())->toBe('Booked by supplier ledger');
});

test('an online payment cannot be posted without choosing an active bank account', function () {
    $entry = supplierPayment($this, 1000);
    $this->meezan->update(['is_active' => false]);

    $this->post(route('reports.supplier-ledger-register.post', $entry))
        ->assertSessionHasErrors('bank_account_id');

    $this->post(route('reports.supplier-ledger-register.post', $entry), ['bank_account_id' => $this->meezan->id])
        ->assertSessionHasErrors('bank_account_id');

    expect($entry->fresh()->isPosted())->toBeFalse()
        ->and(BankStatementEntry::where('supplier_ledger_register_id', $entry->id)->exists())->toBeFalse();
});

test('an entry without an online amount posts without a bank account and adds no bank register line', function () {
    $entry = supplierPayment($this, 0);
    $entry->update(['document_type' => DocumentType::Dg, 'claim_adjust_amount' => 2500]);

    $this->post(route('reports.supplier-ledger-register.post', $entry))
        ->assertSessionHas('success');

    expect($entry->fresh()->isPosted())->toBeTrue()
        ->and($entry->fresh()->bank_account_id)->toBeNull()
        ->and(BankStatementEntry::where('supplier_ledger_register_id', $entry->id)->exists())->toBeFalse();
});

test('a supplier payment line in the bank register can only be corrected from the ledger', function () {
    $entry = supplierPayment($this, 7500);
    $this->post(route('reports.supplier-ledger-register.post', $entry), ['bank_account_id' => $this->hbl->id]);

    $line = BankStatementEntry::where('supplier_ledger_register_id', $entry->id)->sole();

    expect(fn () => app(\App\Services\BankRegister\BankEntryPostingService::class)->reverse($line, $this->user, 'test'))
        ->toThrow(\App\Services\BankRegister\BankRegisterException::class);
});
