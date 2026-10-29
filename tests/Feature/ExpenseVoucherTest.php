<?php

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Currency;
use App\Models\ExpenseVoucher;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\ExpenseVoucherAttachmentService;
use App\Services\ExpenseVoucherService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['list', 'create', 'edit', 'delete', 'approve', 'print'] as $action) {
        Permission::findOrCreate('expense-voucher-'.$action);
    }

    $this->supplier = Supplier::factory()->create();
    $this->moonTraders = Company::first() ?? Company::factory()->create(['company_name' => 'Moon Traders', 'abbr' => 'MT']);
    $this->prefix = strtoupper($this->moonTraders->abbr ?: 'MT');
    $this->expense = ChartOfAccount::factory()->create(['account_code' => '5221', 'account_name' => 'Miscellaneous Expenses']);
    ChartOfAccount::factory()->create(['account_code' => '1121', 'account_name' => 'Cash']);

    $this->maker = User::factory()->create(['supplier_id' => $this->supplier->id]);
    $this->maker->givePermissionTo(['expense-voucher-list', 'expense-voucher-create', 'expense-voucher-edit', 'expense-voucher-delete', 'expense-voucher-approve', 'expense-voucher-print']);
    $this->checker = User::factory()->create(['supplier_id' => $this->supplier->id]);
    $this->checker->givePermissionTo(['expense-voucher-list', 'expense-voucher-approve', 'expense-voucher-print']);

    $this->payload = [
        'voucher_date' => now()->format('Y-m-d'),
        'owner' => 'supplier:'.$this->supplier->id,
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
        ->toBe([$this->prefix.'-EV-'.now()->format('Y').'-000001', $this->prefix.'-EV-'.now()->format('Y').'-000002'])
        ->and(ExpenseVoucher::first()->status)->toBe('draft')
        ->and(ExpenseVoucher::first()->created_by)->toBe($this->maker->id);
});

it('blocks a maker from vouchers of another company', function () {
    $other = Supplier::factory()->create();

    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), ['owner' => 'supplier:'.$other->id] + $this->payload)->assertForbidden();
});

it('lets a different user approve, which posts the ledger entry', function () {
    $this->actingAs($this->maker); // the voucher is stamped with the signed-in user as its maker
    $voucher = ExpenseVoucher::factory()->create(['supplier_id' => $this->supplier->id, 'expense_account_id' => $this->expense->id, 'amount' => 1500]);
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
    $this->actingAs($this->maker); // the voucher is stamped with the signed-in user as its maker
    $voucher = ExpenseVoucher::factory()->pending()->create(['supplier_id' => $this->supplier->id, 'expense_account_id' => $this->expense->id, 'submitted_by' => $this->maker->id]);

    $this->actingAs($this->maker)->post(route('expense-vouchers.approve', $voucher))->assertSessionHas('error');

    expect($voucher->fresh()->status)->toBe('pending');
});

it('sends a rejected voucher back to the maker with the reason', function () {
    $this->actingAs($this->maker); // the voucher is stamped with the signed-in user as its maker
    $voucher = ExpenseVoucher::factory()->pending()->create(['supplier_id' => $this->supplier->id, 'expense_account_id' => $this->expense->id, 'submitted_by' => $this->maker->id]);

    $this->actingAs($this->checker)->post(route('expense-vouchers.reject', $voucher), [])->assertSessionHasErrors('rejection_reason');
    $this->actingAs($this->checker)->post(route('expense-vouchers.reject', $voucher), ['rejection_reason' => 'No receipt'])->assertRedirect();

    $voucher->refresh();
    expect($voucher->status)->toBe('rejected')->and($voucher->rejection_reason)->toBe('No receipt')->and($voucher->isEditable())->toBeTrue();
});

it('prints only approved vouchers', function () {
    $this->actingAs($this->maker); // the voucher is stamped with the signed-in user as its maker
    $voucher = ExpenseVoucher::factory()->pending()->create(['supplier_id' => $this->supplier->id, 'expense_account_id' => $this->expense->id, 'party_name' => 'Ali Tea Stall', 'submitted_by' => $this->maker->id]);
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
    ExpenseVoucher::factory()->create(['supplier_id' => null, 'company_id' => $this->moonTraders->id, 'party_name' => 'Paint Material', 'amount' => 14300] + $base);
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

    $this->actingAs($admin)->post(route('expense-vouchers.store'), ['owner' => 'company:'.$this->moonTraders->id] + $this->payload)->assertRedirect();
    expect(ExpenseVoucher::first())->supplier_id->toBeNull()->company_id->toBe($this->moonTraders->id);

    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), ['owner' => 'company:'.$this->moonTraders->id] + $this->payload)->assertForbidden();
});

