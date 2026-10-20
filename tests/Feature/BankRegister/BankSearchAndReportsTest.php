<?php

use App\Models\Attachment;
use App\Models\BankEntryCategory;
use App\Models\BankStatementEntry;
use App\Models\BankStatementImport;
use App\Models\Company;
use App\Services\BankRegister\BankReportService;
use App\Services\BankRegister\BankStatementSearch;
use Illuminate\Database\Eloquent\Builder;

beforeEach(function () {
    $this->fixture = bankRegisterFixture();
    $this->search = app(BankStatementSearch::class);

    $this->unilever = $this->fixture['company'];
    $this->dalda = Company::factory()->create(['company_name' => 'Dalda Foods']);
    $this->charges = BankEntryCategory::where('slug', 'bank-charges')->first();

    $make = fn (array $a) => BankStatementEntry::factory()->create($a + ['bank_account_id' => $this->fixture['bank']->id]);

    $this->e1 = $make(['posting_date' => '2024-07-02', 'company_id' => $this->unilever->id, 'bank_entry_category_id' => $this->fixture['category']->id, 'credit' => 500000, 'debit' => 0, 'reference' => 'CHQ 00000264', 'narration' => 'Online Deposit | NAVEED ANJUM', 'party_name_in_statement' => 'NAVEED ANJUM']);
    $this->e2 = $make(['posting_date' => '2024-08-15', 'company_id' => $this->dalda->id, 'bank_entry_category_id' => $this->charges->id, 'credit' => 0, 'debit' => 1500000, 'reference' => 'Funds Transfer 9', 'narration' => 'Funds Transfer | Dalda Foods', 'party_name_in_statement' => 'Dalda Foods']);
    $this->e3 = $make(['posting_date' => '2024-08-20', 'credit' => 70000, 'debit' => 0, 'reference' => 'Cash Deposit 1', 'narration' => 'مقامی جمع', 'party_name_in_statement' => 'احمد علی', 'needs_review' => true]);

    Attachment::factory()->create(['journal_entry_id' => null, 'attachable_type' => BankStatementEntry::class, 'attachable_id' => $this->e1->id, 'file_name' => 'slip-unilever.pdf', 'description' => 'Deposit slip for July']);
});

function ids(Builder $query): array
{
    return $query->orderBy('id')->pluck('id')->all();
}

test('search by date range', function () {
    expect(ids($this->search->query(['date_from' => '2024-08-01', 'date_to' => '2024-08-31'])))->toBe([$this->e2->id, $this->e3->id])
        ->and(ids($this->search->query(['date_to' => '2024-07-31'])))->toBe([$this->e1->id]);
});

test('search by company, category and direction', function () {
    expect(ids($this->search->query(['company_id' => $this->dalda->id])))->toBe([$this->e2->id])
        ->and(ids($this->search->query(['company_id' => 'none'])))->toBe([$this->e3->id])
        ->and(ids($this->search->query(['category_id' => $this->charges->id])))->toBe([$this->e2->id])
        ->and(ids($this->search->query(['direction' => 'credit'])))->toBe([$this->e1->id, $this->e3->id])
        ->and(ids($this->search->query(['direction' => 'debit'])))->toBe([$this->e2->id]);
});

test('search by amount range works for both directions', function () {
    expect(ids($this->search->query(['amount_min' => 1000000])))->toBe([$this->e2->id])
        ->and(ids($this->search->query(['amount_min' => 60000, 'amount_max' => 600000])))->toBe([$this->e1->id, $this->e3->id]);
});

test('free text search covers party, narration, reference, company and attachments and works with Urdu', function () {
    expect(ids($this->search->query(['q' => 'naveed'])))->toBe([$this->e1->id])
        ->and(ids($this->search->query(['q' => 'chq 00000264'])))->toBe([$this->e1->id])
        ->and(ids($this->search->query(['q' => 'dalda'])))->toBe([$this->e2->id])
        ->and(ids($this->search->query(['q' => 'slip-unilever'])))->toBe([$this->e1->id])
        ->and(ids($this->search->query(['q' => 'deposit slip for july'])))->toBe([$this->e1->id])
        ->and(ids($this->search->query(['q' => 'احمد'])))->toBe([$this->e3->id])
        ->and(ids($this->search->query(['q' => 'Unilever'])))->toBe([$this->e1->id]);
});

