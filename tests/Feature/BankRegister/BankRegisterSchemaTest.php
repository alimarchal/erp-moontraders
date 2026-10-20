<?php

use App\Models\Attachment;
use App\Models\BankEntryCategory;
use App\Models\Currency;
use App\Models\JournalEntry;
use App\Services\BankRegister\BankRegisterPermissions;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('one migrate creates every bank register table', function () {
    foreach ([
        'bank_entry_categories', 'bank_party_aliases', 'bank_statement_imports', 'bank_entry_rules',
        'bank_receipt_sequences', 'bank_statement_entries', 'bank_entry_approvals',
        'bank_reconciliation_items', 'bank_cheques', 'bank_entry_audit_logs',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("missing table {$table}");
    }

    expect(Schema::hasColumns('attachments', ['attachable_type', 'attachable_id', 'label', 'uploaded_by']))->toBeTrue()
        ->and(Schema::hasColumns('bank_reconciliations', ['period_from', 'period_to', 'opening_balance', 'reconciled_total']))->toBeTrue();
});

test('the migration seeds the default categories, permissions and bank roles', function () {
    expect(BankEntryCategory::count())->toBeGreaterThanOrEqual(11)
        ->and(BankEntryCategory::where('slug', 'bank-charges')->exists())->toBeTrue();

    foreach (BankRegisterPermissions::all() as $permission) {
        expect(Permission::where('name', $permission)->exists())->toBeTrue("missing permission {$permission}");
    }

    expect(Role::findByName('bank-checker')->hasPermissionTo('bank-statement-entry-approve'))->toBeTrue()
        ->and(Role::findByName('bank-checker')->hasPermissionTo('bank-statement-entry-approve-high-value'))->toBeFalse()
        ->and(Role::findByName('bank-senior-approver')->hasPermissionTo('bank-statement-entry-approve-high-value'))->toBeTrue();

    // the seeder gives the accountant the maker slice but never the approval permissions
    $this->seed(RoleAndPermissionSeeder::class);
    expect(Role::findByName('accountant')->hasPermissionTo('bank-statement-entry-submit'))->toBeTrue()
        ->and(Role::findByName('accountant')->hasPermissionTo('bank-statement-entry-approve'))->toBeFalse()
        ->and(Role::findByName('auditor')->hasPermissionTo('report-bank-audit-log'))->toBeTrue();
});

test('existing journal attachments are linked to their journal entry polymorphically', function () {
    bankRegisterFixture();
    $entry = JournalEntry::factory()->create(['entry_date' => now()->toDateString(), 'currency_id' => Currency::value('id')]);
    $attachment = Attachment::factory()->create(['journal_entry_id' => $entry->id]);

    expect($attachment->journalEntry->is($entry))->toBeTrue()
        ->and(Attachment::query()->whereNull('journal_entry_id')->count())->toBe(0);
});
