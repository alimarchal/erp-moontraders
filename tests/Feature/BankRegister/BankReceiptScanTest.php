<?php

use App\Models\Attachment;
use App\Models\BankAccount;
use App\Models\BankReceiptScan;
use App\Models\BankStatementEntry;
use App\Services\BankRegister\BankReceiptScanService;
use App\Services\BankRegister\BankRegisterException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->fixture = bankRegisterFixture();
    $this->maker = bankUser([
        'bank-statement-entry-list', 'bank-statement-entry-create', 'bank-statement-entry-attach', 'bank-statement-entry-submit',
        'bank-receipt-scan-list', 'bank-receipt-scan-create', 'bank-receipt-scan-link', 'bank-receipt-scan-delete',
    ], 'Maker');
    $this->actingAs($this->maker);
});

test('several scans can be dropped into the inbox at once', function () {
    $this->post(route('bank-receipt-scans.store'), [
        'files' => [UploadedFile::fake()->image('slip1.jpg'), UploadedFile::fake()->image('slip2.jpg'), UploadedFile::fake()->create('cheque.pdf', 30, 'application/pdf')],
        'scan_date' => now()->toDateString(),
        'bank_account_id' => $this->fixture['bank']->id,
        'amount' => 5000,
        'note' => 'Morning batch',
    ])->assertRedirect(route('bank-receipt-scans.index'));

    $scans = BankReceiptScan::orderBy('id')->get();
    expect($scans)->toHaveCount(3)
        ->and($scans->pluck('status')->unique()->all())->toBe(['pending'])
        ->and($scans->first()->amount)->toBeNull() // one amount cannot describe three files
        ->and($scans->first()->note)->toBe('Morning batch')
        ->and($scans->first()->uploaded_by)->toBe($this->maker->id);

    foreach ($scans as $scan) {
        Storage::disk('local')->assertExists($scan->file_path);
    }

    $this->get(route('bank-receipt-scans.index'))->assertOk()->assertSee('Receipt Scan Inbox')->assertSee('slip1.jpg');
});

test('a single scan keeps its amount and direction so the matching bank line can be suggested', function () {
    $this->post(route('bank-receipt-scans.store'), [
        'files' => [UploadedFile::fake()->image('slip.jpg')], 'scan_date' => now()->toDateString(),
        'bank_account_id' => $this->fixture['bank']->id, 'amount' => 275000, 'direction' => 'credit',
    ]);
    $scan = BankReceiptScan::firstOrFail();
    $match = BankStatementEntry::factory()->create(['bank_account_id' => $this->fixture['bank']->id, 'posting_date' => now()->subDays(2)->toDateString(), 'credit' => 275000, 'debit' => 0]);
    BankStatementEntry::factory()->create(['bank_account_id' => $this->fixture['bank']->id, 'posting_date' => now()->toDateString(), 'credit' => 111, 'debit' => 0]);
    BankStatementEntry::factory()->create(['bank_account_id' => $this->fixture['bank']->id, 'posting_date' => now()->subDays(30)->toDateString(), 'credit' => 275000, 'debit' => 0]);
    BankStatementEntry::factory()->create(['bank_account_id' => $this->fixture['bank']->id, 'posting_date' => now()->toDateString(), 'credit' => 0, 'debit' => 275000]);

    $suggestions = app(BankReceiptScanService::class)->suggestions($scan);

    expect($suggestions->pluck('id')->all())->toBe([$match->id]);
    $this->get(route('bank-receipt-scans.index'))->assertSee('Looks like');
});

test('linking moves the scan to the entry as a receipt attachment', function () {
    $this->post(route('bank-receipt-scans.store'), ['files' => [UploadedFile::fake()->image('slip.jpg')], 'scan_date' => now()->toDateString(), 'note' => 'Raja Brothers slip']);
    $scan = BankReceiptScan::firstOrFail();
    $oldPath = $scan->file_path;
    $entry = BankStatementEntry::factory()->create(['bank_account_id' => $this->fixture['bank']->id]);

    $this->post(route('bank-receipt-scans.link', $scan), ['bank_statement_entry_id' => $entry->id])->assertSessionHas('success');

    $scan->refresh();
    $attachment = Attachment::firstOrFail();
    expect($scan->status)->toBe('linked')->and($scan->bank_statement_entry_id)->toBe($entry->id)->and($scan->linked_by)->toBe($this->maker->id)
        ->and($attachment->attachable->is($entry))->toBeTrue()
        ->and($attachment->label)->toBe('receipt')->and($attachment->description)->toBe('Raja Brothers slip')
        ->and($entry->auditLogs->pluck('action')->all())->toContain('receipt_linked');
    Storage::disk('local')->assertExists($attachment->file_path);
    Storage::disk('local')->assertMissing($oldPath);

    $this->post(route('bank-receipt-scans.link', $scan), ['bank_statement_entry_id' => $entry->id])->assertSessionHas('error');
});

