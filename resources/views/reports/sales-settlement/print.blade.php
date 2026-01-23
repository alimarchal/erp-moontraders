<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Settlement {{ $settlement->settlement_number }} - Print</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Figtree', sans-serif;
            background-color: white;
            color: black;
            font-size: 12px;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid black;
            margin-bottom: 20px;
        }

        .report-table th,
        .report-table td {
            border: 1px solid black;
            padding: 4px 6px;
            text-align: left;
        }

        .report-table th {
            background-color: #f3f4f6;
            font-weight: bold;
            text-align: center;
        }

        .text-right {
            text-align: right !important;
        }

        .text-center {
            text-align: center !important;
        }

        .font-bold {
            font-weight: bold !important;
        }

        .font-mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        }

        .section-title {
            font-size: 14px;
            font-weight: bold;
            margin-top: 15px;
            margin-bottom: 5px;
            text-decoration: underline;
        }

        .header-info {
            width: 100%;
            margin-bottom: 20px;
        }

        .header-info td {
            border: none;
            padding: 2px 0;
        }

        @media print {
            @page {
                size: portrait;
                margin: 10mm;
            }

            body {
                margin: 0;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body onload="window.print()">

    <div class="max-w-[95%] mx-auto p-4">
        <!-- Header -->
        <div class="text-center mb-6">
            <h1 class="text-xl font-bold uppercase">Moon Traders</h1>
            <h2 class="text-lg">Sales Settlement Detail</h2>
        </div>

        <table class="header-info text-sm">
            <tr>
                <td class="font-bold w-32">Settlement #:</td>
                <td>{{ $settlement->settlement_number }}</td>
                <td class="font-bold w-32 text-right">Date:</td>
                <td class="w-48 text-right">{{ $settlement->settlement_date->format('d-M-Y') }}</td>
            </tr>
            <tr>
                <td class="font-bold">Salesman:</td>
                <td>{{ $settlement->employee->name }} ({{ $settlement->employee->employee_code ?? '-' }})</td>
                <td class="font-bold text-right">Vehicle:</td>
                <td class="text-right">{{ $settlement->vehicle->registration_number }}</td>
            </tr>
            <tr>
                <td class="font-bold">Warehouse:</td>
                <td>{{ $settlement->warehouse->warehouse_name }}</td>
                <td class="font-bold text-right">Status:</td>
                <td class="text-right uppercase">{{ $settlement->status }}</td>
            </tr>
        </table>

        <!-- 1. Product-wise Settlement (Detailed) -->
        <div class="section-title">Product-wise Settlement</div>
        <table class="report-table">
            <thead>
                <tr>
                    <th class="w-8">#</th>
                    <th>Product</th>
                    <th class="text-right">BF In</th>
                    <th class="text-right">Issued</th>
                    <th>Batch Breakdown</th>
                    <th class="text-right">Sold</th>
                    <th class="text-right">Returned</th>
                    <th class="text-right">Shortage</th>
                    <th class="text-right">BF Out</th>
                    <th class="text-right">Sales Value</th>
                </tr>
            </thead>
            <tbody>
                @foreach($settlement->items as $item)
                    @php
                        $bfIn = 0; // Placeholder as per show.blade.php
                        $bfOut = $bfIn + $item->quantity_issued - $item->quantity_sold - $item->quantity_returned - $item->quantity_shortage;
                    @endphp
                    <tr>
                        <td class="text-center">{{ $item->line_no }}</td>
                        <td>
                            <div class="font-bold">{{ $item->product->product_code }}</div>
                            <div class="text-[10px]">{{ $item->product->product_name }}</div>
                        </td>
                        <td class="text-right font-mono">{{ number_format($bfIn, 2) }}</td>
                        <td class="text-right font-mono">{{ number_format($item->quantity_issued, 2) }}</td>
                        <td class="text-[10px]">
                            @if($item->batches->count() > 0)
                                @foreach($item->batches as $b)
                                    <div>
                                        <span class="font-bold">{{ number_format($b->quantity_issued, 0) }}</span> ×
                                        {{ number_format($b->selling_price, 2) }}
                                        @if($b->is_promotional) (Promo) @endif
                                        <span class="text-gray-600">[S:{{ $b->quantity_sold }} R:{{ $b->quantity_returned }}
                                            Sh:{{ $b->quantity_shortage }}]</span>
                                    </div>
                                @endforeach
                            @else
                                <span class="italic text-gray-500">No batch data</span>
                            @endif
                        </td>
                        <td class="text-right font-mono font-bold">{{ number_format($item->quantity_sold, 2) }}</td>
                        <td class="text-right font-mono">{{ number_format($item->quantity_returned, 2) }}</td>
                        <td class="text-right font-mono text-red-600">{{ number_format($item->quantity_shortage, 2) }}</td>
                        <td class="text-right font-mono">{{ number_format($bfOut, 2) }}</td>
                        <td class="text-right font-mono font-bold">{{ number_format($item->total_sales_value, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="bg-gray-100 font-bold">
                    <td colspan="3" class="text-right">Totals:</td>
                    <td class="text-right font-mono">{{ number_format($settlement->items->sum('quantity_issued'), 2) }}
                    </td>
                    <td></td>
                    <td class="text-right font-mono">{{ number_format($settlement->total_quantity_sold, 2) }}</td>
                    <td class="text-right font-mono">{{ number_format($settlement->total_quantity_returned, 2) }}</td>
                    <td class="text-right font-mono">{{ number_format($settlement->total_quantity_shortage, 2) }}</td>
                    <td></td>
                    <td class="text-right font-mono">{{ number_format($settlement->total_sales_amount, 2) }}</td>
                </tr>
            </tfoot>
        </table>

        <!-- Financial Details Section -->
        <div class="mt-6 space-y-6">

            <!-- 1. Credit Sales Detail -->
            @if($settlement->creditSales->count() > 0)
                <div>
                    <div class="section-title">Credit Sales Detail</div>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Invoice #</th>
                                <th>Notes</th>
                                <th class="text-right">Sale Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($settlement->creditSales as $credit)
                                <tr>
                                    <td>{{ $credit->customer->customer_name ?? 'N/A' }}</td>
                                    <td>{{ $credit->invoice_number ?? '-' }}</td>
                                    <td class="text-xs text-gray-600">{{ $credit->notes ?? '-' }}</td>
                                    <td class="text-right font-mono">{{ number_format($credit->sale_amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="font-bold bg-gray-50">
                                <td colspan="3" class="text-right">Total Credit Sales:</td>
                                <td class="text-right font-mono">{{ number_format($settlement->credit_sales_amount, 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif

            <!-- 2. Recoveries Detail -->
            @if($settlement->recoveries->count() > 0)
                <div>
                    <div class="section-title">Recoveries (Received)</div>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Rec #</th>
                                <th>Customer</th>
                                <th class="text-center">Method</th>
                                <th>Bank / Ref</th>
                                <th>Notes</th>
                                <th class="text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($settlement->recoveries as $recovery)
                                <tr>
                                    <td>{{ $recovery->recovery_number ?? '-' }}</td>
                                    <td>{{ $recovery->customer->customer_name ?? 'N/A' }}</td>
                                    <td class="text-center uppercase text-xs">{{ $recovery->payment_method }}</td>
                                    <td class="text-xs">
                                        @if($recovery->payment_method === 'bank_transfer')
                                            {{ $recovery->bankAccount->account_name ?? '-' }}
                                        @elseif($recovery->payment_method === 'cheque')
                                            {{ $recovery->cheque_number ?? '-' }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="text-xs text-gray-600">{{ $recovery->notes ?? '-' }}</td>
                                    <td class="text-right font-mono">{{ number_format($recovery->amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="font-bold bg-gray-50">
                                <td colspan="5" class="text-right">Total Recoveries:</td>
                                <td class="text-right font-mono">{{ number_format($settlement->credit_recoveries, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif

            <!-- 3. Bank Transfers -->
            @if($settlement->bankTransfers->count() > 0)
                <div>
                    <div class="section-title">Bank Transfers</div>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Receiving Bank</th>
                                <th>Ref #</th>
                                <th>Notes</th>
                                <th class="text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($settlement->bankTransfers as $transfer)
                                <tr>
                                    <td>{{ $transfer->transfer_date ? \Carbon\Carbon::parse($transfer->transfer_date)->format('d-M-y') : '-' }}
                                    </td>
                                    <td>{{ $transfer->customer->customer_name ?? 'N/A' }}</td>
                                    <td>{{ $transfer->bankAccount->account_name ?? 'Online' }}</td>
                                    <td>{{ $transfer->reference_number ?? '-' }}</td>
                                    <td class="text-xs text-gray-600">{{ $transfer->notes ?? '-' }}</td>
                                    <td class="text-right font-mono">{{ number_format($transfer->amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="font-bold bg-gray-50">
                                <td colspan="5" class="text-right">Total Transfers:</td>
                                <td class="text-right font-mono">{{ number_format($settlement->bank_transfer_amount, 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif

            <!-- 4. Cheques -->
            @if($settlement->cheques->count() > 0)
                <div>
                    <div class="section-title">Cheque Payments</div>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Cheque #</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Bank Name</th>
                                <th>Holder</th>
                                <th>Status</th>
                                <th>Notes</th>
                                <th class="text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($settlement->cheques as $cheque)
                                <tr>
                                    <td>{{ $cheque->cheque_number }}</td>
                                    <td>{{ $cheque->cheque_date ? \Carbon\Carbon::parse($cheque->cheque_date)->format('d-M-y') : '-' }}
                                    </td>
                                    <td>{{ $cheque->customer->customer_name ?? 'N/A' }}</td>
                                    <td>{{ $cheque->bank_name }}</td>
                                    <td>{{ $cheque->account_holder_name ?? '-' }}</td>
                                    <td class="uppercase text-[10px]">{{ $cheque->status }}</td>
                                    <td class="text-xs text-gray-600">{{ $cheque->notes ?? '-' }}</td>
                                    <td class="text-right font-mono">{{ number_format($cheque->amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="font-bold bg-gray-50">
                                <td colspan="7" class="text-right">Total Cheques:</td>
                                <td class="text-right font-mono">{{ number_format($settlement->cheques_collected, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif

            <!-- 5. Cash Denominations and Expenses Split -->
            <div class="grid grid-cols-2 gap-6">
                <div class="space-y-4">
                    <!-- Main Expense Detail Table (Matches View) -->
                    <div>
                        <div class="section-title">Expense Detail</div>
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Expense Account / Description</th>
                                    <th>Rcpt #</th>
                                    <th class="text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $totalExpenses = 0;
                                @endphp

                                {{-- 1. General Expenses (All records in sales_settlement_expenses) --}}
                                @forelse($settlement->expenses as $expense)
                                    @php $totalExpenses += $expense->amount; @endphp
                                    <tr>
                                        <td>
                                            @if($expense->expenseAccount)
                                                {{ $expense->expenseAccount->account_name }}
                                                <span
                                                    class="text-xs text-gray-500">({{ $expense->expenseAccount->account_code }})</span>
                                            @else
                                                {{ $expense->description ?? 'Unknown Account' }}
                                            @endif
                                        </td>
                                        <td>{{ $expense->receipt_number ?? '-' }}</td>
                                        <td class="text-right font-mono">{{ number_format($expense->amount, 2) }}</td>
                                    </tr>
                                @empty
                                    <!-- No general expenses -->
                                @endforelse

                                {{-- 2. Advance Tax (Added to main table as per show view) --}}
                                @foreach($settlement->advanceTaxes as $tax)
                                    @php $totalExpenses += $tax->tax_amount; @endphp
                                    <tr class="bg-yellow-50">
                                        <td>
                                            Advance Tax - {{ $tax->customer->customer_name }}
                                            @if($tax->invoice_number)
                                                <span class="text-xs text-gray-500">(Inv: {{ $tax->invoice_number }})</span>
                                            @endif
                                            <span class="text-xs text-gray-500">(A/C 1161)</span>
                                        </td>
                                        <td>-</td>
                                        <td class="text-right font-mono">{{ number_format($tax->tax_amount, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="font-bold bg-orange-50">
                                    <td colspan="2" class="text-right">Total Expenses:</td>
                                    <td class="text-right font-mono">{{ number_format($totalExpenses, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- AMR / FMR Breakdowns (Dedicated Tables if any) -->
                    @if($settlement->amrPowders->count() > 0)
                        <div>
                            <div class="section-title">AMR Powder Details</div>
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Notes</th>
                                        <th class="text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($settlement->amrPowders as $amr)
                                        <tr>
                                            <td>{{ $amr->product->product_name }}</td>
                                            <td class="text-xs text-gray-600">{{ $amr->notes ?? '-' }}</td>
                                            <td class="text-right font-mono">{{ number_format($amr->amount, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="font-bold bg-blue-50">
                                        <td colspan="2" class="text-right">Total AMR Powder:</td>
                                        <td class="text-right font-mono">
                                            {{ number_format($settlement->amrPowders->sum('amount'), 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif

                    @if($settlement->amrLiquids->count() > 0)
                        <div>
                            <div class="section-title">AMR Liquid (FMR) Details</div>
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Notes</th>
                                        <th class="text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($settlement->amrLiquids as $amr)
                                        <tr>
                                            <td>{{ $amr->product->product_name }}</td>
                                            <td class="text-xs text-gray-600">{{ $amr->notes ?? '-' }}</td>
                                            <td class="text-right font-mono">{{ number_format($amr->amount, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="font-bold bg-blue-50">
                                        <td colspan="2" class="text-right">Total AMR Liquid:</td>
                                        <td class="text-right font-mono">
                                            {{ number_format($settlement->amrLiquids->sum('amount'), 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif

                </div>

                <!-- Cash Denominations -->
                <div>
                    <div class="section-title">Cash Denominations</div>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Denomination</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $denoms = $settlement->cashDenominations->first();
                                $denomList = [5000, 1000, 500, 100, 50, 20, 10];
                                $coins = $denoms->denom_coins ?? 0;
                                $totalCash = 0;
                            @endphp
                            @foreach($denomList as $val)
                                @php
                                    $qty = $denoms->{"denom_$val"} ?? 0;
                                    $amt = $qty * $val;
                                    $totalCash += $amt;
                                @endphp
                                <tr>
                                    <td>{{ number_format($val) }}</td>
                                    <td class="text-right font-mono">{{ $qty > 0 ? $qty : '0' }}</td>
                                    <td class="text-right font-mono">{{ $amt > 0 ? number_format($amt, 2) : '-' }}</td>
                                </tr>
                            @endforeach
                            <tr>
                                <td>Coins</td>
                                <td class="text-right font-mono">-</td>
                                <td class="text-right font-mono">{{ number_format($coins, 2) }}</td>
                            </tr>
                            @php $totalCash += $coins; @endphp
                        </tbody>
                        <tfoot>
                            <tr class="font-bold bg-green-50">
                                <td colspan="2" class="text-right text-green-800">Total Physical Cash:</td>
                                <td class="text-right font-mono text-green-800">{{ number_format($totalCash, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

        </div>

        <!-- Sales Summary + Grand Total -->
        <div class="break-inside-avoid mt-4">
            <div class="section-title">Sales Summary</div>
            <table class="report-table w-1/2 ml-auto">
                <tr class="border-b font-bold">
                    <td>Total Sale Amount</td>
                    <td class="text-right font-mono">{{ number_format($settlement->total_sales_amount, 2) }}</td>
                </tr>
                <tr>
                    <td>Credit Sales</td>
                    <td class="text-right font-mono">{{ number_format($settlement->credit_sales_amount, 2) }}</td>
                </tr>
                <tr>
                    <td>Cheque Sales</td>
                    <td class="text-right font-mono">{{ number_format($settlement->cheque_sales_amount ?? 0, 2) }}</td>
                </tr>
                <tr>
                    <td>Bank Transfer</td>
                    <td class="text-right font-mono">{{ number_format($settlement->bank_transfer_amount, 2) }}</td>
                </tr>
                <tr>
                    <td>Cash Sales</td>
                    <td class="text-right font-mono">{{ number_format($settlement->cash_sales_amount ?? 0, 2) }}</td>
                </tr>
                <tr class="bg-gray-50 font-bold border-t border-b">
                    <td>Net Sale (Sold Items Value)</td>
                    <td class="text-right font-mono">
                        {{ number_format($settlement->items->sum('total_sales_value'), 2) }}
                    </td>
                </tr>
                <tr>
                    <td>Recovery (Cash + Bank)</td>
                    <td class="text-right font-mono">{{ number_format($settlement->credit_recoveries, 2) }}</td>
                </tr>
                <tr> <!-- Shortage Value -->
                    @php
                        $shortageVal = 0;
                        foreach ($settlement->items as $i) {
                            $price = $i->unit_selling_price > 0 ? $i->unit_selling_price : $i->unit_cost;
                            $shortageVal += $i->quantity_shortage * $price;
                        }
                      @endphp
                    <td>Shortage Value</td>
                    <td class="text-right font-mono text-red-600">{{ number_format($shortageVal, 2) }}</td>
                </tr>
            </table>
        </div>

        <div class="mt-8 grid grid-cols-3 text-center text-sm">
            <div>
                _______________________<br>
                Salesman Signature
            </div>
            <div>
                _______________________<br>
                Verified By
            </div>
            <div>
                _______________________<br>
                Approved By
            </div>
        </div>

        <div class="text-center text-[10px] mt-8 text-gray-500">
            Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
        </div>
    </div>

</body>

</html>