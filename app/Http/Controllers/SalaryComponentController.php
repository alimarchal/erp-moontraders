<?php

namespace App\Http\Controllers;

use App\Models\SalaryComponent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The categories that can appear on a payslip: allowances (earnings) and deductions. New ones can be added any time,
 * from the Categories tab or straight from the salary window.
 */
class SalaryComponentController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:payroll-salary-manage')];
    }

    public function index(): RedirectResponse
    {
        return redirect()->route('payroll.index', ['tab' => 'categories']);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->validated($request);
        $code = Str::slug($data['name'], '_') ?: 'category';
        $code = SalaryComponent::where('code', $code)->exists() ? $code.'_'.Str::lower(Str::random(4)) : $code;

        $component = SalaryComponent::create($data + ['code' => $code, 'is_active' => true, 'sort_order' => 200]);

        if ($request->expectsJson()) {
            return response()->json(['component' => $component->only(['id', 'name', 'type', 'calculation', 'default_value'])], 201);
        }

        return redirect()->route('payroll.index', ['tab' => 'categories'])->with('success', '"'.$data['name'].'" added.');
    }

    /**
     * Categories are shared by every company, so only users who see all companies may change one.
     */
    public function update(Request $request, SalaryComponent $component): RedirectResponse
    {
        abort_unless($request->user()->hasAllSupplierAccess(), 403, 'Only an administrator can change a category that every company uses.');

        $data = $this->validated($request, $component) + ['is_active' => $request->boolean('is_active')];
        $inUse = $component->employeeAssignments()->exists();

        // Turning a category in use from earning to deduction, or into a monthly amount, would silently change every payslip.
        if ($inUse && ($data['type'] !== $component->type || ($data['calculation'] === SalaryComponent::CALC_INPUT) !== ($component->calculation === SalaryComponent::CALC_INPUT))) {
            return back()->withInput()->withErrors(['type' => 'Employees already have "'.$component->name.'". Its kind and whether it is paid every month cannot change; add a new category instead.']);
        }

        $component->update($data);

        return redirect()->route('payroll.index', ['tab' => 'categories'])->with('success', '"'.$component->name.'" updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?SalaryComponent $component = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('salary_components', 'name')->ignore($component?->id)],
            'type' => ['required', Rule::in([SalaryComponent::TYPE_EARNING, SalaryComponent::TYPE_DEDUCTION])],
            'calculation' => ['required', Rule::in([SalaryComponent::CALC_FIXED, SalaryComponent::CALC_PERCENT, SalaryComponent::CALC_PERCENT_COMPONENT, SalaryComponent::CALC_INPUT])],
            'default_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'account_id' => ['nullable', 'exists:chart_of_accounts,id'],
        ], ['name.unique' => 'A category with this name already exists.']);
        $data['default_value'] = $data['default_value'] ?? 0;

        return $data;
    }
}
