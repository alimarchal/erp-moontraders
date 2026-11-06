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
    @include('expense-vouchers._form-styles')
    <div class="ak-page">
        <x-status-message />
        <form method="GET" class="ak-card" style="padding:14px 16px; display:flex; flex-wrap:wrap; gap:12px; align-items:end">
            <div class="ak-field"><label for="f_month">Month</label><input id="f_month" type="month" name="filter[month]" value="{{ $filters['month'] ?? '' }}" style="height:38px; border:1px solid var(--ak-border); border-radius:8px; padding:0 10px"></div>
            <div class="ak-field"><label for="f_status">Status</label><select id="f_status" name="filter[status]" style="height:38px; border:1px solid var(--ak-border); border-radius:8px; padding:0 10px"><option value="">Any</option>@foreach($statuses as $key => $label)<option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
            <button class="ak-btn ak-btn-primary" style="height:38px">Apply</button>
            @if($filters)<a href="{{ route('payroll.index') }}" class="ak-btn ak-btn-outline" style="height:38px">Clear</a>@endif
        </form>
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
