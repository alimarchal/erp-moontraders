<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Supplier;
use App\Services\DailyBusinessSummaryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Daily Business Summary: the management sheet for one company or all companies. Empty until
 * a company is chosen in the filters and the report is generated.
 */
class DailyBusinessSummaryController extends Controller implements HasMiddleware
{
    public const ALL_COMPANIES = 'all';

    public function __construct(private DailyBusinessSummaryService $summary) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:report-audit-daily-business-summary'),
        ];
    }

    /**
     * The report page, or with format=pdf the same report as an A4 PDF download (portrait unless
     * orientation=landscape), like the sales settlement and the supplier ledger.
     */
    public function index(Request $request): View|RedirectResponse|Response
    {
        $user = $request->user();
        $canChooseCompany = $user->can('report-audit-daily-business-summary-all-companies') || $user->isTicketAdmin();
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
        $date = $request->date('date')?->toDateString() ?? now()->toDateString();
        $designation = (string) $request->input('designation', 'Salesman');
        $employeeIds = array_values(array_filter((array) $request->input('employee_ids', []), fn ($id) => ctype_digit((string) $id)));

        $suppliers = Supplier::query()
            ->where('disabled', false)
            ->when(! $canChooseCompany, fn ($query) => $query->where('id', $ownSupplierId ?? 0))
            ->orderBy('supplier_name')
            ->get(['id', 'supplier_name', 'short_name']);

        $designations = Employee::query()
            ->distinct()
            ->whereNotNull('designation')
            ->when(! $canChooseCompany, fn ($query) => $query->where('supplier_id', $ownSupplierId ?? 0))
            ->orderBy('designation')
            ->pluck('designation');

        $employees = $hasQuery
            ? Employee::query()
                ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
                ->when($designation !== '', fn ($query) => $query->where('designation', $designation))
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        $report = $hasQuery
            ? $this->summary->build($date, $supplierId, $designation !== '' ? $designation : null, $employeeIds)
            : null;

        $companyLabel = $allCompanies ? 'All Companies' : (string) $suppliers->firstWhere('id', $supplierId)?->supplier_name;

        if ($request->query('format') === 'pdf') {
            if (! $hasQuery) {
                return redirect()->route('reports.daily-business-summary.index', $request->except(['format', 'orientation']))
                    ->with('error', 'Choose a company before downloading the PDF.');
            }

            $orientation = $request->query('orientation') === 'landscape' ? 'landscape' : 'portrait';

            return Pdf::loadView('reports.daily-business-summary-pdf', [
                'date' => $date,
                'allCompanies' => $allCompanies,
                'companyLabel' => $companyLabel,
                'designation' => $designation,
                'report' => $report,
                'orientation' => $orientation,
            ])->setPaper('a4', $orientation)
                ->download('daily-business-summary-'.str($allCompanies ? 'all-companies' : $companyLabel)->slug().'-'.$date.'.pdf');
        }

        return view('reports.daily-business-summary', [
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
            'employees' => $employees,
            'report' => $report,
        ]);
    }
}
