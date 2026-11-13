<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Bonuses, commissions, overtime, fines, death insurance and the like, decided ahead for chosen employees and a chosen month.
 * Payroll pulls them into that month's slips by itself.
 */
class PayrollAdjustmentController extends Controller implements HasMiddleware
{
    public function __construct(private PayrollService $payroll) {}

    public static function middleware(): array
    {
        return [new Middleware('can:payroll-adjustment-manage')];
    }

    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('payroll.index', array_filter(['tab' => 'adjustments', 'filter' => $request->input('filter')]));
    }

    /**
     * Give the same amount to one or many employees for a month.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'salary_component_id' => ['required', Rule::exists('salary_components', 'id')->where('is_active', true)],
            'employee_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        $month = Carbon::parse($data['month'].'-01');
        $user = $request->user();
        $employees = Employee::query()->whereIn('id', $data['employee_ids'])
            ->when(! $user->hasAllSupplierAccess(), fn ($query) => $query->where('supplier_id', $user->supplier_id ?? 0))
            ->get();

        abort_if($employees->count() !== count(array_unique($data['employee_ids'])), 403, 'You can only add pay for employees of your own company.');

        try {
            DB::transaction(function () use ($employees, $month, $data) {
                foreach ($employees as $employee) {
                    if ($this->payroll->monthIsLocked($employee, $month)) {
                        throw new RuntimeException($employee->name.' already has an approved or pending payroll for '.$month->format('F Y').'. Add it to a later month, or ask for the run to be rejected first.');
                    }

                    $adjustment = PayrollAdjustment::create([
                        'employee_id' => $employee->id, 'salary_component_id' => $data['salary_component_id'], 'period_month' => $month,
                        'amount' => $data['amount'], 'note' => $data['note'] ?? null, 'status' => PayrollAdjustment::STATUS_PENDING,
                    ]);
                    $this->payroll->refreshAdjustment($adjustment);
                }
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('payroll.index', ['tab' => 'adjustments', 'filter' => ['month' => $data['month']]])
            ->with('success', 'Added for '.$employees->count().' '.Str::plural('employee', $employees->count()).' in '.$month->format('F Y').'. It will appear on their salary slip by itself.');
    }

    public function destroy(Request $request, PayrollAdjustment $adjustment): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasAllSupplierAccess() || (int) $adjustment->employee?->supplier_id === (int) $user->supplier_id, 404);

        if (! $adjustment->isPending() || $this->payroll->monthIsLocked($adjustment->employee, $adjustment->period_month)) {
            return back()->with('error', 'This one is already part of an approved or pending payroll, so it cannot be removed.');
        }

        DB::transaction(function () use ($adjustment) {
            $adjustment->delete();
            $this->payroll->refreshAdjustment($adjustment);
        });

        return back()->with('success', 'Removed.');
    }

    /**
     * Employees as the user types (select2), limited to their own company unless they are an admin.
     */
    public function employees(Request $request): JsonResponse
    {
        $user = $request->user();
        $term = trim((string) $request->query('q', ''));

        $employees = Employee::query()
            ->where('is_active', true)
            ->when(! $user->hasAllSupplierAccess(), fn ($query) => $query->where('supplier_id', $user->supplier_id ?? 0))
            ->when($term !== '', fn ($query) => $query->where(fn ($q) => $q->whereLike('name', "%{$term}%")->orWhereLike('employee_code', "%{$term}%")))
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name', 'employee_code', 'designation']);

        return response()->json(['results' => $employees->map(fn (Employee $employee) => [
            'id' => $employee->id,
            'text' => $employee->name.' ('.$employee->employee_code.')'.($employee->designation ? ' - '.$employee->designation : ''),
        ])->values()]);
    }
}
