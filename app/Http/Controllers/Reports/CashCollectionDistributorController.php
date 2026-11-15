<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\ChequeClearing;
use App\Models\Employee;
use App\Models\ExpenseVendorPayment;
use App\Models\ExpenseVoucher;
use App\Models\PayrollRun;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementCheque;
use App\Models\Supplier;
use App\Support\CashNotes;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Daily cash collection by distribution (company): cash, cheque clearing, bank slips, bank transfers,
 * cheques and the bank slip detail. Admins, super admins and holders of the all-companies permission
 * choose any company or "All"; everyone else is held to the company on their own account.
 */
class CashCollectionDistributorController extends Controller implements HasMiddleware
{
    private const ALL_COMPANIES = 'all';

    public static function middleware(): array
    {
        return [
            new Middleware('can:report-audit-cash-collection-distributor'),
        ];
    }

    public function index(Request $request)
    {
        $data = $this->reportData($request);

        if ($request->query('layout') === 'print' && $data['hasQuery']) {
            return view('reports.cash-collection-distributor.pdf', $data + ['orientation' => 'portrait', 'isPdf' => false]);
        }

        return view('reports.cash-collection-distributor', $data);
    }

    public function help(): View
    {
        return view('reports.cash-collection-distributor.help');
    }

    /**
     * A4 PDF of the same sheet, portrait or landscape (?orientation=).
     */
    public function exportPdf(Request $request): Response
    {
        $data = $this->reportData($request);
        abort_unless($data['hasQuery'], 422, 'Select a company and apply the filters before downloading.');

        $orientation = $request->query('orientation') === 'landscape' ? 'landscape' : 'portrait';

        $pdf = Pdf::loadView('reports.cash-collection-distributor.pdf', $data + ['orientation' => $orientation])
            ->setPaper('a4', $orientation);
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $canvas->page_text($canvas->get_width() - 80, $canvas->get_height() - 25, 'Page {PAGE_NUM} of {PAGE_COUNT}', $dompdf->getFontMetrics()->getFont('Helvetica'), 7);

        return $pdf->download('cash-collection-distributor-'.Carbon::parse($data['date'])->format('Y-m-d').'.pdf');
    }

