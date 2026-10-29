<?php

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\ExpenseVoucher;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AccountingService;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['list', 'create', 'edit', 'delete', 'approve', 'print'] as $action) {
        Permission::findOrCreate('expense-voucher-'.$action);
    }

    $this->supplier = Supplier::factory()->create();
    $this->expense = ChartOfAccount::factory()->create(['account_code' => '5221', 'account_name' => 'Miscellaneous Expenses']);
    ChartOfAccount::factory()->create(['account_code' => '1121', 'account_name' => 'Cash']);

    $this->maker = User::factory()->create(['supplier_id' => $this->supplier->id]);
    $this->maker->givePermissionTo(['expense-voucher-list', 'expense-voucher-create', 'expense-voucher-edit', 'expense-voucher-delete', 'expense-voucher-approve', 'expense-voucher-print']);
    $this->checker = User::factory()->create(['supplier_id' => $this->supplier->id]);
    $this->checker->givePermissionTo(['expense-voucher-list', 'expense-voucher-approve', 'expense-voucher-print']);

    $this->payload = [
        'voucher_date' => now()->format('Y-m-d'),
        'supplier_id' => $this->supplier->id,
        'party_name' => 'Ali Tea Stall',
        'expense_account_id' => $this->expense->id,
        'amount' => 1500,
        'narration' => 'Office tea',
    ];
});

it('saves a draft with a running voucher number', function () {
    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), ['action' => 'draft'] + $this->payload)->assertRedirect();
    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), ['action' => 'draft'] + $this->payload)->assertRedirect();

    expect(ExpenseVoucher::orderBy('id')->pluck('voucher_number')->all())
        ->toBe(['EV-'.now()->format('Y').'-0001', 'EV-'.now()->format('Y').'-0002'])
        ->and(ExpenseVoucher::first()->status)->toBe('draft')
        ->and(ExpenseVoucher::first()->created_by)->toBe($this->maker->id);
});

it('blocks a maker from vouchers of another company', function () {
    $other = Supplier::factory()->create();

    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), ['supplier_id' => $other->id] + $this->payload)->assertForbidden();
});

it('lets a different user approve, which posts the ledger entry', function () {
    $voucher = ExpenseVoucher::factory()->create(['supplier_id' => $this->supplier->id, 'expense_account_id' => $this->expense->id, 'amount' => 1500, 'created_by' => $this->maker->id]);
    $this->actingAs($this->maker)->post(route('expense-vouchers.submit', $voucher))->assertRedirect();
    expect($voucher->fresh()->status)->toBe('pending');

    $currency = Currency::first() ?? Currency::factory()->create();
    $period = AccountingPeriod::factory()->create(['start_date' => now()->startOfMonth(), 'end_date' => now()->endOfMonth()]);
    $entry = JournalEntry::factory()->create(['currency_id' => $currency->id, 'accounting_period_id' => $period->id, 'entry_date' => now(), 'status' => 'posted']);
    $this->mock(AccountingService::class, function ($mock) use ($entry, $voucher) {
        $mock->shouldReceive('createJournalEntry')->once()->withArgs(function (array $data) use ($voucher) {
            return $data['reference'] === $voucher->voucher_number
                && $data['lines'][0]['debit'] === 1500.0
                && $data['lines'][1]['credit'] === 1500.0;
        })->andReturn(['success' => true, 'data' => $entry]);
    });

    $this->actingAs($this->checker)->post(route('expense-vouchers.approve', $voucher))->assertRedirect();

    $voucher->refresh();
    expect($voucher->status)->toBe('approved')
        ->and($voucher->checked_by)->toBe($this->checker->id)
        ->and($voucher->journal_entry_id)->toBe($entry->id);
});

it('does not let the maker check their own voucher', function () {
    $voucher = ExpenseVoucher::factory()->pending()->create(['supplier_id' => $this->supplier->id, 'expense_account_id' => $this->expense->id, 'created_by' => $this->maker->id, 'submitted_by' => $this->maker->id]);

    $this->actingAs($this->maker)->post(route('expense-vouchers.approve', $voucher))->assertSessionHas('error');

    expect($voucher->fresh()->status)->toBe('pending');
});

