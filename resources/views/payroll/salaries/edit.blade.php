<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb"><a href="{{ route('payroll.salaries.index') }}">Employee salaries</a><span aria-hidden="true">›</span><span>{{ $employee->name }}</span></nav>
                <h1 class="ak-title" style="margin:0">Salary of {{ $employee->name }}</h1>
                <p class="ak-sub">{{ $employee->employee_code }}{{ $employee->designation ? ' · '.$employee->designation : '' }}</p>
            </div>
            <div class="ak-head-actions"><a href="{{ route('payroll.salaries.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a></div>
        </div>
    </x-slot>
    @include('settings.partials.ui-style')
    @include('expense-vouchers._form-styles')
    <div class="ak-page">
        <x-status-message />
        <x-validation-errors class="mb-4" />
        <form method="POST" action="{{ route('payroll.salaries.update', $employee) }}" class="ak-card ev-form" x-data="{ type: @js(old('salary_type', $salary->salary_type)) }">
            @csrf @method('PUT')
            <div class="ev-form-grid">
                <fieldset class="ev-span-3 ev-payee">
                    <legend class="ev-bi" style="width:100%"><span>Salary type</span></legend>
                    <div class="ev-seg">
                        <label :class="{ 'is-on': type === 'basic' }"><input type="radio" name="salary_type" value="basic" x-model="type"> Basic salary <span class="ak-muted" style="font-weight:400">(cut for absent days)</span></label>
                        <label :class="{ 'is-on': type === 'fixed' }"><input type="radio" name="salary_type" value="fixed" x-model="type"> Fixed salary <span class="ak-muted" style="font-weight:400">(same every month)</span></label>
                    </div>
                    <p class="ev-note" x-show="type === 'basic'">Basic: each absent day costs one day of pay (basic ÷ days in the month). Allowances are not cut.</p>
                    <p class="ev-note" x-show="type === 'fixed'" x-cloak>Fixed: the full amount is paid every month whatever the attendance. Add a fine by hand if one is needed.</p>
                </fieldset>
                <div class="ak-field"><label for="basic_salary">Monthly <span x-text="type === 'fixed' ? 'fixed' : 'basic'"></span> salary (Rs)</label><input id="basic_salary" type="number" step="0.01" min="0" name="basic_salary" required value="{{ old('basic_salary', $salary->basic_salary) }}"></div>
                <div class="ak-field"><label for="pay_method">Usually paid by</label><select id="pay_method" name="pay_method" style="height:40px; border:1px solid var(--ak-border); border-radius:8px; padding:0 12px"><option value="cash" @selected(old('pay_method', $salary->pay_method) === 'cash')>Cash</option><option value="bank" @selected(old('pay_method', $salary->pay_method) === 'bank')>Bank</option></select></div>
                <div class="ak-field"><label for="notes">Notes</label><input id="notes" type="text" name="notes" maxlength="500" value="{{ old('notes', $salary->notes) }}"></div>
            </div>
            <h2 style="margin:22px 0 4px; font-size:15px; font-weight:700">Allowances and regular deductions</h2>
            <p class="ak-hint" style="margin-bottom:10px">Leave empty or 0 for the ones this employee does not get. Overtime, incentive, bonus, fines and tax are added in the month they happen.</p>
            <div style="display:grid; gap:10px; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr))">
                @foreach($components as $component)
                    <div class="ak-field">
                        <label for="c{{ $component->id }}">{{ $component->name }} <span class="ak-muted" style="font-weight:400">({{ $component->type === 'earning' ? 'earning' : 'deduction' }}{{ $component->calculation === 'percent_of_basic' ? ', % of basic' : '' }})</span></label>
                        <input id="c{{ $component->id }}" type="number" step="0.01" min="0" name="components[{{ $component->id }}]" value="{{ old('components.'.$component->id, isset($assigned[$component->id]) ? (float) $assigned[$component->id]->value : '') }}" placeholder="0">
                    </div>
                @endforeach
            </div>
            @if($advances->isNotEmpty())
                <div class="ev-note" style="margin-top:18px"><b>Open advances:</b>
                    @foreach($advances as $advance)<div>{{ $advance->advance_date->format('d M Y') }} · Rs {{ number_format((float) $advance->amount, 2) }} · balance Rs {{ number_format($advance->balance(), 2) }}{{ $advance->installment_amount ? ' · instalment Rs '.number_format((float) $advance->installment_amount, 2) : ' · recovered in one go' }}</div>@endforeach
                    Payroll recovers these automatically; nothing to enter here.</div>
            @endif
            <div class="ev-form-foot"><a href="{{ route('payroll.salaries.index') }}" class="ak-btn ak-btn-outline">Cancel</a><button class="ak-btn ak-btn-primary">Save salary</button></div>
        </form>
    </div>
</x-app-layout>
