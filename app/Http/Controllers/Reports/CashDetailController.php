<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SalesSettlement;
use App\Models\Supplier;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CashDetailController extends Controller implements HasMiddleware
{
    private const ALL_COMPANIES = 'all';

    public static function middleware(): array
    {
        return [
            new Middleware('can:report-audit-cash-detail'),
        ];
    }

    public function index(Request $request)
    {
        $data = $this->reportData($request);

        if ($request->query('layout') === 'print' && $data['hasQuery']) {
            return view('reports.cash-detail.pdf', $data + ['totals' => $this->totals($data), 'orientation' => 'portrait', 'isPdf' => false]);
        }

        return view('reports.cash-detail', $data + ['totals' => $this->totals($data), 'orientation' => 'portrait']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, float>
     */
    private function totals(array $data): array
    {
        return [
            'totalCash' => (float) $data['salesmanData']->sum('amount'),
            'totalBankSlips' => (float) $data['bankSlipsData']->sum('amount'),
            'totalBankTransfers' => (float) $data['bankTransfersData']->sum('amount'),
            'totalCheques' => (float) $data['chequesData']->sum('amount'),
        ];
    }

    /**
     * A4 PDF of the same report, portrait or landscape (?orientation=).
     */
    public function exportPdf(Request $request): Response
    {
        $data = $this->reportData($request);
        abort_unless($data['hasQuery'], 422, 'Select a company and apply the filters before downloading.');

        $orientation = $request->query('orientation') === 'landscape' ? 'landscape' : 'portrait';
        $date = Carbon::parse($data['date']);

        $pdf = Pdf::loadView('reports.cash-detail.pdf', $data + ['totals' => $this->totals($data), 'orientation' => $orientation])
            ->setPaper('a4', $orientation);
        $pdf->render();
        $canvas = $pdf->getDomPDF()->getCanvas();
        $canvas->page_text($canvas->get_width() - 80, $canvas->get_height() - 25, 'Page {PAGE_NUM} of {PAGE_COUNT}', $pdf->getDomPDF()->getFontMetrics()->getFont('Helvetica'), 7);

        return $pdf->download('cash-detail-'.$date->format('Y-m-d').'.pdf');
    }

    /**
     * Admins and super admins, or anyone holding the All Companies permission, may pick any company
     * or "All"; every other user is held to the company on their own account. Nothing is loaded
     * until a company is chosen and the filters are applied.
     *
     * @return array<string, mixed>
     */
    private function reportData(Request $request): array
    {
        $user = $request->user();
        $canChooseCompany = $user->can('report-audit-cash-detail-all-companies') || $user->isTicketAdmin();
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
        $employeeIds = $request->input('employee_ids', []);

        $suppliers = Supplier::query()
            ->when(! $canChooseCompany, fn ($query) => $query->where('id', $ownSupplierId ?? 0))
            ->orderBy('supplier_name')
            ->get();
        $designations = Employee::distinct()->whereNotNull('designation')->orderBy('designation')->pluck('designation');
        $companyLabel = $allCompanies ? 'All Companies' : (string) $suppliers->firstWhere('id', $supplierId)?->supplier_name;

        if (! $hasQuery) {
            return [
                'date' => $date,
                'supplierId' => $supplierId,
                'allCompanies' => false,
                'canChooseCompany' => $canChooseCompany,
                'hasQuery' => false,
                'companyLabel' => '',
                'designation' => $designation,
                'employeeIds' => $employeeIds,
                'suppliers' => $suppliers,
                'designations' => $designations,
                'allEmployees' => collect(),
                'salesmanData' => collect(),
                'denominations' => ['5000' => 0, '1000' => 0, '500' => 0, '100' => 0, '50' => 0, '20' => 0, '10' => 0, 'coins' => 0],
                'bankSlipsData' => collect(),
                'bankTransfersData' => collect(),
                'chequesData' => collect(),
            ];
        }

        $allEmployees = Employee::query();

        if ($supplierId) {
            $allEmployees->where('supplier_id', $supplierId);
        }

        if ($designation) {
            $allEmployees->where('designation', $designation);
        }

        $allEmployees = $allEmployees->orderBy('name')->get();

        // 1. Prepare Salesman Data (Show ALL salesmen if supplier selected, or just those with settlements otherwise?)
        // The user said: "agar mein supplier select kro tou us supplier k saray salesman show honey chieyay"
        // So we start with Employees, then left join settlements.

        $employeesQuery = Employee::query();

        if ($supplierId) {
            $employeesQuery->where('supplier_id', $supplierId);
        }

        if ($designation) {
            $employeesQuery->where('designation', $designation);
        }

        if (! empty($employeeIds)) {
            $employeesQuery->whereIn('id', $employeeIds);
        }

        // We get the relevant employees
        $employees = $employeesQuery->with('supplier:id,short_name')->orderBy('name')->get();

        // With All Companies the company's short name follows the salesman, e.g. "Raja Safeer (Nestle)".
        $employees->each(function (Employee $employee) use ($allCompanies) {
            $employee->display_name = $allCompanies && $employee->supplier?->short_name
                ? $employee->name.' ('.$employee->supplier->short_name.')'
                : $employee->name;
        });

        // Fetch ALL matching settlements once (with relationships) to reuse across all sections.
        // This eliminates the N+1 problem and ensures all settlements per employee are included.
        $settlementsQuery = SalesSettlement::with(['employee', 'cashDenominations', 'bankSlips', 'bankTransfers', 'cheques'])
            ->whereDate('settlement_date', $date);

        if ($supplierId) {
            $settlementsQuery->whereHas('employee', function ($q) use ($supplierId) {
                $q->where('supplier_id', $supplierId);
            });
        }

        if ($designation) {
            $settlementsQuery->whereHas('employee', function ($q) use ($designation) {
                $q->where('designation', $designation);
            });
        }

        if (! empty($employeeIds)) {
            $settlementsQuery->whereIn('employee_id', $employeeIds);
        }

        $settlements = $settlementsQuery->get();

        // Group settlements by employee_id for efficient lookup
        $settlementsByEmployee = $settlements->groupBy('employee_id');

        // 1. Salesman Cash — sum cash across ALL settlements per employee
        $salesmanData = $employees->map(function ($employee) use ($settlementsByEmployee) {
            $employeeSettlements = $settlementsByEmployee->get($employee->id, collect());

            $amount = $employeeSettlements->sum(function ($settlement) {
                $cashDenom = $settlement->cashDenominations->first();

                return ($cashDenom && $cashDenom->total_amount > 0)
                    ? (float) $cashDenom->total_amount
                    : (float) $settlement->cash_collected;
            });

            return (object) [
                'salesman_name' => $employee->display_name,
                'amount' => $amount,
                'has_settlement' => $employeeSettlements->isNotEmpty(),
            ];
        });

        if (! $supplierId && ! $designation && empty($employeeIds)) {
            $salesmanData = $salesmanData->filter(function ($item) {
                return $item->amount > 0 || $item->has_settlement;
            });
        }

        $salesmanData = $salesmanData->sortByDesc('amount')->values();

        // 2. Cash Denominations — aggregate across ALL settlements
        $denominations = [
            '5000' => 0,
            '1000' => 0,
            '500' => 0,
            '100' => 0,
            '50' => 0,
            '20' => 0,
            '10' => 0,
            'coins' => 0,
        ];

        foreach ($settlements as $settlement) {
            $denom = $settlement->cashDenominations->first();
            if ($denom) {
                $denominations['5000'] += $denom->denom_5000;
                $denominations['1000'] += $denom->denom_1000;
                $denominations['500'] += $denom->denom_500;
                $denominations['100'] += $denom->denom_100;
                $denominations['50'] += $denom->denom_50;
                $denominations['20'] += $denom->denom_20;
                $denominations['10'] += $denom->denom_10;
                $denominations['coins'] += $denom->denom_coins;
            }
        }

        // 3. Bank Slips — sum across ALL settlements per employee
        $bankSlipsData = $employees->map(function ($employee) use ($settlementsByEmployee) {
            $employeeSettlements = $settlementsByEmployee->get($employee->id, collect());

            $amount = $employeeSettlements->sum(fn ($settlement) => (float) $settlement->bankSlips->sum('amount'));

            return (object) [
                'salesman_name' => $employee->display_name,
                'amount' => $amount,
                'has_settlement' => $employeeSettlements->isNotEmpty(),
            ];
        });

        if (! $supplierId && ! $designation && empty($employeeIds)) {
            $bankSlipsData = $bankSlipsData->filter(function ($item) {
                return $item->amount > 0 || $item->has_settlement;
            });
        }

        $bankSlipsData = $bankSlipsData->sortByDesc('amount')->values();

        // 4. Bank Transfers — sum across ALL settlements per employee
        $bankTransfersData = $employees->map(function ($employee) use ($settlementsByEmployee) {
            $employeeSettlements = $settlementsByEmployee->get($employee->id, collect());

            $amount = $employeeSettlements->sum(fn ($settlement) => (float) $settlement->bankTransfers->sum('amount'));

            return (object) [
                'salesman_name' => $employee->display_name,
                'amount' => $amount,
                'has_settlement' => $employeeSettlements->isNotEmpty(),
            ];
        });

        if (! $supplierId && ! $designation && empty($employeeIds)) {
            $bankTransfersData = $bankTransfersData->filter(function ($item) {
                return $item->amount > 0 || $item->has_settlement;
            });
        }

        $bankTransfersData = $bankTransfersData->sortByDesc('amount')->values();

        // 5. Cheques — sum across ALL settlements per employee
        $chequesData = $employees->map(function ($employee) use ($settlementsByEmployee) {
            $employeeSettlements = $settlementsByEmployee->get($employee->id, collect());

            $amount = $employeeSettlements->sum(fn ($settlement) => (float) $settlement->cheques->sum('amount'));

            return (object) [
                'salesman_name' => $employee->display_name,
                'amount' => $amount,
                'has_settlement' => $employeeSettlements->isNotEmpty(),
            ];
        });

        if (! $supplierId && ! $designation && empty($employeeIds)) {
            $chequesData = $chequesData->filter(function ($item) {
                return $item->amount > 0 || $item->has_settlement;
            });
        }

        $chequesData = $chequesData->sortByDesc('amount')->values();

        return compact(
            'date',
            'supplierId',
            'allCompanies',
            'canChooseCompany',
            'hasQuery',
            'companyLabel',
            'designation',
            'employeeIds',
            'suppliers',
            'designations',
            'allEmployees',
            'salesmanData',
            'denominations',
            'bankSlipsData',
            'bankTransfersData',
            'chequesData'
        );
    }
}