test('search by attachment, review flag and reference', function () {
    expect(ids($this->search->query(['has_attachment' => 'yes'])))->toBe([$this->e1->id])
        ->and(ids($this->search->query(['has_attachment' => 'no'])))->toBe([$this->e2->id, $this->e3->id])
        ->and(ids($this->search->query(['needs_review' => 'yes'])))->toBe([$this->e3->id])
        ->and(ids($this->search->query(['reference' => 'transfer'])))->toBe([$this->e2->id]);
});

test('filters combine', function () {
    expect(ids($this->search->query(['date_from' => '2024-08-01', 'direction' => 'credit', 'q' => 'احمد'])))->toBe([$this->e3->id])
        ->and(ids($this->search->query(['company_id' => $this->dalda->id, 'direction' => 'credit'])))->toBe([]);
});

test('the status summary counts and adds up per status', function () {
    $summary = $this->search->statusSummary([]);

    expect($summary['unclassified']['count'])->toBe(3)
        ->and($summary['unclassified']['amount'])->toBe(2070000.0);
});

test('company month summary pivots by month and compares with the statement control total', function () {
    BankStatementImport::create([
        'bank_account_id' => $this->fixture['bank']->id, 'file_name' => 'x.xlsx', 'status' => 'imported',
        'control_total_credit' => 570000, 'control_total_debit' => 1500000,
    ]);

    $report = app(BankReportService::class)->build('company-month-summary', ['bank_account_id' => $this->fixture['bank']->id, 'type' => 'credit']);

    expect($report['headings'])->toBe(['Party / company', 'Entries', 'Total (PKR)', 'Jul-24', 'Aug-24'])
        ->and($report['rows'])->toHaveCount(2)
        ->and($report['rows'][0])->toBe(['Unilever', 1, 500000.0, 500000.0, 0.0])
        ->and($report['rows'][1][0])->toBe('احمد علی')
        ->and($report['totals'])->toBe(['Total', 2, 570000.0, 500000.0, 70000.0])
        ->and($report['notes'][0])->toContain('Difference: 0.00');
});

test('category month summary and cash flow add up', function () {
    $service = app(BankReportService::class);

    $byCategory = $service->build('category-month-summary', ['type' => 'debit']);
    expect($byCategory['rows'][0][0])->toBe('Bank Charges')->and($byCategory['totals'][2])->toBe(1500000.0);

    $flow = $service->build('cash-flow', []);
    expect($flow['totals'][2])->toBe(570000.0)->and($flow['totals'][3])->toBe(1500000.0)->and($flow['totals'][4])->toBe(-930000.0);
});

test('company ledger runs a balance', function () {
    $report = app(BankReportService::class)->build('company-ledger', ['company_id' => $this->unilever->id]);

    expect($report['rows'])->toHaveCount(1)->and($report['rows'][0][5])->toBe(500000.0);
});

test('every report builds and renders for a user who may see it', function () {
    $user = bankUser(array_values(array_map(fn ($r) => $r['permission'], BankReportService::REPORTS)));
    $this->actingAs($user);

    $this->get(route('reports.bank.index'))->assertOk()->assertSee('Company-wise month-wise summary');

    foreach (array_keys(BankReportService::REPORTS) as $slug) {
        $this->get(route('reports.bank.show', $slug))->assertOk();
        $this->get(route('reports.bank.export.excel', $slug))->assertOk();
        $this->get(route('reports.bank.export.pdf', $slug))->assertOk();
    }
});

test('a report is forbidden without its permission', function () {
    $this->actingAs(bankUser(['report-bank-cash-flow']));

    $this->get(route('reports.bank.show', 'cash-flow'))->assertOk();
    $this->get(route('reports.bank.show', 'audit-log'))->assertForbidden();
    $this->get(route('reports.bank.show', 'nonsense'))->assertNotFound();
});
