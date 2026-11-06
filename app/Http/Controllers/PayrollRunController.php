<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\PayrollRun;
use App\Models\PayrollSlip;
use App\Models\PayrollSlipLine;
use App\Models\SalaryComponent;
use App\Models\Supplier;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
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
            new Middleware('can:payroll-list', only: ['index', 'show', 'slip', 'printAll', 'help']),
            new Middleware('can:payroll-create', only: ['create', 'store', 'updateSlip', 'addLine', 'removeLine', 'recalculate', 'addEmployees', 'submit', 'destroy']),
            new Middleware('can:payroll-approve', only: ['approve', 'reject']),
            new Middleware('can:payroll-pay', only: ['pay']),
        ];
    }

    public function index(Request $request)
    {
        $filters = array_filter((array) $request->input('filter', []), fn ($value) => $value !== null && $value !== '');

        $runs = PayrollRun::query()
            ->visibleTo($request->user())
            ->with(['supplier:id,supplier_name,short_name', 'company:id,company_name,abbr', 'creator:id,name'])
            ->withCount('slips')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['month'] ?? null, fn ($query, $month) => $query->whereDate('period_month', Carbon::parse($month.'-01')->toDateString()))
            ->latest('period_month')->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('payroll.index', ['runs' => $runs, 'filters' => $filters, 'statuses' => PayrollRun::statusLabels()]);
    }

    public function create(Request $request)
    {
        return view('payroll.create', $this->ownerData($request) + ['month' => now()->subMonthNoOverflow()->format('Y-m')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m', 'before_or_equal:'.now()->format('Y-m')], 'owner' => ['nullable', 'string', 'max:30']]);
        [$supplierId, $companyId] = $this->resolveOwner($request, $data['owner'] ?? null);

        try {
            $run = $this->service->createRun($supplierId, $companyId, Carbon::parse($data['month'].'-01'), $request->user());
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('payroll.show', $run)->with('success', 'Payroll '.$run->run_number.' is ready. Check the payslips, then send it for approval.');
    }

    public function show(Request $request, PayrollRun $payroll)
    {
        $this->authorizeVisible($request, $payroll);

        return view('payroll.show', [
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

        return $this->attempt(fn () => $this->service->setAbsentDays($slip->setRelation('run', $payroll), (float) $data['absent_days']), $payroll, 'Absent days saved and the payslip recalculated.');
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

        return $this->attempt(fn () => $this->service->addManualLine($slip->setRelation('run', $payroll), SalaryComponent::findOrFail($data['salary_component_id']), (float) $data['amount'], $data['label'] ?? null), $payroll, 'Line added.');
    }

    public function removeLine(Request $request, PayrollRun $payroll, PayrollSlipLine $line): RedirectResponse
    {
        $this->authorizeVisible($request, $payroll);
        $this->authorizeMaker($request, $payroll);
        abort_unless($line->slip->payroll_run_id === $payroll->id, 404);

        return $this->attempt(fn () => $this->service->removeLine($line), $payroll, 'Line removed.');
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

        return redirect()->route('payroll.index')->with('success', 'Payroll deleted.');
    }

    public function help(): View
    {
        return view('payroll.help');
    }

    /**
     * @return array<string, mixed>
     */
    private function ownerData(Request $request): array
    {
        $user = $request->user();

        return [
            'suppliers' => Supplier::query()->when(! $user->hasAllSupplierAccess(), fn ($query) => $query->where('id', $user->supplier_id ?? 0))->orderBy('supplier_name')->get(['id', 'supplier_name']),
            'companies' => $user->hasAllSupplierAccess() ? Company::query()->orderBy('id')->limit(1)->get(['id', 'company_name']) : collect(),
        ];
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

    private function attempt(callable $action, PayrollRun $run, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (RuntimeException $e) {
            return redirect()->route('payroll.show', $run)->with('error', $e->getMessage());
        }

        return redirect()->route('payroll.show', $run)->with('success', $message);
    }
}
