<?php

namespace App\Http\Controllers;

use App\Exports\PayrollRunExport;
use App\Exports\PayrollRunsExport;
use App\Exports\PayrollSheetExport;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\PayrollRun;
use App\Models\PayrollSlip;
use App\Models\PayrollSlipLine;
use App\Models\SalaryComponent;
use App\Models\Supplier;
use App\Services\PayrollService;
use App\Services\PayrollSheetService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

/**
 * Monthly payroll: build the whole month with one button, adjust slips, send to a checker, approve, pay.
 */
class PayrollRunController extends Controller implements HasMiddleware
{
    public function __construct(private PayrollService $service) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:payroll-list', only: ['sheetPdf', 'sheetExcel', 'show', 'slip', 'printAll', 'help', 'exportPdf', 'exportExcel', 'slipsPdf', 'listPdf', 'listExcel']),
            new Middleware('can:payroll-create', only: ['create', 'store', 'updateSlip', 'toggleShort', 'variablePay', 'addLine', 'removeLine', 'recalculate', 'addEmployees', 'submit', 'destroy']),
            new Middleware('can:payroll-approve', only: ['approve', 'reject']),
            new Middleware('can:payroll-pay', only: ['pay']),
        ];
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('payroll.index', ['tab' => 'runs', 'new' => 1]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m', 'before_or_equal:'.now()->format('Y-m')], 'owner' => ['nullable', 'string', 'max:30']]);
        [$supplierId, $companyId] = $this->resolveOwner($request, $data['owner'] ?? null);

        try {
            $run = $this->service->createRun($supplierId, $companyId, Carbon::parse($data['month'].'-01'), $request->user());
        } catch (RuntimeException $e) {
            return redirect()->route('payroll.index', ['tab' => 'runs', 'new' => 1])->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('payroll.show', $run)->with('success', 'Payroll '.$run->run_number.' is ready. Check the payslips, then send it for approval.');
    }

    public function show(Request $request, PayrollRun $payroll)
    {
        $this->authorizeVisible($request, $payroll);

        $variableCategories = SalaryComponent::active()->where('calculation', SalaryComponent::CALC_INPUT)->get(['id', 'name', 'type', 'sort_order'])
            ->sortBy(fn (SalaryComponent $component) => [$component->type === SalaryComponent::TYPE_EARNING ? 0 : 1, $component->sort_order, $component->name])->values();

        return view('payroll.show', [
            'variableCategories' => $variableCategories,
            'run' => $payroll->load(['supplier', 'company', 'creator', 'checker', 'slips.employee.supplier', 'slips.lines', 'journalEntry', 'paymentJournalEntry', 'paidBankAccount']),
            'components' => SalaryComponent::active()->where('calculation', SalaryComponent::CALC_INPUT)->get(),
            'bankAccounts' => BankAccount::query()->where('is_active', true)->whereNotNull('chart_of_account_id')->orderBy('bank_name')->get(['id', 'bank_name', 'account_name', 'account_number']),
            'withoutSalary' => $this->service->employeesWithoutSalary($payroll->supplier_id),
            'missing' => $payroll->isEditable() ? $this->service->missingEmployees($payroll) : collect(),
        ]);
    }

    public function printAll(Request $request, PayrollRun $payroll)
    {
        $this->authorizeVisible($request, $payroll);

        return view('payroll.slips-print', ['run' => $payroll->load(['supplier', 'company', 'slips.employee', 'slips.lines'])]);
    }

    public function slip(Request $request, PayrollRun $payroll, PayrollSlip $slip)
    {
        $this->authorizeVisible($request, $payroll);
        abort_unless($slip->payroll_run_id === $payroll->id, 404);

        return view('payroll.slip', ['run' => $payroll->load(['supplier', 'company']), 'slip' => $slip->load(['employee', 'lines'])]);
    }

    public function updateSlip(Request $request, PayrollRun $payroll, PayrollSlip $slip): RedirectResponse
    {
        $this->authorizeVisible($request, $payroll);
        $this->authorizeMaker($request, $payroll);
        abort_unless($slip->payroll_run_id === $payroll->id, 404);
        $data = $request->validate(['absent_days' => ['required', 'numeric', 'min:0', 'max:31']]);

        return $this->attempt(fn () => $this->service->setAbsentDays($slip->setRelation('run', $payroll), (float) $data['absent_days']), $payroll, 'Absent days saved and the payslip recalculated.', $slip);
    }

    /**
     * Save the run's variable-pay grid: one amount per employee and monthly category, plus absent days.
     */
    public function variablePay(Request $request, PayrollRun $payroll): RedirectResponse
    {
        $this->authorizeVisible($request, $payroll);
        $this->authorizeMaker($request, $payroll);

        // The grid sends everything as one JSON field; amounts[slip][category] / absent[slip] are accepted too.
        if ($request->filled('payload')) {
            $rows = json_decode((string) $request->input('payload'), true);
            abort_unless(is_array($rows), 422, 'The grid could not be read. Reload the page and try again.');
            $request->merge([
                'amounts' => collect($rows)->filter(fn ($row) => is_array($row) && isset($row['id']))->mapWithKeys(fn ($row) => [(int) $row['id'] => array_map(fn ($v) => $v === '' ? null : $v, (array) ($row['cells'] ?? []))])->all(),
                'absent' => collect($rows)->filter(fn ($row) => is_array($row) && isset($row['id']) && ($row['absent'] ?? null) !== null)->mapWithKeys(fn ($row) => [(int) $row['id'] => $row['absent'] === '' ? null : $row['absent']])->all(),
            ]);
        }

        $data = $request->validate([
            'amounts' => ['nullable', 'array', 'max:2000'],
            'amounts.*' => ['array'],
            'amounts.*.*' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'absent' => ['nullable', 'array', 'max:2000'],
            'absent.*' => ['nullable', 'numeric', 'min:0', 'max:31'],
        ], ['amounts.*.*.numeric' => 'Every amount in the grid must be a number.', 'amounts.*.*.min' => 'Amounts in the grid cannot be negative.']);

        try {
            $changed = $this->service->setVariablePay($payroll, (array) ($data['amounts'] ?? []), (array) ($data['absent'] ?? []));
        } catch (RuntimeException $e) {
            return redirect()->route('payroll.show', $payroll)->with('error', $e->getMessage());
        }

        return redirect()->route('payroll.show', $payroll)->with('success', $changed === 0 ? 'Nothing changed in the variable pay.' : 'Variable pay saved; '.$changed.' '.Str::plural('payslip', $changed).' recalculated.');
    }

    public function toggleShort(Request $request, PayrollRun $payroll, PayrollSlip $slip): RedirectResponse
    {
        $this->authorizeVisible($request, $payroll);
        $this->authorizeMaker($request, $payroll);
        abort_unless($slip->payroll_run_id === $payroll->id, 404);
        $recover = $request->boolean('recover');

        return $this->attempt(fn () => $this->service->setRecoverShort($slip->setRelation('run', $payroll), $recover), $payroll, $recover ? 'Settlement short will be taken from the salary.' : 'Settlement short waived on this payslip.', $slip);
    }

    public function addLine(Request $request, PayrollRun $payroll, PayrollSlip $slip): RedirectResponse
    {
        $this->authorizeVisible($request, $payroll);
        $this->authorizeMaker($request, $payroll);
        abort_unless($slip->payroll_run_id === $payroll->id, 404);
        $data = $request->validate([
            'salary_component_id' => ['required', Rule::exists('salary_components', 'id')->where('is_active', true)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'label' => ['nullable', 'string', 'max:160'],
        ]);

        return $this->attempt(fn () => $this->service->addManualLine($slip->setRelation('run', $payroll), SalaryComponent::findOrFail($data['salary_component_id']), (float) $data['amount'], $data['label'] ?? null), $payroll, 'Line added.', $slip);
    }

    public function removeLine(Request $request, PayrollRun $payroll, PayrollSlipLine $line): RedirectResponse
    {
        $this->authorizeVisible($request, $payroll);
        $this->authorizeMaker($request, $payroll);
        abort_unless($line->slip->payroll_run_id === $payroll->id, 404);

        return $this->attempt(fn () => $this->service->removeLine($line), $payroll, 'Line removed.', $line->slip);
    }

    public function recalculate(Request $request, PayrollRun $payroll): RedirectResponse
    {
        $this->authorizeVisible($request, $payroll);
        $this->authorizeMaker($request, $payroll);

        return $this->attempt(fn () => $this->service->recalculateRun($payroll), $payroll, 'Every payslip was recalculated from the latest salary set-up and advances.');
    }

    public function addEmployees(Request $request, PayrollRun $payroll): RedirectResponse
    {
        $this->authorizeVisible($request, $payroll);
        $this->authorizeMaker($request, $payroll);

        try {
            $added = $this->service->addMissingEmployees($payroll);
        } catch (RuntimeException $e) {
            return redirect()->route('payroll.show', $payroll)->with('error', $e->getMessage());
        }

        return redirect()->route('payroll.show', $payroll)->with('success', $added === 0 ? 'Everyone who can be paid is already in this payroll.' : $added.' '.Str::plural('employee', $added).' added with their salary, allowances, advances and any bonus or commission for the month.');
    }

    public function submit(Request $request, PayrollRun $payroll): RedirectResponse
    {
        $this->authorizeVisible($request, $payroll);
        $this->authorizeMaker($request, $payroll);

        return $this->attempt(fn () => $this->service->submit($payroll, $request->user()), $payroll, 'Payroll sent to the checker.');
    }

    public function approve(Request $request, PayrollRun $payroll): RedirectResponse
    {
        $this->authorizeVisible($request, $payroll);

        return $this->attempt(fn () => $this->service->approve($payroll, $request->user()), $payroll, 'Payroll approved and posted to the ledger.');
    }

    public function reject(Request $request, PayrollRun $payroll): RedirectResponse
    {
        $this->authorizeVisible($request, $payroll);
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:500']]);

        return $this->attempt(fn () => $this->service->reject($payroll, $request->user(), $data['rejection_reason']), $payroll, 'Payroll rejected and returned to the maker.');
    }

    public function pay(Request $request, PayrollRun $payroll): RedirectResponse
    {
        $this->authorizeVisible($request, $payroll);
        $data = $request->validate([
            'method' => ['required', Rule::in(['cash', 'bank'])],
            'bank_account_id' => ['required_if:method,bank', 'nullable', 'exists:bank_accounts,id'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
        ]);

        return $this->attempt(fn () => $this->service->pay($payroll, $request->user(), $data['method'], $data['method'] === 'bank' ? (int) $data['bank_account_id'] : null, $data['paid_on']), $payroll, 'Salaries marked as paid and posted to the ledger.');
    }

    public function destroy(Request $request, PayrollRun $payroll): RedirectResponse
    {
        $this->authorizeVisible($request, $payroll);
        $this->authorizeMaker($request, $payroll);

        try {
            $this->service->delete($payroll);
        } catch (RuntimeException $e) {
            return redirect()->route('payroll.show', $payroll)->with('error', $e->getMessage());
        }

        return redirect()->route('payroll.index', ['tab' => 'runs'])->with('success', 'Payroll deleted.');
    }

    public function listPdf(Request $request)
    {
        $filters = array_filter((array) $request->input('filter', []), fn ($value) => $value !== null && $value !== '');
        $runs = $this->filteredRuns($request, $filters)->get();
        $subtitle = collect([
            isset($filters['month']) ? Carbon::parse($filters['month'].'-01')->format('F Y') : 'All months',
            isset($filters['status']) ? (PayrollRun::statusLabels()[$filters['status']] ?? $filters['status']) : 'Any status',
        ])->implode(' · ');

        return Pdf::loadView('payroll.pdf.runs', ['runs' => $runs, 'subtitle' => $subtitle])->setPaper('a4', 'portrait')->download('payroll-register.pdf');
    }

    public function listExcel(Request $request)
    {
        $filters = array_filter((array) $request->input('filter', []), fn ($value) => $value !== null && $value !== '');

        return Excel::download(new PayrollRunsExport($this->filteredRuns($request, $filters)->get()), 'payroll-register.xlsx');
    }

    public function exportPdf(Request $request, PayrollRun $payroll)
    {
        $this->authorizeVisible($request, $payroll);

        return $this->paged(Pdf::loadView('payroll.pdf.run', ['run' => $this->loadForExport($payroll)])->setPaper('a4', 'portrait'))->download($payroll->run_number.'.pdf');
    }

    public function exportExcel(Request $request, PayrollRun $payroll)
    {
        $this->authorizeVisible($request, $payroll);

        return Excel::download(new PayrollRunExport($this->loadForExport($payroll)), $payroll->run_number.'.xlsx');
    }

    /**
     * The month's salary sheet: one row per employee, Fixed part | Variable part | Deductions | Net, landscape.
     */
    public function sheetPdf(Request $request, PayrollRun $payroll, PayrollSheetService $sheets)
    {
        $this->authorizeVisible($request, $payroll);
        $this->loadForExport($payroll);

        $sheet = $sheets->build($payroll);
        // A month with very many pay items gets a larger page so the net salary column is never cut off.
        $paper = count(array_merge(...array_values($sheet['columns']))) > 24 ? 'a3' : 'a4';

        return $this->paged(Pdf::loadView('payroll.pdf.sheet', ['run' => $payroll, 'sheet' => $sheet])->setPaper($paper, 'landscape'))
            ->download($payroll->run_number.'-salary-sheet.pdf');
    }

    public function sheetExcel(Request $request, PayrollRun $payroll, PayrollSheetService $sheets)
    {
        $this->authorizeVisible($request, $payroll);
        $this->loadForExport($payroll);

        return Excel::download(new PayrollSheetExport($payroll, $sheets->build($payroll)), $payroll->run_number.'-salary-sheet.xlsx');
    }

    public function slipsPdf(Request $request, PayrollRun $payroll, ?PayrollSlip $slip = null)
    {
        $this->authorizeVisible($request, $payroll);
        abort_if($slip && $slip->payroll_run_id !== $payroll->id, 404);
        $this->loadForExport($payroll);
        $slips = ($slip ? $payroll->slips->where('id', $slip->id) : $payroll->slips)->sortBy(fn ($s) => $s->employee?->name);

        return $this->paged(Pdf::loadView('payroll.pdf.slips', ['run' => $payroll, 'slips' => $slips])->setPaper('a4', 'portrait'))
            ->download($payroll->run_number.($slip ? '-'.($slip->employee?->employee_code ?? $slip->id) : '-slips').'.pdf');
    }

    private function loadForExport(PayrollRun $run): PayrollRun
    {
        return $run->load(['supplier', 'company', 'creator', 'checker', 'slips.employee.supplier', 'slips.lines.component']);
    }

    /** Adds "Page x of y" to every page of a PDF. */
    private function paged(\Barryvdh\DomPDF\PDF $pdf): \Barryvdh\DomPDF\PDF
    {
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $canvas->page_text($canvas->get_width() - 80, $canvas->get_height() - 25, 'Page {PAGE_NUM} of {PAGE_COUNT}', $dompdf->getFontMetrics()->getFont('Helvetica'), 7);

        return $pdf;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<PayrollRun>
     */
    private function filteredRuns(Request $request, array $filters)
    {
        return PayrollRun::query()
            ->visibleTo($request->user())
            ->with(['supplier:id,supplier_name,short_name', 'company:id,company_name,abbr', 'creator:id,name'])
            ->withCount('slips')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['month'] ?? null, fn ($query, $month) => $query->whereDate('period_month', Carbon::parse($month.'-01')->toDateString()))
            ->when($filters['month_from'] ?? null, fn ($query, $month) => $query->whereDate('period_month', '>=', Carbon::parse($month.'-01')->toDateString()))
            ->when($filters['month_to'] ?? null, fn ($query, $month) => $query->whereDate('period_month', '<=', Carbon::parse($month.'-01')->toDateString()))
            ->when($filters['search'] ?? null, fn ($query, $term) => $query->whereLike('run_number', "%{$term}%"))
            ->when($filters['owner'] ?? null, fn ($query, $owner) => $owner === 'company' ? $query->whereNull('supplier_id') : $query->where('supplier_id', (int) $owner))
            ->latest('period_month')->latest('id');
    }

    public function help(): View
    {
        return view('payroll.help');
    }

    /**
     * @return array{0: int|null, 1: int|null} supplier id, company id
     */
    private function resolveOwner(Request $request, ?string $owner): array
    {
        $user = $request->user();
        [$type, $id] = array_pad(explode(':', (string) $owner, 2), 2, null);

        if ($type === 'supplier' && Supplier::whereKey($id)->exists()) {
            abort_if(! $user->hasAllSupplierAccess() && (int) $id !== (int) $user->supplier_id, 403, 'You can only run payroll for your own company.');

            return [(int) $id, null];
        }

        abort_unless($user->hasAllSupplierAccess(), 403, 'You can only run payroll for your own company.');

        return [null, (int) (Company::whereKey($id)->value('id') ?? Company::query()->orderBy('id')->value('id'))];
    }

    private function authorizeVisible(Request $request, PayrollRun $run): void
    {
        abort_unless($run->canBeSeenBy($request->user()), 404);
    }

    /** Only the person who made the run edits it, so the checker is always a second person. */
    private function authorizeMaker(Request $request, PayrollRun $run): void
    {
        abort_unless((int) $run->created_by === $request->user()->id, 403, 'Only the user who made this payroll can change it.');
    }

    /**
     * Run the action and go back to the run; after a payslip change its window opens again.
     */
    private function attempt(callable $action, PayrollRun $run, string $message, ?PayrollSlip $slip = null): RedirectResponse
    {
        $target = route('payroll.show', array_filter(['payroll' => $run, 'slip' => $slip?->id]));

        try {
            $action();
        } catch (RuntimeException $e) {
            return redirect()->to($target)->with('error', $e->getMessage());
        }

        return redirect()->to($target)->with('success', $message);
    }
}