it('sends a rejected voucher back to the maker with the reason', function () {
    $voucher = ExpenseVoucher::factory()->pending()->create(['supplier_id' => $this->supplier->id, 'expense_account_id' => $this->expense->id, 'created_by' => $this->maker->id, 'submitted_by' => $this->maker->id]);

    $this->actingAs($this->checker)->post(route('expense-vouchers.reject', $voucher), [])->assertSessionHasErrors('rejection_reason');
    $this->actingAs($this->checker)->post(route('expense-vouchers.reject', $voucher), ['rejection_reason' => 'No receipt'])->assertRedirect();

    $voucher->refresh();
    expect($voucher->status)->toBe('rejected')->and($voucher->rejection_reason)->toBe('No receipt')->and($voucher->isEditable())->toBeTrue();
});

it('prints only approved vouchers', function () {
    $voucher = ExpenseVoucher::factory()->pending()->create(['supplier_id' => $this->supplier->id, 'expense_account_id' => $this->expense->id, 'party_name' => 'Ali Tea Stall', 'created_by' => $this->maker->id, 'submitted_by' => $this->maker->id]);
    $this->actingAs($this->checker)->get(route('expense-vouchers.print', $voucher))->assertForbidden();

    $voucher->update(['status' => 'approved', 'checked_by' => $this->checker->id, 'checked_at' => now()]);
    $this->actingAs($this->checker)->get(route('expense-vouchers.print', $voucher))->assertOk()->assertSee('Ali Tea Stall', false)->assertSee('80mm', false);
});

it('shows approved vouchers by party on the distributor report, general ones only under All Companies', function () {
    Permission::findOrCreate('report-audit-cash-collection-distributor');
    Permission::findOrCreate('report-audit-cash-collection-distributor-all-companies');
    $this->checker->givePermissionTo(['report-audit-cash-collection-distributor', 'report-audit-cash-collection-distributor-all-companies']);

    $base = ['expense_account_id' => $this->expense->id, 'status' => 'approved'];
    ExpenseVoucher::factory()->create(['supplier_id' => $this->supplier->id, 'party_name' => 'Ali Tea Stall', 'amount' => 700] + $base);
    ExpenseVoucher::factory()->create(['supplier_id' => null, 'party_name' => 'Paint Material', 'amount' => 14300] + $base);
    ExpenseVoucher::factory()->create(['supplier_id' => $this->supplier->id, 'party_name' => 'Draft Party', 'amount' => 900, 'status' => 'draft', 'expense_account_id' => $this->expense->id]);

    $one = $this->actingAs($this->checker)->get(route('reports.cash-collection-distributor.index', ['supplier_id' => $this->supplier->id]))->viewData('tables');
    $all = $this->actingAs($this->checker)->get(route('reports.cash-collection-distributor.index', ['supplier_id' => 'all']))->viewData('tables');

    expect(collect($one['expenses']['rows'])->pluck(1)->all())->toBe(['Ali Tea Stall'])
        ->and(collect($all['expenses']['rows'])->pluck(1)->all())->toBe(['Paint Material', 'Ali Tea Stall'])
        ->and($all['expenses']['rows'][0][2])->toBe('Moon Traders')
        ->and($all['expenses']['total']['cells'][0])->toBe('15,000.00')
        ->and($all['summary']['rows'][3])->toBe(['Less: expenses paid (vouchers)', '15,000.00']);
});

it('lets an admin raise a general expense with no company, but not a company-bound user', function () {
    $admin = User::factory()->create(['is_super_admin' => 'Yes']);

    $this->actingAs($admin)->post(route('expense-vouchers.store'), ['supplier_id' => null] + $this->payload)->assertRedirect();
    expect(ExpenseVoucher::first()->supplier_id)->toBeNull();

    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), ['supplier_id' => null] + $this->payload)->assertSessionHasErrors('supplier_id');
});

