{{--
    A4 payment voucher, the paper twin of the voucher page: document header (company, title, voucher no., date,
    status), payee and payment, the amount with its words, the particulars (one row per journal line, so a bill
    with several expense lines prints as it is), the notes paid out for cash, four signature boxes and DUPLICATE
    on a reprint. ?format=slip prints the 80mm slip instead.
--}}
@php
    use App\Models\ExpenseVoucher;
    use App\Support\AmountInWords;
    use App\Support\CashNotes;

    $title = match ($voucher->payment_method) {
        ExpenseVoucher::METHOD_BANK_TRANSFER => 'Bank Payment Voucher',
        ExpenseVoucher::METHOD_CHEQUE => 'Cheque Payment Voucher',
        ExpenseVoucher::METHOD_CREDIT => 'Credit Expense Voucher',
        default => 'Cash Payment Voucher',
    };
    $amount = (float) $voucher->amount;
    $isCash = $voucher->payment_method === ExpenseVoucher::METHOD_CASH;
    $notes = $voucher->cashPayout ? CashNotes::fromColumns($voucher->cashPayout) : null;
    $showNotes = $isCash && $notes && ! $notes->isEmpty();
    $owner = $voucher->supplier?->supplier_name ?? (($voucher->company?->company_name ?? $companyName).' (general)');
    $creditLabel = $paidFromAccount
        ? $paidFromAccount->account_name
        : ($voucher->isCredit() ? 'Expense Payables' : ($voucher->isPaidByBank() ? $voucher->bankAccount?->account_name : 'Cash'));
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
            ? collect([['code' => $voucher->expenseAccount?->account_code, 'name' => $voucher->expenseAccount?->account_name, 'narration' => $voucher->narration, 'debit' => $amount, 'credit' => 0.0]])
            : $voucher->postingLines()->map(fn ($line) => [
                'code' => $line->expenseAccount?->account_code, 'name' => $line->expenseAccount?->account_name,
                'narration' => $line->description ?: $voucher->narration, 'debit' => (float) $line->amount, 'credit' => 0.0,
            ]))->push(['code' => $paidFromAccount?->account_code, 'name' => $creditLabel, 'narration' => ($voucher->isCredit() ? 'Owed to ' : 'Paid to ').$voucher->party_name, 'debit' => 0.0, 'credit' => $amount]);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $voucher->voucher_number }} · {{ $title }}</title>
    <style>
        @page { size: A4 portrait; margin: 14mm; }
        * { box-sizing: border-box; }
        html, body { background: #fff; }
        body { margin: 0; font-family: 'Helvetica Neue', Arial, sans-serif; font-size: 12.5px; line-height: 1.45; color: #111; }
        .sheet { max-width: 182mm; margin: 0 auto; position: relative; }
        @media screen { body { background: #f1f5f9; padding: 24px 12px; } .sheet { background: #fff; padding: 14mm; box-shadow: 0 1px 4px rgba(0,0,0,.12); } }
        .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; padding-bottom: 10px; border-bottom: 2px solid #111; }
        .company { font-size: 20px; font-weight: 800; letter-spacing: .5px; text-transform: uppercase; }
        .owner { margin-top: 2px; color: #444; }
        .title { margin-top: 10px; display: inline-block; padding: 4px 12px; border: 1.5px solid #111; font-size: 14px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
        .meta { min-width: 64mm; border-collapse: collapse; }
        .meta td { padding: 3px 8px; border: 1px solid #999; }
        .meta td:first-child { width: 28mm; background: #f3f4f6; font-weight: 600; }
        .status { font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
        .status-reversed { color: #b91c1c; }
        .duplicate { position: absolute; top: 34mm; right: 0; left: 0; text-align: center; font-size: 64px; font-weight: 800; letter-spacing: 12px; color: rgba(185, 28, 28, .12); transform: rotate(-18deg); pointer-events: none; }
        .reversed-mark { top: 60mm; color: rgba(185, 28, 28, .16); }
        .reversed { margin-top: 12px; padding: 8px 12px; border: 2px solid #b91c1c; color: #7f1d1d; }
        .reversed b { letter-spacing: .1em; }
        .reversed span { display: block; }
        .duplicate-tag { display: inline-block; margin-top: 6px; padding: 2px 8px; border: 1.5px solid #b91c1c; color: #b91c1c; font-weight: 700; letter-spacing: 2px; font-size: 11px; }
        .section { margin-top: 14px; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #555; }
        table.grid { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.grid th, table.grid td { border: 1px solid #999; padding: 5px 8px; text-align: left; vertical-align: top; }
        table.grid th { background: #f3f4f6; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
        table.grid td.l { width: 30mm; background: #fafafa; font-weight: 600; }
        table.grid .n { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; width: 28mm; }
        table.grid tfoot td { font-weight: 700; background: #f3f4f6; }
        .amount { display: flex; gap: 12px; align-items: stretch; margin-top: 14px; }
        .amount .fig { min-width: 52mm; padding: 8px 12px; border: 2px solid #111; font-size: 18px; font-weight: 800; text-align: right; font-variant-numeric: tabular-nums; }
        .amount .words { flex: 1; padding: 8px 12px; border: 1px solid #999; }
        .amount .words b { display: block; font-size: 10.5px; text-transform: uppercase; letter-spacing: .05em; color: #555; }
        .amount .words span { display: block; font-weight: 600; }
        .amount .words span::first-letter { text-transform: uppercase; }
        .notes { margin-top: 4px; font-size: 11.5px; color: #333; }
        .signs { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-top: 24mm; }
        .signs div { border-top: 1px solid #111; padding-top: 4px; text-align: center; font-size: 11.5px; }
        .signs small { display: block; color: #555; min-height: 15px; }
        .foot { margin-top: 10mm; display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; font-size: 10.5px; color: #555; border-top: 1px dashed #999; padding-top: 4px; }
        .actions { max-width: 182mm; margin: 0 auto 12px; display: flex; justify-content: flex-end; gap: 8px; }
        .actions a, .actions button { font: inherit; font-size: 13px; height: 34px; display: inline-flex; align-items: center; padding: 0 14px; border: 0; border-radius: 980px; background: rgba(118,118,128,.14); color: #111; text-decoration: none; cursor: pointer; }
        .actions button { background: #0071e3; color: #fff; }
        .actions a:focus-visible, .actions button:focus-visible { outline: 3px solid rgba(0,113,227,.45); outline-offset: 2px; }
        @media print { .actions { display: none; } }
        @media screen and (max-width: 640px) {
            body { padding: 8px 0; }
            .actions { padding: 0 12px; }
            .sheet { padding: 12px; }
            .head { flex-direction: column; }
            .meta { width: 100%; min-width: 0; }
            .amount { flex-direction: column; }
            .amount .fig { min-width: 0; }
            .signs { grid-template-columns: repeat(2, 1fr); row-gap: 18mm; margin-top: 14mm; }
            table.grid .n { width: auto; }
            .scroll { overflow-x: auto; }
            .scroll table.grid { min-width: 520px; }
            table.pay, table.pay tbody { display: block; }
            table.pay tr { display: grid; grid-template-columns: minmax(0, 36%) minmax(0, 1fr); }
            table.pay td { display: block; margin: 0 0 -1px -1px; }
            table.pay td.l { width: auto; }
            table.grid td { overflow-wrap: anywhere; }
            .duplicate { font-size: 40px; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <a href="{{ route('expense-vouchers.print', ['expense_voucher' => $voucher, 'format' => 'slip', 'autoprint' => 0]) }}">80mm slip</a>
        <button type="button" onclick="window.print()">Print</button>
    </div>
    <div class="sheet">
        @if($voucher->isReversed())
            <div class="duplicate reversed-mark" aria-hidden="true">REVERSED</div>
        @elseif($isDuplicate)
            <div class="duplicate" aria-hidden="true">DUPLICATE</div>
        @endif

        <header class="head">
            <div>
                <div class="company">{{ $companyName }}</div>
                <div class="owner">{{ $owner }}</div>
                <div class="title">{{ $title }}</div>
                @if($isDuplicate)<div><span class="duplicate-tag">DUPLICATE COPY</span></div>@endif
            </div>
            <table class="meta">
                <tr><td>Voucher no.</td><td><b>{{ $voucher->voucher_number }}</b></td></tr>
                <tr><td>Date</td><td>{{ $voucher->voucher_date->format('d-M-Y') }}</td></tr>
                <tr><td>Status</td><td class="status status-{{ $voucher->status }}">{{ $voucher->statusLabel() }}</td></tr>
            </table>
        </header>

        @if($voucher->isReversed())
            <div class="reversed" role="note">
                <b>REVERSED</b> on {{ $voucher->reversed_at?->format('d-M-Y h:i A') }}{{ $voucher->reverser ? ' by '.$voucher->reverser->name : '' }}{{ $voucher->reversal_journal_entry_id ? ' · reversal journal #'.$voucher->reversal_journal_entry_id : '' }}.
                This voucher no longer counts; the ledger entry was offset.
                <span>Reason: {{ $voucher->reversal_reason }}</span>
            </div>
        @endif

        <div class="section">Payee and payment</div>
        <table class="grid pay" aria-label="Payee and payment">
            <tr><td class="l">Paid to</td><td colspan="3"><b>{{ $voucher->party_name }}</b>@if($voucher->employee) <span style="color:#555">(Employee {{ $voucher->employee->employee_code }}{{ $voucher->isAdvance() ? ', advance salary' : '' }})</span>@endif</td></tr>
            <tr><td class="l">Company</td><td>{{ $owner }}</td><td class="l">Method</td><td>{{ ExpenseVoucher::paymentMethodLabels()[$voucher->payment_method] ?? $voucher->payment_method }}</td></tr>
            @if($voucher->isPaidByBank())
                <tr><td class="l">Bank</td><td colspan="3">{{ $voucher->bankAccount?->bank_name }} · {{ $voucher->bankAccount?->account_name }}{{ $voucher->bankAccount?->account_number ? ' · A/C '.$voucher->bankAccount->account_number : '' }}</td></tr>
                @if($voucher->cheque_number || $voucher->transaction_reference)
                    <tr>
                        <td class="l">{{ $voucher->cheque_number ? 'Cheque' : 'Reference' }}</td>
                        <td colspan="3">{{ $voucher->cheque_number ? 'No. '.$voucher->cheque_number.($voucher->cheque_date ? ', dated '.$voucher->cheque_date->format('d-M-Y') : '') : $voucher->transaction_reference }}{{ $voucher->cheque_number && $voucher->transaction_reference ? ' · Ref. '.$voucher->transaction_reference : '' }}</td>
                    </tr>
                @endif
            @endif
            <tr><td class="l">Shop bill no.</td><td>{{ $voucher->vendor_bill_no ?: '—' }}{{ $voucher->vendor_bill_date ? ', dated '.$voucher->vendor_bill_date->format('d-M-Y') : '' }}</td><td class="l">Category</td><td>{{ $voucher->category?->name ?? '—' }}</td></tr>
        </table>

        <div class="amount">
            <div class="fig">Rs {{ number_format($amount, 2) }}</div>
            <div class="words"><b>Amount in words</b><span>{{ AmountInWords::rupees($amount) }}</span></div>
        </div>

        <div class="section">Particulars</div>
        <div class="scroll"><table class="grid" aria-label="Particulars">
            <thead><tr><th style="width:20mm">Code</th><th>Account</th><th>Description</th><th class="n">Debit (Rs)</th><th class="n">Credit (Rs)</th></tr></thead>
            <tbody>
                @foreach($particulars as $line)
                    <tr><td>{{ $line['code'] }}</td><td>{{ $line['name'] }}</td><td>{{ $line['narration'] ?: '—' }}</td><td class="n">{{ $line['debit'] > 0 ? number_format($line['debit'], 2) : '' }}</td><td class="n">{{ $line['credit'] > 0 ? number_format($line['credit'], 2) : '' }}</td></tr>
                @endforeach
            </tbody>
            <tfoot><tr><td colspan="3">Total</td><td class="n">{{ number_format($particulars->sum('debit'), 2) }}</td><td class="n">{{ number_format($particulars->sum('credit'), 2) }}</td></tr></tfoot>
        </table></div>

        @if($showNotes)
            <div class="section">Notes paid out</div>
            <div class="notes">
                {{ collect(CashNotes::NOTES)->filter(fn ($note) => $notes->count($note) > 0)->map(fn ($note) => number_format($note).' × '.$notes->count($note))->push($notes->coins() > 0 ? 'coins '.number_format($notes->coins(), 2) : null)->filter()->implode(', ') }}
                = Rs {{ number_format($notes->total(), 2) }}, counted and handed over by {{ $voucher->checker?->name ?? 'the checker' }}.
            </div>
        @endif

        <div class="signs">
            <div>Prepared by<small>{{ $voucher->creator?->name }}</small></div>
            <div>Checked by<small>{{ $voucher->checker?->name }}</small></div>
            <div>Approved by<small>&nbsp;</small></div>
            <div>Received by<small>&nbsp;</small></div>
        </div>

        <div class="foot">
            <span>Prepared {{ $voucher->created_at?->format('d-M-Y h:i A') }}{{ $voucher->submitted_at ? ' · submitted '.$voucher->submitted_at->format('d-M-Y h:i A') : '' }} · approved {{ $voucher->checked_at?->format('d-M-Y h:i A') }}{{ $voucher->journal_entry_id ? ' · journal #'.$voucher->journal_entry_id : '' }}</span>
            <span>{{ $isDuplicate ? 'Duplicate printed' : 'Printed' }} {{ now()->format('d-M-Y h:i A') }} by {{ auth()->user()?->name }}</span>
        </div>
    </div>
    @if($autoprint)
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</body>
</html>
