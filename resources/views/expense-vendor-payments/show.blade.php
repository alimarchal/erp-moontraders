@php
    $isMaker = (int) $payment->created_by === auth()->id();
    $tone = ['pending' => 'ak-status-amber', 'approved' => 'ak-status-green', 'rejected' => 'ak-status-red'];
    $lines = $payment->journalEntry?->details ?? collect();
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb"><a href="{{ route('expense-vendor-payments.index') }}">Vendor payments</a><span aria-hidden="true">›</span><span>{{ $payment->payment_number }}</span></nav>
                <h1 class="ak-title" style="margin:0">Payment {{ $payment->payment_number }}</h1>
                <p class="ak-sub">{{ $payment->payment_date->format('l, d M Y') }} · {{ $payment->vendor?->name }} · <span class="ak-status {{ $tone[$payment->status] ?? '' }}"><i aria-hidden="true"></i>{{ $payment->statusLabel() }}</span></p>
            </div>
            <div class="ak-head-actions no-print">
                <a href="{{ route('expense-vendor-payments.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
                <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
            </div>
        </div>
    </x-slot>
    @include('settings.partials.ui-style')
    <style>
        .pv-grid { display:grid; gap:16px; grid-template-columns:1fr; } @media (min-width:900px){ .pv-grid { grid-template-columns: 3fr 2fr; } }
        .pv-pad { padding:18px 20px; } .pv-dl { display:grid; grid-template-columns:1fr 1fr; gap:14px 24px; margin:0; } .pv-dl dt { font-size:12px; text-transform:uppercase; color:var(--ak-muted); } .pv-dl dd { margin:2px 0 0; font-weight:600; }
        .pv-t { width:100%; border-collapse:collapse; font-size:13.5px; } .pv-t th { text-align:left; font-size:11px; text-transform:uppercase; color:var(--ak-muted); padding:6px 8px; border-bottom:1px solid var(--ak-border); } .pv-t td { padding:6px 8px; border-bottom:1px solid var(--ak-line); } .pv-t .n { text-align:right; font-variant-numeric:tabular-nums; }
    </style>
    <div class="ak-page" x-data="{ dialog: null }" @keydown.escape.window="dialog = null">
        <x-status-message />
        <div class="pv-grid">
            <section class="ak-card pv-pad">
                <div style="font-size:12px; text-transform:uppercase; color:var(--ak-muted)">Amount paid</div>
                <div style="font-size:28px; font-weight:800">Rs {{ number_format((float) $payment->amount, 2) }}</div>
                <dl class="pv-dl" style="margin-top:16px">
                    <div><dt>Shop</dt><dd><a class="ak-primary-link" href="{{ route('expense-vendors.show', $payment->vendor) }}">{{ $payment->vendor?->name }}</a></dd></div>
                    <div><dt>Company</dt><dd>{{ $payment->supplier?->supplier_name ?? $payment->company?->company_name ?? 'Moon Traders' }}</dd></div>
                    <div><dt>Paid by</dt><dd>{{ ucfirst($payment->method) }}@if($payment->bankAccount) · {{ $payment->bankAccount->bank_name }} {{ $payment->bankAccount->account_number }}@endif</dd></div>
                    <div><dt>Reference</dt><dd>{{ $payment->reference ?: '—' }}</dd></div>
                    <div style="grid-column:1/-1"><dt>Narration</dt><dd>{{ $payment->narration ?: '—' }}</dd></div>
                    <div><dt>Made by</dt><dd>{{ $payment->creator?->name }}<br><span class="ak-muted" style="font-weight:400">{{ $payment->created_at?->format('d M Y h:i A') }}</span></dd></div>
                    <div><dt>Checked by</dt><dd>@if($payment->checked_at){{ $payment->checker?->name }}<br><span class="ak-muted" style="font-weight:400">{{ $payment->checked_at->format('d M Y h:i A') }}</span>@else — @endif</dd></div>
                </dl>
                @if($payment->status === 'rejected' && $payment->rejection_reason)<div class="ak-alert ak-alert-error" style="margin-top:14px"><b>Rejected:</b> {{ $payment->rejection_reason }}</div>@endif
            </section>
            <section class="ak-card pv-pad">
                <h2 style="margin:0 0 4px; font-size:15px; font-weight:700">{{ $payment->status === 'approved' ? 'Posted to the ledger' : 'What approval will post' }}</h2>
                <p class="ak-muted" style="margin:0 0 10px; font-size:13px">Clears the shop's payable (2113) and reduces {{ $payment->method === 'bank' ? 'the bank account' : 'cash' }}.</p>
                <table class="pv-t"><thead><tr><th>Account</th><th class="n">Debit</th><th class="n">Credit</th></tr></thead><tbody>
                    @forelse($lines as $line)
                        <tr><td>{{ $line->account?->account_code }} {{ $line->account?->account_name }}</td><td class="n">{{ (float) $line->debit > 0 ? number_format($line->debit, 2) : '' }}</td><td class="n">{{ (float) $line->credit > 0 ? number_format($line->credit, 2) : '' }}</td></tr>
                    @empty
                        <tr><td>2113 Expense Payables</td><td class="n">{{ number_format((float) $payment->amount, 2) }}</td><td></td></tr>
                        <tr><td>{{ $payment->method === 'bank' ? 'Bank account' : '1121 Cash' }}</td><td></td><td class="n">{{ number_format((float) $payment->amount, 2) }}</td></tr>
                    @endforelse
                </tbody></table>
            </section>
        </div>
        <section class="ak-card pv-pad" style="overflow-x:auto">
            <h2 style="margin:0 0 8px; font-size:15px; font-weight:700">Vouchers this payment settles</h2>
            <table class="pv-t"><thead><tr><th>Voucher</th><th>Date</th><th>Bill</th><th class="n">Voucher amount</th><th class="n">Paid by this payment</th></tr></thead><tbody>
                @foreach($payment->allocations as $allocation)
                    <tr><td><a class="ak-primary-link" href="{{ route('expense-vouchers.show', $allocation->voucher) }}">{{ $allocation->voucher?->voucher_number }}</a></td><td>{{ $allocation->voucher?->voucher_date->format('d M Y') }}</td><td>{{ $allocation->voucher?->vendor_bill_no ?: '—' }}</td><td class="n">{{ number_format((float) $allocation->voucher?->amount, 2) }}</td><td class="n">{{ number_format((float) $allocation->amount, 2) }}</td></tr>
                @endforeach
            </tbody></table>
        </section>
        @if($payment->status === 'pending')
            @can('expense-vendor-payment-approve')
                <section class="ak-card pv-pad no-print" style="display:flex; flex-wrap:wrap; gap:10px; align-items:center">
                    @if($isMaker)
                        <p class="ak-muted" style="margin:0">You made this payment, so another user has to check it.</p>
                    @else
                        <button type="button" class="ak-btn ak-btn-success" @click="dialog = 'approve'">Approve and post</button>
                        <button type="button" class="ak-btn ak-btn-danger-outline" @click="dialog = 'reject'">Reject</button>
                    @endif
                </section>
            @endcan
        @endif
        @foreach (['approve' => ['Approve and post this payment?', 'Rs '.number_format((float) $payment->amount, 2).' is paid to '.($payment->vendor?->name).': a journal entry is posted and the vouchers become paid. A posted entry cannot be changed.', route('expense-vendor-payments.approve', $payment), 'Approve and post', 'ak-btn-success'], 'reject' => ['Reject this payment?', 'Nothing is posted and the vouchers stay unpaid.', route('expense-vendor-payments.reject', $payment), 'Reject payment', 'ak-btn-danger-outline']] as $key => [$title, $text, $url, $button, $class])
            <div class="uf-modal" x-show="dialog === '{{ $key }}'" x-cloak style="display:none" role="dialog" aria-modal="true">
                <div class="uf-modal-bg" x-show="dialog === '{{ $key }}'" x-transition.opacity @click="dialog = null"></div>
                <div class="uf-modal-box" x-show="dialog === '{{ $key }}'" x-transition>
                    <form method="POST" action="{{ $url }}">@csrf
                        <div class="uf-modal-body" style="display:block"><h3>{{ $title }}</h3><p style="margin:8px 0 0; font-size:14px; color:#334155">{{ $text }}</p>
                            @if($key === 'reject')<label for="pv-reason" style="display:block; margin-top:14px; font-size:13px; font-weight:600">Reason</label><textarea id="pv-reason" name="rejection_reason" required maxlength="500" rows="3" style="margin-top:6px; width:100%; border:1px solid var(--ak-border); border-radius:8px; padding:8px 12px"></textarea>@endif
                        </div>
                        <div class="uf-modal-foot"><button type="button" class="ak-btn ak-btn-outline" @click="dialog = null">Cancel</button><button class="ak-btn {{ $class }}">{{ $button }}</button></div>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
</x-app-layout>
