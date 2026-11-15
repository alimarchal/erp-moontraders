@php
    use App\Models\ExpenseVoucher;
    use App\Support\AmountInWords;
    use App\Support\CashNotes;

    $title = match (true) {
        $voucher->isPaidByBank() => 'Bank Payment Voucher',
        $voucher->payment_method === ExpenseVoucher::METHOD_CREDIT => 'Credit Expense Voucher',
        default => 'Cash Payment Voucher',
    };
    $amount = (float) $voucher->amount;
    $notes = $voucher->cashPayout ? CashNotes::fromColumns($voucher->cashPayout) : null;
    $owner = $voucher->supplier?->supplier_name ?? (($voucher->company?->company_name ?? $companyName).' (general)');
    $creditLabel = $paidFromAccount
        ? $paidFromAccount->account_code.' '.$paidFromAccount->account_name
        : ($voucher->isCredit() ? '2113 Expense Payables' : ($voucher->isPaidByBank() ? $voucher->bankAccount?->account_name : '1121 Cash'));
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
        .duplicate { position: absolute; top: 34mm; right: 0; left: 0; text-align: center; font-size: 64px; font-weight: 800; letter-spacing: 12px; color: rgba(185, 28, 28, .12); transform: rotate(-18deg); pointer-events: none; }
        .duplicate-tag { display: inline-block; margin-top: 6px; padding: 2px 8px; border: 1.5px solid #b91c1c; color: #b91c1c; font-weight: 700; letter-spacing: 2px; font-size: 11px; }
        table.grid { width: 100%; border-collapse: collapse; margin-top: 14px; }
        table.grid th, table.grid td { border: 1px solid #999; padding: 5px 8px; text-align: left; vertical-align: top; }
        table.grid th { background: #f3f4f6; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
        table.grid td.l { width: 34mm; background: #fafafa; font-weight: 600; }
        table.grid .n { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; width: 32mm; }
        table.grid tfoot td { font-weight: 700; background: #f3f4f6; }
        .amount { display: flex; gap: 12px; align-items: stretch; margin-top: 14px; }
        .amount .fig { min-width: 52mm; padding: 8px 12px; border: 2px solid #111; font-size: 18px; font-weight: 800; text-align: right; font-variant-numeric: tabular-nums; }
        .amount .words { flex: 1; padding: 8px 12px; border: 1px solid #999; }
        .amount .words b { display: block; font-size: 10.5px; text-transform: uppercase; letter-spacing: .05em; color: #555; }
        .amount .words span { display: block; font-weight: 600; }
        .amount .words span::first-letter { text-transform: uppercase; }
        .narration { margin-top: 14px; padding: 8px 12px; border: 1px solid #999; min-height: 18mm; }
        .narration b { display: block; font-size: 10.5px; text-transform: uppercase; letter-spacing: .05em; color: #555; }
        .notes { margin-top: 8px; font-size: 11.5px; color: #333; }
        .signs { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-top: 26mm; }
        .signs div { border-top: 1px solid #111; padding-top: 4px; text-align: center; font-size: 11.5px; }
        .signs small { display: block; color: #555; min-height: 15px; }
        .foot { margin-top: 10mm; display: flex; justify-content: space-between; font-size: 10.5px; color: #555; border-top: 1px dashed #999; padding-top: 4px; }
        .actions { max-width: 182mm; margin: 0 auto 12px; display: flex; justify-content: flex-end; gap: 8px; }
        .actions a, .actions button { font: inherit; font-size: 13px; padding: 6px 12px; border: 1px solid #94a3b8; border-radius: 6px; background: #fff; color: #111; text-decoration: none; cursor: pointer; }
        @media print { .actions { display: none; } }
        @media screen and (max-width: 640px) {
            body { padding: 8px 0; }
            .sheet { padding: 12px; }
            .head { flex-direction: column; }
            .meta { width: 100%; min-width: 0; }
            .amount { flex-direction: column; }
            .signs { grid-template-columns: repeat(2, 1fr); row-gap: 18mm; margin-top: 14mm; }
            table.grid .n { width: auto; }
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
        @if($isDuplicate)<div class="duplicate" aria-hidden="true">DUPLICATE</div>@endif

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
                <tr><td>Method</td><td>{{ ExpenseVoucher::paymentMethodLabels()[$voucher->payment_method] ?? $voucher->payment_method }}</td></tr>
                @if($voucher->vendor_bill_no)<tr><td>Bill no.</td><td>{{ $voucher->vendor_bill_no }}</td></tr>@endif
            </table>
        </header>

        <table class="grid" aria-label="Payee and payment">
            <tr><td class="l">Paid to</td><td><b>{{ $voucher->party_name }}</b>@if($voucher->employee) <span style="color:#555">(Employee {{ $voucher->employee->employee_code }}{{ $voucher->isAdvance() ? ', advance salary' : '' }})</span>@endif</td></tr>
            @if($voucher->isPaidByBank())
                <tr><td class="l">Bank</td><td>{{ $voucher->bankAccount?->bank_name }} · {{ $voucher->bankAccount?->account_name }}{{ $voucher->bankAccount?->account_number ? ' · A/C '.$voucher->bankAccount->account_number : '' }}</td></tr>
                @if($voucher->cheque_number)<tr><td class="l">Cheque</td><td>No. {{ $voucher->cheque_number }}{{ $voucher->cheque_date ? ', dated '.$voucher->cheque_date->format('d-M-Y') : '' }}</td></tr>@endif
                @if($voucher->transaction_reference)<tr><td class="l">Reference</td><td>{{ $voucher->transaction_reference }}</td></tr>@endif
            @endif
        </table>

        <table class="grid" aria-label="Accounts">
            <thead><tr><th style="width:24mm">Code</th><th>Account</th><th class="n">Debit (Rs)</th><th class="n">Credit (Rs)</th></tr></thead>
            <tbody>
                <tr><td>{{ $voucher->expenseAccount?->account_code }}</td><td>{{ $voucher->expenseAccount?->account_name }}</td><td class="n">{{ number_format($amount, 2) }}</td><td class="n"></td></tr>
                <tr><td>{{ $paidFromAccount?->account_code }}</td><td>{{ $paidFromAccount?->account_name ?? $creditLabel }}</td><td class="n"></td><td class="n">{{ number_format($amount, 2) }}</td></tr>
            </tbody>
            <tfoot><tr><td colspan="2">Total</td><td class="n">{{ number_format($amount, 2) }}</td><td class="n">{{ number_format($amount, 2) }}</td></tr></tfoot>
        </table>

        <div class="amount">
            <div class="fig">Rs {{ number_format($amount, 2) }}</div>
            <div class="words"><b>Amount in words</b><span>{{ AmountInWords::rupees($amount) }}</span></div>
        </div>

        <div class="narration">
            <b>Narration</b>
            {{ $voucher->narration ?: '—' }}
            @if($notes && ! $notes->isEmpty())
                <div class="notes">Notes paid out:
                    {{ collect(CashNotes::NOTES)->filter(fn ($note) => $notes->count($note) > 0)->map(fn ($note) => number_format($note).' × '.$notes->count($note))->push($notes->coins() > 0 ? 'coins '.number_format($notes->coins(), 2) : null)->filter()->implode(', ') }}
                </div>
            @endif
        </div>

        <div class="signs">
            <div>Prepared by<small>{{ $voucher->creator?->name }}</small></div>
            <div>Checked by<small>{{ $voucher->checker?->name }}</small></div>
            <div>Approved by<small>&nbsp;</small></div>
            <div>Received by<small>&nbsp;</small></div>
        </div>

        <div class="foot">
            <span>Approved {{ $voucher->checked_at?->format('d-M-Y h:i A') }}</span>
            <span>{{ $isDuplicate ? 'Duplicate printed' : 'Printed' }} {{ now()->format('d-M-Y h:i A') }} by {{ auth()->user()?->name }}</span>
        </div>
    </div>
    @if($autoprint)
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</body>
</html>
