@php $tone = ['pending' => 'ak-status-amber', 'approved' => 'ak-status-green', 'rejected' => 'ak-status-red']; @endphp
<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a><span aria-hidden="true">›</span><span>Vendor payments</span></nav>
                <h1 class="ak-title">Vendor payments <span class="ev-ur" lang="ur" dir="rtl">شاپس کو ادائیگی</span></h1>
                <p class="ak-sub">Month-end payments that clear the credit vouchers of shops.</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('expense-vouchers.help') }}" class="ak-btn ak-btn-outline">How it works</a>
                @can('expense-vendor-payment-create')<a href="{{ route('expense-vendor-payments.create') }}" class="ak-btn ak-btn-primary"><span aria-hidden="true">＋</span> New payment</a>@endcan
            </div>
        </div>
    </x-slot>
    @include('settings.partials.ui-style')
    @include('expense-vouchers._form-styles')
    <div class="ak-page">
        <x-status-message />
        <form method="GET" class="ak-card" style="padding:14px 16px; display:flex; flex-wrap:wrap; gap:12px; align-items:end">
            <div class="ak-field"><label for="f_from">From</label><input id="f_from" type="date" name="filter[date_from]" value="{{ $filters['date_from'] ?? '' }}" style="height:38px; border:1px solid var(--ak-border); border-radius:8px; padding:0 10px"></div>
            <div class="ak-field"><label for="f_to">To</label><input id="f_to" type="date" name="filter[date_to]" value="{{ $filters['date_to'] ?? '' }}" style="height:38px; border:1px solid var(--ak-border); border-radius:8px; padding:0 10px"></div>
            <div class="ak-field"><label for="f_status">Status</label><select id="f_status" name="filter[status]" style="height:38px; border:1px solid var(--ak-border); border-radius:8px; padding:0 10px"><option value="">Any</option>@foreach($statuses as $key => $label)<option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
            @if($vendorFilter)<input type="hidden" name="filter[vendor_id]" value="{{ $vendorFilter->id }}"><span class="ak-chip">{{ $vendorFilter->name }}</span>@endif
            <button class="ak-btn ak-btn-primary" style="height:38px">Apply</button>
            @if($filters)<a href="{{ route('expense-vendor-payments.index') }}" class="ak-btn ak-btn-outline" style="height:38px">Clear</a>@endif
        </form>
        <section class="ak-card" style="overflow-x:auto">
            <table class="ak-dt" style="min-width:860px">
                <thead><tr><th>Payment</th><th>Date</th><th>Shop</th><th>Company</th><th>Method</th><th class="ak-num">Amount (Rs)</th><th>Status</th><th>Maker</th></tr></thead>
                <tbody>
                    @forelse($payments as $payment)
                        <tr>
                            <td><a class="ak-primary-link" href="{{ route('expense-vendor-payments.show', $payment) }}">{{ $payment->payment_number }}</a></td>
                            <td>{{ $payment->payment_date->format('d M Y') }}</td>
                            <td>{{ $payment->vendor?->name }}</td>
                            <td>{{ $payment->supplier?->short_name ?? $payment->supplier?->supplier_name ?? $payment->company?->company_name ?? 'Moon Traders' }}</td>
                            <td>{{ ucfirst($payment->method) }}</td>
                            <td class="ak-num ak-strong">{{ number_format((float) $payment->amount, 2) }}</td>
                            <td><span class="ak-status {{ $tone[$payment->status] ?? '' }}"><i aria-hidden="true"></i>{{ $payment->statusLabel() }}</span></td>
                            <td>{{ $payment->creator?->name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="ak-c ak-muted" style="padding:32px">No payments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div style="padding:12px 16px">{{ $payments->links() }}</div>
        </section>
    </div>
</x-app-layout>
