{{--
    Expense voucher (/expense-vouchers/{uuid}) laid out like the paper Cash / Bank Payment Voucher: a document
    header (company, title, voucher no., date, status), the payee and payment block, the amount with its words,
    the particulars (debit / credit lines), the notes paid out, then a side column with the payee card, the
    workflow trail and the attachments. One action bar gives exactly one primary action for the status and
    sits at the bottom of the screen on a phone. Every dialog form is guarded against a second click.
--}}
@php
    use App\Models\ExpenseVoucher;
    use App\Support\AmountInWords;
    use App\Support\CashNotes;

    $user = auth()->user();
    $isMaker = (int) $voucher->created_by === $user->id;
    $status = $voucher->status;
    $isCash = $voucher->payment_method === ExpenseVoucher::METHOD_CASH;
    $hasAttachments = $voucher->attachments->isNotEmpty();
    $notes = $voucher->cashPayout ? CashNotes::fromColumns($voucher->cashPayout) : null;
    $showNotes = $isCash && in_array($status, [ExpenseVoucher::STATUS_APPROVED, ExpenseVoucher::STATUS_REVERSED], true) && $notes && ! $notes->isEmpty();
    $needsNotes = $status === ExpenseVoucher::STATUS_PENDING && $isCash;
    $notesOld = (array) old('denom', []);
    $notesStart = ['coins' => $notesOld['coins'] ?? ''];
    foreach (CashNotes::NOTES as $note) {
        $notesStart[$note] = $notesOld[$note] ?? '';
    }
    $notesFailed = $errors->has('denom') || collect($errors->keys())->contains(fn ($key) => str_starts_with($key, 'denom.'));
    $approveFailed = $notesFailed || $errors->has('approve_without_attachment');

    $methodLabel = ExpenseVoucher::paymentMethodLabels()[$voucher->payment_method] ?? $voucher->payment_method;
    [$voucherTitle, $voucherTitleUrdu] = match ($voucher->payment_method) {
        ExpenseVoucher::METHOD_BANK_TRANSFER => ['Bank Payment Voucher', 'بینک ادائیگی واؤچر'],
        ExpenseVoucher::METHOD_CHEQUE => ['Cheque Payment Voucher', 'چیک ادائیگی واؤچر'],
        ExpenseVoucher::METHOD_CREDIT => ['Credit Expense Voucher', 'ادھار اخراجاتی واؤچر'],
        default => ['Cash Payment Voucher', 'نقد ادائیگی واؤچر'],
    };
    $stageTone = ['draft' => 'grey', 'pending' => 'amber', 'approved' => 'green', 'rejected' => 'red', 'reversed' => 'red'][$status] ?? 'grey';
    $ownerName = $voucher->supplier?->supplier_name ?? (($voucher->company?->company_name ?? $companyName).' (general)');
    $debitName = $voucher->isAdvance() ? 'Employee Advances' : $voucher->expenseAccount?->account_name;
    $creditName = $voucher->isCredit() ? 'Expense Payables (the shop is paid later)' : ($voucher->isPaidByBank() ? $voucher->bankAccount?->bank_name.' ('.$voucher->bankAccount?->account_name.')' : 'Cash');

    /** The bill lines; a voucher saved before bill lines existed shows its own single line. */
    $billLines = $voucher->isAdvance() ? collect() : $voucher->postingLines()->each->loadMissing('expenseAccount');
    $extraLines = max($billLines->count() - 1, 0);

    /** Particulars: the posted journal lines (one debit per bill line), or what approval will post. */
    $journalLines = $voucher->journalEntry?->details ?? collect();
    $particulars = $journalLines->isNotEmpty()
        ? $journalLines->map(fn ($line) => [
            'code' => $line->account?->account_code,
            'name' => $line->account?->account_name,
            'narration' => $line->description ?: $voucher->narration,
            'debit' => (float) $line->debit,
            'credit' => (float) $line->credit,
        ])
        : ($voucher->isAdvance()
            ? collect([['code' => '1131', 'name' => 'Employee Advances', 'narration' => $voucher->narration, 'debit' => (float) $voucher->amount, 'credit' => 0.0]])
            : $billLines->map(fn ($line) => [
                'code' => $line->expenseAccount?->account_code,
                'name' => $line->expenseAccount?->account_name,
                'narration' => $line->description ?: $voucher->narration,
                'debit' => (float) $line->amount,
                'credit' => 0.0,
            ]))->push(
            [
                'code' => $voucher->isCredit() ? '2113' : ($voucher->isPaidByBank() ? $voucher->bankAccount?->chartOfAccount?->account_code : '1121'),
                'name' => $voucher->isCredit() ? 'Expense Payables' : ($voucher->isPaidByBank() ? trim($voucher->bankAccount?->bank_name.' · '.$voucher->bankAccount?->account_name, ' ·') : 'Cash'),
                'narration' => $voucher->isCredit() ? 'Owed to '.$voucher->party_name : 'Paid to '.$voucher->party_name,
                'debit' => 0.0,
                'credit' => (float) $voucher->amount,
            ],
        );

    $canApprove = $status === ExpenseVoucher::STATUS_PENDING && ! $isMaker && $user->can('expense-voucher-approve');
    $canEdit = $voucher->isEditable() && $isMaker && $user->can('expense-voucher-edit');
    $canSubmit = $voucher->isEditable() && $isMaker && $user->can('expense-voucher-create');
    $canDelete = $voucher->isEditable() && $isMaker && $user->can('expense-voucher-delete');
    $canPrint = ($voucher->isApproved() || $voucher->isReversed()) && $user->can('expense-voucher-print');
    $canReverse = $voucher->isApproved() && ! $voucher->reversed_at && ! $isMaker && $user->can('expense-voucher-reverse');

    /** The single primary action for this status and viewer. */
    $primary = match (true) {
        $status === ExpenseVoucher::STATUS_DRAFT && $canSubmit => 'submit',
        $canApprove => 'approve',
        $canPrint => 'print',
        $status === ExpenseVoucher::STATUS_REJECTED && $canEdit => 'edit',
        default => null,
    };
    $nextHint = match (true) {
        $status === ExpenseVoucher::STATUS_PENDING && $isMaker && $user->can('expense-voucher-approve') => 'You made this voucher, so another user has to check it.',
        $status === ExpenseVoucher::STATUS_PENDING && ! $canApprove => 'Waiting for a checker to approve.',
        $voucher->isEditable() && ! $isMaker => 'Only '.($voucher->creator?->name ?? 'the maker').', who made this voucher, can change it or send it to the checker.',
        $status === ExpenseVoucher::STATUS_DRAFT => 'Draft. Send it to the checker when it is ready.',
        $status === ExpenseVoucher::STATUS_REJECTED => 'Correct the voucher, then send it again.',
        $status === ExpenseVoucher::STATUS_PENDING => 'Check the payee, the amount and the bill, then approve or reject.',
        $status === ExpenseVoucher::STATUS_APPROVED => 'Approved and posted to the ledger.',
        default => 'Reversed. The ledger entry was offset.',
    };
    $payeeType = $voucher->expenseVendor ? 'vendor' : ($voucher->employee ? 'employee' : 'other');
    $employeeCompany = $voucher->employee?->supplier?->supplier_name ?? $voucher->employee?->company?->company_name ?? $voucher->employee?->company_name;
    $statusLabels = ExpenseVoucher::statusLabels();
    $toneOf = fn (string $s) => ['draft' => 'grey', 'pending' => 'amber', 'approved' => 'green', 'rejected' => 'red', 'reversed' => 'red'][$s] ?? 'grey';
    $when = fn ($at) => $at?->format('d M Y, h:i A');
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Voucher {{ $voucher->voucher_number }}" :createRoute="null" createLabel="" :showSearch="false" :showRefresh="false" backRoute="{{ route('expense-vouchers.index') }}">
            <x-slot name="actions">
                <button type="button" class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest transition ease-in-out duration-150 no-print"
                    x-data @click="$dispatch('rg-help', { topic: @js($needsNotes ? 'approve' : ($voucher->isApproved() ? 'print' : ($voucher->isReversed() ? 'reverse' : 'statuses'))) })" aria-haspopup="dialog" aria-label="Help in Urdu" title="How vouchers work, in Urdu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></svg>
                </button>
            </x-slot>
        </x-page-header>
    </x-slot>

    @include('settings.partials.ui-style')
    <x-apple-skin />
    <x-register-apple-style />
    <x-apple-fields-style />
    <x-register-help page="expense-vouchers" />
    @include('expense-vouchers.partials.show-style')

    <div class="ak-page ev-show{{ $primary || $canReverse || $canDelete || $canEdit ? ' has-actionbar' : '' }}" x-data="{ dialog: @js($approveFailed ? 'approve' : null) }" @keydown.escape.window="dialog = null">
        <x-status-message />

        @if($voucher->isReversed())
            <div class="ev-banner ev-banner-red" role="status">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" /></svg>
                <div>
                    <b>Reversed</b> on {{ $when($voucher->reversed_at) }}{{ $voucher->reverser ? ' by '.$voucher->reverser->name : '' }}.
                    The ledger entry was offset{!! $voucher->reversal_journal_entry_id ? ' by <a href="'.e(route('journal-entries.show', $voucher->reversal_journal_entry_id)).'">journal entry #'.e($voucher->reversal_journal_entry_id).'</a>' : '' !!}{{ $isCash ? ' and the cash came back on the day of the reversal' : '' }}.
                    <span class="ev-banner-sub">Reason: {{ $voucher->reversal_reason }}</span>
                </div>
            </div>
        @endif
        @if(in_array($status, ['rejected', 'pending'], true) && $voucher->rejection_reason)
            <div class="ev-banner ev-banner-amber" role="status">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.01" /></svg>
                <div><b>{{ $status === 'rejected' ? 'Rejected' : 'Last rejected' }}{{ $status === 'rejected' && $voucher->checker ? ' by '.$voucher->checker->name : '' }}:</b> {{ $voucher->rejection_reason }}</div>
            </div>
        @endif
        @if(! $hasAttachments && in_array($status, ['draft', 'pending', 'rejected'], true))
            <div class="ev-banner ev-banner-amber" role="status">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32" /></svg>
                <div><b>No receipt or bill attached to this voucher.</b> <span class="ev-banner-sub">{{ $canApprove ? 'You can still approve it, after confirming you have checked it.' : 'Attach a photo of the bill so the checker can see it.' }}</span></div>
            </div>
        @endif

        {{-- Action bar: what happens next, then exactly one primary action. Sticky on desktop, at the bottom on a phone. --}}
        <div class="ev-actionbar no-print" role="toolbar" aria-label="Voucher actions">
            <div class="ev-actionbar-info">
                <span class="gl-stage gl-stage-{{ $stageTone }}">{{ $voucher->statusLabel() }}</span>
                <span class="ev-actionbar-hint">{{ $nextHint }}</span>
            </div>
            <div class="ev-actionbar-buttons">
                @if($canReverse)
                    <button type="button" class="ak-btn ev-btn-quiet ev-btn-danger" @click="dialog = 'reverse'">Reverse</button>
                @endif
                @if($canDelete)
                    <button type="button" class="ak-btn ev-btn-quiet ev-btn-danger" @click="dialog = 'delete'">Delete</button>
                @endif
                @if($canEdit && $primary !== 'edit')
                    <a href="{{ route('expense-vouchers.edit', $voucher) }}" class="ak-btn ak-btn-ghost">Edit</a>
                @endif
                @if($canSubmit && $primary !== 'submit')
                    <form method="POST" action="{{ route('expense-vouchers.submit', $voucher) }}" data-once-form>@csrf
                        <button type="submit" class="ak-btn ak-btn-ghost" data-busy-label="Sending…">Send again</button>
                    </form>
                @endif
                @if($canApprove)
                    <button type="button" class="ak-btn ak-btn-ghost" @click="dialog = 'reject'">Reject</button>
                @endif
                @if($canPrint)
                    <a href="{{ route('expense-vouchers.print', ['expense_voucher' => $voucher, 'format' => 'slip']) }}" target="_blank" class="ak-btn ak-btn-ghost" title="Print the 80mm thermal slip">Print slip</a>
                @endif

                @switch($primary)
                    @case('submit')
                        <form method="POST" action="{{ route('expense-vouchers.submit', $voucher) }}" data-once-form>@csrf
                            <button type="submit" class="ak-btn ak-btn-primary" data-primary-action="submit" data-busy-label="Sending…">Submit for approval</button>
                        </form>
                        @break
                    @case('approve')
                        <button type="button" class="ak-btn ak-btn-primary" data-primary-action="approve" @click="dialog = 'approve'">{{ $needsNotes ? 'Count notes and approve' : 'Approve and post' }}</button>
                        @break
                    @case('print')
                        <a href="{{ route('expense-vouchers.print', $voucher) }}" target="_blank" class="ak-btn ak-btn-primary" data-primary-action="print" title="Print the A4 payment voucher">Print voucher</a>
                        @break
                    @case('edit')
                        <a href="{{ route('expense-vouchers.edit', $voucher) }}" class="ak-btn ak-btn-primary" data-primary-action="edit">Edit and correct</a>
                        @break
                @endswitch
            </div>
        </div>

        <div class="ev-layout">
            {{-- The voucher itself, as it reads on paper --}}
            <article class="ak-card ev-doc" aria-labelledby="ev-doc-title">
                <header class="ev-doc-head">
                    <div class="ev-doc-brand">
                        <p class="ev-doc-company">{{ $companyName }}</p>
                        <p class="ev-doc-owner">{{ $ownerName }}</p>
                        <h1 class="ev-doc-title">{{ $voucherTitle }}</h1>
                        <p class="ev-doc-urdu" lang="ur" dir="rtl">{{ $voucherTitleUrdu }}</p>
                    </div>
                    <dl class="ev-doc-meta">
                        <div><dt>Voucher no.</dt><dd class="ev-mono">{{ $voucher->voucher_number }}</dd></div>
                        <div><dt>Date</dt><dd>{{ $voucher->voucher_date->format('d M Y') }}</dd></div>
                        <div><dt>Status</dt><dd><span class="gl-stage gl-stage-{{ $stageTone }}">{{ $voucher->statusLabel() }}</span></dd></div>
                    </dl>
                </header>

                <section class="ev-amount-band" aria-label="Amount">
                    <p class="ev-label">Amount</p>
                    <p class="ev-amount"><small>Rs</small>{{ number_format((float) $voucher->amount, 2) }}</p>
                    <p class="ev-words">{{ ucfirst(AmountInWords::rupees((float) $voucher->amount)) }}</p>
                </section>

                <section class="ev-block" aria-labelledby="ev-payment-h">
                    <h2 class="ev-h2" id="ev-payment-h">Payee and payment</h2>
                    <dl class="ev-dl">
                        <div><dt>Paid to</dt><dd>@if($voucher->expenseVendor)<a href="{{ route('expense-vendors.show', $voucher->expenseVendor) }}">{{ $voucher->party_name }}</a>@else{{ $voucher->party_name }}@endif
                            @if($voucher->employee)<span class="ev-sub">Employee {{ $voucher->employee->employee_code }}{{ $voucher->isAdvance() ? ' · advance salary' : '' }}</span>@endif</dd></div>
                        <div><dt>Company</dt><dd>{{ $ownerName }}</dd></div>
                        <div><dt>Payment method</dt><dd>{{ $methodLabel }}{{ $voucher->isAdvance() ? ' · advance' : '' }}
                            @if($voucher->isApproved() && $voucher->isCredit())<span class="ev-sub">Paid Rs {{ number_format((float) $voucher->paid_amount, 2) }}, owes Rs {{ number_format($voucher->outstanding(), 2) }}</span>@endif</dd></div>
                        @if($voucher->isPaidByBank())
                            <div><dt>Bank</dt><dd>{{ $voucher->bankAccount?->bank_name }}<span class="ev-sub">{{ $voucher->bankAccount?->account_name }}{{ $voucher->bankAccount?->account_number ? ' · '.$voucher->bankAccount->account_number : '' }}</span></dd></div>
                            @if($voucher->cheque_number)<div><dt>Cheque</dt><dd class="ev-mono">No. {{ $voucher->cheque_number }}<span class="ev-sub">{{ $voucher->cheque_date ? 'Dated '.$voucher->cheque_date->format('d M Y') : 'No cheque date' }}</span></dd></div>@endif
                            @if($voucher->transaction_reference)<div><dt>Transaction ref.</dt><dd class="ev-mono">{{ $voucher->transaction_reference }}</dd></div>@endif
                        @endif
                        <div><dt>Shop bill no.</dt><dd>{{ $voucher->vendor_bill_no ?: '—' }}@if($voucher->vendor_bill_date)<span class="ev-sub">Dated {{ $voucher->vendor_bill_date->format('d M Y') }}</span>@endif</dd></div>
                        <div><dt>Category</dt><dd>{{ $voucher->category?->name ?? '—' }}</dd></div>
                        <div><dt>{{ $voucher->isAdvance() ? 'Account' : 'Expense account' }}</dt><dd><span class="ev-mono">{{ $voucher->expenseAccount?->account_code }}</span> {{ $voucher->expenseAccount?->account_name }}@if($extraLines)<span class="ev-sub">+{{ $extraLines }} more {{ $extraLines === 1 ? 'line' : 'lines' }}, see Particulars</span>@endif</dd></div>
                        @if($voucher->vehicle)<div><dt>Vehicle</dt><dd>{{ $voucher->vehicle->vehicle_number }}</dd></div>@endif
                        @if($voucher->isAdvance() && $voucher->advance_installment)<div><dt>Monthly instalment</dt><dd>Rs {{ number_format((float) $voucher->advance_installment, 2) }}</dd></div>@endif
                        <div><dt>Submitted</dt><dd>@if($voucher->submitted_at){{ $voucher->submitter?->name ?? $voucher->creator?->name }}<span class="ev-sub">{{ $when($voucher->submitted_at) }}</span>@else Not yet @endif</dd></div>
                    </dl>
                </section>

                <section class="ev-block" aria-labelledby="ev-particulars-h">
                    <div class="ev-block-head">
                        <h2 class="ev-h2" id="ev-particulars-h">Particulars</h2>
                        <p class="ev-hint">@if($voucher->journal_entry_id)Posted as <a href="{{ route('journal-entries.show', $voucher->journal_entry_id) }}">journal entry #{{ $voucher->journal_entry_id }}</a>@else What approval will post. Nothing is posted until a checker approves. @endif</p>
                    </div>
                    <table class="ev-particulars">
                        <thead><tr><th scope="col" class="ev-p-code">Code</th><th scope="col">Account</th><th scope="col">Description</th><th scope="col" class="n">Debit (Rs)</th><th scope="col" class="n">Credit (Rs)</th></tr></thead>
                        <tbody>
                            @foreach($particulars as $line)
                                <tr>
                                    <td class="ev-p-code ev-mono" data-label="Code">{{ $line['code'] ?: '—' }}</td>
                                    <td class="ev-p-account" data-label="Account">{{ $line['name'] }}</td>
                                    <td class="ev-p-narration" data-label="Description">{{ $line['narration'] ?: '—' }}</td>
                                    <td class="n" data-label="Debit">{{ $line['debit'] > 0 ? number_format($line['debit'], 2) : '' }}</td>
                                    <td class="n" data-label="Credit">{{ $line['credit'] > 0 ? number_format($line['credit'], 2) : '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot><tr><td colspan="3">Total</td><td class="n" data-label="Debit">{{ number_format($particulars->sum('debit'), 2) }}</td><td class="n" data-label="Credit">{{ number_format($particulars->sum('credit'), 2) }}</td></tr></tfoot>
                    </table>
                </section>

                @if($showNotes)
                    <section class="ev-block" aria-labelledby="ev-notes-h">
                        <div class="ev-block-head">
                            <h2 class="ev-h2" id="ev-notes-h">Notes paid out</h2>
                            <p class="ev-hint">Counted and handed over by {{ $voucher->checker?->name ?? 'the checker' }}{{ $voucher->checked_at ? ' on '.$when($voucher->checked_at) : '' }}.</p>
                        </div>
                        <ul class="ev-notes">
                            @foreach(CashNotes::NOTES as $note)
                                @if($notes->count($note) > 0)
                                    <li><span class="ev-note-face">{{ number_format($note) }}</span><span class="ev-note-x">× {{ $notes->count($note) }}</span><b>{{ number_format($note * $notes->count($note), 2) }}</b></li>
                                @endif
                            @endforeach
                            @if($notes->coins() > 0)<li><span class="ev-note-face">Coins</span><span class="ev-note-x"></span><b>{{ number_format($notes->coins(), 2) }}</b></li>@endif
                            <li class="ev-notes-total"><span>Total</span><span></span><b>Rs {{ number_format($notes->total(), 2) }}</b></li>
                        </ul>
                    </section>
                @endif
            </article>

            <aside class="ev-side" aria-label="Payee, trail and attachments">
                {{-- Payee card: what the checker needs to know about who is being paid --}}
                <section class="ak-card ev-card" aria-labelledby="ev-payee-h">
                    <h2 class="ev-h2" id="ev-payee-h">Payee</h2>
                    @if($payeeType === 'vendor')
                        @php $shop = $voucher->expenseVendor; @endphp
                        <p class="ev-payee-name"><a href="{{ route('expense-vendors.show', $shop) }}">{{ $shop->name }}</a><span class="ev-chip">{{ $shop->categoryLabel() }}</span></p>
                        <dl class="ev-facts">
                            <div><dt>Phone</dt><dd>@if($shop->phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $shop->phone) }}">{{ $shop->phone }}</a>@else — @endif</dd></div>
                            <div><dt>City</dt><dd>{{ $shop->city ?: '—' }}</dd></div>
                            <div class="ev-wide"><dt>Address</dt><dd>{{ $shop->address ?: '—' }}</dd></div>
                            <div class="ev-wide"><dt>Default account</dt><dd>{{ $shop->defaultExpenseAccount ? $shop->defaultExpenseAccount->account_code.' '.$shop->defaultExpenseAccount->account_name : '—' }}</dd></div>
                            <div class="ev-wide"><dt>Owed on credit</dt><dd class="{{ $vendorOutstanding > 0 ? 'ev-owed' : '' }}">Rs {{ number_format((float) $vendorOutstanding, 2) }}<span class="ev-sub">Unpaid credit vouchers to this shop</span></dd></div>
                        </dl>
                        <h3 class="ev-h3">Last vouchers to this shop</h3>
                        @if($vendorHistory->isNotEmpty())
                            <ul class="ev-history">
                                @foreach($vendorHistory as $past)
                                    <li>
                                        <a href="{{ route('expense-vouchers.show', $past) }}">
                                            <span class="ev-history-main"><b class="ev-mono">{{ $past->voucher_number }}</b><small>{{ $past->voucher_date->format('d M Y') }}</small></span>
                                            <span class="ev-history-end"><b>{{ number_format((float) $past->amount, 2) }}</b><span class="gl-stage gl-stage-{{ $toneOf($past->status) }}">{{ $statusLabels[$past->status] ?? $past->status }}</span></span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="ev-hint">No other vouchers to this shop yet.</p>
                        @endif
                    @elseif($payeeType === 'employee')
                        @php $employee = $voucher->employee; @endphp
                        <p class="ev-payee-name">{{ $employee->name }}<span class="ev-chip">Employee</span></p>
                        <dl class="ev-facts">
                            <div><dt>Code</dt><dd class="ev-mono">{{ $employee->employee_code ?: '—' }}</dd></div>
                            <div><dt>Designation</dt><dd>{{ $employee->designation ?: '—' }}</dd></div>
                            <div class="ev-wide"><dt>Company</dt><dd>{{ $employeeCompany ?: '—' }}</dd></div>
                            <div class="ev-wide"><dt>Open advance balance</dt><dd class="{{ $advanceBalance > 0 ? 'ev-owed' : '' }}">Rs {{ number_format((float) $advanceBalance, 2) }}<span class="ev-sub">Still to be recovered from salary</span></dd></div>
                        </dl>
                    @else
                        <p class="ev-payee-name">{{ $voucher->party_name }}</p>
                        <p class="ev-hint">Not a saved shop or employee, so there is no history to show.</p>
                    @endif
                </section>

                {{-- Workflow trail --}}
                <section class="ak-card ev-card" aria-labelledby="ev-trail-h">
                    <h2 class="ev-h2" id="ev-trail-h">Workflow</h2>
                    <ol class="ev-trail">
                        <li class="is-done"><span class="ev-dot" aria-hidden="true"></span><div><b>Prepared</b><span>{{ $voucher->creator?->name ?? '—' }}</span><small>{{ $when($voucher->created_at) }}</small></div></li>
                        <li class="{{ $voucher->submitted_at ? 'is-done' : 'is-todo' }}"><span class="ev-dot" aria-hidden="true"></span><div><b>Submitted</b>
                            @if($voucher->submitted_at)<span>{{ $voucher->submitter?->name ?? $voucher->creator?->name }}</span><small>{{ $when($voucher->submitted_at) }}</small>@else<span>Not sent to the checker yet</span>@endif</div></li>
                        @if($status === 'rejected')
                            <li class="is-bad"><span class="ev-dot" aria-hidden="true"></span><div><b>Rejected</b><span>{{ $voucher->checker?->name ?? '—' }}</span><small>{{ $when($voucher->checked_at) }}</small></div></li>
                        @else
                            <li class="{{ $voucher->checked_at && in_array($status, ['approved', 'reversed'], true) ? 'is-done' : ($status === 'pending' ? 'is-now' : 'is-todo') }}"><span class="ev-dot" aria-hidden="true"></span><div><b>Checked and approved</b>
                                @if(in_array($status, ['approved', 'reversed'], true))<span>{{ $voucher->checker?->name ?? '—' }}</span><small>{{ $when($voucher->checked_at) }}</small>@else<span>{{ $status === 'pending' ? 'Waiting for the checker' : 'After it is sent' }}</span>@endif</div></li>
                        @endif
                        <li class="{{ $voucher->journal_entry_id ? 'is-done' : 'is-todo' }}"><span class="ev-dot" aria-hidden="true"></span><div><b>Posted</b>
                            @if($voucher->journal_entry_id)<span><a href="{{ route('journal-entries.show', $voucher->journal_entry_id) }}">Journal entry #{{ $voucher->journal_entry_id }}</a></span><small>{{ $when($voucher->journalEntry?->posted_at ?? $voucher->checked_at) }}</small>@else<span>On approval</span>@endif</div></li>
                        @if($voucher->isReversed())
                            <li class="is-bad"><span class="ev-dot" aria-hidden="true"></span><div><b>Reversed</b><span>{{ $voucher->reverser?->name ?? '—' }}@if($voucher->reversal_journal_entry_id) · <a href="{{ route('journal-entries.show', $voucher->reversal_journal_entry_id) }}">entry #{{ $voucher->reversal_journal_entry_id }}</a>@endif</span><small>{{ $when($voucher->reversed_at) }}</small></div></li>
                        @endif
                    </ol>
                </section>

                {{-- Attachments: image thumbnails, file chips for the rest --}}
                <section class="ak-card ev-card" aria-labelledby="ev-files-h">
                    <h2 class="ev-h2" id="ev-files-h">Attachments @if($hasAttachments)<span class="ev-count">{{ $voucher->attachments->count() }}</span>@endif</h2>
                    @if($hasAttachments)
                        <ul class="ev-files">
                            @foreach($voucher->attachments as $file)
                                @php
                                    $attExt = strtolower(pathinfo($file->file_name, PATHINFO_EXTENSION) ?: pathinfo($file->file_path, PATHINFO_EXTENSION));
                                    $attUrl = $voucher->attachmentUrl($file);
                                    $isImage = in_array($attExt, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic'], true);
                                @endphp
                                <li>
                                    <button type="button" class="ev-file{{ $isImage ? ' is-image' : '' }}" onclick="evPreview(@js($attUrl), @js($attExt), @js($voucher->voucher_number.' - '.$file->file_name))" title="{{ $file->file_name }}">
                                        @if($isImage && $attUrl)
                                            <img src="{{ $attUrl }}" alt="" loading="lazy" decoding="async">
                                        @else
                                            <span class="ev-file-icon" aria-hidden="true">{{ strtoupper($attExt ?: 'file') }}</span>
                                        @endif
                                        <span class="ev-file-name">{{ $file->file_name }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="ev-hint">No receipt or bill attached.</p>
                    @endif
                </section>
            </aside>
        </div>

        {{-- Approve: a short summary for the checker, the attachment check and, for cash, the notes handed out --}}
        @if($canApprove)
            <div class="uf-modal" x-show="dialog === 'approve'" x-cloak style="display:none" role="dialog" aria-modal="true" aria-labelledby="ev-dialog-approve">
                <div class="uf-modal-bg" x-show="dialog === 'approve'" x-transition.opacity @click="dialog = null"></div>
                <div class="uf-modal-box ev-sheet" x-show="dialog === 'approve'" x-transition style="max-width:{{ $needsNotes ? '620px' : '540px' }}">
                    <form method="POST" action="{{ route('expense-vouchers.approve', $voucher) }}" data-once-form class="apl-fields"
                          x-data="{
                              denom: @js($notesStart),
                              amount: @js(round((float) $voucher->amount, 2)),
                              needsNotes: @js($needsNotes),
                              needsConfirm: @js(! $hasAttachments),
                              confirmed: @js((bool) old('approve_without_attachment')),
                              onHand: @js($notesOnHand['counts'] ?? null),
                              get shortNotes() {
                                  if (! this.onHand) { return []; }
                                  return @js(CashNotes::NOTES).filter(n => (Math.max(0, parseInt(this.denom[n], 10)) || 0) > this.onHand[n]);
                              },
                              get counted() {
                                  let total = Number(this.denom.coins) || 0;
                                  @js(CashNotes::NOTES).forEach(n => { total += n * (Math.max(0, parseInt(this.denom[n], 10)) || 0); });
                                  return Math.round(total * 100) / 100;
                              },
                              get balanced() { return ! this.needsNotes || Math.abs(this.counted - this.amount) < 0.01; },
                              get ready() { return this.balanced && (! this.needsConfirm || this.confirmed); },
                              get blocker() { return ! this.balanced ? 'The notes must add up to the voucher amount' : (! this.ready ? 'Tick the box to approve without a receipt' : ''); },
                          }"
                          @submit="if (! ready) { $event.preventDefault(); }">
                        @csrf
                        <div class="ev-sheet-body">
                            <h3 id="ev-dialog-approve">{{ $needsNotes ? 'Count the notes and approve' : 'Approve and post this voucher?' }}</h3>
                            <dl class="ev-sheet-summary">
                                <div><dt>Paid to</dt><dd>{{ $voucher->party_name }}</dd></div>
                                <div><dt>Amount</dt><dd><b>Rs {{ number_format((float) $voucher->amount, 2) }}</b></dd></div>
                                <div><dt>Debit</dt><dd>{{ $voucher->isAdvance() ? '1131' : $voucher->expenseAccount?->account_code }} {{ $debitName }}{{ $extraLines ? ' +'.$extraLines.' more' : '' }}</dd></div>
                                <div><dt>Credit</dt><dd>{{ $creditName }}</dd></div>
                                <div><dt>Shop bill no.</dt><dd>{{ $voucher->vendor_bill_no ?: '—' }}</dd></div>
                                <div><dt>Attachments</dt><dd>{{ $hasAttachments ? $voucher->attachments->count() : 'None' }}</dd></div>
                                @if($payeeType === 'vendor')<div><dt>Shop owed on credit</dt><dd>Rs {{ number_format((float) $vendorOutstanding, 2) }}</dd></div>@endif
                                @if($payeeType === 'employee')<div><dt>Open advance</dt><dd>Rs {{ number_format((float) $advanceBalance, 2) }}</dd></div>@endif
                            </dl>
                            <p class="ev-sheet-text">{{ $voucher->isAdvance() ? 'The advance is then recovered from the next salary. ' : '' }}A posted entry cannot be changed afterwards; it can only be reversed.</p>

                            @if(! $hasAttachments)
                                <div class="ev-sheet-warn" role="alert">
                                    <p><b>No receipt or bill attached to this voucher.</b></p>
                                    <label class="ev-check">
                                        <input type="checkbox" name="approve_without_attachment" value="1" x-model="confirmed">
                                        <span>I have checked — approve without a receipt.</span>
                                    </label>
                                    @error('approve_without_attachment')<p class="ev-error">{{ $message }}</p>@enderror
                                </div>
                            @endif

                            @if($needsNotes)
                                <p class="ev-sheet-text">Enter the notes you hand to {{ $voucher->party_name }}. They must add up to <b>Rs {{ number_format((float) $voucher->amount, 2) }}</b>.</p>
                                <x-cash-notes model="denom" required="amount" />
                                @if($notesOnHand)
                                    {{-- A warning only: the checker may still approve when the box looks short. --}}
                                    <div class="ev-sheet-warn" role="status" x-show="shortNotes.length" x-cloak>
                                        <template x-for="n in shortNotes" :key="n">
                                            <p x-text="'Only ' + Math.max(0, onHand[n]) + ' × Rs ' + n.toLocaleString('en-PK') + ' notes on hand for ' + @js($notesOnHand['company']) + ' on ' + @js($notesOnHand['date']) + '.'"></p>
                                        </template>
                                    </div>
                                @endif
                                @if($notesFailed)
                                    <p class="ev-error">{{ $errors->first('denom') ?: collect($errors->getMessages())->filter(fn ($m, $key) => str_starts_with($key, 'denom.'))->flatten()->first() }}</p>
                                @endif
                            @endif
                        </div>
                        <div class="uf-modal-foot">
                            <button type="button" class="ak-btn ak-btn-ghost" @click="dialog = null">Cancel</button>
                            <button type="submit" class="ak-btn ak-btn-primary" :disabled="! ready" :title="blocker" data-busy-label="Posting…">Approve and post</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="uf-modal" x-show="dialog === 'reject'" x-cloak style="display:none" role="dialog" aria-modal="true" aria-labelledby="ev-dialog-reject">
                <div class="uf-modal-bg" x-show="dialog === 'reject'" x-transition.opacity @click="dialog = null"></div>
                <div class="uf-modal-box ev-sheet" x-show="dialog === 'reject'" x-transition style="max-width:520px">
                    <form method="POST" action="{{ route('expense-vouchers.reject', $voucher) }}" data-once-form class="apl-fields">
                        @csrf
                        <div class="ev-sheet-body">
                            <h3 id="ev-dialog-reject">Reject this voucher?</h3>
                            <p class="ev-sheet-text">It goes back to {{ $voucher->creator?->name ?? 'the maker' }} with your reason, who can correct it and send it again. Nothing is posted.</p>
                            <label for="ev-reject-reason" class="ev-field-label">Reason for rejecting</label>
                            <textarea id="ev-reject-reason" name="rejection_reason" required maxlength="500" rows="3" class="ev-textarea" placeholder="e.g. No bill attached">{{ old('rejection_reason') }}</textarea>
                            @error('rejection_reason')<p class="ev-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="uf-modal-foot">
                            <button type="button" class="ak-btn ak-btn-ghost" @click="dialog = null">Cancel</button>
                            <button type="submit" class="ak-btn ak-btn-danger-outline" data-busy-label="Rejecting…">Reject voucher</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @if($canDelete)
            <div class="uf-modal" x-show="dialog === 'delete'" x-cloak style="display:none" role="dialog" aria-modal="true" aria-labelledby="ev-dialog-delete">
                <div class="uf-modal-bg" x-show="dialog === 'delete'" x-transition.opacity @click="dialog = null"></div>
                <div class="uf-modal-box ev-sheet" x-show="dialog === 'delete'" x-transition style="max-width:480px">
                    <form method="POST" action="{{ route('expense-vouchers.destroy', $voucher) }}" data-once-form>
                        @csrf
                        @method('DELETE')
                        <div class="ev-sheet-body">
                            <h3 id="ev-dialog-delete">Delete this voucher?</h3>
                            <p class="ev-sheet-text">{{ $voucher->voucher_number }} and its attachments are removed. This cannot be undone.</p>
                        </div>
                        <div class="uf-modal-foot">
                            <button type="button" class="ak-btn ak-btn-ghost" @click="dialog = null">Cancel</button>
                            <button type="submit" class="ak-btn ak-btn-danger-outline" data-busy-label="Deleting…">Delete voucher</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @if($canReverse)
            {{-- Reverse: reason and password are both required; the server re-checks every condition. --}}
            <div class="uf-modal" x-show="dialog === 'reverse'" x-init="@js($errors->has('reason') || $errors->has('password')) && (dialog = 'reverse')" x-cloak style="display:none" role="dialog" aria-modal="true" aria-labelledby="ev-reverse-title">
                <div class="uf-modal-bg" x-show="dialog === 'reverse'" x-transition.opacity @click="dialog = null"></div>
                <div class="uf-modal-box ev-sheet" x-show="dialog === 'reverse'" x-transition style="max-width:520px">
                    <form method="POST" action="{{ route('expense-vouchers.reverse', $voucher) }}" data-once-form class="apl-fields">
                        @csrf
                        <div class="ev-sheet-body">
                            <h3 id="ev-reverse-title">Reverse {{ $voucher->voucher_number }}?</h3>
                            <p class="ev-sheet-text">An offsetting journal entry is posted today, so the ledger is back as if the voucher was never paid. {{ $isCash ? 'The cash comes back on today\'s cash sheet; the day of the voucher is not changed.' : '' }} Nothing is deleted.</p>
                            <label for="ev-reverse-reason" class="ev-field-label">Reason</label>
                            <textarea id="ev-reverse-reason" name="reason" required minlength="5" maxlength="500" rows="3" autocomplete="off" placeholder="e.g. Entered twice by mistake" class="ev-textarea">{{ old('reason') }}</textarea>
                            @error('reason')<p class="ev-error">{{ $message }}</p>@enderror
                            {{-- Gives the browser's password manager a username field, so it does not fill the reason with the email. --}}
                            <input type="text" name="username" value="{{ $user->email }}" autocomplete="username" hidden readonly tabindex="-1">
                            <label for="ev-reverse-password" class="ev-field-label">Your password</label>
                            <input id="ev-reverse-password" type="password" name="password" required autocomplete="current-password" class="ev-input">
                            @error('password')<p class="ev-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="uf-modal-foot">
                            <button type="button" class="ak-btn ak-btn-ghost" @click="dialog = null">Cancel</button>
                            <button type="submit" class="ak-btn ak-btn-danger-outline" data-busy-label="Reversing…">Reverse voucher</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- Attachment preview in a window of its own, like the settlement attachments --}}
        <div x-data="{ open: false, url: '', ext: '', name: '' }" x-on:ev-preview.window="url = $event.detail.url; ext = $event.detail.ext; name = $event.detail.name; open = true" x-on:keydown.escape.window="open = false" x-cloak>
            <div x-show="open" class="fixed inset-0 z-[70]" style="display: none;" role="dialog" aria-modal="true" :aria-label="name">
                <div x-show="open" x-transition.opacity class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm" @click="open = false"></div>
                <div class="fixed inset-0 z-10 flex items-center justify-center overflow-y-auto p-2 sm:p-4" @click.self="open = false">
                    <div class="ev-preview" @click.stop>
                        <div class="ev-preview-head">
                            <h3 x-text="name"></h3>
                            <a :href="url" target="_blank" rel="noopener" class="ak-btn ak-btn-ghost ak-btn-sm">Open in new tab</a>
                            <button type="button" @click="open = false" class="ak-btn ak-btn-ghost ak-btn-sm" aria-label="Close">Close</button>
                        </div>
                        <div class="ev-preview-body">
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

            // One click only: a form marked data-once-form ignores a second submit and shows its busy label.
            document.addEventListener('submit', (event) => {
                const form = event.target;
                if (! form.matches('form[data-once-form]') || event.defaultPrevented) {
                    return;
                }
                if (form.dataset.sending === '1') {
                    event.preventDefault();
                    return;
                }
                form.dataset.sending = '1';
                form.setAttribute('aria-busy', 'true');
                form.querySelectorAll('button[type=submit]').forEach((button) => {
                    button.dataset.idleLabel = button.textContent;
                    if (button.dataset.busyLabel) {
                        button.textContent = button.dataset.busyLabel;
                    }
                    setTimeout(() => { button.disabled = true; }, 0);
                });
            });
            // Coming back with the browser's back button gives working forms again.
            window.addEventListener('pageshow', (event) => {
                if (! event.persisted) {
                    return;
                }
                document.querySelectorAll('form[data-once-form][data-sending]').forEach((form) => {
                    delete form.dataset.sending;
                    form.removeAttribute('aria-busy');
                    form.querySelectorAll('button[type=submit]').forEach((button) => {
                        button.disabled = false;
                        if (button.dataset.idleLabel) {
                            button.textContent = button.dataset.idleLabel;
                        }
                    });
                });
            });
        </script>
    @endpush
</x-app-layout>
