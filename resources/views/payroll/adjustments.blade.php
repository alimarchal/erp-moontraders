@php
    $fmt = fn ($value) => number_format((float) $value, 2);
    $tone = ['pending' => 'ak-status-amber', 'applied' => 'ak-status-green'];
    $monthLabel = \Carbon\Carbon::parse($month.'-01')->format('F Y');
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb"><a href="{{ route('payroll.index') }}">Payroll</a><span aria-hidden="true">›</span><span>Bonus &amp; commission</span></nav>
                <h1 class="ak-title">Bonus &amp; commission <span class="ev-ur" lang="ur" dir="rtl">بونس اور کمیشن</span></h1>
                <p class="ak-sub">Decide extra pay for chosen employees and a chosen month. It lands on the salary slip by itself.</p>
            </div>
            <div class="ak-head-actions"><a href="{{ route('payroll.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Payroll</a></div>
        </div>
    </x-slot>
    @include('settings.partials.ui-style')
    @include('expense-vouchers._form-styles')
    <style>
        .pa-grid { display:grid; gap:14px; grid-template-columns:minmax(0,1fr); } @media (min-width:900px){ .pa-grid { grid-template-columns: 1fr 1fr 1fr; } .pa-span-3 { grid-column: span 3; } .pa-span-2 { grid-column: span 2; } }
        .pa-grid > * { min-width:0; } .pa-t { width:100%; border-collapse:collapse; font-size:13.5px; } .pa-t th { text-align:left; background:#f8fafc; padding:9px 12px; font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--ak-muted); border-bottom:1px solid var(--ak-border); } .pa-t td { padding:9px 12px; border-bottom:1px solid var(--ak-line); vertical-align:top; } .pa-t .n { text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }
        .pa-field input, .pa-field select, .pa-field textarea { width:100%; border:1px solid var(--ak-border); border-radius:8px; padding:0 12px; height:40px; font-size:14px; box-sizing:border-box; } .pa-field label { display:block; font-size:13px; font-weight:600; margin-bottom:4px; }
        .pa-field .select2-container { width:100% !important; }
    </style>
    <div class="ak-page" x-data="{ adding: {{ $errors->any() || old('employee_ids') ? 'true' : 'false' }} }">
        <x-status-message />
        <x-validation-errors class="mb-4" />

        <section class="ak-card" style="padding:16px 20px">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap">
                <div><h2 style="margin:0; font-size:15px; font-weight:700">Give extra pay for {{ $monthLabel }}</h2><p class="ak-hint" style="margin:2px 0 0">Choose one or many employees, the kind of pay and the amount.</p></div>
                <button type="button" class="ak-btn ak-btn-primary" @click="adding = ! adding" x-text="adding ? 'Close' : '＋ Add bonus or commission'"></button>
            </div>
            <form method="POST" action="{{ route('payroll.adjustments.store') }}" x-show="adding" x-cloak style="margin-top:16px; {{ $errors->any() || old('employee_ids') ? '' : 'display:none' }}">@csrf
                <div class="pa-grid">
                    <div class="pa-field"><label for="pa_month">For which month</label><input id="pa_month" type="month" name="month" required value="{{ old('month', $month) }}"></div>
                    <div class="pa-field"><label for="pa_component">Kind of pay</label>
                        <select id="pa_component" name="salary_component_id" required><option value="">Choose…</option>
                            @foreach($components as $component)<option value="{{ $component->id }}" @selected((int) old('salary_component_id') === $component->id)>{{ $component->type === 'earning' ? '＋' : '－' }} {{ $component->name }}</option>@endforeach</select></div>
                    <div class="pa-field"><label for="pa_amount">Amount for each employee (Rs)</label><input id="pa_amount" type="number" step="0.01" min="0.01" name="amount" required value="{{ old('amount') }}"></div>
                    <div class="pa-field pa-span-2"><label for="pa_employees">Employees</label>
                        <select id="pa_employees" name="employee_ids[]" multiple data-search-url="{{ route('payroll.adjustments.employees') }}" data-placeholder="Type a name or code, pick as many as you need"></select></div>
                    <div class="pa-field"><label for="pa_note">Note (optional)</label><input id="pa_note" type="text" name="note" maxlength="300" value="{{ old('note') }}" placeholder="e.g. Eid bonus, November sales target"></div>
                </div>
                <p class="ev-note">If that month's payroll is still a draft the amount appears on the slip right away. Otherwise it is picked up when the month's payroll is built.</p>
                <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:12px"><button type="button" class="ak-btn ak-btn-outline" @click="adding = false">Cancel</button><button class="ak-btn ak-btn-primary">Save</button></div>
            </form>
        </section>

        <form method="GET" class="ak-card" style="padding:14px 16px; display:flex; flex-wrap:wrap; gap:12px; align-items:end">
            <div class="ak-field"><label for="f_month">Month</label><input id="f_month" type="month" name="filter[month]" value="{{ $month }}" style="height:38px; border:1px solid var(--ak-border); border-radius:8px; padding:0 10px"></div>
            <div class="ak-field"><label for="f_comp">Kind</label><select id="f_comp" name="filter[component_id]" style="height:38px; border:1px solid var(--ak-border); border-radius:8px; padding:0 10px"><option value="">All</option>@foreach($components as $component)<option value="{{ $component->id }}" @selected((string) ($filters['component_id'] ?? '') === (string) $component->id)>{{ $component->name }}</option>@endforeach</select></div>
            <div class="ak-field"><label for="f_status">Status</label><select id="f_status" name="filter[status]" style="height:38px; border:1px solid var(--ak-border); border-radius:8px; padding:0 10px"><option value="">Any</option><option value="pending" @selected(($filters['status'] ?? '') === 'pending')>Waiting for payroll</option><option value="applied" @selected(($filters['status'] ?? '') === 'applied')>Paid out in payroll</option></select></div>
            <div class="ak-field" style="flex:1; min-width:180px"><label for="f_search">Employee</label><input id="f_search" type="search" name="filter[search]" value="{{ $filters['search'] ?? '' }}" placeholder="Name or code" style="height:38px; width:100%; border:1px solid var(--ak-border); border-radius:8px; padding:0 12px"></div>
            <button class="ak-btn ak-btn-primary" style="height:38px">Apply</button>
        </form>

        <div class="ak-card" style="padding:12px 18px; display:flex; gap:24px; flex-wrap:wrap"><span class="ak-muted">{{ $monthLabel }}</span><span>Extra pay planned: <b>Rs {{ $fmt($monthTotal) }}</b></span></div>

        <section class="ak-card" style="overflow-x:auto">
            <table class="pa-t" style="min-width:720px">
                <thead><tr><th>Employee</th><th>Kind</th><th>Note</th><th class="n">Amount (Rs)</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse($adjustments as $adjustment)
                        <tr>
                            <td><b>{{ $adjustment->employee?->name }}</b><div class="ak-muted">{{ $adjustment->employee?->employee_code }}{{ $adjustment->employee?->designation ? ' · '.$adjustment->employee->designation : '' }}</div></td>
                            <td>{{ $adjustment->component?->type === 'deduction' ? '－' : '＋' }} {{ $adjustment->component?->name }}</td>
                            <td>{{ $adjustment->note ?: '—' }}</td>
                            <td class="n"><b>{{ $fmt($adjustment->amount) }}</b></td>
                            <td><span class="ak-status {{ $tone[$adjustment->status] ?? '' }}"><i aria-hidden="true"></i>{{ $adjustment->isPending() ? 'Waiting for payroll' : 'In '.($adjustment->appliedRun?->run_number ?? 'payroll') }}</span></td>
                            <td class="n">@if($adjustment->isPending())<form method="POST" action="{{ route('payroll.adjustments.destroy', $adjustment) }}" onsubmit="return confirm('Remove this for {{ e($adjustment->employee?->name) }}?')">@csrf @method('DELETE')<button class="ak-btn ak-btn-danger-outline" style="padding:4px 12px">Remove</button></form>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="ak-c ak-muted" style="padding:32px">Nothing planned for {{ $monthLabel }}. Use "Add bonus or commission" to give extra pay to an employee.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div style="padding:12px 16px">{{ $adjustments->links() }}</div>
        </section>
    </div>
    @push('scripts')
        <script>
            $(function () {
                const $e = $('#pa_employees');
                $e.select2({ width: '100%', placeholder: $e.data('placeholder'), closeOnSelect: false, ajax: { url: $e.data('search-url'), dataType: 'json', delay: 250, data: (p) => ({ q: p.term || '' }), processResults: (d) => d } });
                $('#pa_component').select2({ width: '100%', placeholder: 'Choose…' });
            });
        </script>
    @endpush
</x-app-layout>
