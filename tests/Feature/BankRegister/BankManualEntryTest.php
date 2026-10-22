<?php

use App\Models\BankEntryCategory;
use App\Models\BankPartyAlias;
use App\Models\BankStatementEntry;
use App\Models\BankStatementImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->fixture = bankRegisterFixture();
    config()->set('bank.maker_checker.level1_threshold', 1000000.0);
    config()->set('bank.maker_checker.level2_threshold', 10000000.0);

    $this->maker = bankUser([
        'bank-statement-entry-list', 'bank-statement-entry-create', 'bank-statement-entry-edit', 'bank-statement-entry-attach',
        'bank-statement-entry-submit', 'bank-statement-entry-post', 'bank-statement-entry-delete',
    ], 'Maker');
    $this->actingAs($this->maker);

    $this->payload = fn (array $overrides = []) => array_merge([
        'bank_account_id' => $this->fixture['bank']->id,
        'posting_date' => now()->toDateString(),
        'direction' => 'credit',
        'amount' => 125000,
        'reference' => 'CHQ Cr. Online 00000500',
        'party_name_in_statement' => 'AHMED TRADERS',
        'narration' => 'Typed in by hand',
        'after' => 'save',
    ], $overrides);
});

test('the add-entry form renders with money toggle, amount and the three save buttons', function () {
    $this->get(route('bank-statement-entries.create'))
        ->assertOk()
        ->assertSee('Add entry')
        ->assertSee('Received')
        ->assertSee('Paid out')
        ->assertSee('Save &amp; add another', false)
        ->assertSee('Save &amp; submit', false);

    $this->get(route('bank-statement-entries.index'))->assertSee('Add entry');
});

test('a received amount is saved as a credit and a paid amount as a debit', function () {
    $this->post(route('bank-statement-entries.store'), ($this->payload)())->assertRedirect();
    $this->post(route('bank-statement-entries.store'), ($this->payload)(['direction' => 'debit', 'amount' => 3000.5, 'reference' => 'Fee']))->assertRedirect();

    $credit = BankStatementEntry::where('reference', 'CHQ Cr. Online 00000500')->first();
    $debit = BankStatementEntry::where('reference', 'Fee')->first();

    expect((float) $credit->credit)->toBe(125000.0)->and((float) $credit->debit)->toBe(0.0)
        ->and((float) $debit->debit)->toBe(3000.5)->and((float) $debit->credit)->toBe(0.0)
        ->and($credit->import_id)->toBeNull()
        ->and($credit->source_statement)->toBe('Manual entry')
        ->and($credit->created_by)->toBe($this->maker->id)
        ->and($credit->status)->toBe(BankStatementEntry::STATUS_UNCLASSIFIED)
        ->and($credit->auditLogs->pluck('action')->all())->toBe(['created_manually']);
});

test('choosing a category makes the entry classified straight away', function () {
    $this->post(route('bank-statement-entries.store'), ($this->payload)([
        'company_id' => $this->fixture['company']->id, 'bank_entry_category_id' => $this->fixture['category']->id,
    ]));

    $entry = BankStatementEntry::firstOrFail();
    expect($entry->status)->toBe(BankStatementEntry::STATUS_CLASSIFIED)->and($entry->classified_by)->toBe($this->maker->id);
});

test('an alias fills in the company when the person leaves it empty', function () {
    BankPartyAlias::create(['alias_name' => 'ahmed traders', 'canonical_name' => 'Ahmed Traders', 'company_id' => $this->fixture['company']->id]);

    $this->post(route('bank-statement-entries.store'), ($this->payload)());

    expect(BankStatementEntry::firstOrFail()->company_id)->toBe($this->fixture['company']->id);
});

