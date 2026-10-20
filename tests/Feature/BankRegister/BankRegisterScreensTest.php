<?php

use App\Models\Attachment;
use App\Models\BankEntryRule;
use App\Models\BankPartyAlias;
use App\Models\BankStatementEntry;
use App\Models\Currency;
use App\Models\JournalEntry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->fixture = bankRegisterFixture();
    config()->set('bank.maker_checker', [
        'enabled' => true, 'level1_threshold' => 1000000.0, 'level2_threshold' => 10000000.0,
        'allow_self_approval' => false, 'applies_to' => 'both', 'reversal_requires_approval' => true,
        'bulk_approval_max_entries' => 100,
    ]);

    $this->maker = bankUser(['bank-statement-entry-list', 'bank-statement-entry-edit', 'bank-statement-entry-classify', 'bank-statement-entry-attach', 'bank-statement-entry-submit', 'bank-statement-entry-post', 'bank-statement-import-create'], 'Maker');
    $this->checker = bankUser(['bank-statement-entry-list', 'bank-statement-entry-approve', 'bank-statement-entry-reject', 'bank-statement-entry-reverse'], 'Checker');
    $this->entry = BankStatementEntry::factory()->create([
        'bank_account_id' => $this->fixture['bank']->id, 'posting_date' => now()->toDateString(),
        'credit' => 2500000, 'debit' => 0, 'party_name_in_statement' => 'NAVEED ANJUM',
    ]);
});

test('the register lists entries with tiles, status chips and the filter form', function () {
    $this->actingAs($this->maker)
        ->get(route('bank-statement-entries.index'))
        ->assertOk()
        ->assertSee('Bank Statement Register')
        ->assertSee('Waiting to be classified')
        ->assertSee('NAVEED ANJUM');
});

test('the register filters by status and by free text', function () {
    BankStatementEntry::factory()->create(['bank_account_id' => $this->fixture['bank']->id, 'party_name_in_statement' => 'OTHER PARTY', 'status' => 'posted']);

    $this->actingAs($this->maker);

    $this->get(route('bank-statement-entries.index', ['filter' => ['status' => 'posted']]))->assertOk()->assertSee('OTHER PARTY')->assertDontSee('NAVEED ANJUM');
    $this->get(route('bank-statement-entries.index', ['filter' => ['q' => 'naveed']]))->assertOk()->assertSee('NAVEED ANJUM')->assertDontSee('OTHER PARTY');
});

test('the side drawer and the full page show the same detail panel', function () {
    $this->actingAs($this->maker);

    $this->get(route('bank-statement-entries.drawer', $this->entry))->assertOk()->assertSee('Receipt &amp; attachments', false)->assertSee('2,500,000.00');
    $this->get(route('bank-statement-entries.show', $this->entry))->assertOk()->assertSee('Bank Entry #'.$this->entry->id);
});

test('classifying one entry moves it to classified and records history', function () {
    $this->actingAs($this->maker)
        ->put(route('bank-statement-entries.update', $this->entry), [
            'company_id' => $this->fixture['company']->id,
            'bank_entry_category_id' => $this->fixture['category']->id,
            'description' => 'July deposit',
            'needs_review' => 0,
        ])->assertRedirect(route('bank-statement-entries.show', $this->entry));

    $entry = $this->entry->fresh();
    expect($entry->status)->toBe('classified')->and($entry->description)->toBe('July deposit')->and($entry->auditLogs)->toHaveCount(1);
});

test('bulk classify applies company and category to the ticked entries and remembers the party', function () {
    $other = BankStatementEntry::factory()->create(['bank_account_id' => $this->fixture['bank']->id, 'party_name_in_statement' => 'ANOTHER']);

    $this->actingAs($this->maker)
        ->post(route('bank-statement-entries.bulk-classify'), [
            'ids' => [$this->entry->id, $other->id],
            'company_id' => $this->fixture['company']->id,
            'bank_entry_category_id' => $this->fixture['category']->id,
            'remember_alias' => 1,
        ])->assertSessionHas('success', '2 entries updated.');

    expect(BankStatementEntry::where('status', 'classified')->count())->toBe(2)
        ->and(BankPartyAlias::where('alias_name', 'NAVEED ANJUM')->first()->company_id)->toBe($this->fixture['company']->id)
        ->and(BankEntryRule::where('match_value', 'ANOTHER')->exists())->toBeTrue();
});

