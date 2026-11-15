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
    $stageTone = ['draft' => 'grey', 'pending' => 'amber', 'approved' => 'green', 'rejected' => 'red', 'reversed' => 'red'][$voucher->status] ?? 'grey';
    $methodLabel = \App\Models\ExpenseVoucher::paymentMethodLabels()[$voucher->payment_method] ?? $voucher->payment_method;
    $voucherTitle = match (true) {
        $voucher->isPaidByBank() => 'Bank payment voucher',
        $voucher->isCredit() => 'Credit expense voucher',
        default => 'Cash payment voucher',
    };
    $user = auth()->user();
    $canApprove = $voucher->status === 'pending' && ! $isMaker && $user->can('expense-voucher-approve');
    $canEdit = $voucher->isEditable() && $isMaker && $user->can('expense-voucher-edit');
    $canSubmit = $voucher->isEditable() && $isMaker && $user->can('expense-voucher-create');
    $canDelete = $voucher->isEditable() && $isMaker && $user->can('expense-voucher-delete');
    $canPrint = $voucher->isApproved() && $user->can('expense-voucher-print');
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Voucher {{ $voucher->voucher_number }}" :createRoute="null" createLabel="" :showSearch="false" :showRefresh="false" backRoute="{{ route('expense-vouchers.index') }}">
            <x-slot name="actions">
                <button type="button" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest transition ease-in-out duration-150 no-print"
                    x-data @click="$dispatch('rg-help', { topic: @js($needsNotes ? 'approve' : ($voucher->isApproved() ? 'print' : 'statuses')) })" aria-haspopup="dialog" aria-label="Help in Urdu" title="How vouchers work, in Urdu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></svg>
                </button>
            </x-slot>
        </x-page-header>
    </x-slot>

    @include('settings.partials.ui-style')
    <x-apple-skin />
    <x-register-apple-style />
    <x-register-help page="expense-vouchers" />
    @include('expense-vouchers.partials.show-style')

    <div class="ak-page ev-show" x-data="{ dialog: @js($notesFailed ? 'approve' : null) }" @ev-dialog.window="dialog = $event.detail" @keydown.escape.window="dialog = null">
        <x-status-message />

        @if($voucher->isReversed())
            <div class="ev-banner ev-banner-red" role="status">
                <b>Reversed</b> on {{ $voucher->reversed_at?->format('d M Y, h:i A') }}{{ $voucher->reverser ? ' by '.$voucher->reverser->name : '' }}.
                The ledger entry was offset{{ $voucher->reversal_journal_entry_id ? ' by journal entry #'.$voucher->reversal_journal_entry_id : '' }}{{ $voucher->payment_method === 'cash' ? ' and the cash came back on the day of the reversal' : '' }}.
                <span class="ev-banner-sub">Reason: {{ $voucher->reversal_reason }}</span>
            </div>
        @endif
        @if(in_array($voucher->status, ['rejected', 'pending'], true) && $voucher->rejection_reason)
            <div class="ev-banner ev-banner-amber" role="status"><b>{{ $voucher->status === 'rejected' ? 'Rejected' : 'Last rejected' }}{{ $voucher->status === 'rejected' && $voucher->checker ? ' by '.$voucher->checker->name : '' }}:</b> {{ $voucher->rejection_reason }}</div>
        @endif

        {{-- The voucher at a glance, with what can be done next --}}
        <section class="ak-card ev-summary" aria-label="Voucher summary">
            <div class="ev-summary-main">
                <p class="ev-kicker">{{ $voucherTitle }} · {{ $voucher->voucher_date->format('l, d M Y') }}</p>
                <p class="ev-amount"><small>Rs</small>{{ number_format($voucher->amount, 2) }}</p>
                <p class="ev-who">Paid to <b>@if($voucher->expenseVendor)<a href="{{ route('expense-vendors.show', $voucher->expenseVendor) }}">{{ $voucher->party_name }}</a>@else{{ $voucher->party_name }}@endif</b>
                    <span>· {{ $voucher->supplier?->supplier_name ?? (($voucher->company?->company_name ?? 'Moon Traders').' (general)') }}</span></p>
                <div class="ev-chips">
                    <span class="gl-stage gl-stage-{{ $stageTone }}">{{ $voucher->statusLabel() }}</span>
                    <span class="ev-chip">{{ $methodLabel }}{{ $voucher->isAdvance() ? ' · advance' : '' }}</span>
                    @if($voucher->isApproved() && $voucher->isCredit())<span class="ev-chip">{{ ucfirst($voucher->payment_status) }} · owes Rs {{ number_format($voucher->outstanding(), 2) }}</span>@endif
                    @if($voucher->journal_entry_id)<span class="ev-chip">Journal #{{ $voucher->journal_entry_id }}</span>@endif
                </div>
            </div>
            <div class="ev-summary-actions no-print">
                @if($canApprove)
                    <button type="button" class="ak-btn ak-btn-primary" @click="dialog = 'approve'">{{ $needsNotes ? 'Count notes and approve' : 'Approve and post' }}</button>
                    <button type="button" class="ak-btn ak-btn-ghost" @click="dialog = 'reject'">Reject</button>
                @elseif($voucher->status === 'pending' && $isMaker && $user->can('expense-voucher-approve'))
                    <p class="ev-hint">You made this voucher, so another user has to check it.</p>
                @endif
                @if($canSubmit)
                    <form method="POST" action="{{ route('expense-vouchers.submit', $voucher) }}">@csrf
                        <button class="ak-btn ak-btn-primary">Submit for approval</button>
                    </form>
                @endif
                @if($canEdit)
                    <a href="{{ route('expense-vouchers.edit', $voucher) }}" class="ak-btn {{ $voucher->status === 'rejected' ? 'ak-btn-primary' : 'ak-btn-ghost' }}">{{ $voucher->status === 'rejected' ? 'Edit and correct' : 'Edit' }}</a>
                @endif
                @if($canPrint)
                    <a href="{{ route('expense-vouchers.print', $voucher) }}" target="_blank" class="ak-btn ak-btn-primary" title="Print the A4 payment voucher">Print voucher</a>
                    <a href="{{ route('expense-vouchers.print', ['expense_voucher' => $voucher, 'format' => 'slip']) }}" target="_blank" class="ak-btn ak-btn-ghost" title="Print the 80mm thermal slip">Print slip</a>
                @endif
                @if($canReverse)
                    <button type="button" class="ak-btn ak-btn-ghost ev-danger" @click="$dispatch('open-reverse-voucher')">Reverse</button>
                @endif
                @if($canDelete)
                    <button type="button" class="ak-btn ak-btn-ghost ev-danger" @click="dialog = 'delete'">Delete</button>
                @endif
                @if($voucher->isEditable() && ! $isMaker)
                    <p class="ev-hint">Only {{ $voucher->creator?->name }}, who made this voucher, can change it or send it to the checker.</p>
                @endif
            </div>
            <ol class="ev-steps" aria-label="Voucher progress">
                <li class="is-done"><span class="dot">✓</span> Made by {{ $voucher->creator?->name }}</li>
                <li class="{{ $step > 2 ? 'is-done' : ($step === 2 ? 'is-now' : '') }}"><span class="dot">{{ $step > 2 ? '✓' : '2' }}</span> Sent to checker</li>
                <li class="{{ in_array($voucher->status, ['approved', 'reversed'], true) ? 'is-done' : ($voucher->status === 'rejected' ? 'is-bad' : '') }}"><span class="dot">{{ in_array($voucher->status, ['approved', 'reversed'], true) ? '✓' : ($voucher->status === 'rejected' ? '✕' : '3') }}</span>
                    {{ in_array($voucher->status, ['approved', 'reversed'], true) ? 'Approved by '.$voucher->checker?->name : ($voucher->status === 'rejected' ? 'Rejected by '.$voucher->checker?->name : 'Checker approves') }}</li>
                <li class="{{ $voucher->journal_entry_id ? 'is-done' : '' }}"><span class="dot">{{ $voucher->journal_entry_id ? '✓' : '4' }}</span> Posted to the ledger</li>
                @if($voucher->isReversed())<li class="is-bad"><span class="dot">↺</span> Reversed{{ $voucher->reverser ? ' by '.$voucher->reverser->name : '' }}</li>@endif
            </ol>
        </section>

        <div class="ev-grid">
            <section class="ak-card ev-pad" aria-labelledby="ev-details">
                <h2 class="ev-h2" id="ev-details">Details</h2>
                <dl class="ev-dl">
                    <div><dt>Voucher no.</dt><dd>{{ $voucher->voucher_number }}</dd></div>
                    <div><dt>Date</dt><dd>{{ $voucher->voucher_date->format('d M Y') }}</dd></div>
                    <div><dt>Paid to</dt><dd>{{ $voucher->party_name }}@if($voucher->employee)<span class="ev-sub">Employee {{ $voucher->employee->employee_code }}</span>@endif</dd></div>
                    <div><dt>Company</dt><dd>{{ $voucher->supplier?->supplier_name ?? (($voucher->company?->company_name ?? 'Moon Traders').' (general)') }}</dd></div>
                    @if($voucher->category)<div><dt>Category</dt><dd>{{ $voucher->category->name }}</dd></div>@endif
                    <div><dt>{{ $voucher->isAdvance() ? 'Account' : 'Expense account' }}</dt><dd>{{ $voucher->expenseAccount?->account_code }} {{ $voucher->expenseAccount?->account_name }}</dd></div>
                    <div><dt>Payment method</dt><dd>{{ $methodLabel }}
                        @if($voucher->isPaidByBank())
                            <span class="ev-sub">{{ $voucher->bankAccount?->bank_name }} · {{ $voucher->bankAccount?->account_name }}{{ $voucher->bankAccount?->account_number ? ' · '.$voucher->bankAccount->account_number : '' }}</span>
                            @if($voucher->cheque_number)<span class="ev-sub">Cheque {{ $voucher->cheque_number }}{{ $voucher->cheque_date ? ', dated '.$voucher->cheque_date->format('d M Y') : '' }}</span>@endif
                            @if($voucher->transaction_reference)<span class="ev-sub">Ref. {{ $voucher->transaction_reference }}</span>@endif
                        @endif
                        @if($voucher->isApproved() && $voucher->isCredit())<span class="ev-sub">Paid Rs {{ number_format($voucher->paid_amount, 2) }}, owes Rs {{ number_format($voucher->outstanding(), 2) }}</span>@endif
                    </dd></div>
                    @if($voucher->vendor_bill_no)<div><dt>Shop bill no.</dt><dd>{{ $voucher->vendor_bill_no }}</dd></div>@endif
                    @if($voucher->vehicle)<div><dt>Vehicle</dt><dd>{{ $voucher->vehicle->vehicle_number }}</dd></div>@endif
                    @if($voucher->isAdvance() && $voucher->advance_installment)<div><dt>Monthly instalment</dt><dd>Rs {{ number_format($voucher->advance_installment, 2) }}</dd></div>@endif
                    <div class="ev-wide"><dt>Narration</dt><dd>{{ $voucher->narration ?: '—' }}</dd></div>
                    <div><dt>Made</dt><dd>{{ $voucher->creator?->name }}<span class="ev-sub">{{ $voucher->created_at?->format('d M Y h:i A') }}</span></dd></div>
                    <div><dt>Checked</dt><dd>@if($voucher->checked_at){{ $voucher->checker?->name }}<span class="ev-sub">{{ $voucher->checked_at->format('d M Y h:i A') }}</span>@else — @endif</dd></div>
                </dl>
            </section>

            <div class="ev-side">
                <section class="ak-card ev-pad" aria-labelledby="ev-ledger">
                    <h2 class="ev-h2" id="ev-ledger">{{ $voucher->journal_entry_id ? 'Posted to the ledger' : 'What approval will post' }}</h2>
                    <p class="ev-hint">{{ $voucher->journal_entry_id ? 'Journal entry #'.$voucher->journalEntry?->id.' ('.$voucher->journalEntry?->status.').' : 'Nothing is posted until a checker approves.' }}</p>
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

                @if($notes && ! $notes->isEmpty())
                    <section class="ak-card ev-pad" aria-labelledby="ev-notes">
                        <h2 class="ev-h2" id="ev-notes">Notes paid out</h2>
                        <p class="ev-hint">Counted and handed over by {{ $voucher->checker?->name ?? 'the checker' }}{{ $voucher->checked_at ? ' on '.$voucher->checked_at->format('d M Y h:i A') : '' }}.</p>
                        <table class="ev-lines">
                            <tbody>
                                @foreach(\App\Support\CashNotes::NOTES as $note)
                                    @if($notes->count($note) > 0)
                                        <tr><td>Rs {{ number_format($note) }} × {{ $notes->count($note) }}</td><td class="n">{{ number_format($note * $notes->count($note), 2) }}</td></tr>
                                    @endif
                                @endforeach
                                @if($notes->coins() > 0)<tr><td>Coins</td><td class="n">{{ number_format($notes->coins(), 2) }}</td></tr>@endif
                                <tr class="ev-total"><td>Total</td><td class="n">{{ number_format($notes->total(), 2) }}</td></tr>
                            </tbody>
                        </table>
                    </section>
                @endif

                @if($voucher->attachments->isNotEmpty())
                    <section class="ak-card ev-pad" aria-labelledby="ev-files">
                        <h2 class="ev-h2" id="ev-files">Attachments ({{ $voucher->attachments->count() }})</h2>
                        <div class="ev-file-list">
                            @foreach($voucher->attachments as $file)
                                @php $attExt = strtolower(pathinfo($file->file_name, PATHINFO_EXTENSION) ?: pathinfo($file->file_path, PATHINFO_EXTENSION)); @endphp
                                <button type="button" class="ak-btn ak-btn-ghost" onclick="evPreview(@js($voucher->attachmentUrl($file)), @js($attExt), @js($voucher->voucher_number.' - '.$file->file_name))">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" /></svg>
                                    {{ \Illuminate\Support\Str::limit($file->file_name, 28) }}
                                </button>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>
        </div>

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
                                    <label for="ev-reject-reason" style="display:block; margin-top:14px; font-size:13px; font-weight:600">Reason for rejecting</label>
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
                                <label for="ev-reverse-reason" style="display:block; margin-top:14px; font-size:13px; font-weight:600">Reason</label>
                                <textarea id="ev-reverse-reason" name="reason" required minlength="5" maxlength="500" rows="3" autocomplete="off" placeholder="e.g. Entered twice by mistake" style="margin-top:6px; width:100%; border:1px solid var(--ak-border); border-radius:8px; padding:8px 12px; font-size:14px">{{ old('reason') }}</textarea>
                                @error('reason')<p style="margin:4px 0 0; font-size:13px; color:#b91c1c">{{ $message }}</p>@enderror
                                {{-- Gives the browser's password manager a username field, so it does not fill the reason with the email. --}}
                                <input type="text" name="username" value="{{ auth()->user()->email }}" autocomplete="username" hidden readonly tabindex="-1">
                                <label for="ev-reverse-password" style="display:block; margin-top:12px; font-size:13px; font-weight:600">Your password</label>
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
