<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Settlement Details') }}
            </h2>
            <div class="flex space-x-2 no-print">
                @if ($settlement->status === 'draft')
                    <a href="{{ route('sales-settlements.edit', $settlement) }}"
                        class="inline-flex items-center px-4 py-2 bg-amber-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-amber-700 transition">
                        <svg class="w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Edit
                    </a>
                    <form action="{{ route('sales-settlements.post', $settlement->id) }}" method="POST"
                        onsubmit="return confirm('Are you sure you want to post this Sales Settlement? This will record sales and update inventory.');"
                        class="inline-block">
                        @csrf
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 transition">
                            <svg class="w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Post Settlement
                        </button>
                    </form>
                    <form action="{{ route('sales-settlements.destroy', $settlement->id) }}" method="POST"
                        onsubmit="return confirm('Are you sure you want to delete this draft settlement?');"
                        class="inline-block">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 transition">
                            Delete Draft
                        </button>
                    </form>
                @endif
                <a href="{{ route('sales-settlements.index') }}"
                    class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                    Back
                </a>
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

                .max-w-7xl,
                .max-w-8xl {
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

                td, th {
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
        $totalSaleAmount = $totalSale;

        // Expenses & Taxes
        // Note: Advance tax is already stored as an expense row (account_code 1161),
        // so $totalExpenses already includes it. Do NOT add advanceTaxTotal separately.
        $totalExpenses = (float) ($settlement->expenses->sum('amount') ?? 0);
        $advanceTaxTotal = (float) ($settlement->advanceTaxes->sum('tax_amount') ?? 0);
        $totalDeductions = $totalExpenses;

        // Expected Cash Calculation (Professional Accounting)
        // Only Cash Sales and Cash Recoveries should be in the salesman's physical wallet
        $expectedCashGross = $cashSalesAmount + $recoveryCash;
        $expectedCashNet = $expectedCashGross - $totalDeductions;

        // Actual Physical Cash Collected
        $actualPhysicalCash = $cashDenominationTotal > 0 ? $cashDenominationTotal : (float) $settlement->cash_collected;
        $bankSlipsTotal = (float) $settlement->bankSlips->sum('amount');

        // Shortage/Excess (Physical Cash + Bank Slips vs Expected Cash)
        $shortExcess = ($actualPhysicalCash + $bankSlipsTotal) - $expectedCashNet;

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

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4 shadow-md no-print" />

            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mb-4 mt-4 print:shadow-none print:pb-0 p-4">
                
                {{-- Report Header --}}
                <div class="mb-2 report-header">
                    <table class="w-full text-sm">
                        <tr>
                            <td class="text-center font-extrabold text-xl" colspan="8">Moon Traders</td>
                        </tr>
                        <tr>
                            <td class="text-center font-bold text-lg" colspan="8">
                                Sales Settlement <span class="text-sm font-normal">({{ strtoupper($settlement->status) }})</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-left font-semibold">Settlement #:</td>
                            <td class="text-left">{{ $settlement->settlement_number }}</td>
                            <td class="text-left font-semibold">Date/Time:</td>
                            <td class="text-left">{{ \Carbon\Carbon::parse($settlement->settlement_date)->format('d-M-Y') }} {{ $settlement->created_at ? $settlement->created_at->format('h:i A') : '' }}</td>
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
                            <td class="text-left">{{ $settlement->goodsIssue->issue_number }}</td>
                            <td class="text-left font-semibold">GI Date/Time:</td>
                            <td class="text-left" colspan="5">{{ $settlement->goodsIssue->issue_date ? \Carbon\Carbon::parse($settlement->goodsIssue->issue_date)->format('d-M-Y') : '' }} {{ $settlement->goodsIssue->created_at ? $settlement->goodsIssue->created_at->format('h:i A') : '' }}</td>
                        </tr>
                        <tr>
                            <td class="text-left font-semibold">GI Issued By:</td>
                            <td class="text-left" colspan="7">{{ $settlement->goodsIssue->creator->name ?? $settlement->goodsIssue->issuedBy->name ?? '-' }}</td>
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

                                                    {{ number_format($b->quantity_issued, 0) }} × {{ number_format($b->selling_price, 2) }}

                                                    @if($b->is_promotional) (Promo) @endif
                                                    = {{ number_format($b->quantity_issued * $b->selling_price, 2) }} ({{ $b->batch_code ?? 'N/A' }})</span><br>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400">No batch data</span>
                                    @endif
                                </td>
                                <td class="text-right font-bold">{{ number_format($item->quantity_sold, 2) }}</td>
                                <td class="text-right">{{ number_format($item->quantity_returned, 2) }}</td>
                                <td class="text-right {{ $item->quantity_shortage > 0 ? 'text-red-600 font-bold' : '' }}">{{ number_format($item->quantity_shortage, 2) }}</td>
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
                            <td class="text-right">{{ number_format($settlement->items->sum('quantity_issued'), 2) }}</td>
                            <td></td>
                            <td class="text-right">{{ number_format($settlement->total_quantity_sold, 2) }}</td>
                            <td class="text-right">{{ number_format($settlement->total_quantity_returned, 2) }}</td>
                            <td class="text-right">{{ number_format($settlement->total_quantity_shortage, 2) }}</td>
                            <td class="text-right">-</td>
                            <td class="text-right">{{ number_format($settlement->items->sum('total_sales_value'), 2) }}</td>
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
                                <h4 class="font-bold text-sm border-x border-t border-black  text-center">Credit Sales Breakdown</h4>
                                <table class="report-table w-full">
                                    <thead>
                                        <tr class="bg-gray-100">
                                            <th class="text-center w-10">#</th>
                                            <th class="text-center">
                                                <span class="print:hidden"><x-tooltip text="Customer Name">Customer Name</x-tooltip></span>
                                                <span class="hidden print:inline">Name</span>
                                            </th>
                                            <th class="text-center">
                                                <span class="print:hidden"><x-tooltip text="Invoice Number">Inv #</x-tooltip></span>
                                                <span class="hidden print:inline">Inv #</span>
                                            </th>
                                            <th class="text-center">
                                                <span class="print:hidden"><x-tooltip text="Previous Balance">PB</x-tooltip></span>
                                                <span class="hidden print:inline">Pre Bal</span>
                                            </th>
                                            <th class="text-center">
                                                <span class="print:hidden"><x-tooltip text="Credit Sale">Sale</x-tooltip></span>
                                                <span class="hidden print:inline">Credit</span>
                                            </th>
                                            <th class="text-center">
                                                <span class="print:hidden"><x-tooltip text="New Balance">NB</x-tooltip></span>
                                                <span class="hidden print:inline">Bal</span>
                                            </th>
                                            <th class="text-center print:hidden">Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody class="tabular-nums">
                                        @if($maxRows > 0)
                                            @for($i = 0; $i < $maxRows; $i++)
                                                @php $creditSale = $settlement->creditSales->get($i); @endphp
                                                <tr>
                                                    <td class="text-center">{{ $i + 1 }}</td>
                                                    <td>{{ $creditSale?->customer->customer_name ?? '-' }}</td>
                                                    <td>{{ $creditSale?->invoice_number ?? '-' }}</td>
                                                    <td class="text-right">{{ $creditSale ? number_format($creditSale->previous_balance, 2) : '-' }}</td>
                                                    <td class="text-right font-bold">{{ $creditSale ? number_format($creditSale->sale_amount, 2) : '-' }}</td>
                                                    <td class="text-right">{{ $creditSale ? number_format($creditSale->new_balance, 2) : '-' }}</td>
                                                    <td class="text-xs italic print:hidden">{!! $creditSale?->notes ?? '-' !!}</td>
                                                </tr>
                                            @endfor
                                        @else
                                            <tr><td colspan="7" class="text-center italic text-gray-500">No credit sales recorded</td></tr>
                                        @endif
                                    </tbody>
                                    <tfoot class="bg-gray-50 font-bold tabular-nums">
                                        <tr>
                                            <td colspan="4" class="text-right">Total:</td>
                                            <td class="text-right">{{ number_format($settlement->creditSales->sum('sale_amount'), 2) }}</td>
                                            <td colspan="2"></td>
                                        </tr>
                                    </tfoot>    
                                </table>
                            </div>
    
                            {{-- Recoveries --}}
                            <div>
                                <h4 class="ffont-bold text-sm border-x border-t border-black  text-center">Recoveries Breakdown</h4>
                                <table class="report-table w-full">
                                    <thead>
                                        <tr class="bg-gray-100">
                                            <th class="text-center w-10">#</th>
                                            <th class="text-center">
                                                <span class="print:hidden"><x-tooltip text="Customer Name">CN</x-tooltip></span>
                                                <span class="hidden print:inline">Name</span>
                                            </th>
                                            <th class="text-center">
                                                <span class="print:hidden"><x-tooltip text="Recovery Number">Rec #</x-tooltip></span>
                                                <span class="hidden print:inline">Rec #</span>
                                            </th>
                                            <th class="text-center">
                                                <span class="print:hidden"><x-tooltip text="Previous Balance">PB</x-tooltip></span>
                                                <span class="hidden print:inline">Prev Bal.</span>
                                            </th>
                                            <th class="text-center">
                                                <span class="print:hidden"><x-tooltip text="Recovery Amount">Amt</x-tooltip></span>
                                                <span class="hidden print:inline">Rec Amt</span>
                                            </th>
                                            <th class="text-center">
                                                <span class="print:hidden"><x-tooltip text="Payment Method">Method</x-tooltip></span>
                                                <span class="hidden print:inline">Mtd</span>
                                            </th>
                                            <th class="text-center">
                                                <span class="print:hidden"><x-tooltip text="New Balance">NB</x-tooltip></span>
                                                <span class="hidden print:inline">Bal</span>
                                            </th>
                                            <th class="text-center print:hidden">Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody class="tabular-nums">
                                        @if($maxRows > 0)
                                            @for($i = 0; $i < $maxRows; $i++)
                                                @php $recovery = $settlement->recoveries->get($i); @endphp
                                                <tr>
                                                    <td class="text-center">{{ $i + 1 }}</td>
                                                    <td>{{ $recovery?->customer->customer_name ?? '-' }}</td>
                                                    <td>{{ $recovery?->recovery_number ?? '-' }}</td>
                                                    <td class="text-right">{{ $recovery ? number_format($recovery->previous_balance, 2) : '-' }}</td>
                                                    <td class="text-right font-bold">{{ $recovery ? number_format($recovery->amount, 2) : '-' }}</td>
                                                    <td class="text-center text-xs uppercase">
                                                        @if($recovery)
                                                            {{ $recovery->payment_method === 'cash' ? 'Cash' : 'Bank' }}
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td class="text-right">{{ $recovery ? number_format($recovery->new_balance, 2) : '-' }}</td>
                                                    <td class="text-xs italic print:hidden">{!! $recovery?->notes ?? '-' !!}</td>
                                                </tr>
                                            @endfor
                                        @else
                                            <tr><td colspan="8" class="text-center italic text-gray-500">No recoveries recorded</td></tr>
                                        @endif
                                    </tbody>
                                    <tfoot class="bg-gray-50 font-bold tabular-nums">
                                        <tr>
                                            <td colspan="4" class="text-right">Total:</td>
                                            <td class="text-right">{{ number_format($settlement->recoveries->sum('amount'), 2) }}</td>
                                            <td colspan="3"></td>
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
                                <h4 class="font-bold text-sm border-x border-t border-black  text-center">Cheque Payments</h4>
                                <table class="report-table w-full">
                                    <thead>
                                        <tr class="bg-gray-100">
                                            <th class="text-center w-10">#</th>
                                            <th class="text-left">
                                                <span class="print:hidden"><x-tooltip text="Cheque Date">Date</x-tooltip></span>
                                                <span class="hidden print:inline">Date</span>
                                            </th>
                                            <th class="text-left">
                                                <span class="print:hidden"><x-tooltip text="Cheque Number">Chq #</x-tooltip></span>
                                                <span class="hidden print:inline">Chq #</span>
                                            </th>
                                            <th class="text-left">
                                                <span class="print:hidden"><x-tooltip text="Customer Name">CN</x-tooltip></span>
                                                <span class="hidden print:inline">Customer</span>
                                            </th>
                                            <th class="text-left">
                                                <span class="print:hidden"><x-tooltip text="Bank Name">Bank</x-tooltip></span>
                                                <span class="hidden print:inline">Bank</span>
                                            </th>
                                            <th class="text-left">
                                                <span class="print:hidden"><x-tooltip text="Deposit Bank">Dep Bank</x-tooltip></span>
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
                                                    <td>{{ $cheque && $cheque->cheque_date ? \Carbon\Carbon::parse($cheque->cheque_date)->format('d-M-y') : '-' }}</td>
                                                    <td>{{ $cheque->cheque_number ?? '-' }}</td>
                                                    <td>{{ $cheque->customer->customer_name ?? '-' }}</td>
                                                    <td>{{ $cheque->bank_name ?? '-' }}</td>
                                                    <td>{{ $cheque->bankAccount->account_name ?? '-' }}</td>
                                                    <td class="text-right font-bold">{{ $cheque ? number_format($cheque->amount, 2) : '-' }}</td>
                                                </tr>
                                            @endfor
                                        @else
                                            <tr><td colspan="7" class="text-center italic text-gray-500">No cheques recorded</td></tr>
                                        @endif
                                    </tbody>
                                    <tfoot class="bg-gray-50 font-bold tabular-nums">
                                        <tr>
                                            <td colspan="6" class="text-right">Total:</td>
                                            <td class="text-right">{{ number_format($settlement->cheques->sum('amount'), 2) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
    
                            {{-- Bank Transfers --}}
                            <div>
                                <h4 class="font-bold text-sm border-x border-t border-black  text-center">Bank Transfers / Online</h4>
                                <table class="report-table w-full">
                                    <thead>
                                        <tr class="bg-gray-100">
                                            <th class="text-center w-10">#</th>
                                            <th class="text-left">
                                                <span class="print:hidden"><x-tooltip text="Transfer Date">Date</x-tooltip></span>
                                                <span class="hidden print:inline">Date</span>
                                            </th>
                                            <th class="text-left">
                                                <span class="print:hidden"><x-tooltip text="Customer Name">CN</x-tooltip></span>
                                                <span class="hidden print:inline">Customer</span>
                                            </th>
                                            <th class="text-left">
                                                <span class="print:hidden"><x-tooltip text="Bank Account">Bank</x-tooltip></span>
                                                <span class="hidden print:inline">Bank</span>
                                            </th>
                                            <th class="text-left">
                                                <span class="print:hidden"><x-tooltip text="Reference Number">Ref #</x-tooltip></span>
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
                                                    <td>{{ $transfer && $transfer->transfer_date ? \Carbon\Carbon::parse($transfer->transfer_date)->format('d-M-y') : '-' }}</td>
                                                    <td>{{ $transfer->customer->customer_name ?? '-' }}</td>
                                                    <td>{{ $transfer->bankAccount->account_name ?? '-' }}</td>
                                                    <td>{{ $transfer->reference_number ?? '-' }}</td>
                                                    <td class="text-right font-bold">{{ $transfer ? number_format($transfer->amount, 2) : '-' }}</td>
                                                </tr>
                                            @endfor
                                        @else
                                            <tr><td colspan="6" class="text-center italic text-gray-500">No bank transfers recorded</td></tr>
                                        @endif
                                    </tbody>
                                    <tfoot class="bg-gray-50 font-bold tabular-nums">
                                        <tr>
                                            <td colspan="5" class="text-right">Total:</td>
                                            <td class="text-right">{{ number_format($settlement->bankTransfers->sum('amount'), 2) }}</td>
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
                            <h4 class="font-bold text-sm border-x border-t border-black  text-center">AMR Powder (5252)</h4>
                            <table class="report-table w-full">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th class="text-center w-6 px-1 py-0.5">#</th>
                                        <th class="text-center px-1 py-0.5">
                                            <span class="print:hidden"><x-tooltip text="Product Name">SKU</x-tooltip></span>
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
                                            <td class="text-right px-1 py-0.5">{{ $powder ? number_format($powder->quantity, 2) : '-' }}</td>
                                            <td class="text-right font-bold px-1 py-0.5">{{ $powder ? number_format($powder->amount, 2) : '-' }}</td>
                                        </tr>
                                    @endfor
                                </tbody>
                                <tfoot class="bg-gray-50 font-bold tabular-nums">
                                    <tr>
                                        <td colspan="3" class="text-right px-1 py-0.5">Total:</td>
                                        <td class="text-right px-1 py-0.5">{{ number_format($settlement->amrPowders->sum('amount'), 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        {{-- AMR Liquid --}}
                        <div>
                            <h4 class="font-bold text-sm border-x border-t border-black   text-center">AMR Liquid (5262)</h4>
                            <table class="report-table w-full">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th class="text-center w-6 px-1 py-0.5">#</th>
                                        <th class="text-center px-1 py-0.5">
                                            <span class="print:hidden"><x-tooltip text="Product Name">SKU</x-tooltip></span>
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
                                            <td class="text-right px-1 py-0.5">{{ $liquid ? number_format($liquid->quantity, 2) : '-' }}</td>
                                            <td class="text-right font-bold px-1 py-0.5">{{ $liquid ? number_format($liquid->amount, 2) : '-' }}</td>
                                        </tr>
                                    @endfor
                                </tbody>
                                <tfoot class="bg-gray-50 font-bold tabular-nums">
                                    <tr>
                                        <td colspan="3" class="text-right px-1 py-0.5">Total:</td>
                                        <td class="text-right px-1 py-0.5">{{ number_format($settlement->amrLiquids->sum('amount'), 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    {{-- Row 2: Advance Tax | Percentage Expense --}}
                    @php
                        $advTaxCount = $settlement->advanceTaxes->count();
                        $pctExpCount = $settlement->percentageExpenses->count();
                        $maxRowsTax = max($advTaxCount, $pctExpCount, 1);
                    @endphp

                    <div class="grid grid-cols-2 gap-1 items-start print:grid-cols-2 mt-1">
                        {{-- Advance Tax --}}
                        <div>
                            <h4 class="font-bold text-sm border-x border-t border-black text-center">Advance Tax (1161)</h4>
                            <table class="report-table w-full">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th class="text-center w-6 px-1 py-0.5">#</th>
                                        <th class="text-center px-1 py-0.5">
                                            <span class="print:hidden"><x-tooltip text="Customer Name (Code)">Customer</x-tooltip></span>
                                            <span class="hidden print:inline">Customer</span>
                                        </th>
                                        <th class="text-center px-1 py-0.5">Inv #</th>
                                        <th class="text-center px-1 py-0.5">Tax</th>
                                    </tr>
                                </thead>
                                <tbody class="tabular-nums">
                                    @for($i = 0; $i < $maxRowsTax; $i++)
                                        @php $tax = $settlement->advanceTaxes->get($i); @endphp
                                        <tr>
                                            <td class="text-center px-1 py-0.5">{{ $i + 1 }}</td>
                                            <td class="px-1 py-0.5">{{ $tax ? $tax->customer->customer_name . ' (' . $tax->customer->customer_code . ')' : '-' }}</td>
                                            <td class="text-right px-1 py-0.5">{{ $tax?->invoice_number ?? '-' }}</td>
                                            <td class="text-right font-bold px-1 py-0.5">{{ $tax ? number_format($tax->tax_amount, 2) : '-' }}</td>
                                        </tr>
                                    @endfor
                                </tbody>
                                <tfoot class="bg-gray-50 font-bold tabular-nums">
                                    <tr>
                                        <td colspan="3" class="text-right px-1 py-0.5">Total:</td>
                                        <td class="text-right px-1 py-0.5">{{ number_format($settlement->advanceTaxes->sum('tax_amount'), 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        {{-- Percentage Expense --}}
                        <div>
                            <h4 class="font-bold text-sm border-x border-t border-black text-center">Percentage Expense (5223)</h4>
                            <table class="report-table w-full">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th class="text-center w-6 px-1 py-0.5">#</th>
                                        <th class="text-center px-1 py-0.5">
                                            <span class="print:hidden"><x-tooltip text="Customer Name (Code)">Customer</x-tooltip></span>
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
                                            <td class="px-1 py-0.5">{{ $pctExp ? $pctExp->customer->customer_name . ' (' . $pctExp->customer->customer_code . ')' : '-' }}</td>
                                            <td class="px-1 py-0.5">{{ $pctExp?->invoice_number ?? '-' }}</td>
                                            <td class="text-right font-bold px-1 py-0.5">{{ $pctExp ? number_format($pctExp->amount, 2) : '-' }}</td>
                                        </tr>
                                    @endfor
                                </tbody>
                                <tfoot class="bg-gray-50 font-bold tabular-nums">
                                    <tr>
                                        <td colspan="3" class="text-right px-1 py-0.5">Total:</td>
                                        <td class="text-right px-1 py-0.5">{{ number_format($settlement->percentageExpenses->sum('amount'), 2) }}</td>
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
                            $groupExpenseRows[] = [
                                'label' => $predef['label'],
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
                    <div class="border-2 border-black rounded-lg px-2 pb-2 mt-4">
                        <h3 class="font-bold text-md text-center text-black pb-1 border-b border-black mb-2">Expenses & Cash/Bank Deposits Detail</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-1 items-start print:flex print:gap-1" style="page-break-inside: avoid; break-inside: avoid;">
                            {{-- 1. Group Expenses --}}
                            <div class="flex flex-col h-full print:w-1/3">
                                <h4 class="font-bold text-sm border-x border-t border-black text-center">Group Expenses</h4>
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
                                                            <span class="print:hidden underline decoration-dotted cursor-help"><x-tooltip text="See detailed breakdown in the table above">{{ $expRow['code'] }}</x-tooltip></span>
                                                            <span class="hidden print:inline">{{ $expRow['code'] }}</span>
                                                        @else
                                                            {{ $expRow['code'] }}
                                                        @endif
                                                    </td>
                                                    <td class="text-right font-bold px-1 py-0.5">{{ number_format($expRow['amount'], 2) }}</td>
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
                                            <td colspan="3" class="text-right px-1 py-0.5 border-t border-black">Total:</td>
                                            <td class="text-right px-1 py-0.5 border-t border-black">{{ number_format($settlement->expenses->sum('amount'), 2) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            {{-- 2. Cash Detail --}}
                            <div class="flex flex-col h-full print:w-1/3">
                                <h4 class="font-bold text-sm border-x border-t border-black text-center">Cash Detail</h4>
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
                                                    <td class="text-right font-bold px-1 py-0.5">{{ number_format($cashRow['value'], isset($cashRow['is_coin']) && $cashRow['is_coin'] ? 2 : 0) }}</td>
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
                                            <td colspan="3" class="text-right px-1 py-0.5 border-t border-black">Total Physical Cash:</td>
                                            <td class="text-right px-1 py-0.5 border-t border-black">{{ number_format($calculatedCash, 2) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            {{-- 3. Bank Slips / Deposits --}}
                            <div class="flex flex-col h-full print:w-1/3">
                                <h4 class="font-bold text-sm border-x border-t border-black text-center">Bank Slips / Deposits</h4>
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
                                                <td class="px-1 py-0.5 text-xs">{{ $slip->bankAccount->account_name ?? '-' }}</td>
                                                <td class="text-center px-1 py-0.5 text-xs">{{ $slip->deposit_date ? \Carbon\Carbon::parse($slip->deposit_date)->format('d-M-y') : '-' }}</td>
                                                <td class="text-right px-1 py-0.5 font-bold">{{ number_format($slip->amount, 2) }}</td>
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
                                            <td colspan="3" class="text-right px-1 py-0.5 border-t border-black">Total Bank Slips:</td>
                                            <td class="text-right px-1 py-0.5 border-t border-black">{{ number_format($settlement->bankSlips->sum('amount'), 2) }}</td>
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
                    <div class="border-2 border-black rounded-lg px-2 pb-2 mt-4">
                        <h3 class="font-bold text-md text-center text-black pb-1 border-b border-black mb-2">Sales & Collection Summary</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-1 print:flex print:gap-1" style="page-break-inside: avoid; break-inside: avoid;">
                            {{-- 1. Sales Summary --}}
                            <div class="flex flex-col h-full print:w-1/2">
                                <h4 class="font-bold text-sm border-x border-t border-black text-center mt-2">Sales Summary</h4>
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
                                            <td class="px-1 py-0.5">Credit Sale Amount</td>
                                            <td class="text-right px-1 py-0.5 font-bold">{{ number_format($settlement->credit_sales_amount, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">2</td>
                                            <td class="px-1 py-0.5">Cheque Sale Amount</td>
                                            <td class="text-right px-1 py-0.5 font-bold">{{ number_format($settlement->cheque_sales_amount, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">3</td>
                                            <td class="px-1 py-0.5">Bank Transfer Amount</td>
                                            <td class="text-right px-1 py-0.5 font-bold">{{ number_format($settlement->bank_transfer_amount, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">4</td>
                                            <td class="px-1 py-0.5">Cash Sale Amount</td>
                                            <td class="text-right px-1 py-0.5 font-bold">{{ number_format($settlement->cash_sales_amount, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">5</td>
                                            <td class="px-1 py-0.5">Bank Slips / Deposits</td>
                                            <td class="text-right px-1 py-0.5 font-bold">{{ number_format($settlement->bankSlips->sum('amount'), 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">6</td>
                                            <td class="px-1 py-0.5 font-bold">Net Sale (Sold Items Value)</td>
                                            <td class="text-right px-1 py-0.5 font-black border-t border-black">{{ number_format($netSale, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">7</td>
                                            <td class="px-1 py-0.5">Return Value</td>
                                            <td class="text-right px-1 py-0.5">{{ number_format($settlement->sales_return_amount, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">8</td>
                                            <td class="px-1 py-0.5">Shortage Value</td>
                                            <td class="text-right px-1 py-0.5">{{ number_format($settlement->shortage_amount, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">9</td>
                                            <td class="px-1 py-0.5">Recovery (Cash)</td>
                                            <td class="text-right px-1 py-0.5">{{ number_format($recoveryCash, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">10</td>
                                            <td class="px-1 py-0.5">Recovery (Bank/Online)</td>
                                            <td class="text-right px-1 py-0.5">{{ number_format($recoveryBank, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">11</td>
                                            <td class="px-1 py-0.5 font-bold">Total Sale Amount</td>
                                            <td class="text-right px-1 py-0.5 font-black border-t border-black">{{ number_format($netSale, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">12</td>
                                            <td class="px-1 py-0.5 font-semibold text-blue-700">Expected Cash (Sales + Cash Recoveries)</td>
                                            <td class="text-right px-1 py-0.5 font-bold text-blue-700">{{ number_format($expectedCashGross, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">13</td>
                                            <td class="px-1 py-0.5 text-red-700">Less: Expenses</td>
                                            <td class="text-right px-1 py-0.5 text-red-700 font-semibold">{{ number_format($totalExpenses, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">14</td>
                                            <td class="px-1 py-0.5 font-bold text-blue-900 bg-blue-50">Expected Cash (After Expenses)</td>
                                            <td class="text-right px-1 py-0.5 font-black text-blue-900 bg-blue-50 border-t border-blue-900">{{ number_format($expectedCashNet, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">15</td>
                                            <td class="px-1 py-0.5 font-bold">Physical Cash Submitted (Denominations)</td>
                                            <td class="text-right px-1 py-0.5 font-black border-2 border-black">{{ number_format($calculatedCash, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">16</td>
                                            <td class="px-1 py-0.5 font-semibold">Short/Excess</td>
                                            <td class="text-right px-1 py-0.5 font-bold {{ $shortExcess < 0 ? 'text-red-700' : 'text-green-700' }}">{{ number_format($shortExcess, 2) }}</td>
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
                            <div class="flex flex-col h-full print:w-1/2">
                                <h4 class="font-bold text-sm border-x border-t border-black text-center mt-2">Profit Analysis</h4>
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
                                            <td class="text-right px-1 py-0.5 font-semibold">{{ number_format($netSale, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">2</td>
                                            <td class="px-1 py-0.5 text-red-700">Less: Cost of Goods Sold (COGS)</td>
                                            <td class="text-right px-1 py-0.5 text-red-700 font-semibold">{{ number_format($totalCOGS, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">3</td>
                                            <td class="px-1 py-0.5 font-semibold text-green-700">Gross Profit (Sales - COGS)</td>
                                            <td class="text-right px-1 py-0.5 font-bold text-green-700">{{ number_format($grossProfit, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">4</td>
                                            <td class="px-1 py-0.5 text-gray-600 pl-4">Gross Margin %</td>
                                            <td class="text-right px-1 py-0.5 font-semibold">{{ number_format($grossMargin, 2) }}%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">5</td>
                                            <td class="px-1 py-0.5 text-red-700">Less: Operating Expenses</td>
                                            <td class="text-right px-1 py-0.5 text-red-700 font-semibold">{{ number_format($totalExpenses, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">6</td>
                                            <td class="px-1 py-0.5 font-semibold {{ $netProfit >= 0 ? 'text-green-700' : 'text-red-700' }}">Net Profit (After Expenses)</td>
                                            <td class="text-right px-1 py-0.5 font-bold {{ $netProfit >= 0 ? 'text-green-700' : 'text-red-700' }}">{{ number_format($netProfit, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-center px-1 py-0.5">7</td>
                                            <td class="px-1 py-0.5 text-gray-600 pl-4">Net Margin %</td>
                                            <td class="text-right px-1 py-0.5 font-semibold {{ $netMargin >= 0 ? 'text-green-700' : 'text-red-700' }}">{{ number_format($netMargin, 2) }}%</td>
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
                    <div class="w-full clear-both mt-8 print:mt-16 block h-8 print:h-16" style="clear: both;">&nbsp;</div>
                    
                    @php
                        // Fetch all accounts for this employee to generate the Credit Report
                        // We need: Customer Name, Date (Settlement Date), Credit (Today), Recovery (Today), Closing Balance (As of Date)

                        $employeeId = $settlement->employee_id;
                        $settlementDate = $settlement->settlement_date; // Assuming Y-m-d format

                        // 1. Get all active customers for this salesman
                        $customerAccounts = \App\Models\CustomerEmployeeAccount::with('customer')
                            ->where('employee_id', $employeeId)
                            ->where('status', 'active')
                            ->get();

                        // 2. Pre-fetch this settlement's Credit Sales and Recoveries
                        $settlementCredits = $settlement->creditSales->groupBy('customer_id');
                        $settlementRecoveries = $settlement->recoveries->groupBy('customer_id');

                        // 3. Process data
                        $creditReportData = $customerAccounts->map(function ($account) use ($settlementDate, $settlementCredits, $settlementRecoveries) {
                            $customerId = $account->customer_id;

                            // A. Get Activity strictly for THIS settlement
                            $myCredits = $settlementCredits->get($customerId); // Collection of credit sales
                            $myRecoveries = $settlementRecoveries->get($customerId); // Collection of recoveries

                            $creditAmount = $myCredits ? $myCredits->sum('sale_amount') : 0;
                            $recoveryAmount = $myRecoveries ? $myRecoveries->sum('amount') : 0;
                            $hasActivity = ($creditAmount > 0 || $recoveryAmount > 0);

                            // B. Determine Closing Balance (Post-Settlement)
                            // If we have activity, the reliable "Closing Balance" for this settlement is the 'new_balance' of the LAST transaction in this set.
                            // If no activity, we fall back to the account's current ledger balance (as best effort).

                            $closingBalance = 0;

                            if ($hasActivity) {
                                // Find the very last transaction in this settlement to get the final "NB"
                                $lastCredit = $myCredits ? $myCredits->sortByDesc('id')->first() : null;
                                $lastRec = $myRecoveries ? $myRecoveries->sortByDesc('id')->first() : null;

                                if ($lastCredit && $lastRec) {
                                    // Whichever has higher ID (created later) wins
                                    $closingBalance = ($lastCredit->id > $lastRec->id) ? $lastCredit->new_balance : $lastRec->new_balance;
                                } elseif ($lastCredit) {
                                    $closingBalance = $lastCredit->new_balance;
                                } elseif ($lastRec) {
                                    $closingBalance = $lastRec->new_balance;
                                }
                            } else {
                                // Fallback: Calculate balance up to this settlement date (start of day + all day activity)
                                // Ideally, we should fetch the ledger balance as of EOD of settlement date.
                                // Because we can't easily isolate "inter-settlement" balance without complex queries,
                                // we'll use the transactions-based sum up to EOD.
                                // Note: This might match Settlement #14's ending if this is Settlement #13, 
                                // but for a "No Activity" row, showing EOD balance is acceptable standard practice.
                                $closingBalance = $account->transactions()
                                    ->whereDate('transaction_date', '<=', $settlementDate)
                                    ->sum(\DB::raw('debit - credit'));
                            }

                            // C. Back-Calculate Opening Balance for THIS settlement
                            // Op + Credit - Rec = Closing
                            // => Op = Closing - Credit + Rec
                            $openingBalance = $closingBalance - $creditAmount + $recoveryAmount;

                            return (object) [
                                'customer_name' => $account->customer->customer_name,
                                'customer_code' => $account->customer->customer_code,
                                'date' => $settlementDate,
                                'opening_balance' => $openingBalance,
                                'credit_amount' => $creditAmount,
                                'recovery_amount' => $recoveryAmount,
                                'balance' => $closingBalance,
                                'has_activity' => $hasActivity || abs($closingBalance) > 1
                            ];
                        })->filter(function ($row) {
                            // Show if there is activity OR non-zero balance
                            return $row->has_activity;
                        })->sortBy('customer_name');

                        $totalCreditGiven = $creditReportData->sum('credit_amount');
                        $totalRecoveryReceived = $creditReportData->sum('recovery_amount');
                        // Closing balance total is sum of all individual closing balances
                        $totalClosingBalance = $creditReportData->sum('balance');
                    @endphp

                    <div class="rounded-lg pb-2 mt-2 w-full clear-both print:block" style="page-break-inside: auto;">
                        <h3 class="font-bold text-md text-center text-black border-x border-t border-black">
                            Credit Report: {{ $settlement->employee->name ?? 'Salesman' }} ({{ \Carbon\Carbon::parse($settlementDate)->format('d-M-Y') }})
                        </h3>
                        
                        <table class="report-table w-full tabular-nums text-sm">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="text-center w-10 border-b border-black">#</th>
                                    <th class="text-left border-b border-black">Party Name</th>
                                    <th class="text-left border-b border-black">
                                        <span class="print:hidden"><x-tooltip text="Customer Code">Code</x-tooltip></span>
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
                                        <td class="py-1 font-bold">{{ $row->customer_name }}</td>
                                        <td class="py-1 text-center">{{ $row->customer_code }}</td>
                                        <td class="text-right py-1 text-gray-600">{{ number_format($row->opening_balance, 2) }}</td>
                                        <td class="text-right py-1 font-bold">{{ $row->credit_amount > 0 ? number_format($row->credit_amount, 2) : '-' }}</td>
                                        <td class="text-right py-1 font-bold">{{ $row->recovery_amount > 0 ? number_format($row->recovery_amount, 2) : '-' }}</td>
                                        <td class="text-right py-1 font-bold">{{ number_format($row->balance, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 italic text-gray-500">No active creditors found for this date.</td>
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
                             | Journal Entry: <a href="{{ route('journal-entries.show', $settlement->journalEntry) }}" class="underline hover:text-blue-600">{{ $settlement->journalEntry->entry_number }}</a>
                        @endif
                    </div>
                @endif

                


                                    {{-- Final Signature Area --}}
                    <div class="mt-16 grid grid-cols-3 gap-8 text-center print:flex print:justify-between print:mt-32" style="page-break-inside: avoid;">
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
</x-app-layout>
