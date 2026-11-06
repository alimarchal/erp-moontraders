@php $tone = ['draft' => 'ak-status-slate', 'pending' => 'ak-status-amber', 'approved' => 'ak-status-green', 'paid' => 'ak-status-green', 'rejected' => 'ak-status-red']; @endphp
<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a><span aria-hidden="true">›</span><span>Payroll</span></nav>
                <h1 class="ak-title">Payroll <span class="ev-ur" lang="ur" dir="rtl">تنخواہیں</span></h1>
                <p class="ak-sub">One run per company per month: earnings, deductions and advances in one place.</p>
            </div>
            <div class="ak-head-actions">
                <button type="button" class="ak-btn ak-btn-outline" x-data @click="$dispatch('pr-toggle-filters')" aria-controls="pr-filter-panel" title="Show or hide the filters">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" /></svg>
                    Filters @if(collect($filters)->only(['search', 'month', 'month_from', 'month_to', 'status', 'owner'])->count())<span class="ak-count ak-count-dark">{{ collect($filters)->only(['search', 'month', 'month_from', 'month_to', 'status', 'owner'])->count() }}</span>@endif
                </button>
                <a href="{{ route('payroll.list.pdf', request()->only('filter')) }}" class="ak-btn ak-btn-outline">List PDF</a>
                <a href="{{ route('payroll.list.excel', request()->only('filter')) }}" class="ak-btn ak-btn-outline">List Excel</a>
                <a href="{{ route('payroll.help') }}" class="ak-btn ak-btn-outline">How it works</a>
                @can('payroll-adjustment-manage')<a href="{{ route('payroll.adjustments.index') }}" class="ak-btn ak-btn-outline">Bonus &amp; commission</a>@endcan
                @can('payroll-salary-manage')
                    <a href="{{ route('payroll.salaries.index') }}" class="ak-btn ak-btn-outline">Employee salaries</a>
                    <a href="{{ route('payroll.components.index') }}" class="ak-btn ak-btn-outline">Allowances &amp; deductions</a>
                @endcan
                @can('payroll-create')<a href="{{ route('payroll.create') }}" class="ak-btn ak-btn-primary"><span aria-hidden="true">＋</span> Run payroll</a>@endcan
            </div>
        </div>
    </x-slot>
    @include('settings.partials.ui-style')
    @include('settings.partials.select2-init')
    @include('expense-vouchers._form-styles')
    <div class="ak-page">
        <x-status-message />
        @php $filterCount = collect($filters)->only(['search', 'month', 'month_from', 'month_to', 'status', 'owner'])->count(); @endphp
        <section id="pr-filter-panel" class="ak-card" aria-label="Filters" x-data="{ open: {{ $filterCount ? 'true' : 'false' }} }" @pr-toggle-filters.window="open = ! open" x-show="open" x-cloak x-transition style="{{ $filterCount ? '' : 'display:none' }}">
            <form method="GET" action="{{ route('payroll.index') }}" class="ak-filters" style="padding:14px 16px">
                <div style="display:grid; gap:12px; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); align-items:end">
                    <div class="ak-field"><label for="f_search">Payroll no.</label><input id="f_search" type="search" name="filter[search]" value="{{ $filters['search'] ?? '' }}" placeholder="e.g. PAY-2026" autocomplete="off"></div>
                    <div class="ak-field"><label for="f_from">From month</label><input id="f_from" type="month" name="filter[month_from]" value="{{ $filters['month_from'] ?? '' }}"></div>
                    <div class="ak-field"><label for="f_to">To month</label><input id="f_to" type="month" name="filter[month_to]" value="{{ $filters['month_to'] ?? '' }}"></div>
                    @if($suppliers->isNotEmpty())
                        <div class="ak-field"><label for="f_owner">Company</label><select class="js-select2" id="f_owner" name="filter[owner]"><option value="">All</option><option value="company" @selected(($filters['owner'] ?? '') === 'company')>All employees (company-wide)</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected((string) ($filters['owner'] ?? '') === (string) $supplier->id)>{{ $supplier->supplier_name }}</option>@endforeach</select></div>
                    @endif
                    <div class="ak-field"><label for="f_status">Status</label><select class="js-select2" id="f_status" name="filter[status]"><option value="">Any</option>@foreach($statuses as $key => $label)<option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
                    <div class="ak-filter-buttons" style="display:flex; gap:8px"><button class="ak-btn ak-btn-primary" style="height:38px">Apply</button>@if($filters)<a href="{{ route('payroll.index') }}" class="ak-btn ak-btn-outline" style="height:38px">Clear</a>@endif</div>
                </div>
            </form>
        </section>
        <section class="ak-card" style="overflow-x:auto">
            <table class="ak-dt" style="min-width:860px">
                <thead><tr><th>Run</th><th>Month</th><th>Company</th><th class="ak-num">Employees</th><th class="ak-num">Earnings</th><th class="ak-num">Deductions</th><th class="ak-num">Net pay</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse($runs as $run)
                        <tr>
                            <td><a class="ak-primary-link" href="{{ route('payroll.show', $run) }}">{{ $run->run_number }}</a></td>
                            <td>{{ $run->period_month->format('F Y') }}</td>
                            <td>{{ $run->ownerLabel() }}</td>
                            <td class="ak-num">{{ $run->slips_count }}</td>
                            <td class="ak-num">{{ number_format((float) $run->total_earnings, 2) }}</td>
                            <td class="ak-num">{{ number_format((float) $run->total_deductions, 2) }}</td>
                            <td class="ak-num ak-strong">{{ number_format((float) $run->total_net, 2) }}</td>
                            <td><span class="ak-status {{ $tone[$run->status] ?? '' }}"><i aria-hidden="true"></i>{{ $run->statusLabel() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="ak-c ak-muted" style="padding:32px">No payroll yet. Click "Run payroll" to build a month.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div style="padding:12px 16px">{{ $runs->links() }}</div>
        </section>
    </div>
</x-app-layout>
