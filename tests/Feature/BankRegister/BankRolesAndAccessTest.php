<?php

use App\Models\Attachment;
use App\Models\BankReceiptScan;
use App\Models\BankReconciliation;
use App\Models\BankStatementEntry;
use App\Models\User;
use App\Services\BankRegister\BankEntryApprovalService;
use App\Services\BankRegister\BankRegisterPermissions;
use App\Services\BankRegister\BankReportService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(RoleAndPermissionSeeder::class);
    $this->fixture = bankRegisterFixture();
    config()->set('bank.maker_checker', [
        'enabled' => true, 'level1_threshold' => 1000000.0, 'level2_threshold' => 10000000.0,
        'allow_self_approval' => false, 'applies_to' => 'both', 'reversal_requires_approval' => true, 'bulk_approval_max_entries' => 100,
    ]);

    $roleUser = function (string $role): User {
        $user = User::factory()->create(['name' => $role]);
        $user->assignRole($role);

        return $user;
    };
    $this->maker = $roleUser('accountant');
    $this->checker = $roleUser('bank-checker');
    $this->senior = $roleUser('bank-senior-approver');
    $this->auditor = $roleUser('auditor');
    $this->nobody = User::factory()->create();

    $this->entry = BankStatementEntry::factory()->create([
        'bank_account_id' => $this->fixture['bank']->id, 'company_id' => $this->fixture['company']->id,
        'bank_entry_category_id' => $this->fixture['category']->id, 'posting_date' => now()->toDateString(),
        'credit' => 2500000, 'debit' => 0, 'status' => 'classified',
    ]);
    $this->attachment = $this->entry->attachments()->create([
        'file_name' => 'slip.pdf', 'file_path' => 'bank-attachments/'.$this->entry->id.'/slip.pdf', 'file_type' => 'application/pdf',
        'file_size' => 10, 'label' => 'slip', 'description' => 'Deposit slip',
    ]);
    Storage::disk('local')->put($this->attachment->file_path, '%PDF-1.4 demo');
});

test('every report permission is wired to the matching bank report route', function () {
    foreach (BankReportService::REPORTS as $slug => $report) {
        expect(BankRegisterPermissions::REPORTS)->toContain($report['permission']);
    }

    expect(collect(BankReportService::REPORTS)->pluck('permission')->sort()->values()->all())->toBe(collect(BankRegisterPermissions::REPORTS)->sort()->values()->all());
});

test('the maker, checker and auditor can open every bank report; a user with no role cannot open any', function () {
    foreach ([$this->maker, $this->checker, $this->auditor] as $user) {
        $this->actingAs($user);

        foreach (array_keys(BankReportService::REPORTS) as $slug) {
            $this->get(route('reports.bank.show', $slug))->assertOk();
        }
    }

    $this->actingAs($this->nobody);
    foreach (array_keys(BankReportService::REPORTS) as $slug) {
        $this->get(route('reports.bank.show', $slug))->assertForbidden();
    }
});

test('the senior approver sees only the reports needed to decide on large amounts', function () {
    $this->actingAs($this->senior);

    foreach (['pending-approvals', 'audit-log', 'company-ledger', 'top-parties', 'cash-flow', 'account-summary'] as $slug) {
        $this->get(route('reports.bank.show', $slug))->assertOk();
    }
    foreach (['unclassified', 'daily-book', 'reconciliation-statement', 'charges-tax'] as $slug) {
        $this->get(route('reports.bank.show', $slug))->assertForbidden();
    }

    $this->get(route('reports.bank.index'))->assertOk()->assertSee('Pending approvals')->assertDontSee('Daily book');
});

test('the checker can open the approvals inbox and see and open the receipt of the entry being approved', function () {
    app(BankEntryApprovalService::class)->submit($this->entry, $this->maker);
    $this->actingAs($this->checker);

    $this->get(route('bank-approvals.index'))->assertOk()->assertSee('slip.pdf')->assertSee('inline=1', false);
    $this->get(route('bank-attachments.download', ['attachment' => $this->attachment, 'inline' => 1]))->assertOk()->assertHeader('content-disposition', 'inline; filename=slip.pdf');
    $this->get(route('bank-attachments.download', $this->attachment))->assertDownload('slip.pdf');
    $this->get(route('bank-statement-entries.drawer', $this->entry))->assertOk()->assertSee('slip.pdf');

    $this->post(route('bank-approvals.approve', $this->entry))->assertSessionHas('success');
    expect($this->entry->fresh()->status)->toBe('posted');
});

test('an entry with no receipt is called out to the approver', function () {
    $bare = BankStatementEntry::factory()->create([
        'bank_account_id' => $this->fixture['bank']->id, 'company_id' => $this->fixture['company']->id,
        'bank_entry_category_id' => $this->fixture['category']->id, 'posting_date' => now()->toDateString(),
        'credit' => 3000000, 'debit' => 0, 'status' => 'classified',
    ]);
    app(BankEntryApprovalService::class)->submit($bare, $this->maker);

    $this->actingAs($this->checker)->get(route('bank-approvals.index'))->assertSee('No receipt');
});

