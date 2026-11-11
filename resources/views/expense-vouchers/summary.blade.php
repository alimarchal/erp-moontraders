@php $fmt = fn ($value) => number_format((float) $value, 2); @endphp
<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb"><a href="{{ route('expense-vouchers.index') }}">Expense Vouchers</a><span aria-hidden="true">›</span><span>Summary</span></nav>
                <h1 class="ak-title">Expense summary <span class="ev-ur" lang="ur" dir="rtl">اخراجات کا خلاصہ</span></h1>
                <p class="ak-sub">Approved expenses of {{ $from->format('d M Y') }} to {{ $to->format('d M Y') }} by company, category and shop.</p>
            </div>
            <div class="ak-head-actions"><a href="{{ route('expense-vouchers.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Vouchers</a><button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button></div>
        </div>
    </x-slot>
    @include('settings.partials.ui-style')
    <x-apple-skin />
    <style>
        .xs-t { width:100%; border-collapse:collapse; font-size:13.5px; } .xs-t th { text-align:left; background:#f8fafc; padding:9px 12px; font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--ak-muted); border-bottom:1px solid var(--ak-border); } .xs-t td { padding:9px 12px; border-bottom:1px solid var(--ak-line); } .xs-t .n { text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; } .xs-t tfoot td { font-weight:800; background:#f8fafc; border-top:2px solid var(--ak-border); }
        .xs-kpis { display:grid; gap:14px; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); } .xs-kpi { padding:14px 18px; } .xs-kpi small { display:block; font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:var(--ak-muted); } .xs-kpi b { display:block; margin-top:3px; font-size:22px; font-variant-numeric:tabular-nums; }
        @media print { .no-print { display:none !important; } }
    </style>
    <div class="ak-page">
        <form method="GET" class="ak-card no-print" style="padding:14px 16px; display:flex; flex-wrap:wrap; gap:12px; align-items:end">
            <div class="ak-field"><label for="f_from">From</label><input id="f_from" type="date" name="date_from" value="{{ $from->toDateString() }}" max="{{ now()->toDateString() }}" style="height:38px; border:1px solid var(--ak-border); border-radius:8px; padding:0 10px"></div>
            <div class="ak-field"><label for="f_to">To</label><input id="f_to" type="date" name="date_to" value="{{ $to->toDateString() }}" max="{{ now()->toDateString() }}" style="height:38px; border:1px solid var(--ak-border); border-radius:8px; padding:0 10px"></div>
            <div class="ak-field"><label for="f_owner">Company</label>
                <select id="f_owner" name="owner" style="height:38px; border:1px solid var(--ak-border); border-radius:8px; padding:0 10px; max-width:260px"><option value="">All companies</option>
                    @foreach($companies as $company)<option value="company:{{ $company->id }}" @selected($owner === 'company:'.$company->id)>{{ $company->company_name }} (general)</option>@endforeach
                    @foreach($suppliers as $supplier)<option value="supplier:{{ $supplier->id }}" @selected($owner === 'supplier:'.$supplier->id)>{{ $supplier->supplier_name }}</option>@endforeach</select></div>
            <button class="ak-btn ak-btn-primary" style="height:38px">Apply</button>
        </form>
        <div class="xs-kpis">
            <div class="ak-card xs-kpi"><small>Vouchers</small><b>{{ number_format((int) ($grand->vouchers ?? 0)) }}</b></div>
            <div class="ak-card xs-kpi"><small>Total expense</small><b>Rs {{ $fmt($grand->total ?? 0) }}</b></div>
            <div class="ak-card xs-kpi"><small>Still unpaid (credit)</small><b style="color:#b91c1c">Rs {{ $fmt($grand->owed ?? 0) }}</b></div>
        </div>
        @foreach([['By company', $byOwner], ['By category', $byCategory], ['By shop (top 50)', $byVendor]] as [$title, $rows])
            <section class="ak-card" style="overflow-x:auto">
                <h2 style="margin:0; padding:14px 18px 0; font-size:15px; font-weight:700">{{ $title }}</h2>
                <table class="xs-t" style="min-width:520px">
                    <thead><tr><th>{{ str_replace('By ', '', $title) }}</th><th class="n">Vouchers</th><th class="n">Total (Rs)</th><th class="n">Unpaid (Rs)</th></tr></thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr><td>{{ $row->label ?: '—' }}</td><td class="n">{{ $row->vouchers }}</td><td class="n">{{ $fmt($row->total) }}</td><td class="n">{{ (float) $row->owed > 0 ? $fmt($row->owed) : '—' }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="ak-c ak-muted" style="padding:20px">Nothing for this period.</td></tr>
                        @endforelse
                    </tbody>
                    @if($rows->isNotEmpty())<tfoot><tr><td>Total</td><td class="n">{{ $rows->sum('vouchers') }}</td><td class="n">{{ $fmt($rows->sum('total')) }}</td><td class="n">{{ $fmt($rows->sum('owed')) }}</td></tr></tfoot>@endif
                </table>
            </section>
        @endforeach
    </div>
</x-app-layout>