it('lists vouchers with filters and opens the guide', function () {
    $other = Supplier::factory()->create();
    $base = ['expense_account_id' => $this->expense->id, 'status' => 'approved', 'voucher_date' => now()->format('Y-m-d')];
    ExpenseVoucher::factory()->create(['supplier_id' => $this->supplier->id, 'party_name' => 'Ali Tea Stall', 'amount' => 700] + $base);
    ExpenseVoucher::factory()->create(['supplier_id' => $other->id, 'party_name' => 'Other Co Party', 'amount' => 900] + $base);

    $admin = User::factory()->create(['is_super_admin' => 'Yes']);
    $parties = fn ($response) => $response->viewData('vouchers')->getCollection()->pluck('party_name')->sort()->values()->all();

    expect($parties($this->actingAs($admin)->get(route('expense-vouchers.index'))))->toBe(['Ali Tea Stall', 'Other Co Party'])
        ->and($parties($this->actingAs($admin)->get(route('expense-vouchers.index', ['filter' => ['owner' => 'supplier:'.$this->supplier->id]]))))->toBe(['Ali Tea Stall'])
        ->and($parties($this->actingAs($admin)->get(route('expense-vouchers.index', ['filter' => ['party_name' => 'other co', 'amount_min' => 500]]))))->toBe(['Other Co Party'])
        ->and($parties($this->actingAs($this->maker)->get(route('expense-vouchers.index'))))->toBe(['Ali Tea Stall']);

    $this->actingAs($admin)->get(route('expense-vouchers.help'))->assertOk()->assertSee('اخراجاتی واؤچر کیسے کام کرتا ہے')->assertSee('What each step writes to the database');
});

it('books a general voucher against Moon Traders from the companies table and preselects it with the default account', function () {
    $admin = User::factory()->create(['is_super_admin' => 'Yes']);
    $general = ChartOfAccount::factory()->create(['account_code' => ExpenseVoucher::DEFAULT_ACCOUNT_CODE, 'account_name' => 'Moon Traders General Expenses']);

    $page = $this->actingAs($admin)->get(route('expense-vouchers.create'));
    $page->assertOk()->assertSee('value="company:'.$this->moonTraders->id.'" selected', false)->assertSee('value="'.$general->id.'" selected', false);

    $this->actingAs($admin)->post(route('expense-vouchers.store'), ['owner' => 'company:'.$this->moonTraders->id, 'expense_account_id' => $general->id] + $this->payload)->assertRedirect();

    expect(ExpenseVoucher::first()->supplier_id)->toBeNull();
});

it('submits a new voucher for approval in one step and resubmits an edited one', function () {
    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), ['action' => 'submit'] + $this->payload)->assertRedirect();

    $voucher = ExpenseVoucher::first();
    expect($voucher->status)->toBe('pending')->and($voucher->submitted_by)->toBe($this->maker->id);

    $voucher->update(['status' => 'rejected', 'checked_by' => $this->checker->id, 'rejection_reason' => 'No receipt']);
    $this->actingAs($this->maker)->put(route('expense-vouchers.update', $voucher), ['action' => 'submit', 'amount' => 1800] + $this->payload)->assertRedirect();

    $voucher->refresh();
    expect($voucher->status)->toBe('pending')->and((float) $voucher->amount)->toBe(1800.0)->and($voucher->rejection_reason)->toBeNull()->and($voucher->checked_by)->toBeNull()->and($voucher->checked_at)->toBeNull();
});

it('lets anyone who works with vouchers open the guide, and a clearable company means a general expense', function () {
    $maker = User::factory()->create();
    $maker->givePermissionTo('expense-voucher-create');
    $this->actingAs($maker)->get(route('expense-vouchers.help'))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('expense-vouchers.help'))->assertForbidden();

    $admin = User::factory()->create(['is_super_admin' => 'Yes']);
    $this->actingAs($admin)->post(route('expense-vouchers.store'), ['action' => 'draft', 'owner' => ''] + $this->payload)->assertRedirect();
    expect(ExpenseVoucher::first())->supplier_id->toBeNull()->company_id->toBe($this->moonTraders->id);
});

