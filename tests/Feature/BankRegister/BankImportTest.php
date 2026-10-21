<?php

use App\Models\BankStatementEntry;
use App\Models\BankStatementImport;
use App\Models\User;
use App\Services\BankRegister\BankRegisterException;
use App\Services\BankRegister\BankStatementImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    Storage::fake('local');
    $this->fixture = bankRegisterFixture();
    $this->user = User::factory()->create();
});

/**
 * @param  array<int, array<int, mixed>>  $rows  data rows under the accountant's header layout
 */
function statementFile(array $rows, bool $withHeader = true): UploadedFile
{
    $sheet = new Spreadsheet;
    $active = $sheet->getActiveSheet();
    $active->setCellValue('A3', 'MOON TRADERS | HBL 1554-79010983-03 | 1 July 2024-30 June 2025 | PKR');

    $header = ['Statement ID', 'Posting date', 'Value date', 'Party name in statement', 'Client party name - unnamed entries', 'Party used for summary', 'Debit (PKR)', 'Credit (PKR)', 'Transaction / reference', 'Original statement narration', 'Source statement', 'PDF page', 'Response status'];
    $row = 6;
    if ($withHeader) {
        $active->fromArray($header, null, 'A6');
        $row = 7;
    }
    $active->fromArray($rows, null, 'A'.$row);

    $path = tempnam(sys_get_temp_dir(), 'stmt').'.xlsx';
    (new Xlsx($sheet))->save($path);

    return new UploadedFile($path, 'statement.xlsx', null, null, true);
}

function sampleRows(): array
{
    return [
        [1, '2024-07-02', null, 'NAVEED ANJUM', 'Staff', 'NAVEED ANJUM', 0, 132650, 'Online Deposit 11156591', 'Online Deposit 11156591 | NAVEED ANJUM', 'Jul-Dec 2024 statement', 1, 'Original classification retained'],
        [2, '2024-07-02', null, 'BISMILLAH TRADE', 'Bismillah Trader', 'BISMILLAH TRADE', 0, 140000, 'CHQ Cr. Online 00000264', 'CHQ Cr. Online 00000264 | BISMILLAH TRADE', 'Jul-Dec 2024 statement', 1, 'Original classification retained'],
        [3, '2024-07-03', null, null, null, 'Unidentified - Cash Deposit', 0, 50000, 'Cash Deposit 12782622', 'Cash Deposit 12782622', 'Jul-Dec 2024 statement', 2, 'Yellow highlighted - unchanged'],
        [4, '2024-07-04', null, 'Dalda Foods', null, 'Dalda Foods', 90000.5, 0, 'Funds Transfer 123', 'Funds Transfer 123 | Dalda Foods', 'Jul-Dec 2024 statement', 2, 'Original classification retained'],
    ];
}

test('the preview reads the accountant sheet by header name and totals it', function () {
    $import = app(BankStatementImportService::class)->preview($this->fixture['bank'], statementFile(sampleRows()), [
        'control_total_debit' => 90000.5,
        'control_total_credit' => 322650,
    ], $this->user);

    expect($import->status)->toBe('previewed')
        ->and($import->preview['valid_rows'])->toBe(4)
        ->and($import->preview['total_credit'])->toEqual(322650)
        ->and($import->preview['total_debit'])->toEqual(90000.5)
        ->and($import->preview['needs_review_rows'])->toBe(1)
        ->and($import->preview['checks_ok'])->toBeTrue()
        ->and($import->period_from->toDateString())->toBe('2024-07-02')
        ->and($import->period_to->toDateString())->toBe('2024-07-04')
        ->and(BankStatementEntry::count())->toBe(0);
});

test('confirming imports the rows once and a second import skips them as duplicates', function () {
    $service = app(BankStatementImportService::class);

    $first = $service->commit($service->preview($this->fixture['bank'], statementFile(sampleRows()), [], $this->user), $this->user);
    expect($first->imported_rows)->toBe(4)->and(BankStatementEntry::count())->toBe(4);

    $second = $service->commit($service->preview($this->fixture['bank'], statementFile(sampleRows()), [], $this->user), $this->user);
    expect($second->imported_rows)->toBe(0)->and($second->skipped_rows)->toBe(4)->and(BankStatementEntry::count())->toBe(4);

    $entry = BankStatementEntry::where('reference', 'Cash Deposit 12782622')->first();
    expect($entry->needs_review)->toBeTrue()
        ->and($entry->status)->toBe(BankStatementEntry::STATUS_UNCLASSIFIED)
        ->and((float) $entry->credit)->toBe(50000.0)
        ->and($entry->source_statement)->toBe('Jul-Dec 2024 statement');
});

test('two identical lines in one file are both kept', function () {
    $row = [1, '2024-07-02', null, 'MEHBOOB GENERAL', null, 'MEHBOOB GENERAL', 0, 90000, 'CHQ Cr. Online 00000336', 'CHQ Cr. Online 00000336 | MEHBOOB GENERAL', 'Jul-Dec 2024 statement', 1, 'Original classification retained'];
    $service = app(BankStatementImportService::class);

    $import = $service->commit($service->preview($this->fixture['bank'], statementFile([$row, $row]), [], $this->user), $this->user);

    expect($import->imported_rows)->toBe(2);
});