test('required fields, a positive amount and a date that is not in the future are enforced', function () {
    $url = route('bank-statement-entries.store');

    $this->post($url, [])->assertSessionHasErrors(['bank_account_id', 'posting_date', 'direction', 'amount']);
    $this->post($url, ($this->payload)(['amount' => 0]))->assertSessionHasErrors('amount');
    $this->post($url, ($this->payload)(['amount' => -5]))->assertSessionHasErrors('amount');
    $this->post($url, ($this->payload)(['posting_date' => now()->addDay()->toDateString()]))->assertSessionHasErrors('posting_date');
    $this->post($url, ($this->payload)(['direction' => 'sideways']))->assertSessionHasErrors('direction');

    expect(BankStatementEntry::count())->toBe(0);
});

test('a probable duplicate is flagged and only saved after the person confirms', function () {
    $this->post(route('bank-statement-entries.store'), ($this->payload)());

    $this->post(route('bank-statement-entries.store'), ($this->payload)())
        ->assertSessionHas('possible_duplicates');
    expect(BankStatementEntry::count())->toBe(1);

    $this->post(route('bank-statement-entries.store'), ($this->payload)(['confirm_duplicate' => 1]))->assertSessionMissing('possible_duplicates');
    expect(BankStatementEntry::count())->toBe(2)->and(BankStatementEntry::distinct('dedupe_hash')->count('dedupe_hash'))->toBe(2);

    $this->get(route('bank-statement-entries.create'))->assertOk();
});

test('save and add another keeps the account, date and direction for the next entry', function () {
    $response = $this->post(route('bank-statement-entries.store'), ($this->payload)(['after' => 'another', 'direction' => 'debit', 'posting_date' => '2026-10-05']));

    $response->assertRedirect(route('bank-statement-entries.create', [
        'bank_account_id' => $this->fixture['bank']->id, 'posting_date' => '2026-10-05', 'direction' => 'debit',
    ]));

    $this->get($response->headers->get('Location'))->assertOk()->assertSee('2026-10-05');
});

test('save and submit posts a small entry and queues a large one for approval', function () {
    $common = ['company_id' => $this->fixture['company']->id, 'bank_entry_category_id' => $this->fixture['category']->id, 'after' => 'submit'];

    $this->post(route('bank-statement-entries.store'), ($this->payload)($common + ['amount' => 5000, 'reference' => 'small']))->assertSessionHas('success');
    $this->post(route('bank-statement-entries.store'), ($this->payload)($common + ['amount' => 2500000, 'reference' => 'large']));

    expect(BankStatementEntry::where('reference', 'small')->first()->status)->toBe(BankStatementEntry::STATUS_POSTED)
        ->and(BankStatementEntry::where('reference', 'small')->first()->receipt_no)->toStartWith('BR-')
        ->and(BankStatementEntry::where('reference', 'large')->first()->status)->toBe(BankStatementEntry::STATUS_PENDING_APPROVAL);
});

test('save and submit without a category keeps the entry and explains why it was not submitted', function () {
    $this->post(route('bank-statement-entries.store'), ($this->payload)(['after' => 'submit']))
        ->assertSessionHas('success')->assertSessionHas('warning');

    expect(BankStatementEntry::firstOrFail()->status)->toBe(BankStatementEntry::STATUS_UNCLASSIFIED);
});

test('a receipt can be attached while adding the entry', function () {
    $this->post(route('bank-statement-entries.store'), ($this->payload)([
        'files' => [UploadedFile::fake()->create('slip.pdf', 50, 'application/pdf')],
        'label' => 'slip', 'attachment_description' => 'Deposit slip',
    ]))->assertRedirect();

    $entry = BankStatementEntry::firstOrFail();
    $attachment = $entry->attachments()->firstOrFail();
    expect($attachment->file_name)->toBe('slip.pdf')->and($attachment->label)->toBe('slip')->and($attachment->description)->toBe('Deposit slip');
    Storage::disk('local')->assertExists($attachment->file_path);
});