    /**
     * @return array<string, mixed>
     */
    private function reportData(Request $request): array
    {
        $user = $request->user();
        $canChooseCompany = $user->can('report-audit-cash-collection-distributor-all-companies') || $user->isTicketAdmin();
        $ownSupplierId = $user->supplier_id ? (int) $user->supplier_id : null;

        $requested = (string) $request->input('supplier_id', '');
        $allCompanies = false;
        $supplierId = null;

        if ($canChooseCompany) {
            $allCompanies = $requested === self::ALL_COMPANIES;
            $supplierId = ctype_digit($requested) ? (int) $requested : null;
        } else {
            abort_if($requested !== '' && (! ctype_digit($requested) || (int) $requested !== $ownSupplierId), 403, 'You can only see the report of your own company.');
            $supplierId = $requested !== '' ? $ownSupplierId : null;
        }

        $hasQuery = $allCompanies || $supplierId !== null;
        $date = (string) $request->input('date', now()->format('Y-m-d'));
        if (Validator::make(['date' => $date], ['date' => 'date_format:Y-m-d'])->fails()) {
            $date = now()->format('Y-m-d');
            $request->session()->now('warning', 'The date was not valid, so today is shown.');
        }
        $designation = $request->input('designation', 'Salesman');
        $employeeIds = array_filter((array) $request->input('employee_ids', []));

        $suppliers = Supplier::query()
            ->when(! $canChooseCompany, fn ($query) => $query->where('id', $ownSupplierId ?? 0))
            ->orderBy('supplier_name')
            ->get();
        $designations = Employee::distinct()->whereNotNull('designation')->orderBy('designation')->pluck('designation');
        $companyLabel = $allCompanies ? 'All Companies' : (string) $suppliers->firstWhere('id', $supplierId)?->supplier_name;

        $base = [
            'date' => $date,
            'supplierId' => $supplierId,
            'allCompanies' => $allCompanies,
            'canChooseCompany' => $canChooseCompany,
            'hasQuery' => $hasQuery,
            'companyLabel' => $companyLabel,
            'designation' => $designation,
            'employeeIds' => $employeeIds,
            'suppliers' => $suppliers,
            'designations' => $designations,
            'allEmployees' => collect(),
            'kpis' => [],
            'tables' => [],
        ];

        if (! $hasQuery) {
            return $base;
        }

        $allEmployees = Employee::query()
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->when($designation, fn ($query) => $query->where('designation', $designation))
            ->orderBy('name')
            ->get();

        $settlements = $this->settlementsOn($date, $supplierId, $designation, $employeeIds);
        $clearedCheques = $this->clearedChequesOn($date, $supplierId, $designation, $employeeIds);
        // A cheque the bank cleared brought no cash back; it is shown on its own as cleared into bank.
        [$intoBank, $clearings] = $clearedCheques->partition(fn (SalesSettlementCheque $cheque) => $cheque->cleared_via === 'bank');

        $companyIds = $settlements->pluck('company_id')->merge($clearedCheques->pluck('company_id'))->when($supplierId, fn ($ids) => $ids->push($supplierId))->filter()->unique();
        $companies = Supplier::whereIn('id', $companyIds)->orderBy('supplier_name')->get(['id', 'supplier_name', 'short_name']);

        $perCompany = $companies->map(function (Supplier $company) use ($settlements, $clearings, $intoBank) {
            $own = $settlements->where('company_id', $company->id);

            return (object) [
                'name' => $company->supplier_name,
                'cash' => $own->sum(fn ($s) => $this->cashOf($s)),
                'clearing' => (float) $clearings->where('company_id', $company->id)->sum(fn (SalesSettlementCheque $cheque) => $cheque->clearedAmountOrFull()),
                'slips' => (float) $own->sum(fn ($s) => $s->bankSlips->sum('amount')),
                'transfers' => (float) $own->sum(fn ($s) => $this->onlineOf($s)),
                'cheques' => (float) $own->sum(fn ($s) => $s->cheques->sum('amount')),
                'intoBank' => (float) $intoBank->where('company_id', $company->id)->sum(fn (SalesSettlementCheque $cheque) => $cheque->clearedAmountOrFull()),
            ];
        })->each(fn ($row) => $row->collection = $row->cash + $row->slips + $row->transfers + $row->cheques)->values();

        $totals = [
            'cash' => (float) $perCompany->sum('cash'),
            'clearing' => (float) $perCompany->sum('clearing'),
            'slips' => (float) $perCompany->sum('slips'),
            'transfers' => (float) $perCompany->sum('transfers'),
            'cheques' => (float) $perCompany->sum('cheques'),
            'intoBank' => (float) $perCompany->sum('intoBank'),
        ];
        $collectionTotal = $totals['cash'] + $totals['slips'] + $totals['transfers'] + $totals['cheques'];

        $money = fn ($value) => $value > 0 ? number_format((float) $value, 2) : '-';
        $signed = fn ($value) => $this->signedMoney((float) $value);
        $total = fn ($value) => number_format($this->cleanZero((float) $value), 2);

        $tables = [
            'collection' => [
                'no' => 1, 'title' => 'Collection by Distribution', 'explain' => 'total = cash + bank slips + online + cheques received',
                'cols' => [['#', 'c', 4], ['Distribution', '', 20], ['Cash', 'n', 11], ['Cheque clearing', 'n', 11], ['Bank slips', 'n', 11], ['Online', 'n', 11], ['Cheques received', 'n', 11], ['Cleared into bank', 'n', 10], ['Total', 'n', 11]],
                'rows' => $perCompany->map(fn ($row, $i) => [$i + 1, $row->name, $money($row->cash), $money($row->clearing), $money($row->slips), $money($row->transfers), $money($row->cheques), $money($row->intoBank), $money($row->collection)])->all(),
                'total' => ['label' => 'Total', 'span' => 2, 'cells' => [$total($totals['cash']), $total($totals['clearing']), $total($totals['slips']), $total($totals['transfers']), $total($totals['cheques']), $total($totals['intoBank']), $total($collectionTotal)]],
                'bold' => [8], 'empty' => 'No collection for the selected filters.',
            ],
            'slips' => $this->slipTable($settlements, $companies, $money, $total),
        ];

        // Only money that really left the cash box today: cash-method vouchers (advances included), cash payments
        // to shops and cash salaries. A voucher reversed on a later day comes back on that day as a negative line.
        $voucherCash = $this->cashVouchers($supplierId, $date);
        $vouchers = $voucherCash['rows'];
        $vendorPayments = $this->vendorPaymentRows($supplierId, $date);
        $salaries = $this->salaryRows($supplierId, $date);
        $expenses = $vouchers->concat($vendorPayments)->concat($salaries)->values();
        $expenseTotal = (float) $expenses->sum('amount');
        $tables['expenses'] = [
            'no' => 4, 'title' => 'Expenses', 'explain' => 'cash paid out today: vouchers, vendor payments and salaries',
            'cols' => [['#', 'c', 8], ['Party Name', '', 46], ['Distribution', '', 24], ['Amount', 'n', 22]],
            'rows' => $expenses->map(fn ($row, $i) => [$i + 1, $row['party'], $row['company'], $signed($row['amount'])])->all(),
            'total' => ['label' => 'Total expenses', 'span' => 3, 'cells' => [$total($expenseTotal)]],
            'bold' => [3], 'empty' => 'No cash expenses for this day.',
        ];

        $cashReceived = $totals['cash'] + $totals['clearing'];
        $cashInHand = $cashReceived - $expenseTotal;
        $notesIn = $this->notesIn($settlements, $clearings, $supplierId, $date);
        $tables['denominations'] = $this->denominationTable(
            $notesIn['notes'],
            $notesIn['group'],
            $voucherCash['notes'],
            $cashReceived,
            $expenseTotal,
            $signed,
            $total,
        );

        $tables['summary'] = [
            'no' => 5, 'title' => 'Cash Summary', 'explain' => 'how the day adds up',
            'cols' => [['Item', '', 68], ['Amount (Rs.)', 'n', 32]],
            'rows' => [
                ['Notes and coins handed in (settlements)', $total($totals['cash'])],
                ['Cheque clearing cash', $total($totals['clearing'])],
                ['Cash received', $total($cashReceived)],
                ['Less: expenses paid (cash and advance vouchers, net of returned)', $total($vouchers->sum('amount'))],
                ['Less: vendor payments (cash)', $total($vendorPayments->sum('amount'))],
                ['Less: salaries paid (cash)', $total($salaries->sum('amount'))],
                ['Cash in hand', $total($cashInHand)],
                ['Bank slips', $total($totals['slips'])],
                ['Bank transfers / online from customer', $total($totals['transfers'])],
                ['Cheques received', $total($totals['cheques'])],
                ['Cheques cleared into bank', $total($totals['intoBank'])],
            ],
            'total' => ['label' => 'Total collection (cash + slips + online + cheques received)', 'span' => 1, 'cells' => [$total($collectionTotal)]],
            'bold' => [1], 'empty' => '',
        ];

        if (! $allCompanies) {
            $tables['salesmen'] = $this->salesmanTable($settlements, $money, $total);
        }

        return array_merge($base, [
            'allEmployees' => $allEmployees,
            'kpis' => [
                ['Cash (settlement)', $total($totals['cash'])],
                ['Cheque clearing', $total($totals['clearing'])],
                ['Bank slips', $total($totals['slips'])],
                ['Bank transfers / online from customer', $total($totals['transfers'])],
                ['Cheques received', $total($totals['cheques'])],
                ['Cash in hand', $total($cashInHand)],
                ['Total collection', $total($collectionTotal), true],
            ],
            'tables' => $tables,
        ]);
    }