test('bulk classify without any choice is refused and a locked entry is left alone', function () {
    $posted = BankStatementEntry::factory()->create(['bank_account_id' => $this->fixture['bank']->id, 'status' => 'posted']);

    $this->actingAs($this->maker);
    $this->post(route('bank-statement-entries.bulk-classify'), ['ids' => [$this->entry->id]])->assertSessionHas('error');
    $this->post(route('bank-statement-entries.bulk-classify'), ['ids' => [$posted->id], 'bank_entry_category_id' => $this->fixture['category']->id])->assertSessionHas('success', '0 entries updated.');
    expect($posted->fresh()->bank_entry_category_id)->toBeNull();
});

test('a posted entry cannot be edited or deleted', function () {
    $this->entry->update(['status' => 'posted']);
    $this->actingAs(bankUser(['bank-statement-entry-list', 'bank-statement-entry-edit', 'bank-statement-entry-delete']));

    $this->get(route('bank-statement-entries.edit', $this->entry))->assertRedirect(route('bank-statement-entries.show', $this->entry));
    $this->put(route('bank-statement-entries.update', $this->entry), ['description' => 'x'])->assertSessionHas('error');
    $this->delete(route('bank-statement-entries.destroy', $this->entry))->assertSessionHas('error');
    expect(BankStatementEntry::count())->toBe(1);
});

test('the full maker-checker journey through the screens', function () {
    $this->entry->update(['company_id' => $this->fixture['company']->id, 'bank_entry_category_id' => $this->fixture['category']->id, 'status' => 'classified']);

    // maker submits a 2.5M entry: it waits
    $this->actingAs($this->maker)->post(route('bank-statement-entries.submit', $this->entry))->assertSessionHas('success', 'Entry sent for approval.');
    expect($this->entry->fresh()->status)->toBe('pending_approval');

    // maker cannot approve it
    $makerApprover = bankUser(['bank-statement-entry-list', 'bank-statement-entry-approve']);
    $this->entry->update(['submitted_by' => $makerApprover->id]);
    $this->actingAs($makerApprover)->post(route('bank-approvals.approve', $this->entry))->assertSessionHas('error');
    $this->entry->update(['submitted_by' => $this->maker->id]);

    // checker sees it in the inbox and approves
    $this->actingAs($this->checker)->get(route('bank-approvals.index'))->assertOk()->assertSee('Unilever')->assertSee('2,500,000.00');
    $this->post(route('bank-approvals.approve', $this->entry))->assertSessionHas('success');

    $entry = $this->entry->fresh();
    expect($entry->status)->toBe('posted')->and($entry->receipt_no)->toStartWith('BR-');

    // checker reverses it
    $this->post(route('bank-statement-entries.reverse', $entry), ['reason' => 'Wrong company'])->assertSessionHas('success');
    expect($entry->fresh()->status)->toBe('reversed');
});

test('the checker can reject with a reason and the maker sees why', function () {
    $this->entry->update(['company_id' => $this->fixture['company']->id, 'bank_entry_category_id' => $this->fixture['category']->id, 'status' => 'classified']);
    $this->actingAs($this->maker)->post(route('bank-statement-entries.submit', $this->entry));

    $this->actingAs($this->checker)->post(route('bank-approvals.reject', $this->entry), [])->assertSessionHasErrors('reason');
    $this->post(route('bank-approvals.reject', $this->entry), ['reason' => 'Slip missing'])->assertSessionHas('success');

    $this->actingAs($this->maker)->get(route('bank-statement-entries.show', $this->entry))->assertSee('Slip missing');
});

test('bulk submit posts what is below the limit and queues the rest', function () {
    $small = BankStatementEntry::factory()->create([
        'bank_account_id' => $this->fixture['bank']->id, 'posting_date' => now()->toDateString(), 'credit' => 5000, 'debit' => 0,
        'company_id' => $this->fixture['company']->id, 'bank_entry_category_id' => $this->fixture['category']->id, 'status' => 'classified',
    ]);
    $this->entry->update(['company_id' => $this->fixture['company']->id, 'bank_entry_category_id' => $this->fixture['category']->id, 'status' => 'classified']);

    $this->actingAs($this->maker)->post(route('bank-statement-entries.bulk-submit'), ['ids' => [$small->id, $this->entry->id]])->assertSessionHas('success', '2 entries submitted.');

    expect($small->fresh()->status)->toBe('posted')->and($this->entry->fresh()->status)->toBe('pending_approval');
});

test('a maker without the post permission cannot push a small entry straight to the books', function () {
    $limited = bankUser(['bank-statement-entry-list', 'bank-statement-entry-submit']);
    $small = BankStatementEntry::factory()->create([
        'bank_account_id' => $this->fixture['bank']->id, 'posting_date' => now()->toDateString(), 'credit' => 5000, 'debit' => 0,
        'company_id' => $this->fixture['company']->id, 'bank_entry_category_id' => $this->fixture['category']->id, 'status' => 'classified',
    ]);

    $this->actingAs($limited)->post(route('bank-statement-entries.submit', $small))->assertSessionHas('error');
    expect($small->fresh()->status)->toBe('classified');
});

