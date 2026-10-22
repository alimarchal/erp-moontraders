<?php

namespace App\Http\Controllers\Reports;

use App\Exports\BankReportExport;
use App\Helpers\ReportPdf;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\BankEntryCategory;
use App\Models\BankReconciliation;
use App\Models\BankStatementEntry;
use App\Models\Supplier;
use App\Services\BankRegister\BankReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class BankReportController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('reports.index');
    }

    public function show(Request $request, string $report, BankReportService $service): View
    {
        $definition = $this->definition($report);

        return view('reports.bank.show', [
            'slug' => $report,
            'definition' => $definition,
            'data' => $service->build($report, $this->filters($request)),
            'filters' => $this->filters($request),
            'bankAccounts' => BankAccount::orderBy('account_name')->get(['id', 'account_name']),
            'companies' => Supplier::where('disabled', false)->orderBy('supplier_name')->get(['id', 'supplier_name']),
            'categories' => BankEntryCategory::orderBy('name')->get(['id', 'name']),
            'statuses' => BankStatementEntry::STATUSES,
            'reconciliations' => BankReconciliation::with('bankAccount')->where('status', '!=', 'cancelled')->latest('period_to')->get(),
        ]);
    }

    public function exportExcel(Request $request, string $report, BankReportService $service): BinaryFileResponse
    {
        $this->definition($report);

        return Excel::download(new BankReportExport($service->build($report, $this->filters($request))), "bank-{$report}-".now()->format('Y-m-d').'.xlsx');
    }

    public function exportPdf(Request $request, string $report, BankReportService $service): Response
    {
        $this->definition($report);

        $orientation = ReportPdf::orientation($request->query('orientation', 'landscape'));
        $data = $service->build($report, $this->filters($request));

        return ReportPdf::make('reports.bank.pdf', ['data' => $data, 'generatedAt' => now()], $orientation, $data['title'])
            ->download("bank-{$report}-".now()->format('Y-m-d').'.pdf');
    }

    /**
     * @return array{title: string, permission: string, description: string, filters: array<int, string>}
     */
    private function definition(string $report): array
    {
        $definition = BankReportService::REPORTS[$report] ?? null;
        abort_unless($definition, 404);
        abort_unless(auth()->user()->can($definition['permission']), 403);

        return $definition;
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return array_filter((array) $request->input('filter', []), fn ($value) => $value !== null && $value !== '');
    }
}