test('a user without the create permission cannot add entries', function () {
    $this->actingAs(bankUser(['bank-statement-entry-list']));

    $this->get(route('bank-statement-entries.create'))->assertForbidden();
    $this->post(route('bank-statement-entries.store'), ($this->payload)())->assertForbidden();
    expect(BankStatementEntry::count())->toBe(0);
});

test('a manual entry can be corrected until it is posted, and approval is withdrawn when the amount changes', function () {
    $this->post(route('bank-statement-entries.store'), ($this->payload)([
        'company_id' => $this->fixture['company']->id, 'bank_entry_category_id' => $this->fixture['category']->id,
        'amount' => 2500000, 'after' => 'submit',
    ]));
    $entry = BankStatementEntry::firstOrFail();
    expect($entry->status)->toBe(BankStatementEntry::STATUS_PENDING_APPROVAL);

    $this->get(route('bank-statement-entries.edit', $entry))->assertOk()->assertSee('typed in by hand');

    $this->put(route('bank-statement-entries.update', $entry), [
        'direction' => 'debit', 'amount' => 2400000, 'posting_date' => now()->toDateString(), 'reference' => 'Corrected',
        'party_name_in_statement' => 'AHMED TRADERS', 'company_id' => $this->fixture['company']->id,
        'bank_entry_category_id' => $this->fixture['category']->id,
    ])->assertRedirect();

    $entry->refresh();
    expect((float) $entry->debit)->toBe(2400000.0)->and((float) $entry->credit)->toBe(0.0)
        ->and($entry->reference)->toBe('Corrected')
        ->and($entry->status)->toBe(BankStatementEntry::STATUS_CLASSIFIED);
});

test('an imported line keeps what the bank printed even if amount fields are posted to the edit form', function () {
    $imported = BankStatementEntry::factory()->create([
        'bank_account_id' => $this->fixture['bank']->id, 'import_id' => BankStatementImport::create([
            'bank_account_id' => $this->fixture['bank']->id, 'file_name' => 'x.xlsx', 'status' => 'imported',
        ])->id, 'credit' => 1000, 'debit' => 0,
    ]);

    $this->get(route('bank-statement-entries.edit', $imported))->assertOk()->assertDontSee('typed in by hand');
    $this->put(route('bank-statement-entries.update', $imported), ['amount' => 5, 'direction' => 'debit', 'reference' => 'tampered', 'description' => 'ok']);

    $imported->refresh();
    expect((float) $imported->credit)->toBe(1000.0)->and($imported->reference)->not->toBe('tampered')->and($imported->description)->toBe('ok');
});

test('a posted manual entry cannot be changed and an unposted one can be deleted', function () {
    $this->post(route('bank-statement-entries.store'), ($this->payload)([
        'company_id' => $this->fixture['company']->id, 'bank_entry_category_id' => $this->fixture['category']->id, 'after' => 'submit',
    ]));
    $entry = BankStatementEntry::firstOrFail();
    expect($entry->status)->toBe(BankStatementEntry::STATUS_POSTED);

    $this->put(route('bank-statement-entries.update', $entry), ['amount' => 1, 'direction' => 'credit', 'posting_date' => now()->toDateString()])->assertSessionHas('error');
    expect((float) $entry->fresh()->credit)->toBe(125000.0);

    $open = BankStatementEntry::factory()->create(['bank_account_id' => $this->fixture['bank']->id]);
    $this->delete(route('bank-statement-entries.destroy', $open))->assertRedirect();
    expect(BankStatementEntry::whereKey($open->id)->exists())->toBeFalse();
});

test('the category list is offered to the form with each category type so it can follow the money direction', function () {
    $html = $this->get(route('bank-statement-entries.create'))->getContent();

    expect($html)->toContain('Principal Payment')->toContain('Customer Receipt')
        ->and(BankEntryCategory::where('type', 'payment')->exists())->toBeTrue()
        ->and(BankEntryCategory::where('type', 'receipt')->exists())->toBeTrue();
});