test('attachments are uploaded to the private disk with a description and downloaded only with the list permission', function () {
    $this->actingAs($this->maker);

    $this->post(route('bank-statement-entries.attachments.store', $this->entry), [
        'files' => [UploadedFile::fake()->create('slip.pdf', 120, 'application/pdf'), UploadedFile::fake()->image('cheque.jpg')],
        'label' => 'slip',
        'description' => 'Deposit slip for July',
    ])->assertSessionHas('success', '2 file(s) attached.');

    $attachment = Attachment::where('file_name', 'slip.pdf')->firstOrFail();
    expect($attachment->attachable->is($this->entry))->toBeTrue()
        ->and($attachment->label)->toBe('slip')
        ->and($attachment->description)->toBe('Deposit slip for July')
        ->and($attachment->uploaded_by)->toBe($this->maker->id);
    Storage::disk('local')->assertExists($attachment->file_path);

    $this->get(route('bank-attachments.download', $attachment))->assertOk()->assertDownload('slip.pdf');

    $this->actingAs(bankUser(['bank-reconciliation-list']))->get(route('bank-attachments.download', $attachment))->assertForbidden();
});

test('upload rejects dangerous or oversized files and demands at least one file', function () {
    $this->actingAs($this->maker);
    $url = route('bank-statement-entries.attachments.store', $this->entry);

    $this->post($url, ['files' => [UploadedFile::fake()->create('shell.php', 1, 'text/x-php')], 'label' => 'other'])->assertSessionHasErrors('files.0');
    $this->post($url, ['files' => [UploadedFile::fake()->create('huge.pdf', 20000, 'application/pdf')], 'label' => 'other'])->assertSessionHasErrors('files.0');
    $this->post($url, ['label' => 'other'])->assertSessionHasErrors('files');
});

test('attachments can be removed until the entry is reconciled', function () {
    $this->actingAs($this->maker);
    $this->post(route('bank-statement-entries.attachments.store', $this->entry), ['files' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')], 'label' => 'other']);
    $attachment = Attachment::firstOrFail();

    $this->entry->update(['status' => 'reconciled']);
    $this->delete(route('bank-attachments.destroy', $attachment))->assertSessionHas('error');
    expect(Attachment::count())->toBe(1);

    $this->entry->update(['status' => 'posted']);
    $this->delete(route('bank-attachments.destroy', $attachment))->assertSessionHas('success');
    expect(Attachment::count())->toBe(0);
});

test('a journal attachment is not downloadable through the bank route', function () {
    $journal = JournalEntry::create(['currency_id' => Currency::value('id'), 'entry_date' => now()->toDateString(), 'status' => 'draft']);
    $other = Attachment::factory()->create(['journal_entry_id' => $journal->id]);

    $this->actingAs($this->maker)->get(route('bank-attachments.download', $other))->assertNotFound();
});

test('a user with no bank permission sees none of the bank screens', function () {
    $this->actingAs(bankUser([]));

    foreach ([
        route('bank-statement-entries.index'), route('bank-approvals.index'), route('bank-reconciliations.index'),
        route('bank-entry-categories.index'), route('bank-party-aliases.index'), route('bank-entry-rules.index'),
        route('bank-cheques.index'), route('bank-statement-imports.index'), route('bank-register.help'),
    ] as $url) {
        $this->get($url)->assertForbidden();
    }

    $this->post(route('bank-statement-entries.submit', $this->entry))->assertForbidden();
    $this->post(route('bank-approvals.approve', $this->entry))->assertForbidden();
});

test('guests are sent to the login page', function () {
    $this->get(route('bank-statement-entries.index'))->assertRedirect();
});

test('the export respects the filters', function () {
    $this->actingAs(bankUser(['bank-statement-entry-list', 'bank-statement-entry-export']));

    $this->get(route('bank-statement-entries.export', ['filter' => ['q' => 'naveed']]))->assertOk();
    $this->actingAs(bankUser(['bank-statement-entry-list']))->get(route('bank-statement-entries.export'))->assertForbidden();
});

test('the Urdu help page is right-to-left and covers each screen', function () {
    $this->actingAs($this->maker)->get(route('bank-register.help'))
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee('lang="ur"', false)
        ->assertSee('بینک سٹیٹمنٹ رجسٹر')
        ->assertSee('BANK_APPROVAL_LEVEL1_THRESHOLD');
});
