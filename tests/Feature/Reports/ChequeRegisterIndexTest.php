<?php

use App\Exports\ChequeRegisterExport;
use App\Models\BankAccount;
use App\Models\Employee;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementCheque;
use App\Models\Supplier;
use App\Models\User;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->travelTo('2026-11-15 10:00:00');

    foreach (['report-audit-cheque-register', 'report-audit-cheque-register-manage', 'report-audit-cheque-register-all-companies'] as $permission) {
        Permission::findOrCreate($permission);
    }

    $this->supplier = Supplier::factory()->create(['supplier_name' => 'Kausar Oil', 'short_name' => 'Kausar']);
    $this->otherSupplier = Supplier::factory()->create(['supplier_name' => 'Nestle Pakistan', 'short_name' => 'Nestle']);

    $this->settlementFor = fn (Supplier $supplier) => SalesSettlement::factory()->create([
        'supplier_id' => $supplier->id,
        'employee_id' => Employee::factory()->create(['supplier_id' => $supplier->id, 'is_active' => true])->id,
        'status' => 'posted',
        'settlement_date' => '2026-11-01',
    ]);
    $this->settlement = ($this->settlementFor)($this->supplier);
    $this->otherSettlement = ($this->settlementFor)($this->otherSupplier);

    $this->cheque = fn (string $number, array $extra = []) => SalesSettlementCheque::factory()->create($extra + [
        'sales_settlement_id' => $this->settlement->id,
        'cheque_number' => $number,
        'amount' => 1000,
        'bank_name' => 'HBL',
        'cheque_date' => '2026-11-05',
        'status' => 'pending',
        'cleared_date' => null,
    ]);

    $this->companyUser = User::factory()->create(['supplier_id' => $this->supplier->id]);
    $this->companyUser->givePermissionTo(['report-audit-cheque-register', 'report-audit-cheque-register-manage']);

    $this->allCompaniesUser = User::factory()->create(['supplier_id' => null]);
    $this->allCompaniesUser->givePermissionTo(['report-audit-cheque-register', 'report-audit-cheque-register-manage', 'report-audit-cheque-register-all-companies']);

    $this->register = fn (array $query = []) => route('reports.cheque-register.index', $query);
});

it('loads no cheques until filters are applied', function () {
    ($this->cheque)('OWN-CHQ-001');

    $response = $this->actingAs($this->companyUser)->get(($this->register)());

    $response->assertSuccessful()
        ->assertViewHas('cheques', null)
        ->assertSee('Choose a company and filters, then Apply')
        ->assertDontSee('OWN-CHQ-001');

    $this->actingAs($this->companyUser)->get(($this->register)(['filter' => ['supplier_id' => $this->supplier->id]]))
        ->assertSee('OWN-CHQ-001');
});

it('shows every company to a user with the all-companies permission', function () {
    ($this->cheque)('OWN-CHQ-001');
    ($this->cheque)('OTHER-CHQ-001', ['sales_settlement_id' => $this->otherSettlement->id]);

    $response = $this->actingAs($this->allCompaniesUser)->get(($this->register)(['filter' => ['supplier_id' => 'all']]));

    $response->assertSuccessful()
        ->assertSee('<option value="all" selected>All companies</option>', false)
        ->assertSee('OWN-CHQ-001')
        ->assertSee('OTHER-CHQ-001');
    expect($response->viewData('suppliers'))->toHaveCount(2)
        ->and($response->viewData('cheques')->total())->toBe(2);

    $this->actingAs($this->allCompaniesUser)->get(($this->register)(['filter' => ['supplier_id' => $this->otherSupplier->id]]))
        ->assertSee('OTHER-CHQ-001')
        ->assertDontSee('OWN-CHQ-001');
});

it('refuses another company for a company user, including all', function () {
    $this->actingAs($this->companyUser)->get(($this->register)(['filter' => ['supplier_id' => $this->otherSupplier->id]]))->assertForbidden();
    $this->actingAs($this->companyUser)->get(($this->register)(['filter' => ['supplier_id' => 'all']]))->assertForbidden();

    $page = $this->actingAs($this->companyUser)->get(($this->register)());
    $page->assertDontSee('<option value="all"', false);
    expect($page->viewData('suppliers')->pluck('id')->all())->toBe([$this->supplier->id]);
});

