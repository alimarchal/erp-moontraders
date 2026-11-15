@php
    $isMaker = (int) $voucher->created_by === auth()->id();
    $statusTone = ['draft' => 'ak-status-slate', 'pending' => 'ak-status-amber', 'approved' => 'ak-status-green', 'rejected' => 'ak-status-red', 'reversed' => 'ak-status-red'];
    $step = ['draft' => 1, 'pending' => 2, 'rejected' => 3, 'approved' => 3, 'reversed' => 3][$voucher->status] ?? 1;
    $canReverse = $voucher->isApproved() && ! $voucher->reversed_at && auth()->user()->can('expense-voucher-reverse');
    $notes = $voucher->cashPayout ? \App\Support\CashNotes::fromColumns($voucher->cashPayout) : null;
    $needsNotes = $voucher->status === 'pending' && $voucher->payment_method === \App\Models\ExpenseVoucher::METHOD_CASH;
    $notesOld = (array) old('denom', []);
    $notesStart = ['coins' => $notesOld['coins'] ?? ''];
    foreach (\App\Support\CashNotes::NOTES as $note) {
        $notesStart[$note] = $notesOld[$note] ?? '';
    }
    $notesFailed = $errors->has('denom') || collect($errors->keys())->contains(fn ($key) => str_starts_with($key, 'denom.'));
    $creditAccountLabel = $voucher->isCredit() ? '2113 Expense Payables' : ($voucher->isPaidByBank() ? ($voucher->bankAccount?->bank_name.' · '.$voucher->bankAccount?->account_name) : '1121 Cash');
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
                        <a href="{{ route('expense-vouchers.print', $voucher) }}" target="_blank" class="ak-btn ak-btn-outline" title="Print the A4 payment voucher">Print voucher</a>
                        <a href="{{ route('expense-vouchers.print', ['expense_voucher' => $voucher, 'format' => 'slip']) }}" target="_blank" class="ak-btn ak-btn-outline" title="Print the 80mm thermal slip">Print slip</a>
                    @endcan
                @endif
                @if($canReverse)
                    <button type="button" class="ak-btn ak-btn-danger-outline" @click="$dispatch('open-reverse-voucher')">Reverse <span class="ev-ur" lang="ur" dir="rtl" style="color:inherit; font-size:16px">واپس کریں</span></button>
                @endif
                @if($voucher->isEditable() && $isMaker)
                    @can('expense-voucher-edit')
                        <a href="{{ route('expense-vouchers.edit', $voucher) }}" class="ak-btn ak-btn-outline">Edit</a>
                    @endcan
                @endif
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    <x-apple-skin />
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

    <div class="ak-page" x-data="{ dialog: @js($notesFailed ? 'approve' : null) }" @keydown.escape.window="dialog = null">
        <x-status-message />

        @if($voucher->isReversed())
            <div class="ak-alert ak-alert-error" role="status">
                <b>Reversed</b> on {{ $voucher->reversed_at?->format('d M Y, h:i A') }}{{ $voucher->reverser ? ' by '.$voucher->reverser->name : '' }}.
                The ledger entry was offset{{ $voucher->reversal_journal_entry_id ? ' by journal entry #'.$voucher->reversal_journal_entry_id : '' }}{{ $voucher->payment_method === 'cash' ? ' and the cash came back on the day of the reversal' : '' }}.
                <br>Reason: {{ $voucher->reversal_reason }}
            </div>
        @endif

        <section class="ak-card ev-pad" aria-label="Voucher progress">
            <ol class="ev-steps">
                <li class="{{ $step > 1 ? 'is-done' : 'is-now' }}"><span class="dot">{{ $step > 1 ? '✓' : '1' }}</span> Made by {{ $voucher->creator?->name }}</li>
                <li class="{{ $step > 2 ? 'is-done' : ($step === 2 ? 'is-now' : '') }}"><span class="dot">{{ $step > 2 ? '✓' : '2' }}</span> Sent to checker</li>
                <li class="{{ in_array($voucher->status, ['approved', 'reversed'], true) ? 'is-done' : ($voucher->status === 'rejected' ? 'is-bad' : '') }}"><span class="dot">{{ in_array($voucher->status, ['approved', 'reversed'], true) ? '✓' : ($voucher->status === 'rejected' ? '✕' : '3') }}</span>
                    {{ in_array($voucher->status, ['approved', 'reversed'], true) ? 'Approved by '.$voucher->checker?->name : ($voucher->status === 'rejected' ? 'Rejected by '.$voucher->checker?->name : 'Checker approves') }}</li>
                <li class="{{ $voucher->journal_entry_id ? 'is-done' : '' }}"><span class="dot">{{ $voucher->journal_entry_id ? '✓' : '4' }}</span> Posted to the ledger</li>
                @if($voucher->isReversed())<li class="is-bad"><span class="dot">↺</span> Reversed{{ $voucher->reverser ? ' by '.$voucher->reverser->name : '' }}</li>@endif
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
                    <div><dt>Party name <span class="ev-ur" lang="ur" dir="rtl">پارٹی کا نام</span></dt><dd>
                        @if($voucher->expenseVendor)<a class="ak-primary-link" href="{{ route('expense-vendors.show', $voucher->expenseVendor) }}">{{ $voucher->party_name }}</a>@else{{ $voucher->party_name }}@endif
                        @if($voucher->employee)<br><span class="ak-muted" style="font-weight:400">Employee {{ $voucher->employee->employee_code }}</span>@endif
                    </dd></div>
                    <div><dt>Payment <span class="ev-ur" lang="ur" dir="rtl">ادائیگی</span></dt><dd>
                        {{ \App\Models\ExpenseVoucher::paymentMethodLabels()[$voucher->payment_method] ?? $voucher->payment_method }}{{ $voucher->isAdvance() ? ' · Advance salary' : '' }}
                        @if($voucher->isPaidByBank())
                            <br><span class="ak-muted" style="font-weight:400">{{ $voucher->bankAccount?->bank_name }} · {{ $voucher->bankAccount?->account_name }}{{ $voucher->bankAccount?->account_number ? ' · '.$voucher->bankAccount->account_number : '' }}</span>
                            @if($voucher->cheque_number)<br><span class="ak-muted" style="font-weight:400">Cheque {{ $voucher->cheque_number }}{{ $voucher->cheque_date ? ', dated '.$voucher->cheque_date->format('d M Y') : '' }}</span>@endif
                            @if($voucher->transaction_reference)<br><span class="ak-muted" style="font-weight:400">Ref. {{ $voucher->transaction_reference }}</span>@endif
                        @endif
                        @if($voucher->isApproved() && $voucher->isCredit())
                            <br><span class="ak-status {{ ['paid' => 'ak-status-green', 'partial' => 'ak-status-amber', 'unpaid' => 'ak-status-red'][$voucher->payment_status] ?? '' }}"><i aria-hidden="true"></i>{{ ucfirst($voucher->payment_status) }}</span>
                            <span class="ak-muted" style="font-weight:400">paid Rs {{ number_format($voucher->paid_amount, 2) }}, owes Rs {{ number_format($voucher->outstanding(), 2) }}</span>
                        @endif
                    </dd></div>
                    @if($voucher->vendor_bill_no)<div><dt>Shop bill no.</dt><dd>{{ $voucher->vendor_bill_no }}</dd></div>@endif
                    @if($voucher->vehicle)<div><dt>Vehicle <span class="ev-ur" lang="ur" dir="rtl">گاڑی</span></dt><dd>{{ $voucher->vehicle->vehicle_number }}</dd></div>@endif
                    @if($voucher->isAdvance() && $voucher->advance_installment)<div><dt>Monthly instalment</dt><dd>Rs {{ number_format($voucher->advance_installment, 2) }}</dd></div>@endif
                    @if($voucher->category)<div><dt>Category <span class="ev-ur" lang="ur" dir="rtl">قسم</span></dt><dd>{{ $voucher->category->name }}</dd></div>@endif
                    <div><dt>Expense account <span class="ev-ur" lang="ur" dir="rtl">اخراجات کا کھاتہ</span></dt><dd>{{ $voucher->expenseAccount?->account_code }} - {{ $voucher->expenseAccount?->account_name }}</dd></div>
                    <div style="grid-column: 1 / -1"><dt>Narration <span class="ev-ur" lang="ur" dir="rtl">تفصیل</span></dt><dd>{{ $voucher->narration ?: '—' }}</dd></div>
                    <div><dt>Made <span class="ev-ur" lang="ur" dir="rtl">تیار کنندہ</span></dt><dd>{{ $voucher->creator?->name }}<br><span class="ak-muted" style="font-weight:400">{{ $voucher->created_at?->format('d M Y h:i A') }}</span></dd></div>
                    <div><dt>Checked <span class="ev-ur" lang="ur" dir="rtl">جانچ کنندہ</span></dt><dd>@if($voucher->checked_at){{ $voucher->checker?->name }}<br><span class="ak-muted" style="font-weight:400">{{ $voucher->checked_at->format('d M Y h:i A') }}</span>@else — @endif</dd></div>
                </dl>
                @if($notes && ! $notes->isEmpty())
                    <div style="margin-top:16px">
                        <div class="ak-muted" style="font-size:12px; text-transform:uppercase; letter-spacing:.04em">Notes paid out <span class="ev-ur" lang="ur" dir="rtl">ادا کیے گئے نوٹ</span></div>
                        <p class="ak-muted" style="margin:2px 0 0; font-size:13px">Counted and handed over by {{ $voucher->checker?->name ?? 'the checker' }}{{ $voucher->checked_at ? ' on '.$voucher->checked_at->format('d M Y h:i A') : '' }}.</p>
                        <table class="ev-lines" style="margin-top:6px">
                            <tbody>
                                @foreach(\App\Support\CashNotes::NOTES as $note)
                                    @if($notes->count($note) > 0)
                                        <tr><td>Rs {{ number_format($note) }} × {{ $notes->count($note) }}</td><td class="n">{{ number_format($note * $notes->count($note), 2) }}</td></tr>
                                    @endif
                                @endforeach
                                @if($notes->coins() > 0)<tr><td>Coins</td><td class="n">{{ number_format($notes->coins(), 2) }}</td></tr>@endif
                                <tr><td><b>Total</b></td><td class="n"><b>{{ number_format($notes->total(), 2) }}</b></td></tr>
                            </tbody>
                        </table>
                    </div>
                @endif
                @if(in_array($voucher->status, ['rejected', 'pending'], true) && $voucher->rejection_reason)
                    <div class="ak-alert ak-alert-error" style="margin-top:16px"><b>{{ $voucher->status === 'rejected' ? 'Rejected' : 'Last rejected' }}:</b> {{ $voucher->rejection_reason }}</div>
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
                            <tr><td>{{ $voucher->isAdvance() ? '1131 Employee Advances' : $voucher->expenseAccount?->account_code.' '.$voucher->expenseAccount?->account_name }}</td><td class="n">{{ number_format($voucher->amount, 2) }}</td><td class="n"></td></tr>
                            <tr><td>{{ $creditAccountLabel }}</td><td class="n"></td><td class="n">{{ number_format($voucher->amount, 2) }}</td></tr>
                        @endif
                    </tbody>
                </table>
            </section>
        </div>

        @if($voucher->attachments->isNotEmpty())
            <section class="ak-card ev-pad" aria-label="Attachments">
                <h2 style="margin:0 0 10px; font-size:15px; font-weight:700">Attachments ({{ $voucher->attachments->count() }}) <span class="ev-ur" lang="ur" dir="rtl">منسلک دستاویزات</span></h2>
                <div style="display:flex; flex-wrap:wrap; gap:10px">
                    @foreach($voucher->attachments as $file)
                        @php $attExt = strtolower(pathinfo($file->file_name, PATHINFO_EXTENSION) ?: pathinfo($file->file_path, PATHINFO_EXTENSION)); @endphp
                        <button type="button" class="ak-btn ak-btn-outline" onclick="evPreview(@js($voucher->attachmentUrl($file)), @js($attExt), @js($voucher->voucher_number.' - '.$file->file_name))">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" /></svg>
                            {{ \Illuminate\Support\Str::limit($file->file_name, 28) }}
                        </button>
                    @endforeach
                </div>
            </section>
        @endif

        @if($voucher->isEditable() || $voucher->status === 'pending')
            <section class="ak-card ev-pad no-print" aria-label="Actions" style="display:flex; flex-wrap:wrap; gap:10px; align-items:flex-start">
                @if($voucher->isEditable())
                    @if($isMaker)
                        @if($voucher->status === 'rejected')
                            @can('expense-voucher-edit')
                                <a href="{{ route('expense-vouchers.edit', $voucher) }}" class="ak-btn ak-btn-primary">Edit and correct <span class="ev-ur" lang="ur" dir="rtl" style="color:#fff">درست کریں</span></a>
                            @endcan
                        @endif
                        @can('expense-voucher-create')
                            <form method="POST" action="{{ route('expense-vouchers.submit', $voucher) }}">@csrf
                                <button class="ak-btn ak-btn-primary">Submit for approval <span class="ev-ur" lang="ur" dir="rtl" style="color:#fff">منظوری کے لیے جمع کرائیں</span></button>
                            </form>
                        @endcan
                        @can('expense-voucher-delete')
                            <button type="button" class="ak-btn ak-btn-danger-outline" @click="dialog = 'delete'">Delete</button>
                        @endcan
                    @else
                        <p class="ak-muted" style="margin:0; align-self:center">Only {{ $voucher->creator?->name }}, who made this voucher, can change it or send it to the checker.</p>
                    @endif
                @endif
                @if($voucher->status === 'pending')
                    @can('expense-voucher-approve')
                        @if($isMaker)
                            <p class="ak-muted" style="margin:0; align-self:center">You made this voucher, so another user has to check it.</p>
                        @else
                            <button type="button" class="ak-btn ak-btn-success" @click="dialog = 'approve'">Approve and post <span class="ev-ur" lang="ur" dir="rtl" style="color:inherit">منظور کریں</span></button>
                            <button type="button" class="ak-btn ak-btn-danger-outline" @click="dialog = 'reject'">Reject <span class="ev-ur" lang="ur" dir="rtl" style="color:inherit">مسترد کریں</span></button>
                        @endif
                    @endcan
                @endif
            </section>
        @endif

        {{-- Confirmation dialogs: blurred background like the delete dialogs elsewhere --}}
        @php
            $dialogs = [
                'approve' => ['Approve and post this voucher?', 'The voucher is approved and a journal entry is posted: debit '.($voucher->isAdvance() ? 'Employee Advances' : $voucher->expenseAccount?->account_name).', credit '.($voucher->isCredit() ? 'Expense Payables (the shop is paid later)' : ($voucher->isPaidByBank() ? $voucher->bankAccount?->bank_name.' ('.$voucher->bankAccount?->account_name.')' : 'Cash')).', Rs '.number_format($voucher->amount, 2).'.'.($voucher->isAdvance() ? ' The advance is then recovered from the next salary.' : '').' A posted entry cannot be changed afterwards.', route('expense-vouchers.approve', $voucher), 'Approve and post', 'ak-btn-success', 'ak-pill-green'],
                'reject' => ['Reject this voucher?', 'It goes back to '.($voucher->creator?->name ?? 'the maker').' with your reason, who can correct it and submit it again. Nothing is posted.', route('expense-vouchers.reject', $voucher), 'Reject voucher', 'ak-btn-danger-outline', 'ak-pill-red'],
                'delete' => ['Delete this voucher?', $voucher->voucher_number.' is removed. This cannot be undone.', route('expense-vouchers.destroy', $voucher), 'Delete voucher', 'ak-btn-danger-outline', 'ak-pill-red'],
            ];
        @endphp
        @if($needsNotes)
            @php unset($dialogs['approve']); @endphp
            {{-- A cash voucher is approved only with the notes handed out, adding up to the amount. --}}
            <div class="uf-modal" x-show="dialog === 'approve'" x-cloak style="display:none" role="dialog" aria-modal="true" aria-labelledby="ev-dialog-approve-cash">
                <div class="uf-modal-bg" x-show="dialog === 'approve'" x-transition.opacity @click="dialog = null"></div>
                <div class="uf-modal-box" x-show="dialog === 'approve'" x-transition style="max-width:600px">
                    <form method="POST" action="{{ route('expense-vouchers.approve', $voucher) }}"
                          x-data="{
                              denom: @js($notesStart),
                              amount: @js(round((float) $voucher->amount, 2)),
                              sending: false,
                              get counted() {
                                  let total = Number(this.denom.coins) || 0;
                                  @js(\App\Support\CashNotes::NOTES).forEach(n => { total += n * (Math.max(0, parseInt(this.denom[n], 10)) || 0); });
                                  return Math.round(total * 100) / 100;
                              },
                              get balanced() { return Math.abs(this.counted - this.amount) < 0.01; },
                          }"
                          @submit="if (sending || ! balanced) { $event.preventDefault(); return; } sending = true">
                        @csrf
                        <div class="uf-modal-body" style="display:block">
                            <h3 id="ev-dialog-approve-cash">Count the notes and approve</h3>
                            <p style="margin:8px 0 12px; font-size:14px; color:#334155">Enter the notes you hand to {{ $voucher->party_name }}. They must add up to <b>Rs {{ number_format($voucher->amount, 2) }}</b>. Approving posts debit {{ $voucher->isAdvance() ? 'Employee Advances' : $voucher->expenseAccount?->account_name }}, credit Cash.</p>
                            <x-cash-notes model="denom" required="amount" />
                            @if($notesFailed)
                                <p style="margin:8px 0 0; font-size:13px; color:#b91c1c">{{ $errors->first('denom') ?: collect($errors->getMessages())->filter(fn ($m, $key) => str_starts_with($key, 'denom.'))->flatten()->first() }}</p>
                            @endif
                        </div>
                        <div class="uf-modal-foot">
                            <button type="button" class="ak-btn ak-btn-outline" @click="dialog = null">Cancel</button>
                            <button type="submit" class="ak-btn ak-btn-success" :disabled="sending || ! balanced" :title="balanced ? '' : 'The notes must add up to the voucher amount'">
                                <span x-show="! sending">Approve and post</span><span x-show="sending" x-cloak>Posting…</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @foreach ($dialogs as $key => [$title, $text, $url, $button, $buttonClass, $iconClass])
            <div class="uf-modal" x-show="dialog === '{{ $key }}'" x-cloak style="display:none" role="dialog" aria-modal="true" aria-labelledby="ev-dialog-{{ $key }}">
                <div class="uf-modal-bg" x-show="dialog === '{{ $key }}'" x-transition.opacity @click="dialog = null"></div>
                <div class="uf-modal-box" x-show="dialog === '{{ $key }}'" x-transition>
                    <form method="POST" action="{{ $url }}">
                        @csrf
                        @if ($key === 'delete') @method('DELETE') @endif
                        <div class="uf-modal-body">
                            <span class="uf-modal-icon {{ $iconClass }}" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.01" /></svg>
                            </span>
                            <div style="flex:1">
                                <h3 id="ev-dialog-{{ $key }}">{{ $title }}</h3>
                                <p style="margin:8px 0 0; font-size:14px; color:#334155">{{ $text }}</p>
                                @if ($key === 'reject')
                                    <label for="ev-reject-reason" style="display:block; margin-top:14px; font-size:13px; font-weight:600">Reason for rejecting <span class="ev-ur" lang="ur" dir="rtl">مسترد کرنے کی وجہ</span></label>
                                    <textarea id="ev-reject-reason" name="rejection_reason" required maxlength="500" rows="3" style="margin-top:6px; width:100%; border:1px solid var(--ak-border); border-radius:8px; padding:8px 12px; font-size:14px"></textarea>
                                @endif
                            </div>
                        </div>
                        <div class="uf-modal-foot">
                            <button type="button" class="ak-btn ak-btn-outline" @click="dialog = null">Cancel</button>
                            <button type="submit" class="ak-btn {{ $buttonClass }}">{{ $button }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach

        @if($canReverse)
            {{-- Reverse: reason and password are both required; the server re-checks every condition. --}}
            <div class="uf-modal" x-data="{ show: {{ $errors->has('reason') || $errors->has('password') ? 'true' : 'false' }}, sending: false }"
                 x-on:open-reverse-voucher.window="show = true" x-on:keydown.escape.window="show = false"
                 x-show="show" x-cloak style="display:none" role="dialog" aria-modal="true" aria-labelledby="ev-reverse-title">
                <div class="uf-modal-bg" x-show="show" x-transition.opacity @click="show = false"></div>
                <div class="uf-modal-box" x-show="show" x-transition style="max-width:520px">
                    <form method="POST" action="{{ route('expense-vouchers.reverse', $voucher) }}" @submit="if (sending) { $event.preventDefault(); return; } sending = true">
                        @csrf
                        <div class="uf-modal-body">
                            <span class="uf-modal-icon ak-pill-red" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" /></svg>
                            </span>
                            <div style="flex:1">
                                <h3 id="ev-reverse-title">Reverse {{ $voucher->voucher_number }}?</h3>
                                <p style="margin:8px 0 0; font-size:14px; color:#334155">An offsetting journal entry is posted today, so the ledger is back as if the voucher was never paid. {{ $voucher->payment_method === 'cash' ? 'The cash comes back on today\'s cash sheet; the day of the voucher is not changed.' : '' }} Nothing is deleted.</p>
                                <label for="ev-reverse-reason" style="display:block; margin-top:14px; font-size:13px; font-weight:600">Reason <span class="ev-ur" lang="ur" dir="rtl">وجہ</span></label>
                                <textarea id="ev-reverse-reason" name="reason" required minlength="5" maxlength="500" rows="3" autocomplete="off" placeholder="e.g. Entered twice by mistake" style="margin-top:6px; width:100%; border:1px solid var(--ak-border); border-radius:8px; padding:8px 12px; font-size:14px">{{ old('reason') }}</textarea>
                                @error('reason')<p style="margin:4px 0 0; font-size:13px; color:#b91c1c">{{ $message }}</p>@enderror
                                {{-- Gives the browser's password manager a username field, so it does not fill the reason with the email. --}}
                                <input type="text" name="username" value="{{ auth()->user()->email }}" autocomplete="username" hidden readonly tabindex="-1">
                                <label for="ev-reverse-password" style="display:block; margin-top:12px; font-size:13px; font-weight:600">Your password <span class="ev-ur" lang="ur" dir="rtl">آپ کا پاس ورڈ</span></label>
                                <input id="ev-reverse-password" type="password" name="password" required autocomplete="current-password" style="margin-top:6px; width:100%; height:40px; border:1px solid var(--ak-border); border-radius:8px; padding:0 12px; font-size:14px">
                                @error('password')<p style="margin:4px 0 0; font-size:13px; color:#b91c1c">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div class="uf-modal-foot">
                            <button type="button" class="ak-btn ak-btn-outline" @click="show = false">Cancel</button>
                            <button type="submit" class="ak-btn ak-btn-danger-outline" :disabled="sending"><span x-show="! sending">Reverse voucher</span><span x-show="sending" x-cloak>Reversing…</span></button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- Attachment preview in a window of its own, like the settlement attachments --}}
        <div x-data="{ open: false, url: '', ext: '', name: '' }" x-on:ev-preview.window="url = $event.detail.url; ext = $event.detail.ext; name = $event.detail.name; open = true" x-on:keydown.escape.window="open = false" x-cloak>
            <div x-show="open" class="fixed inset-0 z-[70]" style="display: none;">
                <div x-show="open" x-transition.opacity class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm" @click="open = false"></div>
                <div class="fixed inset-0 z-10 flex items-center justify-center overflow-y-auto p-2 sm:p-4" @click.self="open = false">
                    <div class="relative w-full max-w-4xl bg-white rounded-lg shadow-xl overflow-hidden flex flex-col max-h-[92vh]" @click.stop>
                        <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between gap-3 bg-gradient-to-r from-green-600 to-green-700 shrink-0">
                            <h3 class="text-sm sm:text-base font-semibold text-white truncate" x-text="name"></h3>
                            <div class="flex items-center gap-2 shrink-0">
                                <a :href="url" target="_blank" rel="noopener" class="text-xs font-semibold text-white/90 hover:text-white underline">Open in new tab</a>
                                <button type="button" @click="open = false" class="rounded-lg p-1 text-white/80 hover:bg-white/20 hover:text-white transition" aria-label="Close">&times;</button>
                            </div>
                        </div>
                        <div class="flex-grow overflow-auto bg-gray-100 flex items-center justify-center p-2">
                            <template x-if="open && ext === 'pdf'"><iframe :src="url" class="w-full h-[75vh] bg-white rounded"></iframe></template>
                            <template x-if="open && ext !== 'pdf'"><img :src="url" :alt="name" class="max-w-full max-h-[75vh] object-contain rounded"></template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function evPreview(url, ext, name) {
                window.dispatchEvent(new CustomEvent('ev-preview', { detail: { url, ext, name } }));
            }
        </script>
    @endpush
</x-app-layout>
