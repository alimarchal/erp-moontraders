<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SalesSettlement;
use App\Models\SalesSettlementCheque;
use App\Models\Supplier;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;

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
        $date = $request->input('date', now()->format('Y-m-d'));
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

        $scope = fn ($query) => $query
            ->when($designation, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('designation', $designation)))
            ->when($employeeIds !== [], fn ($q) => $q->whereIn('employee_id', $employeeIds));

        $settlements = $scope(SalesSettlement::query())
            ->with(['employee:id,name,supplier_id,designation', 'cashDenominations', 'bankSlips.bankAccount', 'bankTransfers', 'cheques'])
            ->whereDate('settlement_date', $date)
            ->get()
            ->each(fn (SalesSettlement $settlement) => $settlement->setAttribute('company_id', (int) ($settlement->supplier_id ?? $settlement->employee?->supplier_id)))
            ->when($supplierId, fn (Collection $rows) => $rows->where('company_id', $supplierId));

        $clearings = SalesSettlementCheque::query()
            ->with('salesSettlement.employee:id,name,supplier_id,designation')
            ->where('status', 'cleared')
            ->whereDate('cleared_date', $date)
            ->whereHas('salesSettlement', fn ($query) => $scope($query))
            ->get()
            ->each(fn (SalesSettlementCheque $cheque) => $cheque->setAttribute('company_id', (int) ($cheque->salesSettlement->supplier_id ?? $cheque->salesSettlement->employee?->supplier_id)))
            ->when($supplierId, fn (Collection $rows) => $rows->where('company_id', $supplierId));

        $companyIds = $settlements->pluck('company_id')->merge($clearings->pluck('company_id'))->when($supplierId, fn ($ids) => $ids->push($supplierId))->filter()->unique();
        $companies = Supplier::whereIn('id', $companyIds)->orderBy('supplier_name')->get(['id', 'supplier_name', 'short_name']);

        $perCompany = $companies->map(function (Supplier $company) use ($settlements, $clearings) {
            $own = $settlements->where('company_id', $company->id);

            return (object) [
                'name' => $company->supplier_name,
                'cash' => $own->sum(fn ($s) => $this->cashOf($s)),
                'clearing' => (float) $clearings->where('company_id', $company->id)->sum('amount'),
                'slips' => (float) $own->sum(fn ($s) => $s->bankSlips->sum('amount')),
                'transfers' => (float) $own->sum(fn ($s) => $s->bankTransfers->sum('amount')),
                'cheques' => (float) $own->sum(fn ($s) => $s->cheques->sum('amount')),
            ];
        })->values();

        $totals = [
            'cash' => (float) $perCompany->sum('cash'),
            'clearing' => (float) $perCompany->sum('clearing'),
            'slips' => (float) $perCompany->sum('slips'),
            'transfers' => (float) $perCompany->sum('transfers'),
            'cheques' => (float) $perCompany->sum('cheques'),
        ];
        $overall = array_sum($totals);

        $denominations = $this->denominations($settlements, $totals['clearing']);
        $slipRows = $this->slipRows($settlements, $companies);

        $money = fn ($value) => $value > 0 ? number_format((float) $value, 2) : '-';
        $total = fn ($value) => number_format((float) $value, 2);

        $tables = [
            'cash' => [
                'no' => 1, 'title' => 'Cash by Distribution', 'explain' => 'Settlement cash and cheques cleared today',
                'cols' => [['#', 'c', 5], ['Distribution', '', 41], ['Cash', 'n', 18], ['Cheque Clearing', 'n', 18], ['Total Cash', 'n', 18]],
                'rows' => $perCompany->map(fn ($row, $i) => [$i + 1, $row->name, $money($row->cash), $money($row->clearing), $money($row->cash + $row->clearing)])->all(),
                'total' => ['label' => 'Total', 'span' => 2, 'cells' => [$total($totals['cash']), $total($totals['clearing']), $total($totals['cash'] + $totals['clearing'])]],
                'bold' => [4], 'empty' => 'No cash collected for the selected filters.',
            ],
            'denominations' => [
                'no' => 2, 'title' => 'Cash Denominations', 'explain' => 'notes handed in, with cheque clearing cash',
                'cols' => [['#', 'c', 8], ['Denomination', 'c', 34], ['Notes', 'c', 26], ['Value (Rs.)', 'n', 32]],
                'rows' => $denominations['rows'],
                'total' => ['label' => 'Total cash (notes + coins + cheque clearing)', 'span' => 3, 'cells' => [$total($denominations['total'])]],
                'bold' => [1], 'empty' => '',
            ],
            'bank' => [
                'no' => 3, 'title' => 'Bank Slips & Transfers', 'explain' => 'by distribution',
                'cols' => [['#', 'c', 5], ['Distribution', '', 37], ['Bank Slips', 'n', 20], ['Bank Transfers', 'n', 20], ['Total', 'n', 18]],
                'rows' => $perCompany->map(fn ($row, $i) => [$i + 1, $row->name, $money($row->slips), $money($row->transfers), $money($row->slips + $row->transfers)])->all(),
                'total' => ['label' => 'Total', 'span' => 2, 'cells' => [$total($totals['slips']), $total($totals['transfers']), $total($totals['slips'] + $totals['transfers'])]],
                'bold' => [4], 'empty' => 'No bank slips or transfers for the selected filters.',
            ],
            'cheques' => [
                'no' => 4, 'title' => 'Cheques', 'explain' => 'received in settlements',
                'cols' => [['#', 'c', 8], ['Distribution', '', 58], ['Amount (Cheques)', 'n', 34]],
                'rows' => $perCompany->map(fn ($row, $i) => [$i + 1, $row->name, $money($row->cheques)])->all(),
                'total' => ['label' => 'Total', 'span' => 2, 'cells' => [$total($totals['cheques'])]],
                'bold' => [2], 'empty' => 'No cheques for the selected filters.',
            ],
            'slips' => [
                'no' => 5, 'title' => 'Bank Slip Details', 'explain' => 'distribution, salesman and the bank account the amount was submitted to',
                'cols' => [['#', 'c', 4], ['Distribution', '', 17], ['Salesman', '', 19], ['Bank', '', 19], ['Account No.', '', 18], ['Reference', '', 11], ['Amount (Rs.)', 'n', 12]],
                'rows' => $slipRows->map(fn ($row, $i) => [$i + 1, $row['company'], $row['salesman'], $row['bank'], $row['accountNumber'], $row['reference'], $total($row['amount'])])->all(),
                'total' => ['label' => 'Total bank slips', 'span' => 6, 'cells' => [$total($slipRows->sum('amount'))]],
                'bold' => [6], 'empty' => 'No bank slips for the selected filters.',
            ],
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
                ['Bank transfers', $total($totals['transfers'])],
                ['Cheques received', $total($totals['cheques'])],
                ['Overall total', $total($overall), true],
            ],
            'tables' => $tables,
        ]);
    }

    private function cashOf(SalesSettlement $settlement): float
    {
        $denomination = $settlement->cashDenominations->first();

        return ($denomination && $denomination->total_amount > 0)
            ? (float) $denomination->total_amount
            : (float) $settlement->cash_collected;
    }

    /**
     * @param  Collection<int, SalesSettlement>  $settlements
     * @return array{rows: array<int, array<int, string|int>>, total: float}
     */
    private function denominations(Collection $settlements, float $clearingCash): array
    {
        $count = array_fill_keys([5000, 1000, 500, 100, 50, 20, 10], 0);
        $coins = 0.0;

        foreach ($settlements as $settlement) {
            $denomination = $settlement->cashDenominations->first();
            if (! $denomination) {
                continue;
            }
            foreach (array_keys($count) as $note) {
                $count[$note] += (int) $denomination->{'denom_'.$note};
            }
            $coins += (float) $denomination->denom_coins;
        }

        $rows = [];
        $total = $coins;
        $i = 0;
        foreach ($count as $note => $qty) {
            $total += $qty * $note;
            $rows[] = [++$i, number_format($note), $qty > 0 ? $qty : '-', $qty > 0 ? number_format($qty * $note, 2) : '-'];
        }
        $rows[] = [++$i, 'Coins', '-', $coins > 0 ? number_format($coins, 2) : '-'];
        // The cash a salesman brings back for cleared cheques is counted with the notes but is not in a settlement.
        $rows[] = [++$i, 'Cheque clearing', '-', $clearingCash > 0 ? number_format($clearingCash, 2) : '-'];

        return ['rows' => $rows, 'total' => $total + $clearingCash];
    }

    /**
     * @param  Collection<int, SalesSettlement>  $settlements
     * @param  Collection<int, Supplier>  $companies
     * @return Collection<int, array<string, mixed>>
     */
    private function slipRows(Collection $settlements, Collection $companies): Collection
    {
        return $settlements
            ->flatMap(fn (SalesSettlement $settlement) => $settlement->bankSlips->map(fn ($slip) => [
                'company' => (string) $companies->firstWhere('id', $settlement->company_id)?->supplier_name,
                'salesman' => (string) $settlement->employee?->name,
                'bank' => (string) $slip->bankAccount?->bank_name,
                'accountNumber' => (string) $slip->bankAccount?->account_number,
                'reference' => (string) $slip->reference_number,
                'amount' => (float) $slip->amount,
            ]))
            ->sortBy([['company', 'asc'], ['salesman', 'asc']])
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
                'transfers' => (float) $own->sum(fn ($s) => $s->bankTransfers->sum('amount')),
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