    /**
     * The net notes of the day for one company (or all companies when none is given), every salesman included: the
     * "Net notes" column of the Cash Denominations table. The expense voucher approve dialog warns with it.
     */
    public function notesOnHand(?int $supplierId, string $date): CashNotes
    {
        $clearings = $this->clearedChequesOn($date, $supplierId, null, [])
            ->reject(fn (SalesSettlementCheque $cheque) => $cheque->cleared_via === 'bank');
        $notesIn = $this->notesIn($this->settlementsOn($date, $supplierId, null, []), $clearings, $supplierId, $date);

        return $notesIn['notes']->minus($this->cashVouchers($supplierId, $date)['notes']);
    }

    /**
     * Posted settlements of the day, each tagged with its company (its own, else its salesman's).
     *
     * @param  array<int, int|string>  $employeeIds
     * @return Collection<int, SalesSettlement>
     */
    private function settlementsOn(string $date, ?int $supplierId, ?string $designation, array $employeeIds): Collection
    {
        return $this->ofSalesmen(SalesSettlement::query(), $designation, $employeeIds)
            ->with(['employee:id,name,supplier_id,designation', 'cashDenominations', 'bankSlips.bankAccount', 'bankTransfers', 'recoveries', 'cheques'])
            ->where('status', 'posted')
            ->whereDate('settlement_date', $date)
            ->get()
            ->each(fn (SalesSettlement $settlement) => $settlement->setAttribute('company_id', (int) ($settlement->supplier_id ?? $settlement->employee?->supplier_id)))
            ->when($supplierId, fn (Collection $rows) => $rows->where('company_id', $supplierId));
    }