test('a reconciled entry cannot receive a scan and a linked scan cannot be deleted', function () {
    $this->post(route('bank-receipt-scans.store'), ['files' => [UploadedFile::fake()->image('slip.jpg')], 'scan_date' => now()->toDateString()]);
    $scan = BankReceiptScan::firstOrFail();
    $locked = BankStatementEntry::factory()->create(['bank_account_id' => $this->fixture['bank']->id, 'status' => 'reconciled']);

    expect(fn () => app(BankReceiptScanService::class)->link($scan, $locked, $this->maker))->toThrow(BankRegisterException::class, 'reconciled');

    $open = BankStatementEntry::factory()->create(['bank_account_id' => $this->fixture['bank']->id]);
    app(BankReceiptScanService::class)->link($scan, $open, $this->maker);
    $this->delete(route('bank-receipt-scans.destroy', $scan))->assertSessionHas('error');
    expect(BankReceiptScan::count())->toBe(1);
});

test('creating an entry from a scan pre-fills the form and attaches the scan when saved', function () {
    $this->post(route('bank-receipt-scans.store'), [
        'files' => [UploadedFile::fake()->image('slip.jpg')], 'scan_date' => now()->toDateString(),
        'bank_account_id' => $this->fixture['bank']->id, 'amount' => 88000, 'direction' => 'debit', 'note' => 'Fuel bill',
    ]);
    $scan = BankReceiptScan::firstOrFail();

    $this->get(route('bank-statement-entries.create', ['scan_id' => $scan->id]))
        ->assertOk()->assertSee('slip.jpg')->assertSee('88000.00')->assertSee('Fuel bill');

    $this->post(route('bank-statement-entries.store'), [
        'bank_account_id' => $this->fixture['bank']->id, 'posting_date' => now()->toDateString(), 'direction' => 'debit',
        'amount' => 88000, 'description' => 'Fuel bill', 'scan_id' => $scan->id, 'after' => 'save',
    ])->assertRedirect();

    $entry = BankStatementEntry::firstOrFail();
    expect($entry->attachments)->toHaveCount(1)->and($scan->fresh()->status)->toBe('linked')->and($scan->fresh()->bank_statement_entry_id)->toBe($entry->id);
});

test('the register counts entries without a receipt and offers a one-click filter', function () {
    $with = BankStatementEntry::factory()->create(['bank_account_id' => $this->fixture['bank']->id, 'party_name_in_statement' => 'HAS RECEIPT']);
    Attachment::factory()->create(['journal_entry_id' => null, 'attachable_type' => BankStatementEntry::class, 'attachable_id' => $with->id]);
    BankStatementEntry::factory()->create(['bank_account_id' => $this->fixture['bank']->id, 'party_name_in_statement' => 'NO RECEIPT']);

    $this->get(route('bank-statement-entries.index'))->assertOk()->assertSee('No receipt');
    $this->get(route('bank-statement-entries.index', ['filter' => ['has_attachment' => 'no']]))->assertSee('NO RECEIPT')->assertDontSee('HAS RECEIPT');
});

test('an account switcher appears when there is more than one account', function () {
    BankAccount::factory()->create(['account_name' => 'Meezan Wallet Account']);

    $this->get(route('bank-statement-entries.index'))->assertOk()->assertSee('All accounts')->assertSee('Meezan Wallet Account');
});

test('scan permissions are enforced and a scan file is served only to permitted users', function () {
    $this->post(route('bank-receipt-scans.store'), ['files' => [UploadedFile::fake()->image('slip.jpg')], 'scan_date' => now()->toDateString()]);
    $scan = BankReceiptScan::firstOrFail();

    $this->get(route('bank-receipt-scans.file', $scan))->assertOk();

    $this->actingAs(bankUser(['bank-statement-entry-list']));
    $this->get(route('bank-receipt-scans.index'))->assertForbidden();
    $this->get(route('bank-receipt-scans.file', $scan))->assertForbidden();
    $this->post(route('bank-receipt-scans.store'), [])->assertForbidden();
    $this->post(route('bank-receipt-scans.link', $scan), [])->assertForbidden();
    $this->delete(route('bank-receipt-scans.destroy', $scan))->assertForbidden();

    $this->actingAs(bankUser(['bank-receipt-scan-list']));
    $this->get(route('bank-receipt-scans.index'))->assertOk();
    $this->post(route('bank-receipt-scans.store'), [])->assertForbidden();
});

test('uploads are validated', function () {
    $this->post(route('bank-receipt-scans.store'), ['scan_date' => now()->toDateString()])->assertSessionHasErrors('files');
    $this->post(route('bank-receipt-scans.store'), ['files' => [UploadedFile::fake()->create('x.php', 1, 'text/x-php')], 'scan_date' => now()->toDateString()])->assertSessionHasErrors('files.0');
    $this->post(route('bank-receipt-scans.store'), ['files' => [UploadedFile::fake()->image('a.jpg')], 'scan_date' => now()->addDay()->toDateString()])->assertSessionHasErrors('scan_date');
    expect(BankReceiptScan::count())->toBe(0);
});