test('the import is blocked when the control totals do not tally', function () {
    $service = app(BankStatementImportService::class);
    $import = $service->preview($this->fixture['bank'], statementFile(sampleRows()), ['control_total_credit' => 999], $this->user);

    expect($import->preview['checks_ok'])->toBeFalse();

    expect(fn () => $service->commit($import, $this->user))->toThrow(BankRegisterException::class);
    expect(BankStatementEntry::count())->toBe(0);
});

test('opening and closing balance must agree with the file, and running balances are filled in', function () {
    $service = app(BankStatementImportService::class);

    $import = $service->preview($this->fixture['bank'], statementFile(sampleRows()), ['opening_balance' => 1000, 'closing_balance' => 233649.5], $this->user);
    expect($import->preview['checks_ok'])->toBeTrue();

    $service->commit($import, $this->user);
    expect((float) BankStatementEntry::orderBy('id')->first()->running_balance)->toBe(133650.0)
        ->and((float) BankStatementEntry::orderByDesc('id')->first()->running_balance)->toBe(233649.5);
});

test('rows with problems are listed and skipped, not imported', function () {
    $rows = sampleRows();
    $rows[] = [5, 'not a date', null, 'X', null, 'X', 0, 10, 'ref', 'ref', 's', 1, 'Original'];
    $rows[] = [6, '2024-07-05', null, 'Y', null, 'Y', 5, 5, 'ref', 'ref', 's', 1, 'Original'];
    $rows[] = [7, '2024-07-05', null, 'Z', null, 'Z', 0, 0, 'ref', 'ref', 's', 1, 'Original'];

    $service = app(BankStatementImportService::class);
    $import = $service->preview($this->fixture['bank'], statementFile($rows), [], $this->user);

    expect($import->preview['error_rows'])->toBe(3)->and($import->preview['valid_rows'])->toBe(4);
});

test('a file without the expected header is rejected with a clear message', function () {
    $service = app(BankStatementImportService::class);

    expect(fn () => $service->preview($this->fixture['bank'], statementFile(sampleRows(), withHeader: false), [], $this->user))
        ->toThrow(BankRegisterException::class, 'layout was not recognised');

    expect(BankStatementImport::count())->toBe(0);
});

test('the import screens work end to end for a user with the import permission', function () {
    $user = bankUser(['bank-statement-import-list', 'bank-statement-import-create', 'bank-statement-entry-list']);
    $this->actingAs($user);

    $this->get(route('bank-statement-imports.create'))->assertOk()->assertSee('Import Bank Statement');

    $response = $this->post(route('bank-statement-imports.store'), [
        'bank_account_id' => $this->fixture['bank']->id,
        'file' => statementFile(sampleRows()),
        'control_total_credit' => 322650,
        'control_total_debit' => 90000.5,
    ]);

    $import = BankStatementImport::firstOrFail();
    $response->assertRedirect(route('bank-statement-imports.show', $import));

    $this->get(route('bank-statement-imports.show', $import))->assertOk()->assertSee('Tallies');

    $this->post(route('bank-statement-imports.confirm', $import))->assertRedirect();
    expect(BankStatementEntry::count())->toBe(4);

    $this->get(route('bank-statement-imports.index'))->assertOk()->assertSee('statement.xlsx');
});

test('a user without the import permission cannot import', function () {
    $this->actingAs(bankUser(['bank-statement-entry-list']));

    $this->get(route('bank-statement-imports.create'))->assertForbidden();
    $this->post(route('bank-statement-imports.store'), [])->assertForbidden();
});

test('a statement with one signed Amount column and day-first dates is understood', function () {
    $sheet = new Spreadsheet;
    $sheet->getActiveSheet()->fromArray([
        ['Date', 'Description', 'Amount', 'Cheque No'],
        ['15/10/2026', 'Deposit from retailer', 50000, '123456'],
        ['16-Oct-2026', 'Transfer to Dalda', -125000.5, ''],
        ['17.10.2026', 'Service charge', '-1,250.00', ''],
    ], null, 'A1');
    $path = tempnam(sys_get_temp_dir(), 'amt').'.xlsx';
    (new Xlsx($sheet))->save($path);

    $import = app(BankStatementImportService::class)->preview($this->fixture['bank'], new UploadedFile($path, 'amount.xlsx', null, null, true), [], $this->user);

    expect($import->preview['valid_rows'])->toBe(3)->and($import->preview['error_rows'])->toBe(0)
        ->and($import->preview['total_credit'])->toEqual(50000)
        ->and($import->preview['total_debit'])->toEqual(126250.5)
        ->and($import->preview['date_from'])->toBe('2026-10-15')->and($import->preview['date_to'])->toBe('2026-10-17');
});

test('a statement with Dr/Cr marker, a CSV file and Pakistani column names is understood', function () {
    $path = tempnam(sys_get_temp_dir(), 'csv').'.csv';
    file_put_contents($path, "Txn Date,Narration,Amount,Dr/Cr\n05/10/2026,CHQ DEPOSIT 5521,275000,CR\n06/10/2026,FUNDS TRANSFER,90000,DR\n");

    $import = app(BankStatementImportService::class)->preview($this->fixture['bank'], new UploadedFile($path, 'bank.csv', null, null, true), [], $this->user);

    expect($import->preview['total_credit'])->toEqual(275000)->and($import->preview['total_debit'])->toEqual(90000);

    $committed = app(BankStatementImportService::class)->commit($import, $this->user);
    expect($committed->imported_rows)->toBe(2)->and(BankStatementEntry::where('narration', 'FUNDS TRANSFER')->first()->direction)->toBe('debit');
});
