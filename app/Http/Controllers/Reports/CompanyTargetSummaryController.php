<?php

namespace App\Http\Controllers\Reports;

use App\Exports\CompanyTargetSummaryExport;
use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Services\CompanyTargetService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class CompanyTargetSummaryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:report-audit-company-target-summary'),
        ];
    }

    public function index(Request $request, CompanyTargetService $service)
    {
        $data = $this->reportData($request, $service);

        return view('reports.company-target-summary.index', $data + [
            'suppliers' => $this->suppliersForUser(),
        ]);
    }

    public function exportExcel(Request $request, CompanyTargetService $service): BinaryFileResponse
    {
        $data = $this->reportData($request, $service);

        return Excel::download(
            new CompanyTargetSummaryExport($data),
            'company-target-summary-'.now()->format('Y-m-d').'.xlsx'
        );
    }

    public function exportPdf(Request $request, CompanyTargetService $service): Response
    {
        $data = $this->reportData($request, $service) + [
            'generatedBy' => $request->user()->name,
            'generatedAt' => now(),
        ];

        return Pdf::loadView('reports.company-target-summary.pdf', $data)
            ->setPaper('a4', 'landscape')
            ->download('company-target-summary-'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * @return array{supplier: ?Supplier, from: Carbon, to: Carbon, summary: ?array<string, mixed>}
     */
    private function reportData(Request $request, CompanyTargetService $service): array
    {
        $request->validate([
            'supplier_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $user = $request->user();
        $requestedSupplierId = $request->integer('supplier_id') ?: null;

        if ($requestedSupplierId && ! $user->hasAllSupplierAccess() && $requestedSupplierId !== (int) $user->supplier_id) {
            abort(403, 'You do not have permission to view another company.');
        }

        $supplierId = $user->hasAllSupplierAccess() ? $requestedSupplierId : ($user->supplier_id ? (int) $user->supplier_id : null);
        $supplier = $supplierId ? Supplier::query()->where('disabled', false)->find($supplierId, ['id', 'supplier_name']) : null;

        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->startOfDay() : now()->startOfDay();

        return [
            'supplier' => $supplier,
            'from' => $from,
            'to' => $to,
            'summary' => $supplier ? $service->summary($supplier->id, $from, $to) : null,
        ];
    }

    /**
     * @return Collection<int, Supplier>
     */
    private function suppliersForUser(): Collection
    {
        $user = auth()->user();

        return Supplier::query()
            ->where('disabled', false)
            ->when(! $user->hasAllSupplierAccess(), fn ($query) => $user->supplier_id
                ? $query->whereKey($user->supplier_id)
                : $query->whereRaw('1 = 0'))
            ->orderBy('supplier_name')
            ->get(['id', 'supplier_name']);
    }
}
