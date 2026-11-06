<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb"><a href="{{ route('payroll.index') }}">Payroll</a><span aria-hidden="true">›</span><span>Employee salaries</span></nav>
                <h1 class="ak-title">Employee salaries <span class="ev-ur" lang="ur" dir="rtl">ملازمین کی تنخواہ</span></h1>
                <p class="ak-sub">Set each employee's basic salary and allowances once. Payroll uses them every month.</p>
            </div>
            <div class="ak-head-actions"><a href="{{ route('payroll.components.index') }}" class="ak-btn ak-btn-outline">Allowances &amp; deductions</a><a href="{{ route('payroll.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Payroll</a></div>
        </div>
    </x-slot>
    @include('settings.partials.ui-style')
    @include('settings.partials.select2-init')
    @include('expense-vouchers._form-styles')
    <div class="ak-page">
        <x-status-message />
        <form method="GET" class="ak-card" style="padding:14px 16px; display:flex; flex-wrap:wrap; gap:12px; align-items:end">
            <div class="ak-field" style="flex:2; min-width:200px"><label for="f_search">Search</label><input id="f_search" type="search" name="filter[search]" value="{{ $filters['search'] ?? '' }}" placeholder="Name or employee code" style="height:38px; width:100%; border:1px solid var(--ak-border); border-radius:8px; padding:0 12px"></div>
            <div class="ak-field" style="min-width:170px"><label for="f_des">Designation</label><select class="js-select2" id="f_des" name="filter[designation]" style="height:38px; border:1px solid var(--ak-border); border-radius:8px; padding:0 10px"><option value="">All</option>@foreach($designations as $d)<option @selected(($filters['designation'] ?? '') === $d)>{{ $d }}</option>@endforeach</select></div>
            <div class="ak-field" style="min-width:150px"><label for="f_sal">Salary</label><select class="js-select2" id="f_sal" name="filter[salary]" style="height:38px; border:1px solid var(--ak-border); border-radius:8px; padding:0 10px"><option value="">All</option><option value="missing" @selected(($filters['salary'] ?? '') === 'missing')>Not set yet</option><option value="set" @selected(($filters['salary'] ?? '') === 'set')>Already set</option></select></div>
            <button class="ak-btn ak-btn-primary" style="height:38px">Apply</button>
            @if($filters)<a href="{{ route('payroll.salaries.index') }}" class="ak-btn ak-btn-outline" style="height:38px">Clear</a>@endif
        </form>
        <section class="ak-card" style="overflow-x:auto" x-data="{ picked: 0, count() { this.picked = document.querySelectorAll('.sal-pick:checked').length }, all(on) { document.querySelectorAll('.sal-pick').forEach(box => box.checked = on); this.count(); } }">
            <table class="ak-dt" style="min-width:760px">
                <thead><tr><th style="width:34px"><input type="checkbox" aria-label="Tick every employee on this page" @change="all($event.target.checked)"></th><th>Employee</th><th>Company</th><th>Type</th><th class="ak-num">Basic (Rs)</th><th>Paid by</th><th></th></tr></thead>
                <tbody>
                    @forelse($employees as $employee)
                        <tr>
                            <td><input type="checkbox" class="sal-pick" form="bulk-form" name="employee_ids[]" value="{{ $employee->id }}" aria-label="Select {{ $employee->name }}" @change="count()"></td>
                            <td><b>{{ $employee->name }}</b><div class="ak-muted">{{ $employee->employee_code }}{{ $employee->designation ? ' · '.$employee->designation : '' }}</div></td>
                            <td>{{ $employee->supplier?->short_name ?? $employee->supplier?->supplier_name ?? 'Moon Traders' }}</td>
                            <td>{{ $employee->salary ? ($employee->salary->salary_type === 'fixed' ? 'Fixed' : 'Basic') : '—' }}</td>
                            <td class="ak-num ak-strong">{{ $employee->salary ? number_format((float) $employee->salary->basic_salary, 2) : '—' }}</td>
                            <td>{{ $employee->salary ? ucfirst($employee->salary->pay_method) : '—' }}</td>
                            <td><a href="{{ route('payroll.salaries.edit', $employee) }}" class="ak-btn {{ $employee->salary ? 'ak-btn-outline' : 'ak-btn-primary' }}" style="padding:4px 12px">{{ $employee->salary ? 'Edit' : 'Set salary' }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="ak-c ak-muted" style="padding:32px">No employees match.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div style="padding:12px 16px">{{ $employees->links() }}</div>

            <form id="bulk-form" method="POST" action="{{ route('payroll.salaries.bulk') }}" x-show="picked > 0" x-cloak style="display:none; border-top:1px solid var(--ak-border); padding:16px 20px; background:#f8fafc">
                @csrf
                <h2 style="margin:0 0 4px; font-size:15px; font-weight:700">Set the same salary for <span x-text="picked"></span> selected <span x-text="picked === 1 ? 'employee' : 'employees'"></span></h2>
                <p class="ak-hint" style="margin:0 0 12px">Leave a field empty to keep what each employee already has. Type 0 in an allowance to remove it.</p>
                <div style="display:grid; gap:10px; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr))">
                    <div class="ak-field"><label for="b_type">Salary type</label><select class="js-select2" id="b_type" name="salary_type"><option value="">Keep as it is</option><option value="basic">Basic (cut for absent days)</option><option value="fixed">Fixed (same every month)</option></select></div>
                    <div class="ak-field"><label for="b_basic">Monthly salary (Rs)</label><input id="b_basic" type="number" step="0.01" min="0" name="basic_salary" placeholder="Keep as it is" style="height:38px; width:100%; border:1px solid var(--ak-border); border-radius:8px; padding:0 10px"></div>
                    <div class="ak-field"><label for="b_pay">Usually paid by</label><select class="js-select2" id="b_pay" name="pay_method"><option value="">Keep as it is</option><option value="cash">Cash</option><option value="bank">Bank</option></select></div>
                    @foreach($components as $component)
                        <div class="ak-field"><label for="b_c{{ $component->id }}">{{ $component->name }} <span class="ak-muted" style="font-weight:400">({{ $component->type === 'earning' ? 'earning' : 'deduction' }}{{ $component->calculation === 'percent_of_basic' ? ', % of basic' : '' }})</span></label><input id="b_c{{ $component->id }}" type="number" step="0.01" min="0" name="components[{{ $component->id }}]" placeholder="Keep as it is" style="height:38px; width:100%; border:1px solid var(--ak-border); border-radius:8px; padding:0 10px"></div>
                    @endforeach
                </div>
                <div style="margin-top:14px"><button class="ak-btn ak-btn-primary">Save for selected employees</button></div>
            </form>
        </section>
    </div>
</x-app-layout>
