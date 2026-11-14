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
 * Incentives & adjustments: commissions, bonuses, overtime, fines and the like, decided ahead for chosen employees, for one
 * month or every month. Payroll pulls them into the month's slips by itself.
 */
class PayrollAdjustmentController extends Controller implements HasMiddleware
{
    public const REPEAT_ONCE = 'once';

    public const REPEAT_MONTHLY = 'monthly';

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
     * Give the same amount to one or many employees: for one month only, or every month from that month (until an end
     * month or until stopped).
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
            'repeat' => ['nullable', Rule::in([self::REPEAT_ONCE, self::REPEAT_MONTHLY])],
            'end_month' => ['nullable', 'date_format:Y-m', 'after_or_equal:month'],
        ], ['end_month.after_or_equal' => 'The last month cannot be before the first month.']);

        $month = Carbon::parse($data['month'].'-01');
        $recurring = ($data['repeat'] ?? self::REPEAT_ONCE) === self::REPEAT_MONTHLY;
        $endMonth = $recurring && ! empty($data['end_month']) ? Carbon::parse($data['end_month'].'-01') : null;
        $user = $request->user();
        $employees = Employee::query()->whereIn('id', $data['employee_ids'])
            ->when(! $user->hasAllSupplierAccess(), fn ($query) => $query->where('supplier_id', $user->supplier_id ?? 0))
            ->get();

        abort_if($employees->count() !== count(array_unique($data['employee_ids'])), 403, 'You can only add pay for employees of your own company.');

        try {
            DB::transaction(function () use ($employees, $month, $data, $recurring, $endMonth) {
                foreach ($employees as $employee) {
                    $this->payroll->addAdjustment($employee, (int) $data['salary_component_id'], $month, (float) $data['amount'], $data['note'] ?? null, $recurring, $endMonth);
                }
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $when = $recurring ? 'every month from '.$month->format('F Y').($endMonth ? ' to '.$endMonth->format('F Y') : '') : 'in '.$month->format('F Y');

        return redirect()->route('payroll.index', ['tab' => 'adjustments', 'filter' => ['month' => $data['month']]])
            ->with('success', 'Added for '.$employees->count().' '.Str::plural('employee', $employees->count()).' '.$when.'. It will appear on their salary slip by itself.');
    }

    public function destroy(Request $request, PayrollAdjustment $adjustment): RedirectResponse
    {
        $this->authorizeAdjustment($request, $adjustment);

        $locked = $adjustment->is_recurring
            ? $this->payroll->adjustmentIsLocked($adjustment)
            : (! $adjustment->isPending() || $this->payroll->monthIsLocked($adjustment->employee, $adjustment->period_month));

        if ($locked) {
            return back()->with('error', $adjustment->is_recurring
                ? 'It has already been paid on an approved or pending payroll, so it cannot be removed. Use Stop to end it from a later month.'
                : 'This one is already part of an approved or pending payroll, so it cannot be removed.');
        }

        DB::transaction(function () use ($adjustment) {
            $adjustment->delete();
            $this->payroll->refreshAdjustment($adjustment);
        });

        return back()->with('success', 'Removed.');
    }

    /**
     * End a recurring item: it is no longer paid from the chosen month on.
     */
    public function stop(Request $request, PayrollAdjustment $adjustment): RedirectResponse
    {
        $this->authorizeAdjustment($request, $adjustment);
        abort_unless($adjustment->is_recurring, 404);

        $data = $request->validate(['from_month' => ['required', 'date_format:Y-m']]);
        $from = Carbon::parse($data['from_month'].'-01');

        try {
            $this->payroll->stopAdjustment($adjustment, $from);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', ($adjustment->component?->name ?? 'It').' of '.$adjustment->employee?->name.' stops from '.$from->format('F Y').'.');
    }

    private function authorizeAdjustment(Request $request, PayrollAdjustment $adjustment): void
    {
        $user = $request->user();

        abort_unless($user->hasAllSupplierAccess() || (int) $adjustment->employee?->supplier_id === (int) $user->supplier_id, 404);
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
