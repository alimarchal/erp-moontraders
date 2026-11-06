<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\SalaryComponent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The allowances and deductions that can appear on a payslip.
 */
class SalaryComponentController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:payroll-salary-manage')];
    }

    public function index()
    {
        return view('payroll.components', [
            'components' => SalaryComponent::query()->with('account:id,account_code,account_name')->orderBy('type')->orderBy('sort_order')->orderBy('name')->get(),
            'accounts' => ChartOfAccount::query()->where('is_group', false)->whereIn(DB::raw('SUBSTRING(account_code, 1, 2)'), ['11', '21', '52'])->orderBy('account_code')->get(['id', 'account_code', 'account_name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $code = Str::slug($data['name'], '_');
        $code = SalaryComponent::where('code', $code)->exists() ? $code.'_'.Str::lower(Str::random(4)) : $code;

        SalaryComponent::create($data + ['code' => $code, 'is_active' => true, 'sort_order' => 200]);

        return back()->with('success', '"'.$data['name'].'" added.');
    }

    public function update(Request $request, SalaryComponent $component): RedirectResponse
    {
        $data = $this->validated($request) + ['is_active' => $request->boolean('is_active')];
        $component->update($data);

        return back()->with('success', '"'.$component->name.'" updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in([SalaryComponent::TYPE_EARNING, SalaryComponent::TYPE_DEDUCTION])],
            'calculation' => ['required', Rule::in([SalaryComponent::CALC_FIXED, SalaryComponent::CALC_PERCENT, SalaryComponent::CALC_INPUT])],
            'default_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'account_id' => ['nullable', 'exists:chart_of_accounts,id'],
        ]);
        $data['default_value'] = $data['default_value'] ?? 0;

        return $data;
    }
}