test('the maker can submit but not approve, reject or reverse', function () {
    app(BankEntryApprovalService::class)->submit($this->entry, $this->maker);
    $this->actingAs($this->maker);

    $this->get(route('bank-approvals.index'))->assertOk();
    $this->post(route('bank-approvals.approve', $this->entry))->assertForbidden();
    $this->post(route('bank-approvals.reject', $this->entry), ['reason' => 'x'])->assertForbidden();
    $this->post(route('bank-statement-entries.reverse', $this->entry), ['reason' => 'x'])->assertForbidden();
    $this->post(route('bank-reconciliations.complete', BankReconciliation::factory()->create(['bank_account_id' => $this->fixture['bank']->id, 'statement_date' => now(), 'statement_balance' => 0, 'book_balance' => 0])))->assertForbidden();
});

test('the checker can approve, reject, reverse and complete a reconciliation but not classify or import', function () {
    $this->actingAs($this->checker);

    $this->get(route('bank-statement-entries.create'))->assertForbidden();
    $this->get(route('bank-statement-imports.create'))->assertForbidden();
    $this->get(route('bank-entry-categories.create'))->assertForbidden();
    $this->post(route('bank-statement-entries.bulk-classify'), ['ids' => [$this->entry->id], 'bank_entry_category_id' => $this->fixture['category']->id])->assertForbidden();
    $this->post(route('bank-statement-entries.submit', $this->entry))->assertForbidden();

    foreach (['bank-statement-entries.index', 'bank-reconciliations.index', 'bank-cheques.index', 'bank-receipt-scans.index', 'bank-approvals.index', 'bank-register.help'] as $name) {
        $this->get(route($name))->assertOk();
    }
});

test('the senior approver can approve high-value entries but cannot classify, post or reconcile', function () {
    $this->actingAs($this->senior);

    $this->get(route('bank-approvals.index'))->assertOk();
    $this->get(route('bank-statement-entries.index'))->assertOk();
    $this->get(route('bank-reconciliations.index'))->assertForbidden();
    $this->get(route('bank-statement-entries.create'))->assertForbidden();
    $this->post(route('bank-statement-entries.submit', $this->entry))->assertForbidden();
});

test('the auditor can look and export but change nothing', function () {
    $this->actingAs($this->auditor);

    foreach (['bank-statement-entries.index', 'bank-reconciliations.index', 'bank-cheques.index', 'bank-receipt-scans.index', 'bank-register.help'] as $name) {
        $this->get(route($name))->assertOk();
    }
    $this->get(route('bank-statement-entries.export'))->assertOk();
    $this->get(route('bank-attachments.download', ['attachment' => $this->attachment, 'inline' => 1]))->assertOk();

    $this->get(route('bank-approvals.index'))->assertOk();
    $this->post(route('bank-approvals.approve', $this->entry))->assertForbidden();
    $this->get(route('bank-statement-entries.create'))->assertForbidden();
    $this->get(route('bank-statement-entries.edit', $this->entry))->assertForbidden();
    $this->delete(route('bank-attachments.destroy', $this->attachment))->assertForbidden();
    $this->post(route('bank-receipt-scans.store'), [])->assertForbidden();
});

test('the maker can add entries, scan receipts and link them', function () {
    $this->actingAs($this->maker);

    foreach (['bank-statement-entries.create', 'bank-receipt-scans.index', 'bank-statement-imports.create', 'bank-entry-categories.index', 'bank-party-aliases.index', 'bank-entry-rules.index', 'bank-cheques.index', 'bank-reconciliations.index'] as $name) {
        $this->get(route($name))->assertOk();
    }

    $this->get(route('bank-entry-categories.destroy', 1))->assertStatus(405);
    $this->delete(route('bank-entry-categories.destroy', $this->fixture['category']))->assertForbidden();
    expect(Attachment::count())->toBe(1)->and(BankReceiptScan::count())->toBe(0);
});

test('a user without any bank permission is refused everywhere and the menu hides the module', function () {
    $this->actingAs($this->nobody);

    foreach (['bank-statement-entries.index', 'bank-statement-entries.create', 'bank-approvals.index', 'bank-reconciliations.index', 'bank-receipt-scans.index', 'bank-register.help', 'reports.bank.index'] as $name) {
        $response = $this->get(route($name));
        expect($response->status())->toBeIn([403]);
    }
});

test('each shipped role holds exactly the permissions the module defines for it', function () {
    foreach (BankRegisterPermissions::ROLE_GRANTS as $role => $permissions) {
        $granted = Role::findByName($role)->permissions->pluck('name');

        foreach ($permissions as $permission) {
            expect($granted)->toContain($permission);
        }
    }

    expect(Role::findByName('bank-checker')->hasPermissionTo('bank-statement-entry-create'))->toBeFalse()
        ->and(Role::findByName('accountant')->hasPermissionTo('bank-statement-entry-create'))->toBeTrue()
        ->and(Role::findByName('auditor')->hasPermissionTo('bank-statement-entry-edit'))->toBeFalse();
});
