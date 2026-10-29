@php
    $isMaker = (int) $voucher->created_by === auth()->id() || (int) $voucher->submitted_by === auth()->id();
    $statusTone = ['draft' => 'ak-status-slate', 'pending' => 'ak-status-amber', 'approved' => 'ak-status-green', 'rejected' => 'ak-status-red'];
    $step = ['draft' => 1, 'pending' => 2, 'rejected' => 3, 'approved' => 3][$voucher->status] ?? 1;
    $lines = $voucher->journalEntry?->details ?? collect();
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('expense-vouchers.index') }}">Expense Vouchers</a><span aria-hidden="true">›</span><span>{{ $voucher->voucher_number }}</span>
                </nav>
                <h1 class="ak-title" style="margin:0">Voucher {{ $voucher->voucher_number }}</h1>
                <p class="ak-sub">{{ $voucher->voucher_date->format('l, d M Y') }} &middot; {{ $voucher->party_name }} &middot; <span class="ak-status {{ $statusTone[$voucher->status] ?? '' }}"><i aria-hidden="true"></i>{{ $voucher->statusLabel() }}</span></p>
            </div>
            <div class="ak-head-actions no-print">
                <a href="{{ route('expense-vouchers.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to vouchers</a>
                <a href="{{ route('expense-vouchers.help') }}" class="ak-btn ak-btn-outline">How it works <span class="ev-ur" lang="ur" dir="rtl" style="color:inherit; font-size:16px">طریقہ کار</span></a>
                @if($voucher->isApproved())
                    @can('expense-voucher-print')
                        <a href="{{ route('expense-vouchers.print', $voucher) }}" target="_blank" class="ak-btn ak-btn-outline" title="Print the 80mm thermal slip">Print slip</a>
                    @endcan
                @endif
                @if($voucher->isEditable())
                    @can('expense-voucher-edit')
                        <a href="{{ route('expense-vouchers.edit', $voucher) }}" class="ak-btn ak-btn-outline">Edit</a>
                    @endcan
                @endif
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    <link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;600&display=swap" rel="stylesheet">
    <style>
        .ev-ur { font-family: 'Noto Naskh Arabic', 'Jameel Noori Nastaleeq', 'Noto Nastaliq Urdu', 'Geeza Pro', serif; font-size: 14px; font-weight: 600; color: var(--ak-navy); text-transform: none; letter-spacing: 0; margin-inline-start: 8px; }
        .ev-steps { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 0; padding: 0; list-style: none; }
        .ev-steps li { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: var(--ak-muted); }
        .ev-steps li + li::before { content: ''; width: 28px; height: 2px; background: var(--ak-border); }
        .ev-steps .dot { width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; border: 2px solid var(--ak-border); background: #fff; font-size: 12px; }
        .ev-steps li.is-done { color: #15803d; } .ev-steps li.is-done .dot { background: #16a34a; border-color: #16a34a; color: #fff; }
        .ev-steps li.is-now { color: var(--ak-navy); } .ev-steps li.is-now .dot { border-color: var(--ak-navy); color: var(--ak-navy); }
        .ev-steps li.is-bad { color: #b91c1c; } .ev-steps li.is-bad .dot { background: #dc2626; border-color: #dc2626; color: #fff; }
        .ev-grid { display: grid; gap: 16px; grid-template-columns: 1fr; }
        @media (min-width: 900px) { .ev-grid { grid-template-columns: 3fr 2fr; } }
        .ev-dl { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 24px; margin: 0; }
        .ev-dl dt { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: var(--ak-muted); }
        .ev-dl dd { margin: 2px 0 0; font-weight: 600; }
        .ev-amount { font-size: 28px; font-weight: 800; font-variant-numeric: tabular-nums; }
        .ev-pad { padding: 18px 20px; }
        .ev-lines { width: 100%; border-collapse: collapse; font-size: 13.5px; }
        .ev-lines th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: var(--ak-muted); padding: 6px 8px; border-bottom: 1px solid var(--ak-border); }
        .ev-lines td { padding: 6px 8px; border-bottom: 1px solid var(--ak-line); }
        .ev-lines .n { text-align: right; font-variant-numeric: tabular-nums; }
        .ak-status-slate { background: #f1f5f9; color: #475569; } .ak-status-slate i { background: #64748b; }
    </style>

    <div class="ak-page">
        <x-status-message />

        <section class="ak-card ev-pad" aria-label="Voucher progress">
            <ol class="ev-steps">
                <li class="{{ $step > 1 ? 'is-done' : 'is-now' }}"><span class="dot">{{ $step > 1 ? '✓' : '1' }}</span> Made by {{ $voucher->creator?->name }}</li>
                <li class="{{ $step > 2 ? 'is-done' : ($step === 2 ? 'is-now' : '') }}"><span class="dot">{{ $step > 2 ? '✓' : '2' }}</span> Sent to checker</li>
                <li class="{{ $voucher->status === 'approved' ? 'is-done' : ($voucher->status === 'rejected' ? 'is-bad' : '') }}"><span class="dot">{{ $voucher->status === 'approved' ? '✓' : ($voucher->status === 'rejected' ? '✕' : '3') }}</span>
                    {{ $voucher->status === 'approved' ? 'Approved by '.$voucher->checker?->name : ($voucher->status === 'rejected' ? 'Rejected by '.$voucher->checker?->name : 'Checker approves') }}</li>
                <li class="{{ $voucher->journal_entry_id ? 'is-done' : '' }}"><span class="dot">{{ $voucher->journal_entry_id ? '✓' : '4' }}</span> Posted to the ledger</li>
            </ol>
        </section>

        <div class="ev-grid">
            <section class="ak-card ev-pad" aria-label="Voucher details">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:16px">
                    <div><div class="ak-muted" style="font-size:12px; text-transform:uppercase">Amount</div><div class="ev-amount">Rs {{ number_format($voucher->amount, 2) }}</div></div>
                </div>
                <dl class="ev-dl">
                    <div><dt>Date <span class="ev-ur" lang="ur" dir="rtl">تاریخ</span></dt><dd>{{ $voucher->voucher_date->format('d M Y') }}</dd></div>
                    <div><dt>Company <span class="ev-ur" lang="ur" dir="rtl">کمپنی</span></dt><dd>{{ $voucher->supplier?->supplier_name ?? (($voucher->company?->company_name ?? 'Moon Traders').' (general)') }}</dd></div>
                    <div><dt>Party name <span class="ev-ur" lang="ur" dir="rtl">پارٹی کا نام</span></dt><dd>{{ $voucher->party_name }}</dd></div>
                    <div><dt>Expense account <span class="ev-ur" lang="ur" dir="rtl">اخراجات کا کھاتہ</span></dt><dd>{{ $voucher->expenseAccount?->account_code }} - {{ $voucher->expenseAccount?->account_name }}</dd></div>
                    <div style="grid-column: 1 / -1"><dt>Narration <span class="ev-ur" lang="ur" dir="rtl">تفصیل</span></dt><dd>{{ $voucher->narration ?: '—' }}</dd></div>
                    <div><dt>Made <span class="ev-ur" lang="ur" dir="rtl">تیار کنندہ</span></dt><dd>{{ $voucher->creator?->name }}<br><span class="ak-muted" style="font-weight:400">{{ $voucher->created_at?->format('d M Y h:i A') }}</span></dd></div>
                    <div><dt>Checked <span class="ev-ur" lang="ur" dir="rtl">جانچ کنندہ</span></dt><dd>@if($voucher->checked_at){{ $voucher->checker?->name }}<br><span class="ak-muted" style="font-weight:400">{{ $voucher->checked_at->format('d M Y h:i A') }}</span>@else — @endif</dd></div>
                </dl>
                @if($voucher->status === 'rejected' && $voucher->rejection_reason)
                    <div class="ak-alert ak-alert-error" style="margin-top:16px"><b>Rejected:</b> {{ $voucher->rejection_reason }}</div>
                @endif
            </section>

            <section class="ak-card ev-pad" aria-label="Ledger effect">
                <h2 style="margin:0 0 4px; font-size:15px; font-weight:700">{{ $voucher->isApproved() ? 'Posted to the ledger' : 'What approval will post' }}</h2>
                <p class="ak-muted" style="margin:0 0 10px; font-size:13px">{{ $voucher->isApproved() ? 'Journal entry #'.$voucher->journalEntry?->id.' ('.$voucher->journalEntry?->status.').' : 'Nothing is posted until a checker approves.' }}</p>
                <table class="ev-lines">
                    <thead><tr><th>Account</th><th class="n">Debit</th><th class="n">Credit</th></tr></thead>
                    <tbody>
                        @if($lines->isNotEmpty())
                            @foreach($lines as $line)
                                <tr><td>{{ $line->account?->account_code }} {{ $line->account?->account_name }}</td><td class="n">{{ (float) $line->debit > 0 ? number_format($line->debit, 2) : '' }}</td><td class="n">{{ (float) $line->credit > 0 ? number_format($line->credit, 2) : '' }}</td></tr>
                            @endforeach
                        @else
                            <tr><td>{{ $voucher->expenseAccount?->account_code }} {{ $voucher->expenseAccount?->account_name }}</td><td class="n">{{ number_format($voucher->amount, 2) }}</td><td class="n"></td></tr>
                            <tr><td>1121 Cash</td><td class="n"></td><td class="n">{{ number_format($voucher->amount, 2) }}</td></tr>
                        @endif
                    </tbody>
                </table>
            </section>
        </div>

        @if($voucher->attachment)
            @php $isPdf = strtolower(pathinfo($voucher->attachment, PATHINFO_EXTENSION)) === 'pdf'; @endphp
            <section class="ak-card ev-pad" aria-label="Attachment">
                <h2 style="margin:0 0 10px; font-size:15px; font-weight:700">Attachment <span class="ev-ur" lang="ur" dir="rtl">منسلک دستاویز</span></h2>
                @if($isPdf)
                    <a href="{{ $voucher->attachment_url }}" target="_blank" rel="noopener" class="ak-btn ak-btn-outline">Open the PDF</a>
                @else
                    <a href="{{ $voucher->attachment_url }}" target="_blank" rel="noopener"><img src="{{ $voucher->attachment_url }}" alt="Voucher attachment" style="max-width:100%; max-height:420px; border:1px solid var(--ak-border); border-radius:10px"></a>
                @endif
            </section>
        @endif

        @if($voucher->isEditable() || $voucher->status === 'pending')
            <section class="ak-card ev-pad no-print" aria-label="Actions" style="display:flex; flex-wrap:wrap; gap:10px; align-items:flex-start">
                @if($voucher->isEditable())
                    @can('expense-voucher-create')
                        <form method="POST" action="{{ route('expense-vouchers.submit', $voucher) }}">@csrf
                            <button class="ak-btn ak-btn-primary">Send to checker</button>
                        </form>
                    @endcan
                    @can('expense-voucher-delete')
                        <form method="POST" action="{{ route('expense-vouchers.destroy', $voucher) }}" onsubmit="return confirm('Delete this voucher?');">@csrf @method('DELETE')
                            <button class="ak-btn ak-btn-danger-outline">Delete</button>
                        </form>
                    @endcan
                @endif
                @if($voucher->status === 'pending')
                    @can('expense-voucher-approve')
                        @if($isMaker)
                            <p class="ak-muted" style="margin:0; align-self:center">You made this voucher, so another user has to check it.</p>
                        @else
                            <form method="POST" action="{{ route('expense-vouchers.approve', $voucher) }}" onsubmit="return confirm('Approve and post this voucher to the ledger?');">@csrf
                                <button class="ak-btn ak-btn-success">Approve and post</button>
                            </form>
                            <form method="POST" action="{{ route('expense-vouchers.reject', $voucher) }}" style="display:flex; gap:8px">@csrf
                                <input type="text" name="rejection_reason" required maxlength="500" placeholder="Reason for rejecting" style="height:38px; min-width:260px; border:1px solid var(--ak-border); border-radius:8px; padding:0 12px">
                                <button class="ak-btn ak-btn-danger-outline">Reject</button>
                            </form>
                        @endif
                    @endcan
                @endif
            </section>
        @endif
    </div>
</x-app-layout>
