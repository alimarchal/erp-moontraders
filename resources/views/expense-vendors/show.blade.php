<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb"><a href="{{ route('expense-vendors.index') }}">Shops</a><span aria-hidden="true">›</span><span>{{ $vendor->name }}</span></nav>
                <h1 class="ak-title" style="margin:0">{{ $vendor->name }}</h1>
                <p class="ak-sub">{{ $vendor->categoryLabel() }}{{ $vendor->city ? ' · '.$vendor->city : '' }}{{ $vendor->phone ? ' · '.$vendor->phone : '' }}</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('expense-vendors.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to shops</a>
                @if($outstanding > 0)@can('expense-vendor-payment-create')<a href="{{ route('expense-vendor-payments.create', ['vendor' => $vendor->id]) }}" class="ak-btn ak-btn-primary">Pay this shop</a>@endcan @endif
                @can('expense-vendor-edit')<a href="{{ route('expense-vendors.edit', $vendor) }}" class="ak-btn ak-btn-outline">Edit</a>@endcan
            </div>
        </div>
    </x-slot>
    @include('settings.partials.ui-style')
    <style>
        .vs-kpis { display:grid; gap:14px; grid-template-columns:repeat(auto-fit, minmax(190px,1fr)); }
        .vs-kpi { padding:16px 18px; } .vs-kpi small { display:block; font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:var(--ak-muted); } .vs-kpi b { display:block; margin-top:4px; font-size:24px; font-variant-numeric:tabular-nums; }
        .vs-grid { display:grid; gap:16px; grid-template-columns:1fr; } @media (min-width:900px){ .vs-grid { grid-template-columns: 1fr 2fr; } }
        .vs-dl { display:grid; grid-template-columns:1fr; gap:10px; margin:0; padding:18px 20px; } .vs-dl dt { font-size:12px; text-transform:uppercase; color:var(--ak-muted); } .vs-dl dd { margin:2px 0 0; font-weight:600; }
    </style>
    <div class="ak-page">
        <x-status-message />
        <div class="vs-kpis">
            <div class="ak-card vs-kpi"><small>We owe this shop</small><b style="{{ $outstanding > 0 ? 'color:#b91c1c' : '' }}">Rs {{ number_format($outstanding, 2) }}</b></div>
            <div class="ak-card vs-kpi"><small>Spent here (approved)</small><b>Rs {{ number_format($spentTotal, 2) }}</b></div>
            <div class="ak-card vs-kpi"><small>Vouchers</small><b>{{ number_format($vouchers->count()) }}{{ $vouchers->count() === 100 ? '+' : '' }}</b></div>
        </div>
        <div class="vs-grid">
            <section class="ak-card">
                <dl class="vs-dl">
                    <div><dt>Contact person</dt><dd>{{ $vendor->contact_person ?: '—' }}</dd></div>
                    <div><dt>Address</dt><dd>{{ $vendor->address ?: '—' }}</dd></div>
                    <div><dt>NTN / CNIC</dt><dd>{{ $vendor->tax_number ?: '—' }}</dd></div>
                    <div><dt>Usual expense account</dt><dd>{{ $vendor->defaultExpenseAccount ? $vendor->defaultExpenseAccount->account_code.' - '.$vendor->defaultExpenseAccount->account_name : '—' }}</dd></div>
                    <div><dt>Notes</dt><dd>{{ $vendor->notes ?: '—' }}</dd></div>
                </dl>
            </section>
            <section class="ak-card" style="overflow-x:auto">
                <table class="ak-dt" style="min-width:640px">
                    <thead><tr><th>Voucher</th><th>Date</th><th>Company</th><th>Payment</th><th class="ak-num">Amount</th><th class="ak-num">Owes</th></tr></thead>
                    <tbody>
                        @forelse($vouchers as $voucher)
                            <tr>
                                <td><a class="ak-primary-link" href="{{ route('expense-vouchers.show', $voucher) }}">{{ $voucher->voucher_number }}</a></td>
                                <td>{{ $voucher->voucher_date->format('d M Y') }}</td>
                                <td>{{ $voucher->supplier?->short_name ?? $voucher->supplier?->supplier_name ?? $voucher->company?->company_name ?? 'Moon Traders' }}</td>
                                <td>{{ \App\Models\ExpenseVoucher::paymentTypeLabels()[$voucher->payment_type] ?? $voucher->payment_type }}@if($voucher->status !== 'approved') <span class="ak-muted">({{ $voucher->statusLabel() }})</span>@endif</td>
                                <td class="ak-num">{{ number_format((float) $voucher->amount, 2) }}</td>
                                <td class="ak-num">{{ $voucher->isApproved() && $voucher->isCredit() ? number_format($voucher->outstanding(), 2) : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="ak-c ak-muted" style="padding:24px">No vouchers for this shop yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </div>
        @if($payments->isNotEmpty())
            <section class="ak-card" style="overflow-x:auto">
                <h2 style="margin:0; padding:14px 18px 0; font-size:15px; font-weight:700">Payments made to this shop</h2>
                <table class="ak-dt"><thead><tr><th>Payment</th><th>Date</th><th>Method</th><th class="ak-num">Amount</th></tr></thead><tbody>
                    @foreach($payments as $payment)
                        <tr><td><a class="ak-primary-link" href="{{ route('expense-vendor-payments.show', $payment) }}">{{ $payment->payment_number }}</a></td><td>{{ $payment->payment_date->format('d M Y') }}</td><td>{{ ucfirst($payment->method) }}</td><td class="ak-num">{{ number_format((float) $payment->amount, 2) }}</td></tr>
                    @endforeach
                </tbody></table>
            </section>
        @endif
    </div>
</x-app-layout>
