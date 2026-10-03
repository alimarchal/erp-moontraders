{{--
    Print 2: an alternative, purpose-built printable sheet for a sales settlement (A4, portrait or
    landscape, black-and-white friendly). Same figures as the classic sheet on the show page.
    Opened with ?layout=print2 on the settlement show route; ?format=pdf&orientation=portrait|landscape
    downloads the same sheet as a PDF. Layout uses tables only so the PDF engine renders it too.
--}}
@php
    $netSale = (float) $settlement->items->sum('total_sales_value');
    $creditSalesAmount = (float) ($settlement->credit_sales_amount ?? 0);
    $bankSalesAmount = (float) ($settlement->bank_transfer_amount ?? 0);
    $cashDenominations = $settlement->cashDenominations->first();
    $cashDenominationTotal = (float) ($cashDenominations?->total_amount ?? 0.0);
    $coins = (float) ($cashDenominations?->denom_coins ?? 0.0);

    $recoveryCash = (float) $settlement->recoveries->where('payment_method', 'cash')->sum('amount');
    $recoveryBank = (float) $settlement->recoveries->where('payment_method', 'bank_transfer')->sum('amount');
    $recoveryTotal = (float) ($settlement->credit_recoveries ?? 0);

    $usesAdvanceTaxIncome = (bool) ($settlement->supplier?->is_advance_tax_income ?? false);
    $totalExpenses = (float) ($settlement->expenses->sum('amount') ?? 0);
    $advanceTaxEntries = $usesAdvanceTaxIncome ? $settlement->advanceTaxIncomes : $settlement->advanceTaxes;
    $advanceTaxTotal = (float) ($advanceTaxEntries->sum('tax_amount') ?? 0);
    $advanceTaxIncomeTotal = $usesAdvanceTaxIncome ? $advanceTaxTotal : 0.0;

    $chequesTotal = (float) $settlement->cheques->sum('amount');
    $theoreticalCashSales = $netSale - $creditSalesAmount - $bankSalesAmount;
    $isPaymentBreakdownExceeded = $theoreticalCashSales < 0;
    $expectedCashGross = $theoreticalCashSales + $recoveryCash + $advanceTaxIncomeTotal;
    $expectedCashNet = $expectedCashGross - $totalExpenses;

    $actualPhysicalCash = $cashDenominationTotal > 0 ? $cashDenominationTotal : (float) $settlement->cash_collected;
    $bankSlipsTotal = (float) $settlement->bankSlips->sum('amount');
    $submittedTotal = $actualPhysicalCash + $chequesTotal + $bankSlipsTotal;
    $shortExcess = $submittedTotal - $expectedCashNet;
    $roundedShortExcess = round($shortExcess, 2);

    $totalCOGS = (float) ($settlement->items->sum('total_cogs') ?? 0);
    $grossProfit = $netSale - $totalCOGS;
    $grossMargin = $netSale > 0 ? ($grossProfit / $netSale) * 100 : 0;
    $netProfit = $grossProfit - $totalExpenses;
    $netMargin = $netSale > 0 ? ($netProfit / $netSale) * 100 : 0;

    $valueTotals = ['issued_qty' => 0, 'issued_value' => 0, 'sold_qty' => 0, 'sold_value' => 0, 'returned_qty' => 0, 'returned_value' => 0, 'shortage_qty' => 0, 'shortage_value' => 0];
    foreach ($settlement->items as $item) {
        $priceFallback = (float) ($item->unit_selling_price > 0 ? $item->unit_selling_price : $item->unit_cost);
        $lines = $item->batches->count() > 0
            ? $item->batches->map(fn ($batch) => [(float) ($batch->selling_price ?? $priceFallback), $batch])
            : collect([[$priceFallback, $item]]);
        foreach ($lines as [$price, $line]) {
            foreach (['issued', 'sold', 'returned', 'shortage'] as $key) {
                $qty = (float) $line->{'quantity_'.$key};
                $valueTotals[$key.'_qty'] += $qty;
                $valueTotals[$key.'_value'] += $qty * $price;
            }
        }
    }
    $bfOutValue = $valueTotals['issued_value'] - $valueTotals['sold_value'] - $valueTotals['returned_value'] - $valueTotals['shortage_value'];

    // Group expenses (same rows and order as the classic sheet)
    $predefinedExpenseCodes = ['5272', '5252', '5262', '5292', '1161', '5282', '5223', '5221'];
    $predefinedAccountsMap = \App\Models\ChartOfAccount::whereIn('account_code', $predefinedExpenseCodes)->pluck('id', 'account_code')->toArray();
    $predefinedExpenses = [
        ['code' => '5272', 'label' => 'Toll Tax'],
        ['code' => '5252', 'label' => 'AMR Powder'],
        ['code' => '5262', 'label' => 'AMR Liquid'],
        ['code' => '5292', 'label' => 'Scheme Discount Expense'],
        ['code' => '1161', 'label' => 'Advance Tax'],
        ['code' => '5282', 'label' => 'Food/Salesman/Loader Charges'],
        ['code' => '5223', 'label' => 'Percentage Expense'],
        ['code' => '5221', 'label' => 'Miscellaneous Expenses'],
    ];
    $predefinedIds = collect($predefinedExpenseCodes)->map(fn ($code) => $predefinedAccountsMap[$code] ?? null)->filter()->toArray();
    $savedExpenseAmounts = $settlement->expenses->keyBy('expense_account_id');
    $groupExpenseRows = [];
    foreach ($predefinedExpenses as $predef) {
        $accountId = $predefinedAccountsMap[$predef['code']] ?? null;
        $amount = (float) ($savedExpenseAmounts->get($accountId)?->amount ?? 0);
        $label = $predef['label'];
        if ($usesAdvanceTaxIncome && $predef['code'] === '1161') {
            $amount = $advanceTaxIncomeTotal;
            $label .= ' - Income';
        }
        $groupExpenseRows[] = ['label' => $label, 'code' => $predef['code'], 'amount' => $amount];
    }
    foreach ($settlement->expenses->reject(fn ($expense) => in_array($expense->expense_account_id, $predefinedIds)) as $expense) {
        $groupExpenseRows[] = ['label' => $expense->expenseAccount->account_name ?? 'Unknown', 'code' => $expense->expenseAccount->account_code ?? '-', 'amount' => (float) $expense->amount];
    }

    // Cash notes
    $cashDetailRows = [];
    $calculatedCash = 0;
    foreach ([5000, 1000, 500, 100, 50, 20, 10] as $note) {
        $qty = (int) ($cashDenominations?->{'denom_'.$note} ?? 0);
        $cashDetailRows[] = ['label' => number_format($note), 'qty' => $qty, 'value' => $qty * $note];
        $calculatedCash += $qty * $note;
    }
    $calculatedCash += $coins;

    // Credit report (same rules as the classic sheet)
    $settlementDate = $settlement->settlement_date;
    $customerAccounts = \App\Models\CustomerEmployeeAccount::with('customer')
        ->where('employee_id', $settlement->employee_id)
        ->where('status', 'active')
        ->get();
    $settlementCredits = $settlement->creditSales->groupBy('customer_id');
    $settlementRecoveries = $settlement->recoveries->groupBy('customer_id');
    $isPosted = $settlement->status === 'posted';
    $balanceMap = \App\Models\CustomerEmployeeAccountTransaction::query()
        ->whereIn('customer_employee_account_id', $customerAccounts->pluck('id'))
        ->whereDate('transaction_date', '<=', $settlementDate)
        ->groupBy('customer_employee_account_id')
        ->selectRaw('customer_employee_account_id, SUM(debit - credit) as balance')
        ->pluck('balance', 'customer_employee_account_id');
    $creditReportData = $customerAccounts->map(function ($account) use ($settlementCredits, $settlementRecoveries, $isPosted, $balanceMap) {
        $creditAmount = (float) ($settlementCredits->get($account->customer_id)?->sum('sale_amount') ?? 0);
        $recoveryAmount = (float) ($settlementRecoveries->get($account->customer_id)?->sum('amount') ?? 0);
        if ($isPosted) {
            $closingBalance = (float) ($balanceMap[$account->id] ?? 0);
            $openingBalance = $closingBalance - $creditAmount + $recoveryAmount;
        } else {
            $openingBalance = (float) ($balanceMap[$account->id] ?? 0);
            $closingBalance = $openingBalance + $creditAmount - $recoveryAmount;
        }

        return (object) [
            'customer_name' => $account->customer->customer_name,
            'customer_code' => $account->customer->customer_code,
            'address' => $account->customer->address,
            'opening_balance' => $openingBalance,
            'credit_amount' => $creditAmount,
            'recovery_amount' => $recoveryAmount,
            'balance' => $closingBalance,
            'has_activity' => $creditAmount > 0 || $recoveryAmount > 0 || abs($closingBalance) > 1,
        ];
    })->filter(fn ($row) => $row->has_activity)->sortBy('customer_name')->values();

    $money = fn ($value, $decimals = 2) => number_format((float) $value, $decimals);
    $supplierName = $settlement->supplier?->supplier_name ?? $settlement->goodsIssue?->supplier?->supplier_name ?? '-';

    $isPdf = (bool) ($isPdf ?? false);
    $orientation = ($orientation ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
    $sectionNo = 0;
    $pdfUrl = fn (string $orientation) => route('sales-settlements.show', [$settlement, 'layout' => 'print2', 'format' => 'pdf', 'orientation' => $orientation]);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $settlement->settlement_number }} - Settlement sheet</title>
    <style>
        /* No page size in the browser: Chrome only shows the Layout (Portrait / Landscape) choice
           when the page does not fix a size. Choose A4 under "Paper size" in the dialog. The PDF is always A4. */
        @page {
            @if ($isPdf) size: A4 {{ $orientation }}; @endif
            margin: {{ $isPdf ? '8mm 8mm 13mm 8mm' : '8mm 8mm 12mm 8mm' }};
            @unless ($isPdf)
            @bottom-left { content: "{{ $settlement->settlement_number }} - {{ $settlement->employee->name ?? '' }} - {{ $settlement->settlement_date?->format('d-M-Y') }}"; font: 8px Arial, sans-serif; color: #000; }
            @bottom-right { content: "Page " counter(page) " of " counter(pages); font: 8px Arial, sans-serif; color: #000; }
            @endunless
        }
        * { box-sizing: border-box; }
        html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { margin: 0; background: {{ $isPdf ? '#fff' : '#e2e8f0' }}; color: #000; font-family: {{ $isPdf ? 'Helvetica, Arial, sans-serif' : 'Arial, Helvetica, sans-serif' }}; font-size: 10px; line-height: 1.3; }

        /* Screen toolbar */
        .p2-bar { position: sticky; top: 0; z-index: 5; padding: 10px 16px; background: #0f172a; color: #fff; font-size: 13px; text-align: center; }
        .p2-bar span { float: left; color: #94a3b8; padding-top: 6px; }
        .p2-bar a, .p2-bar button { display: inline-block; margin: 0 3px; padding: 6px 14px; border-radius: 8px; border: 1px solid #334155; background: #1e293b; color: #fff; font: inherit; font-weight: 600; text-decoration: none; cursor: pointer; }
        .p2-bar .is-main { background: #2563eb; border-color: #2563eb; }
        .p2-bar .hint { display: block; margin-top: 6px; font-size: 11.5px; color: #94a3b8; }
        .p2-paper { width: 210mm; margin: 18px auto; padding: 8mm; background: #fff; box-shadow: 0 1px 3px rgba(15,23,42,.08), 0 12px 32px -8px rgba(15,23,42,.25); }
        body.is-landscape .p2-paper { width: 297mm; }

        /* Layout tables (used instead of grid/flex so the same sheet renders in the PDF engine) */
        table.lay { width: 100%; border-collapse: collapse; }
        table.lay > tbody > tr > td, table.lay > tr > td { padding: 0; vertical-align: top; }
        table.lay > tbody > tr > td.gap, table.lay > tr > td.gap { width: 10px; min-width: 10px; padding: 0 5px; }

        .p2-head td { vertical-align: bottom; }
        .p2-head { border-bottom: 2.5px solid #000; padding-bottom: 4px; }
        .p2-title { font-size: 18px; font-weight: bold; letter-spacing: .5px; }
        .p2-subtitle { font-size: 11.5px; font-weight: bold; }
        .p2-no { text-align: right; font-size: 15px; font-weight: bold; }
        .p2-status { display: inline-block; margin-left: 4px; padding: 0 6px; border: 1px solid #000; border-radius: 8px; font-size: 9px; font-weight: bold; text-transform: uppercase; }

        table.p2-meta { width: 100%; border-collapse: collapse; margin: 4px 0 7px; font-size: 10px; }
        table.p2-meta td { padding: 2px 6px 2px 0; border-bottom: 1px dotted #000; width: 33.33%; }
        table.p2-meta b { white-space: nowrap; }

        table.p2-kpis { width: 100%; border-collapse: collapse; border: 2px solid #000; margin-bottom: 7px; page-break-inside: avoid; }
        table.p2-kpis td { width: 25%; padding: 3px 6px; border: 1px solid #000; }
        table.p2-kpis small { display: block; font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: .3px; }
        table.p2-kpis b { font-size: 12.5px; }
        table.p2-kpis td.bad { background: #e6e6e6; }

        .p2-sec { margin-top: 9px; }
        .p2-h2 { margin: 0 0 3px; padding: 3px 2px 2px; border-top: 2.5px solid #000; border-bottom: 1px solid #000; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: .3px; page-break-after: avoid; break-after: avoid; }
        .p2-h2 small, .p2-h3 small { float: right; font-weight: bold; text-transform: none; letter-spacing: 0; font-size: 9px; padding-top: 1px; }
        .p2-h3 { margin: 0; padding: 2px 5px; border: 1px solid #000; border-bottom: 0; font-size: 9.5px; font-weight: bold; text-transform: uppercase; letter-spacing: .3px; page-break-after: avoid; break-after: avoid; }
        tr.cap th { background: #fff; padding: 3px 2px 2px; border-left: 0; border-right: 0; border-top: 2.5px solid #000; border-bottom: 1px solid #000; font-size: 11px; letter-spacing: .3px; text-align: left; }
        tr.cap th small { float: right; text-transform: none; letter-spacing: 0; font-size: 9px; }
        table.p2.capped { margin-top: -1px; }
        .p2-keep { page-break-inside: avoid; break-inside: avoid; }
        .mt { margin-top: 6px; }

        table.p2 { width: 100%; border-collapse: collapse; font-size: 9.5px; line-height: 1.25; font-variant-numeric: tabular-nums; }
        table.p2 th, table.p2 td { border: 0.75pt solid #000; padding: 2px 4px; vertical-align: top; }
        table.p2 thead { display: table-header-group; }
        table.p2 th { background: #e6e6e6; font-size: 8.5px; text-transform: uppercase; letter-spacing: .2px; text-align: left; white-space: nowrap; }
        table.p2 tr { page-break-inside: avoid; break-inside: avoid; }
        table.p2 tr.sum td { font-weight: bold; border-top: 2.5px double #000; }
        table.p2 tr.tot td { border-top: 1.5px solid #000; font-weight: bold; }
        table.p2 tr.hl td { background: #e6e6e6; font-weight: bold; border-top: 1.5px solid #000; border-bottom: 1.5px solid #000; }
        table.p2 .n { text-align: right; white-space: nowrap; }
        table.p2 th.n { text-align: right; }
        table.p2 .c { text-align: center; white-space: nowrap; }
        table.p2 th.c { text-align: center; }
        table.p2 .i { width: 1%; text-align: center; white-space: nowrap; }
        table.p2 .b { font-weight: bold; }
        table.p2 .muted { color: #444; }
        table.p2 .basis { font-size: 8.5px; color: #222; }
        table.p2 .batch { display: block; font-size: 8.5px; white-space: nowrap; }
        .p2-none { margin: 0; padding: 3px 6px; border: 1px dashed #000; font-style: italic; font-size: 9px; }
        .p2-nonelist { margin-top: 4px; font-size: 9px; font-style: italic; }
        .p2-warn { margin: 6px 0; padding: 4px 6px; border: 2px solid #000; font-weight: bold; }
        .p2-notes { margin-top: 8px; padding: 4px 6px; border: 1px solid #000; page-break-inside: avoid; }
        .p2-foot { margin-top: 4px; font-size: 8.5px; }
        table.p2-sign { width: 100%; margin-top: 34px; border-collapse: separate; border-spacing: 28px 0; page-break-inside: avoid; }
        table.p2-sign td { width: 33.33%; border-top: 1px solid #000; padding-top: 4px; text-align: center; font-weight: bold; font-size: 10px; }

        /* PDF footer (the PDF engine has no @page margin boxes) */
        .pdf-footer { position: fixed; bottom: -9mm; left: 0; right: 0; font-size: 8px; }

        @if ($isPdf)
        .p2-paper, body.is-landscape .p2-paper { width: auto; margin: 0; padding: 0; box-shadow: none; }
        @endif
        @media screen and (max-width: 820px) { .p2-paper, body.is-landscape .p2-paper { width: auto; margin: 0; padding: 12px; } }
        @media print {
            body { background: #fff; }
            .p2-bar { display: none; }
            .p2-paper, body.is-landscape .p2-paper { width: auto; margin: 0; padding: 0; box-shadow: none; }
            a { color: inherit; text-decoration: none; }
        }
    </style>
</head>
<body class="{{ $orientation === 'landscape' ? 'is-landscape' : '' }}">
    @if ($isPdf)
        <table class="lay pdf-footer"><tr>
            <td>{{ $settlement->settlement_number }} - {{ $settlement->employee->name ?? '' }} - {{ $settlement->settlement_date?->format('d-M-Y') }}</td>
        </tr></table>
    @else
        <div class="p2-bar">
            <span>Print 2 - settlement sheet</span>
            <a href="{{ route('sales-settlements.show', $settlement) }}">&larr; Back</a>
            <button type="button" class="is-main" onclick="window.print()">Print</button>
            <a href="{{ $pdfUrl('portrait') }}">Download PDF (Portrait)</a>
            <a href="{{ $pdfUrl('landscape') }}">Download PDF (Landscape)</a>
            <a href="{{ route('sales-settlements.show', [$settlement, 'layout' => 'print2', 'orientation' => $orientation === 'landscape' ? 'portrait' : 'landscape']) }}">Preview {{ $orientation === 'landscape' ? 'portrait' : 'landscape' }}</a>
            <small class="hint">In the print dialog choose Paper size A4 and Layout Portrait or Landscape; "Save as PDF" there also works.</small>
        </div>
    @endif

    <main class="p2-paper">
        {{-- Header --}}
        <table class="lay p2-head"><tr>
            <td>
                <div class="p2-title">MOON TRADERS</div>
                <div class="p2-subtitle">Sales Settlement Sheet <span class="p2-status">{{ $settlement->status }}</span></div>
            </td>
            <td class="p2-no">{{ $settlement->settlement_number }}<div class="p2-subtitle">{{ $settlement->settlement_date?->format('l, d M Y') }}</div></td>
        </tr></table>

        <table class="p2-meta">
            <tr>
                <td><b>Salesman:</b> {{ $settlement->employee->name ?? '-' }}</td>
                <td><b>Vehicle:</b> {{ $settlement->vehicle->registration_number ?? '-' }}</td>
                <td><b>Warehouse:</b> {{ $settlement->warehouse->warehouse_name ?? '-' }}</td>
            </tr>
            <tr>
                <td><b>Supplier:</b> {{ $supplierName }}</td>
                <td><b>Goods Issue:</b> {{ $settlement->goodsIssue->issue_number ?? '-' }}</td>
                <td><b>GI Date:</b> {{ $settlement->goodsIssue?->issue_date ? \Carbon\Carbon::parse($settlement->goodsIssue->issue_date)->format('d-M-Y') : '' }} {{ $settlement->goodsIssue?->created_at?->format('h:i A') }}</td>
            </tr>
            <tr>
                <td><b>GI Issued By:</b> {{ $settlement->goodsIssue?->creator->name ?? $settlement->goodsIssue?->issuedBy->name ?? '-' }}</td>
                <td><b>Created By:</b> {{ $settlement->creator->name ?? '-' }} {{ $settlement->created_at?->format('h:i A') }}</td>
                <td><b>Posted:</b> {{ $settlement->posted_at ? $settlement->posted_at->format('d-M-Y h:i A') : 'Not posted' }}{{ $settlement->journalEntry ? ' - JE #'.$settlement->journalEntry->id : '' }}</td>
            </tr>
        </table>

        {{-- Key figures --}}
        <table class="p2-kpis">
            <tr>
                <td><small>Net sale (sold value)</small><b>{{ $money($netSale) }}</b></td>
                <td><small>Credit given</small><b>{{ $money($creditSalesAmount) }}</b></td>
                <td><small>Recoveries (cash + bank)</small><b>{{ $money($recoveryTotal) }}</b></td>
                <td><small>Expenses</small><b>{{ $money($totalExpenses) }}</b></td>
            </tr>
            <tr>
                <td><small>Expected cash (after expenses)</small><b>{{ $money($expectedCashNet) }}</b></td>
                <td><small>Submitted (cash + cheques + slips)</small><b>{{ $money($submittedTotal) }}</b></td>
                <td class="{{ $roundedShortExcess < 0 ? 'bad' : '' }}"><small>Short / Excess</small><b>{{ $roundedShortExcess == 0 ? 'Balanced' : ($roundedShortExcess < 0 ? 'SHORT ' : 'EXCESS ').$money(abs($roundedShortExcess)) }}</b></td>
                <td><small>Gross profit</small><b>{{ $money($grossProfit) }}</b> ({{ number_format($grossMargin, 1) }}%)</td>
            </tr>
        </table>

        @if ($isPaymentBreakdownExceeded)
            <p class="p2-warn">Warning: payment breakdown exceeds Net Sale by Rs {{ $money(abs($theoreticalCashSales)) }}.</p>
        @endif

        {{-- Stock & sales --}}
        <section class="p2-sec">
            @php $secNo0 = ++$sectionNo; @endphp
            <table class="p2">
                <thead><tr class="cap"><th colspan="10">{{ $secNo0 }} - Stock &amp; Sales <small>{{ $settlement->items->count() }} products</small></th></tr>
                    <tr>
                        <th class="i">#</th><th style="width:21%">Product</th><th class="n">B/F In</th><th class="n">Issued</th>
                        <th>Batch: qty x TP = value (code)</th>
                        <th class="n">Sold</th><th class="n">Return</th><th class="n">Short</th><th class="n">B/F Out</th><th class="n">Sales value</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($settlement->items as $index => $item)
                        @php
                            $bfIn = (float) ($bfMap[$item->product_id] ?? 0);
                            $bfOut = $bfIn + $item->quantity_issued - $item->quantity_sold - $item->quantity_returned - $item->quantity_shortage;
                        @endphp
                        <tr>
                            <td class="i">{{ $index + 1 }}</td>
                            <td class="b">{{ $item->product->product_name }}</td>
                            <td class="n">{{ $money($bfIn) }}</td>
                            <td class="n">{{ $money($item->quantity_issued) }}</td>
                            <td>
                                @forelse ($item->batches as $b)
                                    <span class="batch">{{ number_format($b->quantity_issued, 0) }} x {{ $money($b->selling_price) }}@if ($b->is_promotional) (Promo)@endif = {{ $money($b->quantity_issued * $b->selling_price) }} ({{ $b->batch_code ?? 'N/A' }})</span>
                                @empty
                                    <span class="muted">No batch data</span>
                                @endforelse
                            </td>
                            <td class="n b">{{ $money($item->quantity_sold) }}</td>
                            <td class="n">{{ $money($item->quantity_returned) }}</td>
                            <td class="n {{ $item->quantity_shortage > 0 ? 'b' : '' }}">{{ $money($item->quantity_shortage) }}</td>
                            <td class="n">{{ $money($bfOut) }}</td>
                            <td class="n b">{{ $money($item->total_sales_value) }}</td>
                        </tr>
                    @endforeach
                    <tr class="sum">
                        <td colspan="3" class="n">Totals (qty)</td>
                        <td class="n">{{ $money($settlement->items->sum('quantity_issued')) }}</td>
                        <td></td>
                        <td class="n">{{ $money($settlement->total_quantity_sold) }}</td>
                        <td class="n">{{ $money($settlement->total_quantity_returned) }}</td>
                        <td class="n">{{ $money($settlement->total_quantity_shortage) }}</td>
                        <td class="n">-</td>
                        <td class="n">{{ $money($netSale) }}</td>
                    </tr>
                    <tr class="tot">
                        <td colspan="3" class="n">Value (Rs)</td>
                        <td class="n">{{ $money($valueTotals['issued_value']) }}</td>
                        <td></td>
                        <td class="n">{{ $money($valueTotals['sold_value']) }}</td>
                        <td class="n">{{ $money($valueTotals['returned_value']) }}</td>
                        <td class="n">{{ $money($valueTotals['shortage_value']) }}</td>
                        <td class="n">{{ $money($bfOutValue) }}</td>
                        <td class="n">{{ $money($valueTotals['sold_value']) }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        {{-- Credit sales --}}
        <section class="p2-sec">
            @php $secNo1 = ++$sectionNo; @endphp
                <table class="p2">
                    <thead><tr class="cap"><th colspan="6">{{ $secNo1 }} - Credit Sales <small>{{ $settlement->creditSales->count() }} customers - Rs {{ $money($settlement->creditSales->sum('sale_amount')) }}</small></th></tr><tr><th class="i">#</th><th>Customer</th><th>Address</th><th class="n">Prev. balance</th><th class="n">Credit sale</th><th class="n">New balance</th></tr></thead>
                    <tbody>
                        @forelse ($settlement->creditSales as $i => $creditSale)
                            <tr>
                                <td class="i">{{ $i + 1 }}</td>
                                <td class="b">{{ $creditSale->customer->customer_name ?? '-' }}</td>
                                <td>{{ $creditSale->customer->address ?? '-' }}</td>
                                <td class="n">{{ $money($creditSale->previous_balance) }}</td>
                                <td class="n b">{{ $money($creditSale->sale_amount) }}</td>
                                <td class="n">{{ $money($creditSale->new_balance) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="c muted">No credit sales recorded.</td></tr>
                        @endforelse
                        <tr class="sum"><td colspan="4" class="n">Total credit sales</td><td class="n">{{ $money($settlement->creditSales->sum('sale_amount')) }}</td><td></td></tr>
                    </tbody>
                </table>
        </section>

        {{-- Recoveries --}}
        <section class="p2-sec">
            @php $secNo2 = ++$sectionNo; @endphp
                <table class="p2">
                    <thead><tr class="cap"><th colspan="7">{{ $secNo2 }} - Recoveries <small>{{ $settlement->recoveries->count() }} - cash {{ $money($recoveryCash) }} - bank {{ $money($recoveryBank) }}</small></th></tr><tr><th class="i">#</th><th>Customer</th><th>Address</th><th class="n">Prev. balance</th><th class="n">Recovered</th><th class="c">Method</th><th class="n">New balance</th></tr></thead>
                    <tbody>
                        @forelse ($settlement->recoveries as $i => $recovery)
                            <tr>
                                <td class="i">{{ $i + 1 }}</td>
                                <td class="b">{{ $recovery->customer->customer_name ?? '-' }}</td>
                                <td>{{ $recovery->customer->address ?? '-' }}</td>
                                <td class="n">{{ $money($recovery->previous_balance) }}</td>
                                <td class="n b">{{ $money($recovery->amount) }}</td>
                                <td class="c">{{ $recovery->payment_method === 'cash' ? 'Cash' : 'Bank' }}</td>
                                <td class="n">{{ $money($recovery->new_balance) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="c muted">No recoveries recorded.</td></tr>
                        @endforelse
                        <tr class="sum"><td colspan="4" class="n">Total recoveries</td><td class="n">{{ $money($settlement->recoveries->sum('amount')) }}</td><td colspan="2"></td></tr>
                    </tbody>
                </table>
        </section>

        {{-- Cheques and bank transfers --}}
            <section class="p2-sec">
                <div class="p2-h2">{{ ++$sectionNo }} - Cheques &amp; Bank Transfers</div>
                    <div class="p2-h3">Cheque payments <small>Rs {{ $money($chequesTotal) }}</small></div>
                    <table class="p2">
                        <thead><tr><th class="i">#</th><th class="c">Date</th><th>Cheque #</th><th>Customer</th><th>Bank</th><th>Deposit bank</th><th class="n">Amount</th></tr></thead>
                        <tbody>
                            @forelse ($settlement->cheques as $i => $cheque)
                                <tr>
                                    <td class="i">{{ $i + 1 }}</td>
                                    <td class="c">{{ $cheque->cheque_date ? \Carbon\Carbon::parse($cheque->cheque_date)->format('d-M-y') : '-' }}</td>
                                    <td>{{ $cheque->cheque_number ?? '-' }}</td>
                                    <td>{{ $cheque->customer->customer_name ?? '-' }}</td>
                                    <td>{{ $cheque->bank_name ?? '-' }}</td>
                                    <td>{{ $cheque->bankAccount->account_name ?? '-' }}</td>
                                    <td class="n b">{{ $money($cheque->amount) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="c muted">No cheques recorded.</td></tr>
                            @endforelse
                            <tr class="sum"><td colspan="6" class="n">Total cheques</td><td class="n">{{ $money($chequesTotal) }}</td></tr>
                        </tbody>
                    </table>
                    <div class="p2-h3 mt">Bank transfers / online from customers <small>Rs {{ $money($settlement->bankTransfers->sum('amount')) }}</small></div>
                    <table class="p2">
                        <thead><tr><th class="i">#</th><th class="c">Date</th><th>Customer</th><th>Bank account</th><th>Ref #</th><th class="n">Amount</th></tr></thead>
                        <tbody>
                            @forelse ($settlement->bankTransfers as $i => $transfer)
                                <tr>
                                    <td class="i">{{ $i + 1 }}</td>
                                    <td class="c">{{ $transfer->transfer_date ? \Carbon\Carbon::parse($transfer->transfer_date)->format('d-M-y') : '-' }}</td>
                                    <td>{{ $transfer->customer->customer_name ?? '-' }}</td>
                                    <td>{{ $transfer->bankAccount->account_name ?? '-' }}</td>
                                    <td>{{ $transfer->reference_number ?? '-' }}</td>
                                    <td class="n b">{{ $money($transfer->amount) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="c muted">No bank transfers recorded.</td></tr>
                            @endforelse
                            <tr class="sum"><td colspan="5" class="n">Total bank transfers</td><td class="n">{{ $money($settlement->bankTransfers->sum('amount')) }}</td></tr>
                        </tbody>
                    </table>
            </section>

        {{-- Expense details --}}
        @php
            $expenseDetailTables = [
                ['title' => 'AMR Powder (5252)', 'rows' => $settlement->amrPowders, 'kind' => 'product'],
                ['title' => 'AMR Liquid (5262)', 'rows' => $settlement->amrLiquids, 'kind' => 'product'],
                ['title' => 'Advance Tax'.($usesAdvanceTaxIncome ? ' - Income' : '').' to NTN customers (1161)', 'rows' => $advanceTaxEntries, 'kind' => 'tax'],
                ['title' => 'Percentage Expense (5223)', 'rows' => $settlement->percentageExpenses, 'kind' => 'pct'],
            ];
            $productDetailTables = array_values(array_filter($expenseDetailTables, fn ($t) => $t['kind'] === 'product'));
            $customerDetailTables = array_values(array_filter($expenseDetailTables, fn ($t) => $t['kind'] !== 'product'));
        @endphp
        <section class="p2-sec">
            <div class="p2-h2">{{ ++$sectionNo }} - Expense Details <small>total expenses Rs {{ $money($totalExpenses) }}</small></div>
            @if ($productDetailTables)
                <table class="lay"><tr>
                    @foreach ($productDetailTables as $detail)
                        @if (! $loop->first)<td class="gap"></td>@endif
                        <td style="width:{{ count($productDetailTables) > 1 ? '50%' : '100%' }}">
                            <div class="p2-h3">{{ $detail['title'] }} <small>{{ $detail['rows']->count() }} items - Rs {{ $money($detail['rows']->sum('amount')) }}</small></div>
                            <table class="p2">
                                <thead><tr><th class="i">#</th><th>Product</th><th class="n">Qty</th><th class="n">Amount</th></tr></thead>
                                <tbody>
                                    @forelse ($detail['rows'] as $i => $row)
                                        <tr>
                                            <td class="i">{{ $i + 1 }}</td>
                                            <td>{{ $row->product->product_name ?? '-' }}</td>
                                            <td class="n">{{ $money($row->quantity) }}</td>
                                            <td class="n b">{{ $money($row->amount) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="c muted">None recorded.</td></tr>
                                    @endforelse
                                    <tr class="sum"><td colspan="3" class="n">Total</td><td class="n">{{ $money($detail['rows']->sum('amount')) }}</td></tr>
                                </tbody>
                            </table>
                        </td>
                    @endforeach
                </tr></table>
            @endif
            @foreach ($customerDetailTables as $detail)
                @php $amountField = $detail['kind'] === 'tax' ? 'tax_amount' : 'amount'; @endphp
                <div class="p2-h3 mt">{{ $detail['title'] }} <small>{{ $detail['rows']->count() }} customers - Rs {{ $money($detail['rows']->sum($amountField)) }}</small></div>
                <table class="p2">
                    <thead><tr><th class="i">#</th><th>Customer</th><th class="c">Code</th><th class="c">Invoice #</th><th class="n">{{ $detail['kind'] === 'tax' ? 'Tax' : 'Amount' }}</th></tr></thead>
                    <tbody>
                        @forelse ($detail['rows'] as $i => $row)
                            <tr>
                                <td class="i">{{ $i + 1 }}</td>
                                <td>{{ $row->customer->customer_name ?? '-' }}</td>
                                <td class="c">{{ $row->customer->customer_code ?? '' }}</td>
                                <td class="c">{{ $row->invoice_number ?? '-' }}</td>
                                <td class="n b">{{ $money($row->{$amountField}) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="c muted">None recorded.</td></tr>
                        @endforelse
                        <tr class="sum"><td colspan="4" class="n">Total</td><td class="n">{{ $money($detail['rows']->sum($amountField)) }}</td></tr>
                    </tbody>
                </table>
            @endforeach
        </section>

        {{-- Expenses, cash and deposits --}}
        <section class="p2-sec p2-keep">
            <div class="p2-h2">{{ ++$sectionNo }} - Expenses, Cash &amp; Bank Deposits</div>
            <table class="lay"><tr>
                <td style="width:50%">
                    <div class="p2-h3">Group expenses <small>paid by salesman from cash</small></div>
                    <table class="p2">
                        <thead><tr><th class="i">#</th><th>Expense account</th><th class="c">COA</th><th class="n">Amount</th></tr></thead>
                        <tbody>
                            @foreach ($groupExpenseRows as $i => $row)
                                <tr>
                                    <td class="i">{{ $i + 1 }}</td>
                                    <td>{{ $row['label'] }}</td>
                                    <td class="c">{{ $row['code'] }}</td>
                                    <td class="n {{ $row['amount'] > 0 ? 'b' : 'muted' }}">{{ $money($row['amount']) }}</td>
                                </tr>
                            @endforeach
                            <tr class="sum"><td colspan="3" class="n">Total expenses</td><td class="n">{{ $money($totalExpenses) }}</td></tr>
                        </tbody>
                    </table>
                    <div class="p2-h3 mt">Cash check</div>
                    <table class="p2">
                        <tbody>
                            <tr><td>Expected cash (after expenses)</td><td class="n b">{{ $money($expectedCashNet) }}</td></tr>
                            <tr><td>Physical cash</td><td class="n">{{ $money($actualPhysicalCash) }}</td></tr>
                            <tr><td>Cheques</td><td class="n">{{ $money($chequesTotal) }}</td></tr>
                            <tr><td>Bank slips</td><td class="n">{{ $money($bankSlipsTotal) }}</td></tr>
                            <tr class="tot"><td>Total submitted</td><td class="n">{{ $money($submittedTotal) }}</td></tr>
                            <tr class="hl"><td>Short / Excess</td><td class="n">{{ $roundedShortExcess == 0 ? 'Balanced' : ($roundedShortExcess < 0 ? 'SHORT ' : 'EXCESS ').$money(abs($roundedShortExcess)) }}</td></tr>
                        </tbody>
                    </table>
                </td>
                <td class="gap"></td>
                <td style="width:50%">
                    <div class="p2-h3">Bank slips / deposits by salesman <small>Rs {{ $money($bankSlipsTotal) }}</small></div>
                        <table class="p2">
                            <thead><tr><th class="i">#</th><th>Bank</th><th>Ref #</th><th class="c">Date</th><th class="n">Amount</th></tr></thead>
                            <tbody>
                                @forelse ($settlement->bankSlips as $i => $slip)
                                    <tr>
                                        <td class="i">{{ $i + 1 }}</td>
                                        <td>{{ $slip->bankAccount->account_name ?? '-' }}</td>
                                        <td>{{ $slip->reference_number ?? '-' }}</td>
                                        <td class="c">{{ $slip->deposit_date ? \Carbon\Carbon::parse($slip->deposit_date)->format('d-M-y') : '-' }}</td>
                                        <td class="n b">{{ $money($slip->amount) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="c muted">No bank slips.</td></tr>
                                @endforelse
                                <tr class="sum"><td colspan="4" class="n">Total bank slips</td><td class="n">{{ $money($bankSlipsTotal) }}</td></tr>
                            </tbody>
                        </table>

                    <div class="p2-h3 mt">Physical cash (notes counted) <small>Rs {{ $money($calculatedCash) }}</small></div>
                        <table class="p2">
                            <thead><tr><th>Note</th><th class="n">Qty</th><th class="n">Value</th></tr></thead>
                            <tbody>
                                @foreach ($cashDetailRows as $row)
                                    <tr><td>Rs {{ $row['label'] }}</td><td class="n {{ $row['qty'] ? '' : 'muted' }}">{{ $row['qty'] }}</td><td class="n {{ $row['value'] ? 'b' : 'muted' }}">{{ $money($row['value'], 0) }}</td></tr>
                                @endforeach
                                <tr><td>Coins / loose</td><td class="n muted">-</td><td class="n {{ $coins > 0 ? 'b' : 'muted' }}">{{ $money($coins) }}</td></tr>
                                <tr class="sum"><td colspan="2" class="n">Total physical cash</td><td class="n">{{ $money($calculatedCash) }}</td></tr>
                            </tbody>
                        </table>

                </td>
            </tr></table>
        </section>

        {{-- Sales summary and profit --}}
        <section class="p2-sec p2-keep">
            <div class="p2-h2">{{ ++$sectionNo }} - Sales Summary &amp; Profit</div>
            <table class="lay"><tr>
                <td style="width:60%">
                    <table class="p2">
                        <thead><tr><th class="i">#</th><th>Sales &amp; cash summary</th><th>Basis</th><th class="n">Amount</th></tr></thead>
                        <tbody>
                            <tr><td class="i">1</td><td>Credit sale amount</td><td class="basis">credit invoices</td><td class="n b">{{ $money($settlement->credit_sales_amount) }}</td></tr>
                            <tr><td class="i">2</td><td>Cheque sale amount</td><td class="basis">cheques from customers</td><td class="n b">{{ $money($settlement->cheque_sales_amount) }}</td></tr>
                            <tr><td class="i">3</td><td>Bank transfer amount</td><td class="basis">direct bank / online</td><td class="n b">{{ $money($settlement->bank_transfer_amount) }}</td></tr>
                            <tr><td class="i">4</td><td>Cash sale amount</td><td class="basis">net sale - credit - bank</td><td class="n b">{{ $money($settlement->cash_sales_amount) }}</td></tr>
                            <tr><td class="i">5</td><td>Bank slips / deposits</td><td class="basis">deposited by salesman</td><td class="n b">{{ $money($bankSlipsTotal) }}</td></tr>
                            <tr class="tot"><td class="i">6</td><td>Net sale (sold items value)</td><td class="basis">credit + cheque + bank + cash</td><td class="n">{{ $money($netSale) }}</td></tr>
                            <tr><td class="i">7</td><td>Return value</td><td class="basis">returned x unit price</td><td class="n">{{ $money($settlement->sales_return_amount) }}</td></tr>
                            <tr><td class="i">8</td><td>Shortage value</td><td class="basis">shortage x unit price</td><td class="n">{{ $money($settlement->shortage_amount) }}</td></tr>
                            <tr><td class="i">9</td><td>Recovery (cash)</td><td class="basis">old credit, in cash</td><td class="n">{{ $money($recoveryCash) }}</td></tr>
                            <tr><td class="i">10</td><td>Recovery (bank / online)</td><td class="basis">old credit, via bank</td><td class="n">{{ $money($recoveryBank) }}</td></tr>
                            <tr class="tot"><td class="i">11</td><td>Total sale amount</td><td class="basis">= row 6</td><td class="n">{{ $money($netSale) }}</td></tr>
                            <tr><td class="i">12</td><td>Expected cash (sales + cash recoveries)</td><td class="basis">row 4 + row 9{{ $advanceTaxIncomeTotal > 0 ? ' + adv. tax income' : '' }}</td><td class="n b">{{ $money($expectedCashGross) }}</td></tr>
                            <tr><td class="i">13</td><td>Less: expenses</td><td class="basis">paid from cash</td><td class="n b">{{ $money($totalExpenses) }}</td></tr>
                            <tr class="hl"><td class="i">14</td><td>Expected cash (after expenses)</td><td class="basis">row 12 - row 13</td><td class="n">{{ $money($expectedCashNet) }}</td></tr>
                            <tr><td class="i">15</td><td>Physical cash submitted</td><td class="basis">notes + coins</td><td class="n b">{{ $money($calculatedCash) }}</td></tr>
                            <tr class="hl"><td class="i">16</td><td>Short / Excess</td><td class="basis">(cash + cheques + slips) - row 14</td><td class="n">{{ $roundedShortExcess < 0 ? 'SHORT ' : ($roundedShortExcess > 0 ? 'EXCESS ' : '') }}{{ $money($shortExcess) }}</td></tr>
                        </tbody>
                    </table>
                </td>
                <td class="gap"></td>
                <td style="width:40%">
                    <table class="p2">
                        <thead><tr><th class="i">#</th><th>Profit analysis</th><th class="n">Amount</th></tr></thead>
                        <tbody>
                            <tr><td class="i">1</td><td>Net sales revenue</td><td class="n b">{{ $money($netSale) }}</td></tr>
                            <tr><td class="i">2</td><td>Less: cost of goods sold</td><td class="n b">{{ $money($totalCOGS) }}</td></tr>
                            <tr class="tot"><td class="i">3</td><td>Gross profit</td><td class="n">{{ $money($grossProfit) }}</td></tr>
                            <tr><td class="i">4</td><td>Gross margin</td><td class="n">{{ number_format($grossMargin, 2) }}%</td></tr>
                            <tr><td class="i">5</td><td>Less: operating expenses</td><td class="n b">{{ $money($totalExpenses) }}</td></tr>
                            <tr class="hl"><td class="i">6</td><td>Net profit (after expenses)</td><td class="n">{{ $money($netProfit) }}</td></tr>
                            <tr><td class="i">7</td><td>Net margin</td><td class="n">{{ number_format($netMargin, 2) }}%</td></tr>
                        </tbody>
                    </table>

                    <table class="p2 mt">
                        <thead><tr><th>Stock movement</th><th class="n">Qty</th><th class="n">Value</th></tr></thead>
                        <tbody>
                            <tr><td>Issued</td><td class="n">{{ $money($valueTotals['issued_qty'], 0) }}</td><td class="n">{{ $money($valueTotals['issued_value']) }}</td></tr>
                            <tr><td>Sold</td><td class="n b">{{ $money($valueTotals['sold_qty'], 0) }}</td><td class="n b">{{ $money($valueTotals['sold_value']) }}</td></tr>
                            <tr><td>Returned</td><td class="n">{{ $money($valueTotals['returned_qty'], 0) }}</td><td class="n">{{ $money($valueTotals['returned_value']) }}</td></tr>
                            <tr><td>Shortage</td><td class="n {{ $valueTotals['shortage_qty'] > 0 ? 'b' : '' }}">{{ $money($valueTotals['shortage_qty'], 0) }}</td><td class="n {{ $valueTotals['shortage_qty'] > 0 ? 'b' : '' }}">{{ $money($valueTotals['shortage_value']) }}</td></tr>
                            <tr class="tot"><td>B/F out (left in van)</td><td class="n">{{ $money($valueTotals['issued_qty'] - $valueTotals['sold_qty'] - $valueTotals['returned_qty'] - $valueTotals['shortage_qty'], 0) }}</td><td class="n">{{ $money($bfOutValue) }}</td></tr>
                        </tbody>
                    </table>

                    <table class="p2 mt">
                        <thead><tr><th>Activity</th><th class="n">Count</th><th class="n">Amount</th></tr></thead>
                        <tbody>
                            <tr><td>Credit sales</td><td class="n">{{ $settlement->creditSales->count() }}</td><td class="n">{{ $money($creditSalesAmount) }}</td></tr>
                            <tr><td>Recoveries</td><td class="n">{{ $settlement->recoveries->count() }}</td><td class="n">{{ $money($recoveryTotal) }}</td></tr>
                            <tr><td>Cheques</td><td class="n">{{ $settlement->cheques->count() }}</td><td class="n">{{ $money($chequesTotal) }}</td></tr>
                            <tr><td>Bank slips</td><td class="n">{{ $settlement->bankSlips->count() }}</td><td class="n">{{ $money($bankSlipsTotal) }}</td></tr>
                        </tbody>
                    </table>
                </td>
            </tr></table>
        </section>

        {{-- Credit report --}}
        <section class="p2-sec">
            @php $secNo3 = ++$sectionNo; @endphp
            <table class="p2">
                <thead><tr class="cap"><th colspan="7">{{ $secNo3 }} - Credit Report: {{ $settlement->employee->name ?? 'Salesman' }} <small>as of {{ \Carbon\Carbon::parse($settlementDate)->format('d-M-Y') }} - {{ $creditReportData->count() }} parties</small></th></tr><tr><th class="i">#</th><th>Party (address)</th><th class="c">Code</th><th class="n">Opening</th><th class="n">Credit</th><th class="n">Recovery</th><th class="n">Closing</th></tr></thead>
                <tbody>
                    @forelse ($creditReportData as $i => $row)
                        <tr>
                            <td class="i">{{ $i + 1 }}</td>
                            <td><b>{{ $row->customer_name }}</b>{{ $row->address ? ' ('.$row->address.')' : '' }}</td>
                            <td class="c">{{ $row->customer_code }}</td>
                            <td class="n muted">{{ $money($row->opening_balance) }}</td>
                            <td class="n b">{{ $row->credit_amount > 0 ? $money($row->credit_amount) : '-' }}</td>
                            <td class="n b">{{ $row->recovery_amount > 0 ? $money($row->recovery_amount) : '-' }}</td>
                            <td class="n b">{{ $money($row->balance) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="c muted">No active creditors found for this date.</td></tr>
                    @endforelse
                    <tr class="sum">
                        <td colspan="4" class="n">Total (this settlement)</td>
                        <td class="n">{{ $money($creditReportData->sum('credit_amount')) }}</td>
                        <td class="n">{{ $money($creditReportData->sum('recovery_amount')) }}</td>
                        <td class="n">{{ $money($creditReportData->sum('balance')) }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        @if ($settlement->notes)
            <div class="p2-notes"><b>Notes:</b> {{ $settlement->notes }}</div>
        @endif

        <p class="p2-foot">Printed {{ now()->format('d-M-Y h:i A') }} by {{ auth()->user()->name ?? '' }}</p>

        <table class="p2-sign"><tr>
            <td>Prepared By</td>
            <td>Checked By</td>
            <td>Authorized Signature</td>
        </tr></table>
    </main>
</body>
</html>