it('lists vouchers with filters and opens the guide', function () {
    $other = Supplier::factory()->create();
    $base = ['expense_account_id' => $this->expense->id, 'status' => 'approved', 'voucher_date' => now()->format('Y-m-d')];
    ExpenseVoucher::factory()->create(['supplier_id' => $this->supplier->id, 'party_name' => 'Ali Tea Stall', 'amount' => 700] + $base);
    ExpenseVoucher::factory()->create(['supplier_id' => $other->id, 'party_name' => 'Other Co Party', 'amount' => 900] + $base);

    $admin = User::factory()->create(['is_super_admin' => 'Yes']);
    $parties = fn ($response) => $response->viewData('vouchers')->getCollection()->pluck('party_name')->sort()->values()->all();

    expect($parties($this->actingAs($admin)->get(route('expense-vouchers.index'))))->toBe(['Ali Tea Stall', 'Other Co Party'])
        ->and($parties($this->actingAs($admin)->get(route('expense-vouchers.index', ['filter' => ['supplier_id' => $this->supplier->id]]))))->toBe(['Ali Tea Stall'])
        ->and($parties($this->actingAs($admin)->get(route('expense-vouchers.index', ['filter' => ['party_name' => 'other co', 'amount_min' => 500]]))))->toBe(['Other Co Party'])
        ->and($parties($this->actingAs($this->maker)->get(route('expense-vouchers.index'))))->toBe(['Ali Tea Stall']);

    $this->actingAs($admin)->get(route('expense-vouchers.help'))->assertOk()->assertSee('اخراجاتی واؤچر کیسے کام کرتا ہے')->assertSee('What each step writes to the database');
});

it('books a general voucher with no company and preselects Moon Traders and the default account', function () {
    $admin = User::factory()->create(['is_super_admin' => 'Yes']);
    $general = ChartOfAccount::factory()->create(['account_code' => ExpenseVoucher::DEFAULT_ACCOUNT_CODE, 'account_name' => 'Moon Traders General Expenses']);

    $page = $this->actingAs($admin)->get(route('expense-vouchers.create'));
    $page->assertOk()->assertSee('value="general" selected', false)->assertSee('value="'.$general->id.'" selected', false);

    $this->actingAs($admin)->post(route('expense-vouchers.store'), ['supplier_id' => 'general', 'expense_account_id' => $general->id] + $this->payload)->assertRedirect();

    expect(ExpenseVoucher::first()->supplier_id)->toBeNull();
});

it('submits a new voucher for approval in one step and resubmits an edited one', function () {
    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), ['action' => 'submit'] + $this->payload)->assertRedirect();

    $voucher = ExpenseVoucher::first();
    expect($voucher->status)->toBe('pending')->and($voucher->submitted_by)->toBe($this->maker->id);

    $voucher->update(['status' => 'rejected', 'checked_by' => $this->checker->id, 'rejection_reason' => 'No receipt']);
    $this->actingAs($this->maker)->put(route('expense-vouchers.update', $voucher), ['action' => 'submit', 'amount' => 1800] + $this->payload)->assertRedirect();

    $voucher->refresh();
    expect($voucher->status)->toBe('pending')->and((float) $voucher->amount)->toBe(1800.0)->and($voucher->rejection_reason)->toBeNull();
});

it('lets anyone who works with vouchers open the guide, and a clearable company means a general expense', function () {
    $maker = User::factory()->create();
    $maker->givePermissionTo('expense-voucher-create');
    $this->actingAs($maker)->get(route('expense-vouchers.help'))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('expense-vouchers.help'))->assertForbidden();

    $admin = User::factory()->create(['is_super_admin' => 'Yes']);
    $this->actingAs($admin)->post(route('expense-vouchers.store'), ['action' => 'draft', 'supplier_id' => ''] + $this->payload)->assertRedirect();
    expect(ExpenseVoucher::first()->supplier_id)->toBeNull();
});