it('stores an attachment under Vouchers/dd-mm-yyyy on a private disk, replaces and removes it', function () {
    Storage::fake(ExpenseVoucherAttachmentService::disk());

    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), [
        'action' => 'draft', 'voucher_date' => '2026-10-28', 'attachment' => UploadedFile::fake()->image('bill.jpg'),
    ] + $this->payload)->assertRedirect();

    $voucher = ExpenseVoucher::first();
    expect($voucher->attachment)->toStartWith('Vouchers/28-10-2026/')->and($voucher->attachment)->toEndWith('.jpg');
    Storage::disk(ExpenseVoucherAttachmentService::disk())->assertExists($voucher->attachment);
    $first = $voucher->attachment;

    $this->actingAs($this->maker)->get(route('expense-vouchers.attachment', $voucher))->assertOk();
    $this->actingAs(User::factory()->create(['supplier_id' => Supplier::factory()->create()->id])->givePermissionTo('expense-voucher-list'))
        ->get(route('expense-vouchers.attachment', $voucher))->assertNotFound();

    $this->actingAs($this->maker)->put(route('expense-vouchers.update', $voucher), [
        'action' => 'draft', 'voucher_date' => '2026-10-28', 'attachment' => UploadedFile::fake()->create('slip.pdf', 100, 'application/pdf'),
    ] + $this->payload)->assertRedirect();
    $voucher->refresh();
    expect($voucher->attachment)->toEndWith('.pdf');
    Storage::disk(ExpenseVoucherAttachmentService::disk())->assertMissing($first)->assertExists($voucher->attachment);

    $this->actingAs($this->maker)->put(route('expense-vouchers.update', $voucher), ['action' => 'draft', 'voucher_date' => '2026-10-28', 'remove_attachment' => 1] + $this->payload)->assertRedirect();
    expect($voucher->fresh()->attachment)->toBeNull();
});

it('rejects an attachment of a type that is not allowed', function () {
    Storage::fake(ExpenseVoucherAttachmentService::disk());

    $this->actingAs($this->maker)->post(route('expense-vouchers.store'), ['attachment' => UploadedFile::fake()->create('run.exe', 10)] + $this->payload)->assertSessionHasErrors('attachment');
});

it('uses a uuid in the links and keeps voucher numbers unique and running per year', function () {
    $this->actingAs($this->maker);
    $first = ExpenseVoucher::factory()->create(['supplier_id' => $this->supplier->id, 'expense_account_id' => $this->expense->id]);

    expect($first->uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/')
        ->and(route('expense-vouchers.show', $first))->toContain($first->uuid)->not->toContain('/expense-vouchers/'.$first->id.'/');

    $this->get(route('expense-vouchers.show', $first))->assertOk();
    $this->get('/expense-vouchers/'.$first->id)->assertNotFound();

    app(ExpenseVoucherService::class)->create([...Arr::except($this->payload, ['owner']), 'supplier_id' => $this->supplier->id, 'company_id' => null]);
    app(ExpenseVoucherService::class)->create([...Arr::except($this->payload, ['owner']), 'supplier_id' => $this->supplier->id, 'company_id' => null]);
    $numbers = ExpenseVoucher::pluck('voucher_number')->filter(fn ($n) => str_contains($n, '-EV-'.now()->format('Y').'-'))->sort()->values()->all();

    expect($numbers)->toBe([$this->prefix.'-EV-'.now()->format('Y').'-000001', $this->prefix.'-EV-'.now()->format('Y').'-000002'])
        ->and(count(array_unique($numbers)))->toBe(2);
});

it('lets only the maker edit, delete or submit a voucher, so the checker can always be someone else', function () {
    $this->actingAs($this->maker);
    $voucher = ExpenseVoucher::factory()->create(['supplier_id' => $this->supplier->id, 'expense_account_id' => $this->expense->id]);

    $other = User::factory()->create(['supplier_id' => $this->supplier->id]);
    $other->givePermissionTo(['expense-voucher-list', 'expense-voucher-create', 'expense-voucher-edit', 'expense-voucher-delete', 'expense-voucher-approve']);

    $this->actingAs($other)->post(route('expense-vouchers.submit', $voucher))->assertForbidden();
    $this->actingAs($other)->get(route('expense-vouchers.edit', $voucher))->assertForbidden();
    $this->actingAs($other)->delete(route('expense-vouchers.destroy', $voucher))->assertForbidden();

    $this->actingAs($this->maker)->post(route('expense-vouchers.submit', $voucher))->assertRedirect();
    $this->actingAs($other)->get(route('expense-vouchers.show', $voucher))->assertOk()->assertSee('Approve and post');
});