    /**
     * Cheques cleared on the day (cash and bank), each tagged with its settlement's company.
     *
     * @param  array<int, int|string>  $employeeIds
     * @return Collection<int, SalesSettlementCheque>
     */
    private function clearedChequesOn(string $date, ?int $supplierId, ?string $designation, array $employeeIds): Collection
    {
        return SalesSettlementCheque::query()
            ->with('salesSettlement.employee:id,name,supplier_id,designation')
            ->where('status', 'cleared')
            ->whereDate('cleared_date', $date)
            ->whereHas('salesSettlement', fn ($query) => $this->ofSalesmen($query, $designation, $employeeIds))
            ->get()
            ->each(fn (SalesSettlementCheque $cheque) => $cheque->setAttribute('company_id', (int) ($cheque->salesSettlement->supplier_id ?? $cheque->salesSettlement->employee?->supplier_id)))
            ->when($supplierId, fn (Collection $rows) => $rows->where('company_id', $supplierId));
    }

    /**
     * Narrow a settlement query to the chosen designation and salesmen.
     *
     * @param  array<int, int|string>  $employeeIds
     */
    private function ofSalesmen(Builder $query, ?string $designation, array $employeeIds): Builder
    {
        return $query
            ->when($designation, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('designation', $designation)))
            ->when($employeeIds !== [], fn ($q) => $q->whereIn('employee_id', $employeeIds));
    }

    /**
     * Money that came straight from customers into a bank account: the settlement's bank transfers
     * (Bank Transfers / Online From Customer) and the recoveries received by bank instead of cash.
     */
    private function onlineOf(SalesSettlement $settlement): float
    {
        return (float) $settlement->bankTransfers->sum('amount')
            + (float) $settlement->recoveries->where('payment_method', '!=', 'cash')->sum('amount');
    }

    private function cashOf(SalesSettlement $settlement): float
    {
        $denomination = $settlement->cashDenominations->first();

        return ($denomination && $denomination->total_amount > 0)
            ? (float) $denomination->total_amount
            : (float) $settlement->cash_collected;
    }

    /**
     * The notes counted into the cash box on the day: each settlement's count, the notes of each cash clearing
     * batch of the day, and the notes counted on old cheques cleared one by one (before batches). A batch's notes
     * count only when every cheque in it is inside the filter; when the filter shows only part of a batch, its
     * notes cannot be split, so the in-scope cheques are returned as one amount ("group") instead.
     *
     * @param  Collection<int, SalesSettlement>  $settlements
     * @param  Collection<int, SalesSettlementCheque>  $clearings  cheques cleared for cash that day, in scope
     * @return array{notes: CashNotes, group: float}
     */
    private function notesIn(Collection $settlements, Collection $clearings, ?int $supplierId, string $date): array
    {
        $group = 0.0;
        $notes = CashNotes::fromInput([]);

        foreach ($settlements as $settlement) {
            $denomination = $settlement->cashDenominations->first();
            if ($denomination) {
                $notes = $notes->plus(CashNotes::fromColumns($denomination));
            }
        }

        $batchIds = $clearings->pluck('cheque_clearing_id')->filter()->unique()->values();
        if ($batchIds->isNotEmpty()) {
            ChequeClearing::query()
                ->whereIn('id', $batchIds)
                ->where('cleared_via', 'cash')
                ->whereDate('cleared_date', $date)
                ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
                ->withCount('cheques')
                ->get()
                ->each(function (ChequeClearing $batch) use (&$notes, &$group, $clearings) {
                    $inScope = $clearings->where('cheque_clearing_id', $batch->id);
                    if ($inScope->count() === $batch->cheques_count) {
                        $notes = $notes->plus(CashNotes::fromColumns($batch));
                    } else {
                        $group += (float) $inScope->sum(fn (SalesSettlementCheque $cheque) => $cheque->clearedAmountOrFull());
                    }
                });
        }

        foreach ($clearings->whereNull('cheque_clearing_id') as $cheque) {
            $notes = $notes->plus(CashNotes::fromColumns($cheque, 'cleared_denom_'));
        }

        return ['notes' => $notes, 'group' => round($group, 2)];
    }

    /**
     * Notes in, notes out and net per note. Cash received that was not counted note by note and cash paid out
     * with no notes counted each get a line of their own, as do cheques of a batch only partly inside the filter,
     * so the net total always equals the cash in hand.
     *
     * @return array<string, mixed>
     */
    private function denominationTable(CashNotes $in, float $group, CashNotes $out, float $cashReceived, float $expenseTotal, callable $signed, callable $total): array
    {
        $net = $in->minus($out);
        $notCountedIn = round($cashReceived - $in->total() - $group, 2);
        $notCountedOut = round($expenseTotal - $out->total(), 2);
        $count = fn (int $notes) => $notes === 0 ? '-' : $notes;

        $rows = [];
        foreach (CashNotes::NOTES as $note) {
            $rows[] = [
                number_format($note),
                $count($in->count($note)), $signed($in->count($note) * $note),
                $count($out->count($note)), $signed($out->count($note) * $note),
                $count($net->count($note)), $signed($net->count($note) * $note),
            ];
        }
        $rows[] = ['Coins', '-', $signed($in->coins()), '-', $signed($out->coins()), '-', $signed($net->coins())];
        $rows[] = ['Group clearing (other salesmen)', '-', $signed($group), '-', '-', '-', $signed($group)];
        $rows[] = ['Cash not counted', '-', $signed($notCountedIn), '-', '-', '-', $signed($notCountedIn)];
        $rows[] = ['Paid out, no note count', '-', '-', '-', $signed($notCountedOut), '-', $signed(-$notCountedOut)];

        return [
            'no' => 3, 'title' => 'Cash Denominations', 'explain' => 'net total = cash in hand',
            'cols' => [['Denomination', 'c', 20], ['Notes in', 'c', 10], ['In (Rs.)', 'n', 17], ['Notes out', 'c', 10], ['Out (Rs.)', 'n', 17], ['Net notes', 'c', 10], ['Net (Rs.)', 'n', 16]],
            'rows' => $rows,
            'total' => ['label' => 'Total', 'span' => 1, 'cells' => [
                '', $total($in->total() + $group + $notCountedIn),
                '', $total($out->total() + $notCountedOut),
                '', $total($net->total() + $group + $notCountedIn - $notCountedOut),
            ]],
            'bold' => [6], 'empty' => '',
        ];
    }

    /**
     * An amount with its sign, or a dash for nothing.
     */
    private function signedMoney(float $value): string
    {
        $value = $this->cleanZero($value);

        return $value == 0 ? '-' : number_format($value, 2);
    }

    /**
     * Rounds to paisa and turns -0 into 0, so a balanced figure never prints as "-0.00".
     */
    private function cleanZero(float $value): float
    {
        $value = round($value, 2);

        return abs($value) < 0.005 ? 0.0 : $value;
    }

    /**
     * One row per distribution and salesman, one column per bank account the slips were submitted to
     * (a salesman may use more than one account), so every amount sits under its account.
     *
     * @param  Collection<int, SalesSettlement>  $settlements
     * @param  Collection<int, Supplier>  $companies
     * @return array<string, mixed>
     */
    private function slipTable(Collection $settlements, Collection $companies, callable $money, callable $total): array
    {
        $slips = $settlements->flatMap(fn (SalesSettlement $settlement) => $settlement->bankSlips->map(fn ($slip) => [
            'company' => (string) $companies->firstWhere('id', $settlement->company_id)?->supplier_name,
            'salesman' => (string) $settlement->employee?->name,
            'account' => (int) $slip->bank_account_id,
            'label' => trim(($slip->bankAccount?->bank_name ?? '')."\n".($slip->bankAccount?->account_number ?? '')),
            'amount' => (float) $slip->amount,
        ]));

        $accounts = $slips->unique('account')->sortBy('label')->pluck('label', 'account');
        $rows = $slips->groupBy(fn ($slip) => $slip['company']."\0".$slip['salesman'])->sortKeys()->values();
        $width = $accounts->isEmpty() ? 0 : round(50 / $accounts->count(), 2);

        $cols = [['#', 'c', 4], ['Distribution', '', $accounts->isEmpty() ? 40 : 16], ['Salesman', '', $accounts->isEmpty() ? 40 : 18]];
        foreach ($accounts as $label) {
            $cols[] = [$label, 'n', $width];
        }
        $cols[] = ['Total', 'n', $accounts->isEmpty() ? 16 : 12];

        $tableRows = $rows->map(function (Collection $own, int $i) use ($accounts, $money) {
            $cells = $accounts->keys()->map(fn ($id) => $money($own->where('account', $id)->sum('amount')))->all();

            return [$i + 1, $own->first()['company'], $own->first()['salesman'], ...$cells, $money($own->sum('amount'))];
        })->all();

        $columnTotals = $accounts->keys()->map(fn ($id) => $total($slips->where('account', $id)->sum('amount')))->all();

        return [
            'no' => 2, 'title' => 'Bank Slip Details', 'explain' => 'distribution, salesman and the bank account each amount was submitted to',
            'cols' => $cols,
            'rows' => $tableRows,
            'total' => ['label' => 'Total bank slips', 'span' => 3, 'cells' => [...$columnTotals, $total($slips->sum('amount'))]],
            'bold' => [count($cols) - 1], 'empty' => 'No bank slips for the selected filters.',
        ];
    }

    /**
     * Cash-method expense vouchers (advances included) that moved cash on the day, by party, with the notes the
     * approver counted out. A voucher reversed on the day it was dated never moved cash and is left out. A voucher
     * reversed on a later day stays on its own day and comes back on the reversal day as "Returned: {number}",
     * a negative line whose notes go back into the box. A single company sees its own vouchers; All Companies
     * also includes the general Moon Traders ones that belong to no company.
     *
     * @return array{rows: Collection<int, array{party: string, company: string, amount: float}>, notes: CashNotes}
     */
    private function cashVouchers(?int $supplierId, string $date): array
    {
        $candidates = ExpenseVoucher::query()
            ->with(['supplier:id,supplier_name,short_name', 'cashPayout'])
            ->where('payment_method', ExpenseVoucher::METHOD_CASH)
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->where(fn ($query) => $query
                ->where(fn ($paid) => $paid->whereIn('status', [ExpenseVoucher::STATUS_APPROVED, ExpenseVoucher::STATUS_REVERSED])->whereDate('voucher_date', $date))
                ->orWhere(fn ($returned) => $returned->where('status', ExpenseVoucher::STATUS_REVERSED)->whereDate('reversed_at', $date)))
            ->get();

        $reversedLater = fn (ExpenseVoucher $voucher) => $voucher->reversed_at !== null
            && $voucher->reversed_at->toDateString() > $voucher->voucher_date->toDateString();

        $paid = $candidates->filter(fn (ExpenseVoucher $voucher) => $voucher->voucher_date->toDateString() === $date
            && ($voucher->status === ExpenseVoucher::STATUS_APPROVED || $reversedLater($voucher)));
        $returned = $candidates->filter(fn (ExpenseVoucher $voucher) => $voucher->status === ExpenseVoucher::STATUS_REVERSED
            && $reversedLater($voucher) && $voucher->reversed_at->toDateString() === $date);

        $notesOf = fn (ExpenseVoucher $voucher) => $voucher->cashPayout ? CashNotes::fromColumns($voucher->cashPayout) : CashNotes::fromInput([]);
        $notes = CashNotes::fromInput([]);
        foreach ($paid as $voucher) {
            $notes = $notes->plus($notesOf($voucher));
        }
        foreach ($returned as $voucher) {
            $notes = $notes->minus($notesOf($voucher));
        }

        $paidRows = $paid
            ->groupBy(fn (ExpenseVoucher $voucher) => $voucher->party_name."\0".$voucher->payment_type."\0".$voucher->supplier_id)
            ->map(fn (Collection $own) => [
                'party' => $own->first()->party_name.($own->first()->isAdvance() ? ' (advance salary)' : ''),
                'company' => $this->companyName($own->first()->supplier),
                'amount' => (float) $own->sum('amount'),
            ])
            ->sortByDesc('amount')
            ->values();

        $returnedRows = $returned
            ->sortBy('voucher_number')
            ->map(fn (ExpenseVoucher $voucher) => [
                'party' => 'Returned: '.$voucher->voucher_number,
                'company' => $this->companyName($voucher->supplier),
                'amount' => -1 * (float) $voucher->amount,
            ])
            ->values();

        return ['rows' => $paidRows->concat($returnedRows)->values(), 'notes' => $notes];
    }

    /**
     * The distribution an expense belongs to; one with no company is a general Moon Traders expense.
     */
    private function companyName(?Supplier $supplier): string
    {
        return (string) ($supplier?->short_name ?? $supplier?->supplier_name ?? 'Moon Traders (general)');
    }

    /**
     * Approved payments to shops, made in cash on the day.
     *
     * @return Collection<int, array{party: string, company: string, amount: float}>
     */
    private function vendorPaymentRows(?int $supplierId, string $date): Collection
    {
        return ExpenseVendorPayment::query()
            ->with(['vendor:id,name', 'supplier:id,supplier_name,short_name', 'company:id,company_name,abbr'])
            ->where('status', ExpenseVendorPayment::STATUS_APPROVED)
            ->where('method', 'cash')
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->whereDate('payment_date', $date)
            ->get()
            ->map(fn (ExpenseVendorPayment $payment) => [
                'party' => 'Vendor payment - '.$payment->vendor?->name,
                'company' => (string) ($payment->supplier?->short_name ?? $payment->supplier?->supplier_name ?? $payment->company?->company_name ?? 'Moon Traders (general)'),
                'amount' => (float) $payment->amount,
            ])
            ->values();
    }

    /**
     * Salaries paid in cash on the day.
     *
     * @return Collection<int, array{party: string, company: string, amount: float}>
     */
    private function salaryRows(?int $supplierId, string $date): Collection
    {
        return PayrollRun::query()
            ->with(['supplier:id,supplier_name,short_name', 'company:id,company_name,abbr'])
            ->where('status', PayrollRun::STATUS_PAID)
            ->where('paid_method', 'cash')
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->whereDate('paid_on', $date)
            ->get()
            ->map(fn (PayrollRun $run) => [
                'party' => 'Salaries - '.$run->period_month->format('M Y'),
                'company' => (string) ($run->supplier?->short_name ?? $run->supplier?->supplier_name ?? $run->company?->company_name ?? 'Moon Traders (general)'),
                'amount' => (float) $run->total_net,
            ])
            ->values();
    }

    /**
     * @param  Collection<int, SalesSettlement>  $settlements
     * @return array<string, mixed>
     */
    private function salesmanTable(Collection $settlements, callable $money, callable $total): array
    {
        $rows = $settlements->groupBy('employee_id')->map(function (Collection $own) {
            $row = [
                'name' => (string) $own->first()->employee?->name,
                'cash' => $own->sum(fn ($s) => $this->cashOf($s)),
                'slips' => (float) $own->sum(fn ($s) => $s->bankSlips->sum('amount')),
                'transfers' => (float) $own->sum(fn ($s) => $this->onlineOf($s)),
                'cheques' => (float) $own->sum(fn ($s) => $s->cheques->sum('amount')),
            ];
            $row['total'] = $row['cash'] + $row['slips'] + $row['transfers'] + $row['cheques'];

            return $row;
        })->sortByDesc('total')->values();

        return [
            'no' => 6, 'title' => 'Salesman-wise Detail', 'explain' => 'settlement collections of the selected company',
            'cols' => [['#', 'c', 4], ['Salesman', '', 30], ['Cash', 'n', 14], ['Bank Slips', 'n', 14], ['Bank Transfers', 'n', 14], ['Cheques', 'n', 12], ['Total', 'n', 12]],
            'rows' => $rows->map(fn ($row, $i) => [$i + 1, $row['name'], $money($row['cash']), $money($row['slips']), $money($row['transfers']), $money($row['cheques']), $money($row['total'])])->all(),
            'total' => ['label' => 'Total', 'span' => 2, 'cells' => [$total($rows->sum('cash')), $total($rows->sum('slips')), $total($rows->sum('transfers')), $total($rows->sum('cheques')), $total($rows->sum('total'))]],
            'bold' => [6], 'empty' => 'No settlements for the selected filters.',
        ];
    }
}
