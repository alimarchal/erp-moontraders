<x-app-layout>
    <x-slot name="header">
        @php
            $ssTone = ['draft' => 'ak-status-amber', 'verified' => 'ak-status-green', 'posted' => 'ak-status-green'][$settlement->status] ?? 'ak-status-red';
        @endphp
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('sales-settlements.index') }}">Sales Settlements</a><span aria-hidden="true">›</span>
                    <span>{{ $settlement->settlement_number }}</span>
                </nav>
                <div class="ak-person" style="align-items:center; margin-top:6px">
                    <span class="ak-avatar" style="width:48px; height:48px" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.11c0-1.13-.84-2.09-1.96-2.18a48.42 48.42 0 0 0-1.12-.08m-5.8 0c-.07.21-.1.45-.1.69 0 .41.34.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.69m-5.8 0A2.25 2.25 0 0 1 13.5 2.25H15c1.01 0 1.87.67 2.15 1.6m-5.8 0c-.38.02-.75.05-1.12.08C9.1 4.02 8.25 4.98 8.25 6.11V8.25m0 0H4.88c-.62 0-1.13.5-1.13 1.13v11.25c0 .62.5 1.12 1.13 1.12h9.75c.62 0 1.12-.5 1.12-1.12V9.38c0-.62-.5-1.13-1.12-1.13H8.25Z" /></svg>
                    </span>
                    <div>
                        <h1 class="ak-title" style="margin:0">Settlement {{ $settlement->settlement_number }}</h1>
                        <p class="ak-sub" style="margin-top:2px">
                            {{ $settlement->settlement_date?->format('l, d M Y') }}
                            &middot; {{ $settlement->employee->name ?? '—' }} &middot; {{ $settlement->vehicle->registration_number ?? '' }}
                            &middot; {{ $settlement->supplier?->supplier_name ?? $settlement->goodsIssue?->supplier?->supplier_name ?? '' }}
                            &middot; <span class="ak-status {{ $ssTone }}"><i aria-hidden="true"></i>{{ ucfirst($settlement->status) }}</span>
                        </p>
                    </div>
                </div>
            </div>
            <div class="ak-head-actions no-print">
                <a href="{{ route('sales-settlements.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to Settlements</a>
                <a href="javascript:window.location.reload();" class="ak-btn ak-btn-outline" title="Refresh">Refresh</a>
                {{-- Print loads the printable sheet (layout=print2) in a hidden frame and opens the print dialog with it --}}
                <button type="button" class="ak-btn ak-btn-outline" data-print-url="{{ route('sales-settlements.show', [$settlement, 'layout' => 'print2']) }}" onclick="ssPrintSheet(this)" title="Print the settlement sheet (A4, portrait or landscape)">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.83c-.24.03-.48.06-.72.1m.72-.1a42.4 42.4 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.17c.24.03.48.06.72.1m-.72-.1L17.66 18m0 0 .23 2.5a1.13 1.13 0 0 1-1.12 1.24H7.23a1.13 1.13 0 0 1-1.12-1.24L6.34 18m11.32 0h1.09A2.25 2.25 0 0 0 21 15.75V9.46c0-1.08-.77-2.01-1.84-2.18a48 48 0 0 0-1.41-.2M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.46c0-1.08.77-2.01 1.84-2.18.47-.07.94-.14 1.41-.2m0 0a48.6 48.6 0 0 1 11.5 0m-11.5 0V3.38c0-.62.5-1.13 1.13-1.13h9.24c.63 0 1.13.5 1.13 1.13v3.7M18 10.5h.01" /></svg>
                    <span>Print</span>
                </button>
                @foreach (['portrait' => 'PDF Portrait', 'landscape' => 'PDF Landscape'] as $pdfOrientation => $pdfLabel)
                    <a href="{{ route('sales-settlements.show', [$settlement, 'layout' => 'print2', 'format' => 'pdf', 'orientation' => $pdfOrientation]) }}" class="ak-btn ak-btn-outline" title="Download the settlement sheet as an A4 {{ $pdfOrientation }} PDF">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                        {{ $pdfLabel }}
                    </a>
                @endforeach
                @if ($settlement->status === 'draft')
                    @can('sales-settlement-edit')
                        <a href="{{ route('sales-settlements.edit', $settlement) }}" class="ak-btn ak-btn-outline">Edit</a>
                    @endcan
                    @can('sales-settlement-delete')
                        <button type="button" x-data x-on:click="$dispatch('open-sales-settlement-delete-modal')" class="ak-btn ak-btn-danger-outline">Delete Draft</button>
                    @endcan
                    @can('sales-settlement-post')
                        <button type="button" x-data x-on:click="$dispatch('open-sales-settlement-post-modal')" class="ak-btn ak-btn-success">Post Settlement</button>
                    @endcan
                @endif
                @if ($settlement->status === 'posted')
                    @can('sales-settlement-revert')
                        {{-- Currently Do Not Needed Hide It
                        <form id="revertSettlementForm" action="{{ route('sales-settlements.revert', $settlement->id) }}"
                            method="POST" onsubmit="return confirmRevertSettlement();" class="inline-block">
                            @csrf
                            <input type="hidden" id="revert_password" name="password" value="">
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                title="Revert Settlement">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                    stroke="currentColor" class="size-6">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
                                </svg>
                            </button>
                        </form> --}}
                    @endcan
                    @role('super-admin')
                        <a href="{{ route('sales-settlements.edit-special', $settlement->id) }}" class="ak-btn ak-btn-outline" style="color:#b45309; border-color:#fcd34d">Special Edit</a>
                    @endrole
                @endif
            </div>
        </div>
    </x-slot>

    @push('header')
        <style>
            .report-table {
                width: 100%;
                border-collapse: collapse;
                border: 1px solid black;
                font-size: 12px;
                line-height: 1.2;
            }

            .report-table th,
            .report-table td {
                border: 1px solid black;
                padding: 4px 6px;
                white-space: nowrap;
            }

            .print-only {
                display: none;
            }

            @media print {
                @page {
                    margin: 5mm 5mm 15mm 5mm;

                    @bottom-center {
                        content: "Page " counter(page) " of " counter(pages);
                    }
                }

                .no-print {
                    display: none !important;
                }

                body {
                    margin: 0 !important;
                    padding: 0 !important;
                    counter-reset: page 1;
                    background-color: white !important;
                }

                .max-w-8xl,
                .max-w-7xl {
                    max-width: 100% !important;
                    width: 100% !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }

                .bg-white {
                    background-color: white !important;
                    margin: 0 !important;
                    padding: 10px !important;
                    border: none !important;
                    box-shadow: none !important;
                }

                .shadow-xl,
                .shadow-lg,
                .shadow-md,
                .shadow-sm {
                    box-shadow: none !important;
                }

                .rounded-lg,
                .sm\:rounded-lg {
                    border-radius: 0 !important;
                }

                .overflow-x-auto {
                    overflow: visible !important;
                }

                .report-table {
                    font-size: 12px !important;
                    width: 100% !important;
                    table-layout: auto;
                }

                .report-table tr {
                    page-break-inside: avoid;
                    break-inside: avoid;
                }

                tr {
                    page-break-inside: avoid !important;
                    break-inside: avoid !important;
                }

                td,
                th {
                    page-break-inside: avoid !important;
                    break-inside: avoid !important;
                }

                .report-table th,
                .report-table td {
                    padding: 1px 2px !important;
                    color: #000 !important;
                    background-color: white !important;
                    white-space: normal !important;
                    overflow-wrap: break-word;
                }

                /* Ensure specific background colors are removed in print */
                .bg-gray-100,
                .bg-gray-50,
                .bg-blue-50,
                .bg-red-50,
                .bg-indigo-50,
                .bg-green-50,
                .bg-orange-50,
                .bg-red-100,
                .bg-red-200,
                .bg-green-100,
                .bg-emerald-100,
                .bg-purple-50,
                .bg-yellow-50 {
                    background-color: white !important;
                }

                p {
                    margin-top: 0 !important;
                    margin-bottom: 4px !important;
                }

                .print-info {
                    font-size: 8px !important;
                    margin-top: 2px !important;
                    margin-bottom: 5px !important;
                    color: #000 !important;
                }

                /* Header visibility in print */
                .report-header {
                    display: block !important;
                }

                .print-only {
                    display: block !important;
                }

                .page-footer {
                    display: none;
                }

                /* Side-by-side tables (two or three across) get a slightly smaller font so the
                   last column is not cut off at the page edge; long names wrap inside their cell. */
                .grid .report-table,
                .flex .report-table {
                    font-size: 10px !important;
                    line-height: 1.15;
                }

                .grid .report-table th,
                .grid .report-table td,
                .flex .report-table th,
                .flex .report-table td {
                    padding: 1px 2px !important;
                    word-break: break-word;
                }

                .grid > div,
                .flex > div {
                    min-width: 0;
                }

                /* Keep a box title with its tables, and do not leave a blank page after the signatures */
                .ss-keep { break-inside: avoid; page-break-inside: avoid; }
                h3, h4 { break-after: avoid; page-break-after: avoid; }
                .py-6 { padding-top: 0 !important; padding-bottom: 0 !important; }
                .ss-sign { margin-top: 2.5rem !important; }
                .min-h-screen { min-height: 0 !important; }
                .fixed, [x-cloak] { display: none !important; }
                main > div:last-child > *:last-child { margin-bottom: 0 !important; }

                /* Force grid for summary tables in print */
                .summary-grid {
                    display: grid !important;
                    grid-template-columns: repeat(2, 1fr) !important;
                    gap: 0.5rem !important;
                    margin-top: 0.5rem !important;
                }
            }

            /* Screen styles for header */
            .report-header {
                /* Visible on screen by default now */
            }
        </style>
    @endpush

    @php
        $netSale = (float) $settlement->items->sum('total_sales_value');
        $creditSalesAmount = (float) ($settlement->credit_sales_amount ?? 0);
        $chequeSalesAmount = (float) ($settlement->cheque_sales_amount ?? 0);
        $bankSalesAmount = (float) ($settlement->bank_transfer_amount ?? 0);
        $cashSalesAmount = (float) ($settlement->cash_sales_amount ?? 0);
        $cashDenominations = $settlement->cashDenominations->first();
        $cashDenominationTotal = (float) ($cashDenominations?->total_amount ?? 0.0);
        $coins = (float) ($cashDenominations?->denom_coins ?? 0.0);
        // Correct Coins logic if needed, previously it was qty=amount for coins

        // Recovery Breakdown
        $recoveryCash = (float) $settlement->recoveries->where('payment_method', 'cash')->sum('amount');
        $recoveryBank = (float) $settlement->recoveries->where('payment_method', 'bank_transfer')->sum('amount');
        $recoveryTotal = (float) ($settlement->credit_recoveries ?? 0);
        $totalSale = $netSale + $recoveryTotal;

        // Expenses & Taxes
        $usesAdvanceTaxIncome = (bool) ($settlement->supplier?->is_advance_tax_income ?? false);
        $totalExpenses = (float) ($settlement->expenses->sum('amount') ?? 0);
        $advanceTaxEntries = $usesAdvanceTaxIncome ? $settlement->advanceTaxIncomes : $settlement->advanceTaxes;
        $advanceTaxTotal = (float) ($advanceTaxEntries->sum('tax_amount') ?? 0);
        $advanceTaxIncomeTotal = $usesAdvanceTaxIncome ? $advanceTaxTotal : 0.0;
        $totalDeductions = $totalExpenses;

        // Expected Cash Calculation (Professional Accounting)
        // Cheques are cash-equivalent submissions; recalculate cash sales without deducting cheques
        $netSaleItems = (float) $settlement->items->sum('total_sales_value');
        $chequesTotal = (float) $settlement->cheques->sum('amount');
        $theoreticalCashSales = $netSaleItems - $creditSalesAmount - $bankSalesAmount;
        $isPaymentBreakdownExceeded = $theoreticalCashSales < 0;
        $paymentBreakdownExcess = $isPaymentBreakdownExceeded ? abs($theoreticalCashSales) : 0.0;
        $expectedCashGross = $theoreticalCashSales + $recoveryCash + $advanceTaxIncomeTotal;
        $expectedCashNet = $expectedCashGross - $totalDeductions;

        // Actual Physical Cash Collected
        $actualPhysicalCash = $cashDenominationTotal > 0 ? $cashDenominationTotal : (float) $settlement->cash_collected;
        $bankSlipsTotal = (float) $settlement->bankSlips->sum('amount');

        // Total submitted = Physical Cash + Cheques (cash-equivalent) + Bank Slips
        $shortExcess = ($actualPhysicalCash + $chequesTotal + $bankSlipsTotal) - $expectedCashNet;

        // Profit Analysis
        $totalCOGS = (float) ($settlement->items->sum('total_cogs') ?? 0);
        $grossProfit = $netSale - $totalCOGS;
        $grossMargin = $netSale > 0 ? ($grossProfit / $netSale) * 100 : 0;
        $netProfit = $grossProfit - $totalExpenses;
        $netMargin = $netSale > 0 ? ($netProfit / $netSale) * 100 : 0;

        $valueTotals = [
            'bf_in_qty' => 0,
            'bf_in_value' => 0,
            'issued_qty' => 0,
            'issued_value' => 0,
            'sold_qty' => 0,
            'sold_value' => 0,
            'returned_qty' => 0,
            'returned_value' => 0,
            'shortage_qty' => 0,
            'shortage_value' => 0,
        ];

        foreach ($settlement->items as $item) {
            $priceFallback = (float) ($item->unit_selling_price > 0 ? $item->unit_selling_price : $item->unit_cost);

            if ($item->batches->count() > 0) {
                foreach ($item->batches as $batch) {
                    $price = (float) ($batch->selling_price ?? $priceFallback);
                    $issuedQty = (float) $batch->quantity_issued;
                    $soldQty = (float) $batch->quantity_sold;
                    $returnedQty = (float) $batch->quantity_returned;
                    $shortageQty = (float) $batch->quantity_shortage;

                    $valueTotals['issued_qty'] += $issuedQty;
                    $valueTotals['issued_value'] += $issuedQty * $price;
                    $valueTotals['sold_qty'] += $soldQty;
                    $valueTotals['sold_value'] += $soldQty * $price;
                    $valueTotals['returned_qty'] += $returnedQty;
                    $valueTotals['returned_value'] += $returnedQty * $price;
                    $valueTotals['shortage_qty'] += $shortageQty;
                    $valueTotals['shortage_value'] += $shortageQty * $price;
                }
            } else {
                $issuedQty = (float) $item->quantity_issued;
                $soldQty = (float) $item->quantity_sold;
                $returnedQty = (float) $item->quantity_returned;
                $shortageQty = (float) $item->quantity_shortage;

                $valueTotals['issued_qty'] += $issuedQty;
                $valueTotals['issued_value'] += $issuedQty * $priceFallback;
                $valueTotals['sold_qty'] += $soldQty;
                $valueTotals['sold_value'] += $soldQty * $priceFallback;
                $valueTotals['returned_qty'] += $returnedQty;
                $valueTotals['returned_value'] += $returnedQty * $priceFallback;
                $valueTotals['shortage_qty'] += $shortageQty;
                $valueTotals['shortage_value'] += $shortageQty * $priceFallback;
            }
        }

        $totalAvailableQty = $valueTotals['bf_in_qty'] + $valueTotals['issued_qty'];
        $totalAvailableValue = $valueTotals['bf_in_value'] + $valueTotals['issued_value'];
        $bfOutQty = $totalAvailableQty - $valueTotals['sold_qty'] - $valueTotals['returned_qty'] - $valueTotals['shortage_qty'];
        $bfOutValue = $totalAvailableValue - $valueTotals['sold_value'] - $valueTotals['returned_value'] - $valueTotals['shortage_value'];
    @endphp

    @include('settings.partials.ui-style')
    <style>
        .ss-summary { display: flex; flex-direction: column; gap: 14px; margin-bottom: 16px; }
        .ss-strip { display: grid; gap: 10px; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
        .ss-chip { padding: 10px 12px; border: 1px solid var(--ak-border); border-radius: 10px; background: #fff; box-shadow: var(--ak-shadow); font-size: 12.5px; color: var(--ak-muted); }
        .ss-chip b { display: block; font-size: 15px; color: var(--ak-text); font-variant-numeric: tabular-nums; }
        .ss-chip.is-bad b { color: #b91c1c; }
        .ss-links { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; font-size: 13px; }
        .ss-links span { color: var(--ak-muted); font-weight: 600; margin-right: 2px; }
        .ss-links a { padding: 5px 10px; border: 1px solid var(--ak-border); border-radius: 999px; color: var(--ak-navy); text-decoration: none; background: #fff; font-weight: 600; }
        .ss-links a:hover { background: var(--ak-soft); }
        .ss-short { color: #b91c1c !important; }
        .ss-excess { color: #047857 !important; }
        @media print { .ss-summary { display: none !important; } }
    </style>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4 shadow-md no-print" />

            {{-- Screen summary (not printed): the key figures of the sheet below, and where to go next --}}
            @php
                $ssUser = auth()->user();
                $submittedTotal = $actualPhysicalCash + $chequesTotal + $bankSlipsTotal;
                $ssSince = '2020-01-01';
                $ssToday = now()->toDateString();
                $ssLinks = array_filter([
                    $settlement->goodsIssue && $ssUser->can('goods-issue-list') ? ['Goods Issue '.$settlement->goodsIssue->issue_number, route('goods-issues.show', $settlement->goodsIssue)] : null,
                    $settlement->journalEntry && $ssUser->can('journal-entry-list') ? ['Journal entry #'.$settlement->journalEntry->id, route('journal-entries.show', $settlement->journalEntry)] : null,
                    $ssUser->can('report-audit-creditors-ledger') ? [($settlement->employee->name ?? 'Salesman').'\'s customer credit', route('reports.creditors-ledger.index', ['filter' => ['employee_id' => $settlement->employee_id, 'has_balance' => 'yes']])] : null,
                    $ssUser->can('report-inventory-van-stock-ledger') && $settlement->vehicle_id ? ['Van stock ledger', route('reports.van-stock-ledger.vehicle-ledger', $settlement->vehicle_id)] : null,
                    $ssUser->can('sales-settlement-list') && $settlement->vehicle_id ? ['Settlements of this vehicle', route('sales-settlements.index', ['filter' => ['vehicle_id' => $settlement->vehicle_id, 'settlement_date_from' => $ssSince, 'settlement_date_to' => $ssToday]])] : null,
                    $ssUser->can('report-sales-daily-sales') ? ['Daily sales report', route('reports.daily-sales.index', ['filter' => ['start_date' => $settlement->settlement_date?->toDateString(), 'end_date' => $settlement->settlement_date?->toDateString()]])] : null,
                ]);
            @endphp
            <div class="ss-summary no-print">
                <section class="ak-kpis" aria-label="Settlement summary">
                    <div class="ak-kpi" title="Rs {{ number_format($netSale, 2) }}">
                        <span class="ak-kpi-icon ak-tone-green" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.31 4.31a11.95 11.95 0 0 1 5.81-5.52l2.74-1.22m0 0-5.94-2.28m5.94 2.28-2.28 5.94" /></svg></span>
                        <span class="ak-kpi-body">
                            <span class="ak-kpi-label">Net sale (sold value)</span>
                            <span class="ak-kpi-value">Rs {{ number_format($netSale) }}</span>
                            <span class="ak-kpi-hint">cash {{ number_format($theoreticalCashSales) }} &middot; credit {{ number_format($creditSalesAmount) }} &middot; bank {{ number_format($bankSalesAmount) }}{{ $chequesTotal > 0 ? ' · cheques '.number_format($chequesTotal) : '' }}</span>
                        </span>
                    </div>
                    <div class="ak-kpi" title="Rs {{ number_format($recoveryTotal, 2) }}">
                        <span class="ak-kpi-icon ak-tone-navy" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></span>
                        <span class="ak-kpi-body">
                            <span class="ak-kpi-label">Credit recovered</span>
                            <span class="ak-kpi-value">Rs {{ number_format($recoveryTotal) }}</span>
                            <span class="ak-kpi-hint">{{ $settlement->recoveries->count() }} {{ \Illuminate\Support\Str::plural('recovery', $settlement->recoveries->count()) }} &middot; cash {{ number_format($recoveryCash) }} &middot; bank {{ number_format($recoveryBank) }}</span>
                        </span>
                    </div>
                    <div class="ak-kpi">
                        <span class="ak-kpi-icon {{ round($shortExcess, 2) < 0 ? 'ak-tone-amber' : 'ak-tone-green' }}" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.8 2.1c.73.2 1.45-.34 1.45-1.1V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.38c0-.62.5-1.12 1.13-1.12H20.25M2.25 6v9m18-10.5v.75c0 .41.34.75.75.75h.75m-1.5-1.5h.38c.62 0 1.12.5 1.12 1.13v9.75c0 .62-.5 1.12-1.12 1.12h-.38m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.38a1.13 1.13 0 0 1-1.12-1.12V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg></span>
                        <span class="ak-kpi-body">
                            <span class="ak-kpi-label">Cash check</span>
                            @php $roundedShortExcess = round($shortExcess, 2); @endphp
                            <span class="ak-kpi-value {{ $roundedShortExcess < 0 ? 'ss-short' : ($roundedShortExcess > 0 ? 'ss-excess' : '') }}">{{ $roundedShortExcess == 0 ? 'Balanced' : ($roundedShortExcess < 0 ? 'Short Rs '.number_format(abs($roundedShortExcess), 2) : 'Excess Rs '.number_format($roundedShortExcess, 2)) }}</span>
                            <span class="ak-kpi-hint">expected Rs {{ number_format($expectedCashNet) }} &middot; submitted Rs {{ number_format($submittedTotal) }} (cash {{ number_format($actualPhysicalCash) }}{{ $chequesTotal > 0 ? ' + cheques '.number_format($chequesTotal) : '' }}{{ $bankSlipsTotal > 0 ? ' + slips '.number_format($bankSlipsTotal) : '' }})</span>
                        </span>
                    </div>
                    <div class="ak-kpi">
                        <span class="ak-kpi-icon ak-tone-slate" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.13C3 12.5 3.5 12 4.13 12h2.25c.62 0 1.12.5 1.12 1.13v6.75C7.5 20.5 7 21 6.38 21H4.13A1.13 1.13 0 0 1 3 19.88v-6.75ZM9.75 8.63c0-.63.5-1.13 1.13-1.13h2.25c.62 0 1.12.5 1.12 1.13v11.25c0 .62-.5 1.12-1.12 1.12h-2.25a1.13 1.13 0 0 1-1.13-1.12V8.63ZM16.5 4.13c0-.63.5-1.13 1.13-1.13h2.25C20.5 3 21 3.5 21 4.13v15.75c0 .62-.5 1.12-1.12 1.12h-2.25a1.13 1.13 0 0 1-1.13-1.12V4.13Z" /></svg></span>
                        <span class="ak-kpi-body">
                            <span class="ak-kpi-label">Gross profit</span>
                            <span class="ak-kpi-value">Rs {{ number_format($grossProfit) }}</span>
                            <span class="ak-kpi-hint">{{ number_format($grossMargin, 1) }}% margin &middot; after expenses Rs {{ number_format($netProfit) }} ({{ number_format($netMargin, 1) }}%)</span>
                        </span>
                    </div>
                </section>

                <div class="ss-strip" aria-label="Stock on this settlement">
                    <div class="ss-chip">Issued<b>{{ number_format($valueTotals['issued_qty'], 0) }} pcs</b>Rs {{ number_format($valueTotals['issued_value']) }}</div>
                    <div class="ss-chip">Sold<b>{{ number_format($valueTotals['sold_qty'], 0) }} pcs</b>Rs {{ number_format($valueTotals['sold_value']) }}</div>
                    <div class="ss-chip">Returned<b>{{ number_format($valueTotals['returned_qty'], 0) }} pcs</b>Rs {{ number_format($valueTotals['returned_value']) }}</div>
                    <div class="ss-chip{{ $valueTotals['shortage_qty'] > 0 ? ' is-bad' : '' }}">Shortage<b>{{ number_format($valueTotals['shortage_qty'], 0) }} pcs</b>Rs {{ number_format($valueTotals['shortage_value']) }}</div>
                    <div class="ss-chip">Expenses<b>Rs {{ number_format($totalExpenses) }}</b>{{ $settlement->expenses->count() }} {{ \Illuminate\Support\Str::plural('entry', $settlement->expenses->count()) }}</div>
                    <div class="ss-chip">Credit given<b>Rs {{ number_format($creditSalesAmount) }}</b>{{ $settlement->creditSales->count() }} {{ \Illuminate\Support\Str::plural('customer', $settlement->creditSales->count()) }}</div>
                </div>

                @if ($ssLinks)
                    <nav class="ss-links" aria-label="Related">
                        <span>Related:</span>
                        @foreach ($ssLinks as [$label, $url])
                            <a href="{{ $url }}">{{ $label }} →</a>
                        @endforeach
                    </nav>
                @endif
            </div>

            @if ($isPaymentBreakdownExceeded)
                <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-amber-900 shadow-sm">
                    <p class="text-sm font-semibold">Warning</p>
                    <p class="text-sm">
                        Payment breakdown exceeds Net Sale (Sold Items Value) by
                        <strong>Rs {{ number_format($paymentBreakdownExcess, 2) }}</strong>.
                        This settlement is visible for review, but keep this imbalance in mind before reconciliation.
                    </p>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mb-4 mt-4 print:shadow-none print:pb-0 p-4">

                {{-- Report Header --}}
                <div class="mb-2 report-header">
                    <table class="w-full text-sm">
                        <tr>
                            <td class="text-center font-extrabold text-xl" colspan="8">Moon Traders</td>
                        </tr>
                        <tr>
                            <td class="text-center font-bold text-lg" colspan="8">
                                Sales Settlement <span
                                    class="text-sm font-normal">({{ strtoupper($settlement->status) }})</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-left font-semibold">Settlement #:</td>
                            <td class="text-left">{{ $settlement->settlement_number }}</td>
                            <td class="text-left font-semibold">Date/Time:</td>
                            <td class="text-left">
                                {{ \Carbon\Carbon::parse($settlement->settlement_date)->format('d-M-Y') }}
                                {{ $settlement->created_at ? $settlement->created_at->format('h:i A') : '' }}
                            </td>
                            <td class="text-left font-semibold">Created By:</td>
                            <td class="text-left" colspan="3">{{ $settlement->creator->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-left font-semibold">Salesman:</td>
                            <td class="text-left">{{ $settlement->employee->name }}</td>
                            <td class="text-left font-semibold">Vehicle:</td>
                            <td class="text-left">{{ $settlement->vehicle->registration_number }}</td>
                            <td class="text-left font-semibold">Warehouse:</td>
                            <td class="text-left" colspan="3">{{ $settlement->warehouse->warehouse_name }}</td>
                        </tr>
                        <tr>
                            <td class="text-left font-semibold">Goods Issue:</td>
                            <td class="text-left">@can('goods-issue-list')<a href="{{ route('goods-issues.show', $settlement->goodsIssue) }}" class="text-blue-700 underline print:text-black print:no-underline">{{ $settlement->goodsIssue->issue_number }}</a>@else{{ $settlement->goodsIssue->issue_number }}@endcan</td>
                            <td class="text-left font-semibold">GI Date/Time:</td>
                            <td class="text-left">
                                {{ $settlement->goodsIssue->issue_date ? \Carbon\Carbon::parse($settlement->goodsIssue->issue_date)->format('d-M-Y') : '' }}
                                {{ $settlement->goodsIssue->created_at ? $settlement->goodsIssue->created_at->format('h:i A') : '' }}
                            </td>
                            <td class="text-left font-semibold">Supplier:</td>
                            <td class="text-left" colspan="3">
                                {{ $settlement->supplier?->supplier_name ?? $settlement->goodsIssue->supplier?->supplier_name ?? '-' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="text-left font-semibold">GI Issued By:</td>
                            <td class="text-left" colspan="7">
                                {{ $settlement->goodsIssue->creator->name ?? $settlement->goodsIssue->issuedBy->name ?? '-' }}
                            </td>
                        </tr>
                    </table>
                </div>

                {{-- Product Table --}}
                <table class="report-table mb-1 text-black">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="text-center w-10">Sr#</th>
                            <th class="text-center">SKU</th>
                            <th class="text-center">B/F (In)</th>
                            <th class="text-center">Qty Issued</th>
                            <th class="text-center">Batch (TP)</th>
                            <th class="text-center">Sold</th>
                            <th class="text-center">Return</th>
                            <th class="text-center">Short</th>
                            <th class="text-center">B/F (Out)</th>
                            <th class="text-center">Sales Value</th>
                        </tr>
                    </thead>
                    <tbody class="tabular-nums">
                        @foreach ($settlement->items as $index => $item)
                            <tr>
                                <td class="text-center">{{ $index + 1 }}</td>
                                <td>
                                    <div class="font-semibold">{{ $item->product->product_name }}</div>
                                    <!-- <div class="text-xs">{{ $item->product->product_name }}</div> -->

                                </td>
                                <td class="text-right">
                                    @php
                                        $bfIn = $bfMap[$item->product_id] ?? 0;
                                    @endphp
                                    {{ number_format($bfIn, 2) }}
                                </td>
                                <td class="text-right">{{ number_format($item->quantity_issued, 2) }}</td>
                                <td>
                                    @if($item->batches->count() > 0)
                                        <div class="text-xs space-y-1">

                                            @foreach($item->batches as $b)
                                                <span class="tabular-nums text-black font-bold">

                                                    {{ number_format($b->quantity_issued, 0) }} ×
                                                    {{ number_format($b->selling_price, 2) }}

                                                    @if($b->is_promotional) (Promo) @endif
                                                    = {{ number_format($b->quantity_issued * $b->selling_price, 2) }}
                                                    ({{ $b->batch_code ?? 'N/A' }})</span><br>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400">No batch data</span>
                                    @endif
                                </td>
                                <td class="text-right font-bold">{{ number_format($item->quantity_sold, 2) }}</td>
                                <td class="text-right">{{ number_format($item->quantity_returned, 2) }}</td>
                                <td class="text-right {{ $item->quantity_shortage > 0 ? 'text-red-600 font-bold' : '' }}">
                                    {{ number_format($item->quantity_shortage, 2) }}
                                </td>
                                <td class="text-right">
                                    @php
                                        $bfOut = $bfIn + $item->quantity_issued - $item->quantity_sold - $item->quantity_returned - $item->quantity_shortage;
                                    @endphp
                                    {{ number_format($bfOut, 2) }}
                                </td>
                                <td class="text-right font-bold">{{ number_format($item->total_sales_value, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-100 font-bold tabular-nums">
                        <tr>
                            <td colspan="3" class="text-right">Totals:</td>
                            <td class="text-right">{{ number_format($settlement->items->sum('quantity_issued'), 2) }}
                            </td>
                            <td></td>
                            <td class="text-right">{{ number_format($settlement->total_quantity_sold, 2) }}</td>
                            <td class="text-right">{{ number_format($settlement->total_quantity_returned, 2) }}</td>
                            <td class="text-right">{{ number_format($settlement->total_quantity_shortage, 2) }}</td>
                            <td class="text-right">-</td>
                            <td class="text-right">{{ number_format($settlement->items->sum('total_sales_value'), 2) }}
                            </td>
                        </tr>
                        <tr class="bg-gray-50 text-xs text-gray-600">
                            <td colspan="3" class="text-right">Value Breakdown:</td>
                            <td class="text-right">{{ number_format($valueTotals['issued_value'], 2) }}</td>
                            <td></td>
                            <td class="text-right">{{ number_format($valueTotals['sold_value'], 2) }}</td>
                            <td class="text-right">{{ number_format($valueTotals['returned_value'], 2) }}</td>
                            <td class="text-right">{{ number_format($valueTotals['shortage_value'], 2) }}</td>
                            <td class="text-right">{{ number_format($bfOutValue, 2) }}</td>
                            <td class="text-right">{{ number_format($valueTotals['sold_value'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>

                {{-- Full Width Financial Tables --}}
                <div class="space-y-1 text-black">

                    @php
                        $csCount = $settlement->creditSales->count();
                        $recCount = $settlement->recoveries->count();
                        $maxRows = max($csCount, $recCount);
                    @endphp

                    {{-- Sales & Collection Details Group --}}
                    <div class="border-2 border-black rounded-lg px-2 pb-2 mt-2">
                        <h3 class="font-bold text-md text-center text-black  pb-1">Sales & Collection Details</h3>

                        <div class="grid grid-cols-2 lg:grid-cols-2 gap-1 items-start print:grid-cols-2">
                            {{-- Credit Sales --}}
                            <div>
                                <h4 class="font-bold text-sm border-x border-t border-black  text-center">Credit Sales
                                    Breakdown</h4>
                                <table class="report-table w-full">
                                    <thead>
                                        <tr class="bg-gray-100">
                                            <th class="text-center w-10">#</th>
                                            <th class="text-center">
                                                <span class="print:hidden"><x-tooltip text="Customer Name">Customer
                                                        Name</x-tooltip></span>
                                                <span class="hidden print:inline">Name</span>
                                            </th>
                                            <th class="text-center">
                                                <span class="print:hidden"><x-tooltip
                                                        text="Address">Address</x-tooltip></span>
                                                <span class="hidden print:inline">Address</span>
                                            </th>
                                            <th class="text-center">
                                                <span class="print:hidden"><x-tooltip
                                                        text="Previous Balance">PB</x-tooltip></span>
                                                <span class="hidden print:inline">Pre Bal</span>
                                            </th>
                                            <th class="text-center">
                                                <span class="print:hidden"><x-tooltip
                                                        text="Credit Sale">Sale</x-tooltip></span>
                                                <span class="hidden print:inline">Credit</span>
                                            </th>
                                            <th class="text-center">
                                                <span class="print:hidden"><x-tooltip
                                                        text="New Balance">NB</x-tooltip></span>
                                                <span class="hidden print:inline">Bal</span>
                                            </th>
                                            {{-- <th class="text-center print:hidden">Notes</th> --}}
                                        </tr>
                                    </thead>
                                    <tbody class="tabular-nums">
                                        @if($maxRows > 0)
                                            @for($i = 0; $i < $maxRows; $i++)
                                                @php $creditSale = $settlement->creditSales->get($i); @endphp
                                                <tr>
                                                    <td class="text-center">{{ $i + 1 }}</td>
                                                    <td>{{ $creditSale?->customer->customer_name ?? '-' }}</td>
                                                    <td>{{ $creditSale?->customer->address ?? '-' }}</td>
                                                    <td class="text-right">
                                                        {{ $creditSale ? number_format($creditSale->previous_balance, 2) : '-' }}
                                                    </td>
                                                    <td class="text-right font-bold">
                                                        {{ $creditSale ? number_format($creditSale->sale_amount, 2) : '-' }}
                                                    </td>
                                                    <td class="text-right">
                                                        {{ $creditSale ? number_format($creditSale->new_balance, 2) : '-' }}
                                                    </td>
                                                    {{-- <td class="text-xs italic print:hidden">{!! $creditSale?->notes ?? '-'
                                                        !!} --}}
                                                    </td>
                                                </tr>
                                            @endfor
                                        @else
                                            <tr>
                                                <td colspan="7" class="text-center italic text-gray-500">No credit sales
                                                    recorded</td>
                                            </tr>
                                        @endif
                                    </tbody>
                                    <tfoot class="bg-gray-50 font-bold tabular-nums">
                                        <tr>
                                            <td colspan="4" class="text-right">Total:</td>
                                            <td class="text-right">
                                                {{ number_format($settlement->creditSales->sum('sale_amount'), 2) }}
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            {{-- Recoveries --}}
                            <div>
                                <h4 class="font-bold text-sm border-x border-t border-black  text-center">Recoveries
                                    Breakdown (Cash + Online Bank Trafer From Customer)</h4>
                                <table class="report-table w-full">
                                    <thead>
                                        <tr class="bg-gray-100">
                                            <th class="text-center w-10">#</th>
                                            <th class="text-center">
                                                <span class="print:hidden"><x-tooltip
                                                        text="Customer Name">CN</x-tooltip></span>
                                                <span class="hidden print:inline">Name</span>
                                            </th>
                                            <th class="text-center">
                                                <span class="print:hidden"><x-tooltip
                                                        text="Address">Address</x-tooltip></span>
                                                <span class="hidden print:inline">Address</span>
                                            </th>
                                            <th class="text-center">
                                                <span class="print:hidden"><x-tooltip
                                                        text="Previous Balance">PB</x-tooltip></span>
                                                <span class="hidden print:inline">Prev Bal.</span>
                                            </th>
                                            <th class="text-center">
                                                <span class="print:hidden"><x-tooltip
                                                        text="Recovery Amount">Amt</x-tooltip></span>
                                                <span class="hidden print:inline">Rec Amt</span>
                                            </th>
                                            <th class="text-center">
                                                <span class="print:hidden"><x-tooltip
                                                        text="Payment Method">Method</x-tooltip></span>
                                                <span class="hidden print:inline">Mtd</span>
                                            </th>
                                            <th class="text-center">
                                                <span class="print:hidden"><x-tooltip
                                                        text="New Balance">NB</x-tooltip></span>
                                                <span class="hidden print:inline">Bal</span>
                                            </th>
                                            {{-- <th class="text-center print:hidden">Notes</th> --}}
                                        </tr>
                                    </thead>
                                    <tbody class="tabular-nums">
                                        @if($maxRows > 0)
                                            @for($i = 0; $i < $maxRows; $i++)
                                                @php $recovery = $settlement->recoveries->get($i); @endphp
                                                <tr>
                                                    <td class="text-center">{{ $i + 1 }}</td>
                                                    <td>{{ $recovery?->customer->customer_name ?? '-' }}</td>
                                                    <td>{{ $recovery?->customer->address ?? '-' }}</td>
                                                    <td class="text-right">
                                                        {{ $recovery ? number_format($recovery->previous_balance, 2) : '-' }}
                                                    </td>
                                                    <td class="text-right font-bold">
                                                        {{ $recovery ? number_format($recovery->amount, 2) : '-' }}
                                                    </td>
                                                    <td class="text-center text-xs uppercase">
                                                        @if($recovery)
                                                            {{ $recovery->payment_method === 'cash' ? 'Cash' : 'Bank' }}
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td class="text-right">
                                                        {{ $recovery ? number_format($recovery->new_balance, 2) : '-' }}
                                                    </td>
                                                    {{-- <td class="text-xs italic print:hidden">{!! $recovery?->notes ?? '-'
                                                        !!}
                                                    </td> --}}
                                                </tr>
                                            @endfor
                                        @else
                                            <tr>
                                                <td colspan="8" class="text-center italic text-gray-500">No recoveries
                                                    recorded</td>
                                            </tr>
                                        @endif
                                    </tbody>
                                    <tfoot class="bg-gray-50 font-bold tabular-nums">
                                        <tr>
                                            <td colspan="4" class="text-right">Total:</td>
                                            <td class="text-right">
                                                {{ number_format($settlement->recoveries->sum('amount'), 2) }}
                                            </td>
                                            <td colspan="2"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        @php
                            $chequeCount = $settlement->cheques->count();
                            $bankCount = $settlement->bankTransfers->count();
                            $maxRows2 = max($chequeCount, $bankCount);
                        @endphp

                        <div class="grid grid-cols-2 lg:grid-cols-2 gap-1 items-start print:grid-cols-2 mt-1">
                            {{-- Cheque Payments --}}
                            <div>
                                <h4 class="font-bold text-sm border-x border-t border-black  text-center">Cheque
                                    Payments</h4>
                                <table class="report-table w-full">
                                    <thead>
                                        <tr class="bg-gray-100">
                                            <th class="text-center w-10">#</th>
                                            <th class="text-left">
                                                <span class="print:hidden"><x-tooltip
                                                        text="Cheque Date">Date</x-tooltip></span>
                                                <span class="hidden print:inline">Date</span>
                                            </th>
                                            <th class="text-left">
                                                <span class="print:hidden"><x-tooltip text="Cheque Number">Chq
                                                        #</x-tooltip></span>
                                                <span class="hidden print:inline">Chq #</span>
                                            </th>
                                            <th class="text-left">
                                                <span class="print:hidden"><x-tooltip
                                                        text="Customer Name">CN</x-tooltip></span>
                                                <span class="hidden print:inline">Customer</span>
                                            </th>
                                            <th class="text-left">
                                                <span class="print:hidden"><x-tooltip
                                                        text="Bank Name">Bank</x-tooltip></span>
                                                <span class="hidden print:inline">Bank</span>
                                            </th>
                                            <th class="text-left">
                                                <span class="print:hidden"><x-tooltip text="Deposit Bank">Dep
                                                        Bank</x-tooltip></span>
                                                <span class="hidden print:inline">Dep Bank</span>
                                            </th>
                                            <th class="text-right">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="tabular-nums">
                                        @if($maxRows2 > 0)
                                            @for($i = 0; $i < $maxRows2; $i++)
                                                @php $cheque = $settlement->cheques->get($i); @endphp
                                                <tr>
                                                    <td class="text-center">{{ $i + 1 }}</td>
                                                    <td>{{ $cheque && $cheque->cheque_date ? \Carbon\Carbon::parse($cheque->cheque_date)->format('d-M-y') : '-' }}
                                                    </td>
                                                    <td>{{ $cheque->cheque_number ?? '-' }}</td>
                                                    <td>{{ $cheque->customer->customer_name ?? '-' }}</td>
                                                    <td>{{ $cheque->bank_name ?? '-' }}</td>
                                                    <td>{{ $cheque->bankAccount->account_name ?? '-' }}</td>
                                                    <td class="text-right font-bold">
                                                        {{ $cheque ? number_format($cheque->amount, 2) : '-' }}
                                                    </td>
                                                </tr>
                                            @endfor
                                        @else
                                            <tr>
                                                <td colspan="7" class="text-center italic text-gray-500">No cheques recorded
                                                </td>
                                            </tr>
                                        @endif
                                    </tbody>
                                    <tfoot class="bg-gray-50 font-bold tabular-nums">
                                        <tr>
                                            <td colspan="6" class="text-right">Total:</td>
                                            <td class="text-right">
                                                {{ number_format($settlement->cheques->sum('amount'), 2) }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            {{-- Bank Transfers --}}
                            <div>
                                <h4 class="font-bold text-sm border-x border-t border-black  text-center">Bank Transfers
                                    / Online From Customer</h4>
                                <table class="report-table w-full">
                                    <thead>
                                        <tr class="bg-gray-100">
                                            <th class="text-center w-10">#</th>
                                            <th class="text-left">
                                                <span class="print:hidden"><x-tooltip
                                                        text="Transfer Date">Date</x-tooltip></span>
                                                <span class="hidden print:inline">Date</span>
                                            </th>
                                            <th class="text-left">
                                                <span class="print:hidden"><x-tooltip
                                                        text="Customer Name">CN</x-tooltip></span>
                                                <span class="hidden print:inline">Customer</span>
                                            </th>
                                            <th class="text-left">
                                                <span class="print:hidden"><x-tooltip
                                                        text="Bank Account">Bank</x-tooltip></span>
                                                <span class="hidden print:inline">Bank</span>
                                            </th>
                                            <th class="text-left">
                                                <span class="print:hidden"><x-tooltip text="Reference Number">Ref
                                                        #</x-tooltip></span>
                                                <span class="hidden print:inline">Ref #</span>
                                            </th>
                                            <th class="text-right">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="tabular-nums">
                                        @if($maxRows2 > 0)
                                            @for($i = 0; $i < $maxRows2; $i++)
                                                @php $transfer = $settlement->bankTransfers->get($i); @endphp
                                                <tr>
                                                    <td class="text-center">{{ $i + 1 }}</td>
                                                    <td>{{ $transfer && $transfer->transfer_date ? \Carbon\Carbon::parse($transfer->transfer_date)->format('d-M-y') : '-' }}
                                                    </td>
                                                    <td>{{ $transfer->customer->customer_name ?? '-' }}</td>
                                                    <td>{{ $transfer->bankAccount->account_name ?? '-' }}</td>
                                                    <td>{{ $transfer->reference_number ?? '-' }}</td>
                                                    <td class="text-right font-bold">
                                                        {{ $transfer ? number_format($transfer->amount, 2) : '-' }}
                                                    </td>
                                                </tr>
                                            @endfor
                                        @else
                                            <tr>
                                                <td colspan="6" class="text-center italic text-gray-500">No bank transfers
                                                    recorded</td>
                                            </tr>
                                        @endif
                                    </tbody>
                                    <tfoot class="bg-gray-50 font-bold tabular-nums">
                                        <tr>
                                            <td colspan="5" class="text-right">Total:</td>
                                            <td class="text-right">
                                                {{ number_format($settlement->bankTransfers->sum('amount'), 2) }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>


                    </div>

                    {{-- Expense Details Group --}}
                    <div class="border-2 border-black rounded-lg px-2 pb-2 mt-2">
                        <h3 class="font-bold text-md text-center text-black  pb-1">Expense Details</h3>

                        {{-- Row 1: AMR Powder | AMR Liquid --}}
                        @php
                            $amrPowderCount = $settlement->amrPowders->count();
                            $amrLiquidCount = $settlement->amrLiquids->count();
                            $maxRowsAmr = max($amrPowderCount, $amrLiquidCount, 1);
                        @endphp

                        <div class="grid grid-cols-2 gap-1 items-start print:grid-cols-2">
                            {{-- AMR Powder --}}
                            <div>
                                <h4 class="font-bold text-sm border-x border-t border-black  text-center">AMR Powder
                                    (5252)</h4>
                                <table class="report-table w-full">
                                    <thead>
                                        <tr class="bg-gray-100">
                                            <th class="text-center w-6 px-1 py-0.5">#</th>
                                            <th class="text-center px-1 py-0.5">
                                                <span class="print:hidden"><x-tooltip
                                                        text="Product Name">SKU</x-tooltip></span>
                                                <span class="hidden print:inline">Product</span>
                                            </th>
                                            <th class="text-center px-1 py-0.5">Qty</th>
                                            <th class="text-center px-1 py-0.5">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="tabular-nums">
                                        @for($i = 0; $i < $maxRowsAmr; $i++)
                                            @php $powder = $settlement->amrPowders->get($i); @endphp
                                            <tr>
                                                <td class="text-center px-1 py-0.5">{{ $i + 1 }}</td>
                                                <td class="px-1 py-0.5">{{ $powder?->product->product_name ?? '-' }}</td>
                                                <td class="text-right px-1 py-0.5">
                                                    {{ $powder ? number_format($powder->quantity, 2) : '-' }}
                                                </td>
                                                <td class="text-right font-bold px-1 py-0.5">
                                                    {{ $powder ? number_format($powder->amount, 2) : '-' }}
                                                </td>
                                            </tr>
                                        @endfor
                                    </tbody>
                                    <tfoot class="bg-gray-50 font-bold tabular-nums">
                                        <tr>
                                            <td colspan="3" class="text-right px-1 py-0.5">Total:</td>
                                            <td class="text-right px-1 py-0.5">
                                                {{ number_format($settlement->amrPowders->sum('amount'), 2) }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            {{-- AMR Liquid --}}
                            <div>
                                <h4 class="font-bold text-sm border-x border-t border-black   text-center">AMR Liquid
                                    (5262)</h4>
                                <table class="report-table w-full">
                                    <thead>
                                        <tr class="bg-gray-100">
                                            <th class="text-center w-6 px-1 py-0.5">#</th>
                                            <th class="text-center px-1 py-0.5">
                                                <span class="print:hidden"><x-tooltip
                                                        text="Product Name">SKU</x-tooltip></span>
                                                <span class="hidden print:inline">Product</span>
                                            </th>
                                            <th class="text-center px-1 py-0.5">Qty</th>
                                            <th class="text-center px-1 py-0.5">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="tabular-nums">
                                        @for($i = 0; $i < $maxRowsAmr; $i++)
                                            @php $liquid = $settlement->amrLiquids->get($i); @endphp
                                            <tr>
                                                <td class="text-center px-1 py-0.5">{{ $i + 1 }}</td>
                                                <td class="px-1 py-0.5">{{ $liquid?->product->product_name ?? '-' }}</td>
                                                <td class="text-right px-1 py-0.5">
                                                    {{ $liquid ? number_format($liquid->quantity, 2) : '-' }}
                                                </td>
                                                <td class="text-right font-bold px-1 py-0.5">
                                                    {{ $liquid ? number_format($liquid->amount, 2) : '-' }}
                                                </td>
                                            </tr>
                                        @endfor
                                    </tbody>
                                    <tfoot class="bg-gray-50 font-bold tabular-nums">
                                        <tr>
                                            <td colspan="3" class="text-right px-1 py-0.5">Total:</td>
                                            <td class="text-right px-1 py-0.5">
                                                {{ number_format($settlement->amrLiquids->sum('amount'), 2) }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        {{-- Row 2: Advance Tax | Percentage Expense --}}
                        @php
                            $advTaxCount = $advanceTaxEntries->count();
                            $pctExpCount = $settlement->percentageExpenses->count();
                            $maxRowsTax = max($advTaxCount, $pctExpCount, 1);
                        @endphp

                        <div class="grid grid-cols-2 gap-1 items-start print:grid-cols-2 mt-1">
                            {{-- Advance Tax --}}
                            <div>
                                <h4 class="font-bold text-sm border-x border-t border-black text-center">Advance
                                    Tax{{ $usesAdvanceTaxIncome ? ' - Income' : '' }}
                                    Benifits To NTN Customer
                                    (1161)</h4>
                                <table class="report-table w-full">
                                    <thead>
                                        <tr class="bg-gray-100">
                                            <th class="text-center w-6 px-1 py-0.5">#</th>
                                            <th class="text-center px-1 py-0.5">
                                                <span class="print:hidden"><x-tooltip
                                                        text="Customer Name (Code)">Customer</x-tooltip></span>
                                                <span class="hidden print:inline">Customer</span>
                                            </th>
                                            <th class="text-center px-1 py-0.5">Inv #</th>
                                            <th class="text-center px-1 py-0.5">Tax</th>
                                        </tr>
                                    </thead>
                                    <tbody class="tabular-nums">
                                        @for($i = 0; $i < $maxRowsTax; $i++)
                                            @php $tax = $advanceTaxEntries->get($i); @endphp
                                            <tr>
                                                <td class="text-center px-1 py-0.5">{{ $i + 1 }}</td>
                                                <td class="px-1 py-0.5">
                                                    {{ $tax ? $tax->customer->customer_name . ' (' . $tax->customer->customer_code . ')' : '-' }}
                                                </td>
                                                <td class="text-right px-1 py-0.5">{{ $tax?->invoice_number ?? '-' }}</td>
                                                <td class="text-right font-bold px-1 py-0.5">
                                                    {{ $tax ? number_format($tax->tax_amount, 2) : '-' }}
                                                </td>
                                            </tr>
                                        @endfor
                                    </tbody>
                                    <tfoot class="bg-gray-50 font-bold tabular-nums">
                                        <tr>
                                            <td colspan="3" class="text-right px-1 py-0.5">Total:</td>
                                            <td class="text-right px-1 py-0.5">
                                                {{ number_format($advanceTaxEntries->sum('tax_amount'), 2) }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            {{-- Percentage Expense --}}
                            <div>
                                <h4 class="font-bold text-sm border-x border-t border-black text-center">Percentage
                                    Expense (5223)</h4>
                                <table class="report-table w-full">
                                    <thead>
                                        <tr class="bg-gray-100">
                                            <th class="text-center w-6 px-1 py-0.5">#</th>
                                            <th class="text-center px-1 py-0.5">
                                                <span class="print:hidden"><x-tooltip
                                                        text="Customer Name (Code)">Customer</x-tooltip></span>
                                                <span class="hidden print:inline">Customer</span>
                                            </th>
                                            <th class="text-center px-1 py-0.5">Inv #</th>
                                            <th class="text-center px-1 py-0.5">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="tabular-nums">
                                        @for($i = 0; $i < $maxRowsTax; $i++)
                                            @php $pctExp = $settlement->percentageExpenses->get($i); @endphp
                                            <tr>
                                                <td class="text-center px-1 py-0.5">{{ $i + 1 }}</td>
                                                <td class="px-1 py-0.5">
                                                    {{ $pctExp ? $pctExp->customer->customer_name . ' (' . $pctExp->customer->customer_code . ')' : '-' }}
                                                </td>
                                                <td class="px-1 py-0.5">{{ $pctExp?->invoice_number ?? '-' }}</td>
                                                <td class="text-right font-bold px-1 py-0.5">
                                                    {{ $pctExp ? number_format($pctExp->amount, 2) : '-' }}
                                                </td>
                                            </tr>
                                        @endfor
                                    </tbody>
                                    <tfoot class="bg-gray-50 font-bold tabular-nums">
                                        <tr>
                                            <td colspan="3" class="text-right px-1 py-0.5">Total:</td>
                                            <td class="text-right px-1 py-0.5">
                                                {{ number_format($settlement->percentageExpenses->sum('amount'), 2) }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        {{-- Other Expenses --}}
                        @php
                            // Dynamically resolve predefined expense account IDs from account codes
                            $predefinedExpenseCodes = ['5272', '5252', '5262', '5292', '1161', '5282', '5223', '5221'];
                            $predefinedAccountsMap = \App\Models\ChartOfAccount::whereIn('account_code', $predefinedExpenseCodes)
                                ->pluck('id', 'account_code')
                                ->toArray();

                            // Define predefined expense accounts in order (matching create/edit page)
                            $predefinedExpenses = [
                                ['id' => $predefinedAccountsMap['5272'] ?? null, 'label' => 'Toll Tax', 'code' => '5272'],
                                ['id' => $predefinedAccountsMap['5252'] ?? null, 'label' => 'AMR Powder', 'code' => '5252'],
                                ['id' => $predefinedAccountsMap['5262'] ?? null, 'label' => 'AMR Liquid', 'code' => '5262'],
                                ['id' => $predefinedAccountsMap['5292'] ?? null, 'label' => 'Scheme Discount Expense', 'code' => '5292'],
                                ['id' => $predefinedAccountsMap['1161'] ?? null, 'label' => 'Advance Tax', 'code' => '1161'],
                                ['id' => $predefinedAccountsMap['5282'] ?? null, 'label' => 'Food/Salesman/Loader Charges', 'code' => '5282'],
                                ['id' => $predefinedAccountsMap['5223'] ?? null, 'label' => 'Percentage Expense', 'code' => '5223'],
                                ['id' => $predefinedAccountsMap['5221'] ?? null, 'label' => 'Miscellaneous Expenses', 'code' => '5221'],
                            ];
                            $predefinedIds = collect($predefinedExpenses)->pluck('id')->filter()->toArray();

                            // Codes that have detailed breakdowns shown in tables above
                            $detailedBreakdownCodes = ['5252', '5262', '1161', '5223'];

                            // Get saved expense amounts indexed by account ID
                            $savedExpenseAmounts = $settlement->expenses->keyBy('expense_account_id');

                            // Get any additional expenses not in predefined list
                            $additionalExpenses = $settlement->expenses->filter(function ($expense) use ($predefinedIds) {
                                return !in_array($expense->expense_account_id, $predefinedIds);
                            });

                            // Prepare Group Expenses Rows
                            $groupExpenseRows = [];
                            foreach ($predefinedExpenses as $predef) {
                                $savedExpense = $savedExpenseAmounts->get($predef['id']);
                                $amount = $savedExpense ? $savedExpense->amount : 0;

                                // Income-mode settlements still show the 1161
                                // row in Group Expenses, but the footer total
                                // remains the persisted expense sum.
                                if ($usesAdvanceTaxIncome && $predef['code'] === '1161') {
                                    $amount = $advanceTaxIncomeTotal;
                                }
                                $groupExpenseRows[] = [
                                    'label' => $usesAdvanceTaxIncome && $predef['code'] === '1161'
                                        ? $predef['label'] . ' - Income'
                                        : $predef['label'],
                                    'code' => $predef['code'],
                                    'amount' => $amount,
                                    'is_predefined' => true,
                                    'id' => $predef['id']
                                ];
                            }
                            foreach ($additionalExpenses as $expense) {
                                $groupExpenseRows[] = [
                                    'label' => $expense->expenseAccount->account_name ?? 'Unknown',
                                    'code' => $expense->expenseAccount->account_code ?? '-',
                                    'amount' => $expense->amount,
                                    'is_predefined' => false
                                ];
                            }

                            // Prepare Cash Detail Rows
                            $cashDetailRows = [];
                            $denominations = [
                                ['label' => '5000', 'qty' => $cashDenominations?->denom_5000 ?? 0, 'value' => 5000],
                                ['label' => '1000', 'qty' => $cashDenominations?->denom_1000 ?? 0, 'value' => 1000],
                                ['label' => '500', 'qty' => $cashDenominations?->denom_500 ?? 0, 'value' => 500],
                                ['label' => '100', 'qty' => $cashDenominations?->denom_100 ?? 0, 'value' => 100],
                                ['label' => '50', 'qty' => $cashDenominations?->denom_50 ?? 0, 'value' => 50],
                                ['label' => '20', 'qty' => $cashDenominations?->denom_20 ?? 0, 'value' => 20],
                                ['label' => '10', 'qty' => $cashDenominations?->denom_10 ?? 0, 'value' => 10],
                            ];

                            $calculatedCash = 0;
                            foreach ($denominations as $d) {
                                $rowVal = $d['qty'] * $d['value'];
                                $calculatedCash += $rowVal;
                                $cashDetailRows[] = [
                                    'label' => $d['label'],
                                    'qty' => $d['qty'],
                                    'value' => $rowVal,
                                    'is_coin' => false
                                ];
                            }
                            // Add Coins
                            $cashDetailRows[] = [
                                'label' => 'Coins/Loose',
                                'qty' => '-',
                                'value' => $coins,
                                'is_coin' => true
                            ];
                            $calculatedCash += $coins;

                            // Max Rows for Equal Height
                            $maxRowsExp = max(count($groupExpenseRows), count($cashDetailRows));
                        @endphp

                    </div> {{-- End of Expense Details Group --}}

                    {{-- Other Expenses & Cash Detail Row --}}
                    {{-- Group 1: Expenses & Collections Detail --}}
                    <div class="border-2 border-black rounded-lg px-2 pb-2 mt-4 ss-keep">
                        <h3 class="font-bold text-md text-center text-black pb-1 border-b border-black mb-2">Expenses &
                            Cash/Bank Deposits Detail</h3>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-1 items-start print:flex print:gap-1"
                            style="page-break-inside: avoid; break-inside: avoid;">
                            {{-- 1. Group Expenses --}}
                            <div class="flex flex-col h-full print:w-1/3">
                                <h4 class="font-bold text-sm border-x border-t border-black text-center">Group Expenses
                                </h4>
                                <table class="report-table w-full flex-grow tabular-nums">
                                    <thead>
                                        <tr class="bg-gray-100">
                                            <th class="text-center w-6 px-1 py-0.5">#</th>
                                            <th class="text-left px-1 py-0.5">Expense Account</th>
                                            <th class="text-center px-1 py-0.5">COA Code</th>
                                            <th class="text-right px-1 py-0.5">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @for($i = 0; $i < $maxRowsExp; $i++)
                                            @php $expRow = $groupExpenseRows[$i] ?? null; @endphp
                                            <tr>
                                                <td class="text-center px-1 py-0.5">{{ $i + 1 }}</td>
                                                @if($expRow)
                                                    <td class="px-1 py-0.5">{{ $expRow['label'] }}</td>
                                                    <td class="text-center px-1 py-0.5">
                                                        @if(isset($expRow['is_predefined']) && $expRow['is_predefined'] && in_array($expRow['code'], $detailedBreakdownCodes))
                                                            <span
                                                                class="print:hidden underline decoration-dotted cursor-help"><x-tooltip
                                                                    text="See detailed breakdown in the table above">{{ $expRow['code'] }}</x-tooltip></span>
                                                            <span class="hidden print:inline">{{ $expRow['code'] }}</span>
                                                        @else
                                                            {{ $expRow['code'] }}
                                                        @endif
                                                    </td>
                                                    <td class="text-right font-bold px-1 py-0.5">
                                                        {{ number_format($expRow['amount'], 2) }}
                                                    </td>
                                                @else
                                                    <td class="px-1 py-0.5 border-none">&nbsp;</td>
                                                    <td class="text-center px-1 py-0.5 border-none">&nbsp;</td>
                                                    <td class="text-right font-bold px-1 py-0.5 border-none">&nbsp;</td>
                                                @endif
                                            </tr>
                                        @endfor
                                    </tbody>
                                    <tfoot class="bg-gray-50 font-bold">
                                        <tr>
                                            <td colspan="3" class="text-right px-1 py-0.5 border-t border-black">Total:
                                            </td>
                                            <td class="text-right px-1 py-0.5 border-t border-black">
                                                {{ number_format($settlement->expenses->sum('amount'), 2) }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            {{-- 2. Cash Detail --}}
                            <div class="flex flex-col h-full print:w-1/3">
                                <h4 class="font-bold text-sm border-x border-t border-black text-center">Cash Detail
                                </h4>
                                <table class="report-table w-full flex-grow tabular-nums">
                                    <thead>
                                        <tr class="bg-gray-100">
                                            <th class="text-center px-1 py-0.5">#</th>
                                            <th class="text-left px-1 py-0.5 ">Note</th>
                                            <th class="text-right px-1 py-0.5">Qty</th>
                                            <th class="text-right px-1 py-0.5">Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @for($i = 0; $i < $maxRowsExp; $i++)
                                            @php $cashRow = $cashDetailRows[$i] ?? null; @endphp
                                            <tr>
                                                <td class="text-center px-1 py-0.5">{{ $i + 1 }}</td>
                                                @if($cashRow)
                                                    <td class="px-1 py-0.5">{{ $cashRow['label'] }}</td>
                                                    <td class="text-right px-1 py-0.5">{{ $cashRow['qty'] }}</td>
                                                    <td class="text-right font-bold px-1 py-0.5">
                                                        {{ number_format($cashRow['value'], isset($cashRow['is_coin']) && $cashRow['is_coin'] ? 2 : 0) }}
                                                    </td>
                                                @else
                                                    <td class="px-1 py-0.5 border-none">&nbsp;</td>
                                                    <td class="text-right px-1 py-0.5 border-none">&nbsp;</td>
                                                    <td class="text-right font-bold px-1 py-0.5 border-none">&nbsp;</td>
                                                @endif
                                            </tr>
                                        @endfor
                                    </tbody>
                                    <tfoot class="bg-gray-50 font-bold">
                                        <tr>
                                            <td colspan="3" class="text-right px-1 py-0.5 border-t border-black">Total
                                                Physical Cash:</td>
                                            <td class="text-right px-1 py-0.5 border-t border-black">
                                                {{ number_format($calculatedCash, 2) }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            {{-- 3. Bank Slips / Deposits --}}
                            <div class="flex flex-col h-full print:w-1/3">
                                <h4 class="font-bold text-sm border-x border-t border-black text-center">Bank Slips /
                                    Deposits to Bank From Salesman</h4>
                                <table class="report-table w-full flex-grow tabular-nums">
                                    <thead>
                                        <tr class="bg-gray-100">
                                            <th class="text-center w-8 px-1 py-0.5">#</th>
                                            <th class="text-left px-1 py-0.5">Bank</th>
                                            <th class="text-center px-1 py-0.5">Date</th>
                                            <th class="text-right px-1 py-0.5">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $slipCount = 0; @endphp
                                        @foreach($settlement->bankSlips as $index => $slip)
                                            @php $slipCount++; @endphp
                                            <tr>
                                                <td class="text-center px-1 py-0.5">{{ $index + 1 }}</td>
                                                <td class="px-1 py-0.5 text-xs">
                                                    {{ $slip->bankAccount->account_name ?? '-' }}
                                                </td>
                                                <td class="text-center px-1 py-0.5 text-xs">
                                                    {{ $slip->deposit_date ? \Carbon\Carbon::parse($slip->deposit_date)->format('d-M-y') : '-' }}
                                                </td>
                                                <td class="text-right px-1 py-0.5 font-bold">
                                                    {{ number_format($slip->amount, 2) }}
                                                </td>
                                            </tr>
                                        @endforeach

                                        {{-- Filler rows for height symmetry --}}
                                        @for($i = $slipCount + 1; $i <= $maxRowsExp; $i++)
                                            <tr>
                                                <td class="text-center px-1 py-0.5 border-none">&nbsp;</td>
                                                <td class="px-1 py-0.5 border-none">&nbsp;</td>
                                                <td class="text-center px-1 py-0.5 border-none">&nbsp;</td>
                                                <td class="text-right px-1 py-0.5 border-none">&nbsp;</td>
                                            </tr>
                                        @endfor
                                    </tbody>
                                    <tfoot class="bg-gray-50 font-bold">
                                        <tr>
                                            <td colspan="3" class="text-right px-1 py-0.5 border-t border-black">Total
                                                Bank Slips:</td>
                                            <td class="text-right px-1 py-0.5 border-t border-black">
                                                {{ number_format($settlement->bankSlips->sum('amount'), 2) }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>



                    {{-- Financial Summary --}}
                    {{-- Financial Summary --}}
                    {{-- Financial Summary Grid --}}
                    {{-- Group 2: Sales & Collection Summary --}}
                    <div class="border-2 border-black rounded-lg px-2 pb-2 mt-4 ss-keep">
                        <h3 class="font-bold text-md text-center text-black pb-1 border-b border-black mb-2">Sales &
                            Collection Summary</h3>

                        <div class="flex flex-row gap-1" style="page-break-inside: avoid; break-inside: avoid;">
                            {{-- 1. Sales Summary --}}
                            <div class="flex flex-col h-full w-1/2">
                                <h4 class="font-bold text-sm border-x border-t border-black text-center mt-2">Sales
                                    Summary</h4>
                                <table class="report-table w-full flex-grow tabular-nums">
                                    <thead>
                                        <tr class="bg-gray-100">
                                            <th class="text-center w-8 px-1 py-0.5">#</th>
                                            <th class="text-left px-1 py-0.5">Description</th>
                                            <th class="text-right px-1 py-0.5">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">1</td>
                                            <td class="px-1 py-0.5">
                                                Credit Sale Amount <span class="text-xs font-normal text-black">(Σ
                                                    Credit sales entered on-credit invoices)</span>
                                            </td>
                                            <td class="text-right px-1 py-0.5 font-bold">
                                                {{ number_format($settlement->credit_sales_amount, 2) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">2</td>
                                            <td class="px-1 py-0.5">
                                                Cheque Sale Amount <span class="text-xs font-normal text-black">(Σ
                                                    Cheque payments collected from customers)</span>
                                            </td>
                                            <td class="text-right px-1 py-0.5 font-bold">
                                                {{ number_format($settlement->cheque_sales_amount, 2) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">3</td>
                                            <td class="px-1 py-0.5">
                                                Bank Transfer Amount <span class="text-xs font-normal text-black">(Σ
                                                    Direct bank / online transfers received)</span>
                                            </td>
                                            <td class="text-right px-1 py-0.5 font-bold">
                                                {{ number_format($settlement->bank_transfer_amount, 2) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">4</td>
                                            <td class="px-1 py-0.5">
                                                Cash Sale Amount <span class="text-xs font-normal text-black">(Net Sale
                                                    − Credit − Bank Transfers)</span>
                                            </td>
                                            <td class="text-right px-1 py-0.5 font-bold">
                                                {{ number_format($settlement->cash_sales_amount, 2) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">5</td>
                                            <td class="px-1 py-0.5">
                                                Bank Slips / Deposits <span class="text-xs font-normal text-black">(Σ
                                                    Cash deposited directly to bank by salesman)</span>
                                            </td>
                                            <td class="text-right px-1 py-0.5 font-bold">
                                                {{ number_format($settlement->bankSlips->sum('amount'), 2) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">6</td>
                                            <td class="px-1 py-0.5 font-bold">
                                                Net Sale (Sold Items Value) <span
                                                    class="text-xs font-normal text-black">(Credit + Cheque + Bank +
                                                    Cash)</span>
                                            </td>
                                            <td class="text-right px-1 py-0.5 font-black border-t border-black">
                                                {{ number_format($netSale, 2) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">7</td>
                                            <td class="px-1 py-0.5">
                                                Return Value <span class="text-xs font-normal text-black">(Σ Returned
                                                    items × unit price)</span>
                                            </td>
                                            <td class="text-right px-1 py-0.5">
                                                {{ number_format($settlement->sales_return_amount, 2) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">8</td>
                                            <td class="px-1 py-0.5">
                                                Shortage Value <span class="text-xs font-normal text-black">(Σ Shortage
                                                    items × unit price)</span>
                                            </td>
                                            <td class="text-right px-1 py-0.5">
                                                {{ number_format($settlement->shortage_amount, 2) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">9</td>
                                            <td class="px-1 py-0.5">
                                                Recovery (Cash) <span class="text-xs font-normal text-black">(Σ Previous
                                                    credit balances recovered in cash)</span>
                                            </td>
                                            <td class="text-right px-1 py-0.5">
                                                {{ number_format($recoveryCash, 2) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">10</td>
                                            <td class="px-1 py-0.5">
                                                Recovery (Bank/Online) <span class="text-xs font-normal text-black">(Σ
                                                    Previous credit balances recovered via bank)</span>
                                            </td>
                                            <td class="text-right px-1 py-0.5">
                                                {{ number_format($recoveryBank, 2) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">11</td>
                                            <td class="px-1 py-0.5 font-bold">
                                                Total Sale Amount <span class="text-xs font-normal text-black">(= Row 6,
                                                    total invoiced value)</span>
                                            </td>
                                            <td class="text-right px-1 py-0.5 font-black border-t border-black">
                                                {{ number_format($netSale, 2) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">12</td>
                                            <td class="px-1 py-0.5 font-semibold text-blue-700">
                                                Expected Cash (Sales + Cash Recoveries) <span
                                                    class="text-xs font-normal text-black">(Row 4 + Row 9)</span>
                                            </td>
                                            <td class="text-right px-1 py-0.5 font-bold text-blue-700">
                                                {{ number_format($expectedCashGross, 2) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">13</td>
                                            <td class="px-1 py-0.5 text-red-700">
                                                Less: Expenses <span class="text-xs font-normal text-black">(Σ Expenses
                                                    paid by salesman from cash)</span>
                                            </td>
                                            <td class="text-right px-1 py-0.5 text-red-700 font-semibold">
                                                {{ number_format($totalExpenses, 2) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">14</td>
                                            <td class="px-1 py-0.5 font-bold text-blue-900 bg-blue-50">
                                                Expected Cash (After Expenses) <span
                                                    class="text-xs font-normal text-black">(Row 12 − Row 13)</span>
                                            </td>
                                            <td
                                                class="text-right px-1 py-0.5 font-black text-blue-900 bg-blue-50 border-t border-blue-900">
                                                {{ number_format($expectedCashNet, 2) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">15</td>
                                            <td class="px-1 py-0.5 font-bold">
                                                Physical Cash Submitted (Denominations) <span
                                                    class="text-xs font-normal text-black">(Σ Cash notes + coins)</span>
                                            </td>
                                            <td class="text-right px-1 py-0.5 font-black border-2 border-black">
                                                {{ number_format($calculatedCash, 2) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">16</td>
                                            <td class="px-1 py-0.5 font-semibold">
                                                Short/Excess <span class="text-xs font-normal text-black">((Cash +
                                                    Cheques + Bank Slips) − Row 14)</span>
                                            </td>
                                            <td
                                                class="text-right px-1 py-0.5 font-bold {{ $shortExcess < 0 ? 'text-red-700' : 'text-green-700' }}">
                                                {{ number_format($shortExcess, 2) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="bg-gray-50 border-t border-black">
                                        <tr>
                                            <td colspan="3" class="py-1">&nbsp;</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            {{-- 2. Profit Analysis --}}
                            <div class="flex flex-col h-full w-1/2">
                                <h4 class="font-bold text-sm border-x border-t border-black text-center mt-2">Profit
                                    Analysis</h4>
                                <table class="report-table w-full flex-grow tabular-nums">
                                    <thead>
                                        <tr class="bg-gray-100">
                                            <th class="text-center w-8 px-1 py-0.5">#</th>
                                            <th class="text-left px-1 py-0.5">Description</th>
                                            <th class="text-right px-1 py-0.5">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">1</td>
                                            <td class="px-1 py-0.5">Net Sales Revenue (Sold Items)</td>
                                            <td class="text-right px-1 py-0.5 font-semibold">
                                                {{ number_format($netSale, 2) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">2</td>
                                            <td class="px-1 py-0.5 text-red-700">Less: Cost of Goods Sold (COGS)</td>
                                            <td class="text-right px-1 py-0.5 text-red-700 font-semibold">
                                                {{ number_format($totalCOGS, 2) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">3</td>
                                            <td class="px-1 py-0.5 font-semibold text-green-700">Gross Profit (Sales -
                                                COGS)</td>
                                            <td class="text-right px-1 py-0.5 font-bold text-green-700">
                                                {{ number_format($grossProfit, 2) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">4</td>
                                            <td class="px-1 py-0.5 text-gray-600 pl-4">Gross Margin %</td>
                                            <td class="text-right px-1 py-0.5 font-semibold">
                                                {{ number_format($grossMargin, 2) }}%
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">5</td>
                                            <td class="px-1 py-0.5 text-red-700">Less: Operating Expenses</td>
                                            <td class="text-right px-1 py-0.5 text-red-700 font-semibold">
                                                {{ number_format($totalExpenses, 2) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">6</td>
                                            <td
                                                class="px-1 py-0.5 font-semibold {{ $netProfit >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                                Net Profit (After Expenses)</td>
                                            <td
                                                class="text-right px-1 py-0.5 font-bold {{ $netProfit >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                                {{ number_format($netProfit, 2) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">7</td>
                                            <td class="px-1 py-0.5 text-gray-600 pl-4">Net Margin %</td>
                                            <td
                                                class="text-right px-1 py-0.5 font-semibold {{ $netMargin >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                                {{ number_format($netMargin, 2) }}%
                                            </td>
                                        </tr>
                                        {{-- Filler rows to match Sales Summary Height --}}
                                        @for($i = 8; $i <= 16; $i++)
                                            <tr>
                                                <td class="text-center px-1 py-0.5 border-none">&nbsp;</td>
                                                <td class="px-1 py-0.5 border-none">&nbsp;</td>
                                                <td class="text-right px-1 py-0.5 border-none">&nbsp;</td>
                                            </tr>
                                        @endfor
                                    </tbody>
                                    <tfoot class="bg-gray-50 border-t border-black">
                                        <tr>
                                            <td colspan="3" class="py-1">&nbsp;</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>



                    {{-- Credit Report Section --}}
                    <div class="w-full clear-both mt-8 print:mt-16 block h-8 print:h-16" style="clear: both;">&nbsp;
                    </div>

                    @php
                        $employeeId = $settlement->employee_id;
                        $settlementDate = $settlement->settlement_date;

                        $customerAccounts = \App\Models\CustomerEmployeeAccount::with('customer')
                            ->where('employee_id', $employeeId)
                            ->where('status', 'active')
                            ->get();

                        $settlementCredits = $settlement->creditSales->groupBy('customer_id');
                        $settlementRecoveries = $settlement->recoveries->groupBy('customer_id');

                        $isPosted = $settlement->status === 'posted';
                        $accountIds = $customerAccounts->pluck('id');

                        $balanceMap = \App\Models\CustomerEmployeeAccountTransaction::query()
                            ->whereIn('customer_employee_account_id', $accountIds)
                            ->whereDate('transaction_date', '<=', $settlementDate)
                            ->groupBy('customer_employee_account_id')
                            ->selectRaw('customer_employee_account_id, SUM(debit - credit) as balance')
                            ->pluck('balance', 'customer_employee_account_id');

                        $creditReportData = $customerAccounts->map(function ($account) use ($settlement, $settlementDate, $settlementCredits, $settlementRecoveries, $isPosted, $balanceMap) {
                            $customerId = $account->customer_id;

                            $myCredits = $settlementCredits->get($customerId);
                            $myRecoveries = $settlementRecoveries->get($customerId);

                            $creditAmount = $myCredits ? $myCredits->sum('sale_amount') : 0;
                            $recoveryAmount = $myRecoveries ? $myRecoveries->sum('amount') : 0;
                            $hasActivity = ($creditAmount > 0 || $recoveryAmount > 0);

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
                                'date' => $settlementDate,
                                'opening_balance' => $openingBalance,
                                'credit_amount' => $creditAmount,
                                'recovery_amount' => $recoveryAmount,
                                'balance' => $closingBalance,
                                'has_activity' => $hasActivity || abs($closingBalance) > 1
                            ];
                        })->filter(function ($row) {
                            return $row->has_activity;
                        })->sortBy('customer_name');

                        $totalCreditGiven = $creditReportData->sum('credit_amount');
                        $totalRecoveryReceived = $creditReportData->sum('recovery_amount');
                        $totalClosingBalance = $creditReportData->sum('balance');
                    @endphp

                    <div class="rounded-lg pb-2 mt-2 w-full clear-both print:block" style="page-break-inside: auto;">
                        <h3 class="font-bold text-md text-center text-black border-x border-t border-black">
                            Credit Report: {{ $settlement->employee->name ?? 'Salesman' }}
                            ({{ \Carbon\Carbon::parse($settlementDate)->format('d-M-Y') }})
                        </h3>

                        <table class="report-table w-full tabular-nums text-sm">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="text-center w-10 border-b border-black">#</th>
                                    <th class="text-left border-b border-black">Party Name</th>
                                    <th class="text-left border-b border-black">
                                        <span class="print:hidden"><x-tooltip
                                                text="Customer Code">Code</x-tooltip></span>
                                        <span class="hidden print:inline">Code</span>
                                    </th>
                                    <th class="text-right w-24 border-b border-black">Opening Balance</th>
                                    <th class="text-right w-24 border-b border-black">Credit Amount</th>
                                    <th class="text-right w-24 border-b border-black">Recovery Amount</th>
                                    <th class="text-right w-28 border-b border-black">Closing Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($creditReportData as $index => $row)
                                    <tr>
                                        <td class="text-center py-1">{{ $loop->iteration }}</td>
                                        <td class="py-1 font-bold">
                                            {{ $row->customer_name }}{{ $row->address ? ' (' . $row->address . ')' : '' }}
                                        </td>
                                        <td class="py-1 text-center">{{ $row->customer_code }}</td>
                                        <td class="text-right py-1 text-gray-600">
                                            {{ number_format($row->opening_balance, 2) }}
                                        </td>
                                        <td class="text-right py-1 font-bold">
                                            {{ $row->credit_amount > 0 ? number_format($row->credit_amount, 2) : '-' }}
                                        </td>
                                        <td class="text-right py-1 font-bold">
                                            {{ $row->recovery_amount > 0 ? number_format($row->recovery_amount, 2) : '-' }}
                                        </td>
                                        <td class="text-right py-1 font-bold">{{ number_format($row->balance, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 italic text-gray-500">No active creditors
                                            found for this date.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="bg-gray-50 font-bold border-t-2 border-black">
                                <tr>
                                    <td colspan="4" class="text-right py-1 pr-2">Total (This Settlement):</td>
                                    <td class="text-right py-1">{{ number_format($totalCreditGiven, 2) }}</td>
                                    <td class="text-right py-1">{{ number_format($totalRecoveryReceived, 2) }}</td>
                                    <td class="text-right py-1">{{ number_format($totalClosingBalance, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>



                    @if ($settlement->notes)
                        <div class="mt-6 border p-2">
                            <h3 class="text-sm font-bold uppercase mb-1">Notes</h3>
                            <p class="text-sm">{{ $settlement->notes }}</p>
                        </div>
                    @endif

                    @if ($settlement->posted_at)
                        <div class="mt-2 text-xs text-gray-500 italic">
                            Posted on {{ $settlement->posted_at->format('d M Y, h:i A') }}
                            @if ($settlement->journalEntry)
                                | Journal Entry: <a href="{{ route('journal-entries.show', $settlement->journalEntry) }}"
                                    class="underline hover:text-blue-600">{{ $settlement->journalEntry->entry_number ?? 'JE #'.$settlement->journalEntry->id }}</a>
                            @endif
                        </div>
                    @endif




                    {{-- Final Signature Area --}}
                    <div class="ss-sign mt-16 grid grid-cols-3 gap-8 text-center print:flex print:justify-between"
                        style="page-break-inside: avoid;">
                        <div class="border-t border-black pt-2">
                            <p class="font-bold text-sm">Prepared By</p>
                        </div>
                        <div class="border-t border-black pt-2">
                            <p class="font-bold text-sm">Checked By</p>
                        </div>
                        <div class="border-t border-black pt-2">
                            <p class="font-bold text-sm">Authorized Signature</p>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        @if ($settlement->status === 'draft')
            @can('sales-settlement-post')
                <x-alpine-confirmation-modal eventName="open-sales-settlement-post-modal" title="Post Sales Settlement"
                    formAction="{{ route('sales-settlements.post', $settlement->id) }}" confirmButtonText="Post Settlement"
                    confirmButtonClass="bg-green-600 hover:bg-green-700" iconBgClass="bg-green-100"
                    iconColorClass="text-green-600" iconPath="M9 12.75 11.25 15 15 9.75m6 2.25a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z">
                    <div class="space-y-2 text-sm text-gray-600">
                        <p>Once posted, this settlement will update ledger balances and inventory records.</p>
                        <div class="rounded-md bg-gray-50 p-3 text-xs text-gray-700 space-y-1">
                            <p><span class="font-semibold">Settlement #:</span> {{ $settlement->settlement_number }}</p>
                            <p><span class="font-semibold">Date:</span>
                                {{ $settlement->settlement_date?->format('d-M-Y') ?? '-' }}
                            </p>
                            <p><span class="font-semibold">Salesman:</span> {{ $settlement->employee?->name ?? '-' }}</p>
                            <p><span class="font-semibold">Total Sales:</span>
                                {{ number_format((float) ($settlement->total_sales_amount ?? 0), 2) }}
                            </p>
                        </div>
                        <p class="font-medium text-gray-800">Do you want to continue?</p>
                    </div>
                </x-alpine-confirmation-modal>
            @endcan

            @can('sales-settlement-delete')
                <x-alpine-confirmation-modal eventName="open-sales-settlement-delete-modal" title="Delete Draft Settlement"
                    formAction="{{ route('sales-settlements.destroy', $settlement->id) }}" csrfMethod="DELETE"
                    confirmButtonText="Delete Draft" confirmButtonClass="bg-red-600 hover:bg-red-700" iconBgClass="bg-red-100"
                    iconColorClass="text-red-600"
                    iconPath="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0">
                    <div class="space-y-2 text-sm text-gray-600">
                        <p>This will permanently remove this draft sales settlement and all its draft line data.</p>
                        <div class="rounded-md bg-gray-50 p-3 text-xs text-gray-700 space-y-1">
                            <p><span class="font-semibold">Settlement #:</span> {{ $settlement->settlement_number }}</p>
                            <p><span class="font-semibold">Date:</span>
                                {{ $settlement->settlement_date?->format('d-M-Y') ?? '-' }}
                            </p>
                            <p><span class="font-semibold">Salesman:</span> {{ $settlement->employee?->name ?? '-' }}</p>
                            <p><span class="font-semibold">Status:</span> {{ strtoupper($settlement->status) }}</p>
                        </div>
                        <p class="font-medium text-gray-800">This action cannot be undone.</p>
                    </div>
                </x-alpine-confirmation-modal>
            @endcan
        @endif

        @if ($settlement->status === 'posted')
            @can('sales-settlement-revert')
                <x-password-confirm-modal id="revertSettlementModal" title="Confirm Settlement Revert"
                    message="WARNING: This will reverse ALL stock movements, inventory ledger entries, customer balances, and GL entries for this settlement and reset it to DRAFT."
                    warningClass="text-red-600" confirmButtonText="Confirm Revert"
                    confirmButtonClass="bg-red-600 hover:bg-red-700" />

                <script>
                    function confirmRevertSettlement() {
                        if (!confirm('Are you sure you want to REVERT this Sales Settlement?\n\nAll inventory, ledger, and GL entries will be reversed. This action cannot be undone.')) {
                            return false;
                        }

                        window.showPasswordModal('revertSettlementModal');
                        return false;
                    }

                    document.addEventListener('passwordConfirmed', function (event) {
                        const { modalId, password } = event.detail;

                        if (modalId === 'revertSettlementModal') {
                            document.getElementById('revert_password').value = password;
                            document.getElementById('revertSettlementForm').submit();
                        }
                    });
                </script>
            @endcan
        @endif
    <script>
        /** Loads the printable sheet in a hidden frame, then opens the browser print dialog for it. */
        function ssPrintSheet(button) {
            const label = button.querySelector('span');
            const frameId = 'ss-print-frame';
            document.getElementById(frameId)?.remove();
            const frame = document.createElement('iframe');
            frame.id = frameId;
            frame.setAttribute('aria-hidden', 'true');
            frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden';
            label.textContent = 'Preparing…';
            button.disabled = true;
            frame.onload = () => {
                label.textContent = 'Print';
                button.disabled = false;
                frame.contentWindow.focus();
                frame.contentWindow.print();
            };
            frame.src = button.dataset.printUrl;
            document.body.appendChild(frame);
        }

        // Ctrl+P / Cmd+P prints the same sheet as the Print button instead of the screen page.
        document.addEventListener('keydown', (event) => {
            if ((event.ctrlKey || event.metaKey) && !event.altKey && !event.shiftKey && event.key.toLowerCase() === 'p') {
                const button = document.querySelector('[data-print-url]');
                if (button && !button.disabled) {
                    event.preventDefault();
                    ssPrintSheet(button);
                }
            }
        });
    </script>
</x-app-layout>