it('filters by cleared date, cleared via, deposit bank and amount range', function () {
    $bank = BankAccount::factory()->create(['account_name' => 'HBL Main']);
    $otherBank = BankAccount::factory()->create(['account_name' => 'MCB Main']);
    $cleared = ['status' => 'cleared', 'cleared_date' => '2026-11-12', 'cleared_via' => 'bank', 'cleared_bank_account_id' => $bank->id, 'amount' => 5000];

    ($this->cheque)('MATCH-1', $cleared);
    ($this->cheque)('EARLY-1', ['cleared_date' => '2026-11-09'] + $cleared);
    ($this->cheque)('CASH-1', ['cleared_via' => 'cash'] + $cleared);
    ($this->cheque)('OTHERBANK-1', ['cleared_bank_account_id' => $otherBank->id] + $cleared);
    ($this->cheque)('BIG-1', ['amount' => 9000] + $cleared);
    ($this->cheque)('PENDING-1', ['amount' => 5000]);

    $response = $this->actingAs($this->companyUser)->get(($this->register)(['filter' => [
        'supplier_id' => $this->supplier->id,
        'cleared_date_from' => '2026-11-10',
        'cleared_date_to' => '2026-11-13',
        'cleared_via' => 'bank',
        'deposit_bank_id' => $bank->id,
        'amount_min' => 4000,
        'amount_max' => 6000,
    ]]));

    $response->assertSuccessful();
    expect($response->viewData('cheques')->pluck('cheque_number')->all())->toBe(['MATCH-1']);
});

it('counts cheques per status in the tabs', function () {
    ($this->cheque)('P-1', ['amount' => 1000]);
    ($this->cheque)('P-2', ['amount' => 2500]);
    ($this->cheque)('C-1', ['status' => 'cleared', 'cleared_date' => '2026-11-10', 'amount' => 4000]);
    ($this->cheque)('B-1', ['status' => 'bounced', 'amount' => 700]);
    ($this->cheque)('X-1', ['sales_settlement_id' => $this->otherSettlement->id, 'amount' => 99999]);

    $response = $this->actingAs($this->companyUser)->get(($this->register)(['filter' => ['supplier_id' => $this->supplier->id, 'status' => 'pending']]));

    $stats = $response->viewData('stats');
    expect($stats['all'])->toBe(['count' => 4, 'amount' => 8200.0])
        ->and($stats['pending'])->toBe(['count' => 2, 'amount' => 3500.0])
        ->and($stats['cleared'])->toBe(['count' => 1, 'amount' => 4000.0])
        ->and($stats['bounced'])->toBe(['count' => 1, 'amount' => 700.0])
        ->and($stats['cancelled'])->toBe(['count' => 0, 'amount' => 0.0])
        ->and($response->viewData('cheques')->pluck('cheque_number')->sort()->values()->all())->toBe(['P-1', 'P-2']);
});

it('exports the filtered cheques to Excel', function () {
    Excel::fake();
    ($this->cheque)('P-1');
    ($this->cheque)('C-1', ['status' => 'cleared', 'cleared_date' => '2026-11-10']);

    $this->actingAs($this->companyUser)->get(($this->register)(['filter' => ['supplier_id' => $this->supplier->id, 'status' => 'cleared'], 'export' => 'xlsx']));

    Excel::assertDownloaded('cheque-register-2026-11-15.xlsx', fn (ChequeRegisterExport $export) => $export->query()->pluck('cheque_number')->all() === ['C-1']);
});

it('downloads the filtered cheques as an A4 landscape PDF', function () {
    ($this->cheque)('P-1');

    $response = $this->actingAs($this->companyUser)->get(($this->register)(['filter' => ['supplier_id' => $this->supplier->id], 'format' => 'pdf']));

    $response->assertSuccessful()->assertHeader('content-type', 'application/pdf');
});

it('shows the status column to viewers without the manage permission', function () {
    $viewer = User::factory()->create(['supplier_id' => $this->supplier->id]);
    $viewer->givePermissionTo('report-audit-cheque-register');
    $cheque = ($this->cheque)('C-1', ['status' => 'cleared', 'cleared_date' => '2026-11-10', 'cleared_via' => 'cash']);

    $this->actingAs($viewer)->get(($this->register)(['filter' => ['supplier_id' => $this->supplier->id]]))
        ->assertSuccessful()
        ->assertSee('Cleared')
        ->assertSee(route('reports.cheque-register.history', $cheque), false)
        ->assertDontSee(route('reports.cheque-register.update-status', $cheque), false)
        ->assertDontSee(route('reports.cheque-register.bulk-clear'), false);
});

it('shows an error flashed by a failed clearing', function () {
    $own = ($this->cheque)('OWN-1');
    $other = ($this->cheque)('OTHER-1', ['sales_settlement_id' => $this->otherSettlement->id]);
    $url = ($this->register)(['filter' => ['supplier_id' => 'all']]);

    $this->actingAs($this->allCompaniesUser)->from($url)->followingRedirects()
        ->post(route('reports.cheque-register.bulk-clear'), [
            'cheque_ids' => [$own->id, $other->id], 'cleared_date' => '2026-11-15', 'cleared_via' => 'cash', 'denom' => ['1000' => 2],
        ])
        ->assertSuccessful()
        ->assertSee('Cash clearing must be one company at a time');
});
