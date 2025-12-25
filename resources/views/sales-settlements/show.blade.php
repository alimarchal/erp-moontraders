<x-app-layout>
    <x-slot name="header">
        <h3 class="font-semibold text-sm text-black leading-tight inline-block">
            {{ $settlement->settlement_number }} - {{ $settlement->warehouse->warehouse_name }}
            <br>
            {{
    \Carbon\Carbon::parse($settlement->settlement_date)->format('d M
            Y') }} -
            {{ $settlement->goodsIssue->issue_number }} to {{ $settlement->employee->full_name }} on Vehicle:
            {{ $settlement->vehicle->vehicle_number }}

            <br>
        </h3>
        <div class="flex justify-center items-center float-right space-x-2">
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
                class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-900 transition">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
        </div>
    </x-slot>

    @php
        $netSale = (float) $settlement->items->sum('total_sales_value');
        $creditSalesAmount = (float) ($settlement->credit_sales_amount ?? 0);
        $chequeSalesAmount = (float) ($settlement->cheque_sales_amount ?? 0);
        $bankSalesAmount = (float) ($settlement->bank_transfer_amount ?? 0);
        $cashSalesAmount = (float) ($settlement->cash_sales_amount ?? 0);
        $cashDenominations = $settlement->cashDenominations->first();
        $cashDenominationTotal = (float) ($cashDenominations?->total_amount ?? 0.0);
        if ($cashDenominationTotal > 0) {
            $cashSalesAmount = $cashDenominationTotal;
        }

        $recoveryCash = (float) $settlement->recoveries->where('payment_method', 'cash')->sum('amount');
        $recoveryBank = (float) $settlement->recoveries->where('payment_method', 'bank_transfer')->sum('amount');
        $recoveryTotal = (float) ($settlement->credit_recoveries ?? 0);

        $expensesTotal = (float) ($settlement->expenses->sum('amount') ?? 0);
        $totalSaleAmount = $cashSalesAmount + $chequeSalesAmount + $bankSalesAmount + $creditSalesAmount;

        $netBalance = ($netSale + $recoveryTotal) - $creditSalesAmount - $expensesTotal;
        $cashReceived = $cashSalesAmount + $bankSalesAmount + $chequeSalesAmount;
        $shortExcess = $cashReceived - $netBalance;

        $totalCOGS = (float) ($settlement->items->sum('total_cogs') ?? 0);
        $grossProfit = $netSale - $totalCOGS;
        $grossMargin = $netSale > 0 ? ($grossProfit / $netSale) * 100 : 0;
        $netProfit = $grossProfit - $expensesTotal;
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
            <x-status-message class="mb-4 shadow-md" />

            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">

                <x-detail-table :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Product', 'align' => 'text-left'],
        ['label' => 'BF In', 'align' => 'text-right'],
        ['label' => 'Issued', 'align' => 'text-right'],
        ['label' => 'Batch Breakdown', 'align' => 'text-left'],
        ['label' => 'Sold', 'align' => 'text-right'],
        ['label' => 'Returned', 'align' => 'text-right'],
        ['label' => 'Shortage', 'align' => 'text-right'],
        ['label' => 'BF Out', 'align' => 'text-right'],
        ['label' => 'Sales Value', 'align' => 'text-right'],
    ]">
                    @foreach ($settlement->items as $item)
                        <tr class="border-b border-gray-200 text-sm">
                            <td class="py-1 px-2 text-center">{{ $item->line_no }}</td>
                            <td class="py-1 px-2">
                                <div class="font-semibold text-black">{{ $item->product->product_code }}</div>
                                <div class="text-xs text-black">{{ $item->product->product_name }}</div>
                            </td>
                            <td class="py-1 px-2 text-right">
                                @php
                                    // BF In = Total balance forward from previous period for this product
                                    // This would be calculated from previous settlement balance or initial stock
                                    $bfIn = 0; // You may want to link this from a balance forward calculation
                                @endphp
                                {{ number_format($bfIn, 2) }}
                            </td>
                            <td class="py-1 px-2 text-right">{{ number_format($item->quantity_issued, 2) }}</td>
                            <td class="py-1 px-2">
                                @if($item->batches->count() > 0)
                                    @if($item->batches->count() === 1)
                                                    @php $b = $item->batches->first(); @endphp
                                                    <div class="flex items-center space-x-1">
                                                        <span class="font-semibold text-green-600">
                                                            {{ number_format($b->quantity_issued, 0) }} × {{
                                        number_format($b->selling_price, 2) }}
                                                        </span>
                                                        @if($b->is_promotional)
                                                            <span
                                                                class="px-2 py-1 ml-1 text-xs font-semibold rounded bg-orange-100 text-orange-800">
                                                                Promotional
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <div class="text-xs text-black mt-1">
                                                        Sold: {{ number_format($b->quantity_sold, 0) }} |
                                                        Returned: {{ number_format($b->quantity_returned, 0) }} |
                                                        Shortage: {{ number_format($b->quantity_shortage, 0) }}
                                                    </div>
                                    @else
                                        <div class="space-y-1">
                                            @foreach($item->batches as $b)
                                                            <div
                                                                class="text-xs border-l-2 pl-2 {{ $b->is_promotional ? 'border-orange-400' : 'border-gray-300' }}">
                                                                <div class="flex justify-between">
                                                                    <span class="text-black font-medium">
                                                                        {{ number_format($b->quantity_issued, 0) }} × {{
                                                number_format($b->selling_price, 2) }}
                                                                        @if($b->is_promotional)
                                                                            <span title="Promotional">🎁</span>
                                                                        @endif
                                                                    </span>
                                                                    <span class="font-semibold">= {{ number_format($b->quantity_issued *
                                                $b->selling_price, 2) }}</span>
                                                                </div>
                                                                <div class="text-black mt-0.5">
                                                                    S: {{ number_format($b->quantity_sold, 0) }} |
                                                                    R: {{ number_format($b->quantity_returned, 0) }} |
                                                                    Sh: {{ number_format($b->quantity_shortage, 0) }}
                                                                </div>
                                                            </div>
                                            @endforeach
                                        </div>
                                    @endif
                                @else
                                    <span class="text-black text-xs">No batch data</span>
                                @endif
                            </td>
                            <td class="py-1 px-2 text-right font-semibold text-green-700">
                                {{ number_format($item->quantity_sold, 2) }}
                            </td>
                            <td class="py-1 px-2 text-right text-blue-700">
                                {{ number_format($item->quantity_returned, 2) }}
                            </td>
                            <td class="py-1 px-2 text-right text-red-700">
                                {{ number_format($item->quantity_shortage, 2) }}
                            </td>
                            <td class="py-1 px-2 text-right text-orange-700">
                                @php
                                    // BF Out = BF In + Issued - Sold - Returned - Shortage
                                    $bfOut = $bfIn + $item->quantity_issued - $item->quantity_sold -
                                        $item->quantity_returned - $item->quantity_shortage;
                                @endphp
                                {{ number_format($bfOut, 2) }}
                            </td>
                            <td class="py-1 px-2 text-right font-bold text-emerald-600">
                                {{ number_format($item->total_sales_value, 2) }}
                            </td>
                        </tr>
                    @endforeach

                    <x-slot name="footer">
                        <tr class="border-t-2 border-gray-300">
                            <td colspan="2" class="py-1 px-2 text-right font-bold text-lg">Totals:</td>
                            <td class="py-1 px-2 text-right font-bold text-lg text-purple-700">-</td>
                            <td class="py-1 px-2 text-right font-bold text-lg text-purple-700">
                                {{ number_format($settlement->items->sum('quantity_issued'), 2) }}
                            </td>
                            <td class="py-1 px-2"></td>
                            <td class="py-1 px-2 text-right font-bold text-lg text-green-700">
                                {{ number_format($settlement->total_quantity_sold, 2) }}
                            </td>
                            <td class="py-1 px-2 text-right font-bold text-lg text-blue-700">
                                {{ number_format($settlement->total_quantity_returned, 2) }}
                            </td>
                            <td class="py-1 px-2 text-right font-bold text-lg text-red-700">
                                {{ number_format($settlement->total_quantity_shortage, 2) }}
                            </td>
                            <td class="py-1 px-2 text-right font-bold text-lg text-orange-700">-</td>
                            <td class="py-1 px-2 text-right font-bold text-lg text-emerald-600">
                                {{ number_format($settlement->items->sum('total_sales_value'), 2) }}
                            </td>
                        </tr>
                        <tr class="border-t border-gray-300 bg-blue-50">
                            <td colspan="2" class="py-1 px-2 text-right font-bold text-sm">Value Totals:</td>
                            <td class="py-1 px-2 text-right font-bold text-sm text-purple-700">
                                {{ $valueTotals['bf_in_value'] > 0 ? number_format($valueTotals['bf_in_value'], 2) : '-' }}
                            </td>
                            <td class="py-1 px-2 text-right font-bold text-sm text-purple-700">
                                {{ number_format($valueTotals['issued_value'], 2) }}
                            </td>
                            <td class="py-1 px-2"></td>
                            <td class="py-1 px-2 text-right font-bold text-sm text-green-700">
                                {{ number_format($valueTotals['sold_value'], 2) }}
                            </td>
                            <td class="py-1 px-2 text-right font-bold text-sm text-blue-700">
                                {{ number_format($valueTotals['returned_value'], 2) }}
                            </td>
                            <td class="py-1 px-2 text-right font-bold text-sm text-red-700">
                                {{ number_format($valueTotals['shortage_value'], 2) }}
                            </td>
                            <td class="py-1 px-2 text-right font-bold text-sm text-orange-700">
                                {{ number_format($bfOutValue, 2) }}
                            </td>
                            <td class="py-1 px-2 text-right font-bold text-sm text-emerald-600">
                                {{ number_format($valueTotals['sold_value'], 2) }}
                            </td>
                        </tr>
                    </x-slot>
                </x-detail-table>

                <div class="pt-2 pb-6 pl-2 pr-2">
                    {{-- Professional Settlement Header --}}


                    {{-- Payment Details Cards - Moved here after Product-wise Settlement --}}
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6 items-start">

                        {{-- Credit Sales Detail Card --}}
                        <div class="bg-white rounded-lg border border-orange-300 overflow-hidden">
                            <div class="bg-gradient-to-r from-orange-500 to-orange-600 px-3 py-2">
                                <h4 class="text-sm font-bold text-white">Credit Sales Detail</h4>
                            </div>
                            <div class="p-0">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="border-b-2 border-gray-300">
                                            <th class="py-1 px-1 text-left text-black">Customer</th>
                                            <th class="py-1 px-1 text-right text-black">Sale</th>
                                            <th class="py-1 px-1 text-right text-black"
                                                title="Balance with this Salesman">BAL</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($settlement->creditSales as $creditSale)
                                                                            @php
                                                                                $saleAmount = $creditSale->sale_amount ?? 0;
                                                                                $salesmanBalance = $saleAmount;
                                                                            @endphp
                                                                            <tr class="hover:bg-gray-50">
                                                                                <td class="py-1 px-1 text-xs">{{ $creditSale->customer->customer_name ??
                                            'N/A' }}</td>
                                                                                <td class="py-1 px-1 text-right font-semibold text-xs"> {{
                                            number_format($saleAmount, 0) }}</td>
                                                                                <td class="py-1 px-1 text-right font-semibold text-xs text-blue-600"
                                                                                    title="Balance with Salesman:  {{ number_format($salesmanBalance, 2) }}">
                                                                                    {{ number_format($salesmanBalance, 0) }}
                                                                                </td>
                                                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="py-2 px-1 text-center text-black text-xs italic">
                                                    No credit sales entries</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    <tfoot class="border-t-2 border-gray-300">
                                        <tr class="bg-orange-50">
                                            <td class="py-1.5 px-1 text-right font-semibold text-orange-900 text-xs">
                                                Total:</td>
                                            <td class="py-1.5 px-1 text-right font-bold text-orange-700 text-xs"> {{
    number_format($settlement->creditSales->sum('sale_amount'), 0) }}</td>
                                            <td class="py-1.5 px-1 text-right font-bold text-blue-700 text-xs"> {{
    number_format($settlement->creditSales->sum('sale_amount'), 0) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        {{-- Recoveries Detail Card --}}
                        <div class="bg-white rounded-lg border border-green-300 overflow-hidden">
                            <div class="bg-gradient-to-r from-green-500 to-green-600 px-3 py-2">
                                <h4 class="text-sm font-bold text-white">Recoveries Detail</h4>
                            </div>
                            <div class="p-0">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="border-b-2 border-gray-300">
                                            <th class="py-1 px-1 text-left text-black">Customer</th>
                                            <th class="py-1 px-1 text-center text-black">Method</th>
                                            <th class="py-1 px-1 text-left text-black">Bank Account</th>
                                            <th class="py-1 px-1 text-right text-black">Recovery</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($settlement->recoveries as $recovery)
                                            <tr class="hover:bg-gray-50">
                                                <td class="py-1 px-1 text-xs">{{ $recovery->customer->customer_name ?? 'N/A' }}</td>
                                                <td class="py-1 px-1 text-center text-xs">
                                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $recovery->payment_method === 'cash' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700' }}">
                                                        {{ $recovery->payment_method === 'cash' ? 'Cash' : 'Bank' }}
                                                    </span>
                                                </td>
                                                <td class="py-1 px-1 text-xs">
                                                    @if($recovery->payment_method === 'bank_transfer')
                                                        {{ $recovery->bankAccount->account_name ?? '—' }}
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td class="py-1 px-1 text-right font-semibold text-xs text-green-600">
                                                    {{ number_format($recovery->amount, 0) }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="py-2 px-1 text-center text-black text-xs italic">
                                                    No recovery entries</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    <tfoot class="border-t-2 border-gray-300">
                                        <tr class="bg-green-50">
                                            <td class="py-1.5 px-1 text-right font-semibold text-green-900 text-xs">
                                                Total:</td>
                                            <td colspan="3" class="py-1.5 px-1 text-right font-bold text-green-700 text-xs">
                                                {{ number_format($settlement->recoveries->sum('amount'), 0) }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        {{-- Cheque Payments Detail Card --}}
                        <div class="bg-white rounded-lg border border-purple-300 overflow-hidden">
                            <div class="bg-gradient-to-r from-purple-500 to-purple-600 px-3 py-2">
                                <h4 class="text-sm font-bold text-white">Cheque Payments</h4>
                            </div>
                            <div class="p-0">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="border-b-2 border-gray-300">
                                            <th class="py-1 px-1 text-left text-black">Customer</th>
                                            <th class="py-1 px-1 text-left text-black">Cheque #</th>
                                            <th class="py-1 px-1 text-left text-black">Bank</th>
                                            <th class="py-1 px-1 text-left text-black">Deposit Bank</th>
                                            <th class="py-1 px-1 text-left text-black">Cheque Date</th>
                                            <th class="py-1 px-1 text-right text-black">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($settlement->cheques as $cheque)
                                            <tr class="hover:bg-gray-50">
                                                <td class="py-1 px-1 text-xs">{{ $cheque->customer->customer_name ?? 'N/A' }}</td>
                                                <td class="py-1 px-1 text-xs">{{ $cheque->cheque_number ?? 'N/A' }}</td>
                                                <td class="py-1 px-1 text-xs">{{ $cheque->bank_name ?? 'N/A' }}</td>
                                                <td class="py-1 px-1 text-xs">{{ $cheque->bankAccount->account_name ?? 'N/A' }}</td>
                                                <td class="py-1 px-1 text-xs">
                                                    {{ $cheque->cheque_date ? \Carbon\Carbon::parse($cheque->cheque_date)->format('d M Y') : 'N/A' }}
                                                </td>
                                                <td class="py-1 px-1 text-right font-semibold text-xs">
                                                    {{ number_format($cheque->amount, 2) }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="py-2 px-1 text-center text-black text-xs italic">
                                                    No cheque payments</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    <tfoot class="border-t-2 border-gray-300">
                                        <tr class="bg-purple-50">
                                            <td colspan="5"
                                                class="py-1.5 px-1 text-right font-semibold text-purple-900 text-xs">
                                                Total:</td>
                                            <td class="py-1.5 px-1 text-right font-bold text-purple-700 text-xs"> {{
    number_format($settlement->cheques->sum('amount'), 2) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        {{-- Bank Transfer Detail Card --}}
                        <div class="bg-white rounded-lg border border-blue-300 overflow-hidden">
                            <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-3 py-2">
                                <h4 class="text-sm font-bold text-white">Bank Transfers</h4>
                            </div>
                            <div class="p-0">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="border-b-2 border-gray-300">
                                            <th class="py-1 px-1 text-left text-black">Customer</th>
                                            <th class="py-1 px-1 text-left text-black">Bank</th>
                                            <th class="py-1 px-1 text-left text-black">Ref #</th>
                                            <th class="py-1 px-1 text-left text-black">Transfer Date</th>
                                            <th class="py-1 px-1 text-right text-black">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($settlement->bankTransfers as $transfer)
                                            <tr class="hover:bg-gray-50">
                                                <td class="py-1 px-1 text-xs">{{ $transfer->customer->customer_name ?? 'N/A' }}</td>
                                                <td class="py-1 px-1 text-xs">{{ $transfer->bankAccount->account_name ?? ($transfer->bankAccount->bank_name ?? 'Online') }}</td>
                                                <td class="py-1 px-1 text-xs">
                                                    {{ $transfer->reference_number ?? 'Transfer' }}
                                                </td>
                                                <td class="py-1 px-1 text-xs">
                                                    {{ $transfer->transfer_date ? \Carbon\Carbon::parse($transfer->transfer_date)->format('d M Y') : 'N/A' }}
                                                </td>
                                                <td class="py-1 px-1 text-right font-semibold text-xs">
                                                    {{ number_format($transfer->amount, 2) }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="py-2 px-1 text-center text-black text-xs italic">
                                                    No bank transfers</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    <tfoot class="border-t-2 border-gray-300">
                                        <tr class="bg-blue-50">
                                            <td colspan="4"
                                                class="py-1.5 px-1 text-right font-semibold text-blue-900 text-xs">
                                                Total:</td>
                                            <td class="py-1.5 px-1 text-right font-bold text-blue-700 text-xs"> {{
    number_format($settlement->bankTransfers->sum('amount'), 2) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6 items-start">
                        {{-- Cash Detail Card --}}
                        <div class="bg-white rounded-lg border border-orange-300 overflow-hidden">
                            <div class="bg-gradient-to-r from-orange-500 to-orange-600 px-3 py-2">
                                <h4 class="text-sm font-bold text-white text-center">Cash Detail (Denomination
                                    Breakdown)
                                </h4>
                            </div>
                            <div class="p-0">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="border-b-2 border-gray-300">
                                            <th class="py-1 px-1 text-left text-black">Denomination</th>
                                            <th class="py-1 px-1 text-right text-black">Quantity</th>
                                            <th class="py-1 px-1 text-right text-black" title="Recovery">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $denomData = $cashDenominations;
                                            $denominations = [
                                                ['label' => '5,000 Notes', 'qty' => $denomData?->denom_5000 ?? 0, 'value' => 5000],
                                                ['label' => '1,000 Notes', 'qty' => $denomData?->denom_1000 ?? 0, 'value' => 1000],
                                                ['label' => '500 Notes', 'qty' => $denomData?->denom_500 ?? 0, 'value' => 500],
                                                ['label' => '100 Notes', 'qty' => $denomData?->denom_100 ?? 0, 'value' => 100],
                                                ['label' => '50 Notes', 'qty' => $denomData?->denom_50 ?? 0, 'value' => 50],
                                                ['label' => '20 Notes', 'qty' => $denomData?->denom_20 ?? 0, 'value' => 20],
                                                ['label' => '10 Notes', 'qty' => $denomData?->denom_10 ?? 0, 'value' => 10],
                                                ['label' => 'Loose Cash/Coins', 'qty' => $denomData?->denom_coins ?? 0, 'value' => 1, 'is_coins' => true],
                                            ];
                                            $totalCash = 0;
                                        @endphp
                                        @foreach($denominations as $denom)
                                            @php
                                                if (isset($denom['is_coins']) && $denom['is_coins']) {
                                                    $amount = (float) $denom['qty'];
                                                } else {
                                                    $amount = $denom['qty'] * $denom['value'];
                                                }
                                                $totalCash += $amount;
                                            @endphp
                                            <tr style="border-top: 1px solid #000;">
                                                <td style="padding: 3px 6px; border: none;">{{ $denom['label'] }}</td>
                                                <td style="padding: 3px 6px; text-align: right; border: none;">
                                                    @if(isset($denom['is_coins']) && $denom['is_coins'])
                                                        {{ number_format($denom['qty'], 2) }}
                                                    @else
                                                        {{ number_format($denom['qty'], 0) }}
                                                    @endif
                                                </td>
                                                <td
                                                    style="padding: 3px 6px; text-align: right; font-weight: 600; border: none;">
                                                    {{ number_format($amount, 2) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                        <tr style="background-color: #f0fdf4; border-top: 2px solid #059669;">
                                            <td colspan="2"
                                                style="padding: 4px 6px; font-weight: bold; color: #047857;">
                                                Total Physical Cash
                                            </td>
                                            <td
                                                style="padding: 4px 6px; text-align: right; font-weight: bold; color: #047857;">
                                                {{ number_format($totalCash, 2) }}
                                            </td>
                                        </tr>

                                        @if($settlement->bank_transfer_amount > 0)
                                            <tr style="background-color: #eff6ff;">
                                                <td colspan="2" style="padding: 3px 6px;">
                                                    <span style="font-weight: 600; color: #1e40af;">Bank Transfer / Online
                                                        Payment</span>
                                                </td>
                                                <td
                                                    style="padding: 3px 6px; text-align: right; font-weight: 600; color: #1e40af; border: none;">
                                                    {{ number_format($settlement->bank_transfer_amount, 2) }}
                                                </td>
                                            </tr>
                                        @endif

                                        @if($settlement->cheque_count > 0)
                                            <tr style="background-color: #faf5ff;">
                                                <td colspan="3" style="padding: 4px 6px;">
                                                    <div style="font-weight: 600; color: #7c3aed; margin-bottom: 4px;">
                                                        Cheque Details ({{ $settlement->cheque_count }} cheque(s))
                                                    </div>
                                                    <div style="display: flex; flex-direction: column; gap: 4px;">
                                                        @foreach($settlement->cheques as $cheque)
                                                                                                    <div
                                                                                                        style="background-color: white; padding: 4px; border: 1px solid #c4b5fd; border-radius: 4px; font-size: 10px;">
                                                                                                        <div
                                                                                                            style="display: grid; grid-template-columns: 1fr 1fr; gap: 4px;">
                                                                                                            <div><span style="font-weight: 500;">Cheque #:</span> {{
                                                            $cheque->cheque_number ?? 'N/A' }}</div>
                                                                                                            <div><span style="font-weight: 500;">Amount:</span> {{
                                                            number_format($cheque->amount ?? 0, 0) }}</div>
                                                                                                            <div><span style="font-weight: 500;">Bank:</span> {{
                                                            $cheque->bank_name ?? 'N/A' }}</div>
                                                                                                            <div><span style="font-weight: 500;">Deposit Bank:</span> {{
                                                            $cheque->bankAccount->account_name ?? 'N/A' }}</div>
                                                                                                            <div><span style="font-weight: 500;">Date:</span> {{
                                                            isset($cheque->cheque_date) ?
                                                            \Carbon\Carbon::parse($cheque->cheque_date)->format('d M Y') : 'N/A' }}</div>
                                                                                                        </div>
                                                                                                    </div>
                                                        @endforeach
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr style="background-color: #e9d5ff;">
                                                <td colspan="2" style="padding: 4px 6px; font-weight: 600; color: #7c3aed;">
                                                    Total Cheques
                                                </td>
                                                <td
                                                    style="padding: 4px 6px; text-align: right; font-weight: 600; color: #7c3aed;">
                                                    {{ number_format($settlement->cheques_collected, 2) }}
                                                </td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Expense Detail Card --}}
                        <div class="bg-white rounded-lg border border-orange-300 overflow-hidden">
                            <div class="bg-gradient-to-r from-orange-500 to-orange-600 px-3 py-2">
                                <h4 class="text-sm font-bold text-white">Expense Detail
                                </h4>
                            </div>
                            <div class="p-0">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="border-b-2 border-gray-300">
                                            <th class="py-1 px-1 text-left text-black">Expense Account</th>
                                            <th class="py-1 px-1 text-right text-black">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $totalExpenses = 0; @endphp
                                        @forelse($settlement->expenses as $expense)
                                            @php $totalExpenses += $expense->amount; @endphp
                                            <tr class="hover:bg-gray-50">
                                                <td class="py-1 px-1 text-xs">
                                                    @if($expense->expenseAccount)
                                                        {{ $expense->expenseAccount->account_name }}
                                                        <span class="text-gray-500">({{ $expense->expenseAccount->account_code
                                                                            }})</span>
                                                    @else
                                                        {{ $expense->description ?? 'Unknown Account' }}
                                                    @endif
                                                </td>
                                                <td class="py-1 px-1 text-right font-semibold text-xs">
                                                    {{ number_format($expense->amount, 2) }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="2" class="py-2 px-1 text-center text-black text-xs italic">
                                                    No expenses recorded
                                                </td>
                                            </tr>
                                        @endforelse

                                        {{-- Advance Tax Details --}}
                                        @if($settlement->advanceTaxes->count() > 0)
                                            @foreach($settlement->advanceTaxes as $tax)
                                                <tr class="hover:bg-gray-50 bg-yellow-50">
                                                    <td class="py-1 px-1 text-xs">
                                                        Advance Tax - {{ $tax->customer->customer_name ?? 'N/A' }}
                                                        @if($tax->invoice_number)
                                                            <span class="text-gray-500">(Inv: {{ $tax->invoice_number }})</span>
                                                        @endif
                                                        <span class="text-gray-500">(A/C 1171)</span>
                                                    </td>
                                                    <td class="py-1 px-1 text-right font-semibold text-xs">
                                                        {{ number_format($tax->tax_amount, 0) }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endif
                                    </tbody>
                                    <tfoot class="border-t-2 border-gray-300">
                                        <tr class="bg-orange-50">
                                            <td class="py-1.5 px-1 text-right font-semibold text-orange-900 text-xs">
                                                Total:</td>
                                            <td class="py-1.5 px-1 text-right font-bold text-orange-700 text-xs">
                                                {{ number_format($totalExpenses, 2) }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        {{-- Sales Summary --}}
                        <div class="bg-white rounded-lg border border-orange-300 overflow-hidden">
                            <div class="bg-gradient-to-r from-orange-500 to-orange-600 px-3 py-2">
                                <h4 class="text-sm font-bold text-white">Sales Summary</h4>
                            </div>
                            <div class="p-0">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="border-b-2 border-gray-300">
                                            <th class="py-1 px-1 text-left text-black">Description</th>
                                            <th class="py-1 px-1 text-right text-black">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr class="border-t border-gray-200">
                                            <td class="py-1 px-1 text-xs font-bold text-black">Total Sale Amount</td>
                                            <td class="py-1 px-1 text-right font-bold text-xs text-black">
                                                {{ number_format($totalSaleAmount, 2) }}
                                            </td>
                                        </tr>
                                        <tr class="border-t border-gray-200">
                                            <td class="py-1 px-1 text-xs text-black">Credit Sales</td>
                                            <td class="py-1 px-1 text-right font-semibold text-xs text-black">
                                                {{ number_format($creditSalesAmount, 2) }}
                                            </td>
                                        </tr>
                                        <tr class="border-t border-gray-200">
                                            <td class="py-1 px-1 text-xs text-black">Cheque Sales</td>
                                            <td class="py-1 px-1 text-right font-semibold text-xs text-black">
                                                {{ number_format($chequeSalesAmount, 2) }}
                                            </td>
                                        </tr>
                                        <tr class="border-t border-gray-200">
                                            <td class="py-1 px-1 text-xs text-black">Bank Transfer</td>
                                            <td class="py-1 px-1 text-right font-semibold text-xs text-black">
                                                {{ number_format($bankSalesAmount, 2) }}
                                            </td>
                                        </tr>
                                        <tr class="border-t border-gray-200">
                                            <td class="py-1 px-1 text-xs text-black">Cash Sales</td>
                                            <td class="py-1 px-1 text-right font-semibold text-xs text-black">
                                                {{ number_format($cashSalesAmount, 2) }}
                                            </td>
                                        </tr>
                                        <tr class="border-t border-gray-200 bg-gray-50">
                                            <td class="py-1 px-1 text-xs text-black">Net Sale (Sold Items Value)</td>
                                            <td class="py-1 px-1 text-right font-semibold text-xs text-black">
                                                {{ number_format($netSale, 2) }}
                                            </td>
                                        </tr>
                                        <tr class="border-t border-gray-200">
                                            <td class="py-1 px-1 text-xs text-black">Recovery (From Customers)</td>
                                            <td class="py-1 px-1 text-right font-semibold text-xs text-teal-700">
                                                <div class="flex flex-col items-end">
                                                    <span>{{ number_format($recoveryTotal, 2) }}</span>
                                                    @if($recoveryBank > 0 || $recoveryCash > 0)
                                                        <span class="text-[10px] text-gray-500 italic">
                                                            (Cash: {{ number_format($recoveryCash, 2) }}, Bank: {{ number_format($recoveryBank, 2) }})
                                                        </span>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="border-t border-gray-200">
                                            <td class="py-1 px-1 text-xs text-red-700">Less: Expenses</td>
                                            <td class="py-1 px-1 text-right font-semibold text-xs text-red-700">
                                                {{ number_format($expensesTotal, 2) }}
                                            </td>
                                        </tr>
                                        <tr class="bg-indigo-50 border-y-2 border-indigo-200">
                                            <td class="py-1 px-1 text-xs font-semibold text-indigo-900">Net Balance
                                            </td>
                                            <td class="py-1 px-1 text-right font-bold text-xs text-indigo-900">
                                                {{ number_format($netBalance, 2) }}
                                            </td>
                                        </tr>
                                        <tr class="border-t border-gray-200">
                                            <td class="py-1 px-1 text-xs text-black">
                                                Cash Received (counted)
                                                <div class="text-[10px] text-gray-600 italic">
                                                    Physical + Bank + Cheques
                                                </div>
                                            </td>
                                            <td class="py-1 px-1 text-right font-semibold text-xs text-emerald-700">
                                                {{ number_format($cashReceived, 2) }}
                                            </td>
                                        </tr>
                                        <tr class="bg-purple-50 border-y-2 border-purple-200">
                                            <td class="py-1 px-1 text-xs font-semibold text-purple-900">
                                                Short/Excess</td>
                                            <td class="py-1 px-1 text-right font-bold text-xs text-purple-900">
                                                {{ number_format($shortExcess, 2) }}
                                            </td>
                                        </tr>
                                        {{-- Profit Analysis --}}
                                        <tr class="bg-gray-100 border-t-2 border-gray-300">
                                            <td colspan="2"
                                                class="py-1 px-1 text-center font-bold text-black text-xs uppercase tracking-wide">
                                                Profit Analysis</td>
                                        </tr>
                                        <tr class="border-t border-gray-200">
                                            <td class="py-0.5 px-1 text-xs text-gray-700">Total COGS</td>
                                            <td class="py-0.5 px-1 text-right font-semibold text-xs text-black">
                                                {{ number_format($totalCOGS, 2) }}
                                            </td>
                                        </tr>
                                        <tr class="bg-green-50 border-t border-green-200">
                                            <td class="py-0.5 px-1 font-semibold text-xs text-green-800">Gross Profit
                                                (Sales - COGS)</td>
                                            <td class="py-0.5 px-1 text-right font-bold text-xs text-green-700">
                                                {{ number_format($grossProfit, 2) }}
                                            </td>
                                        </tr>
                                        <tr class="border-t border-gray-200">
                                            <td class="py-0.5 px-1 text-xs text-gray-500 pl-2">Gross Margin</td>
                                            <td class="py-0.5 px-1 text-right text-xs font-semibold">
                                                {{ number_format($grossMargin, 2) }}%
                                            </td>
                                        </tr>
                                        <tr class="border-t border-gray-200">
                                            <td class="py-0.5 px-1 text-xs text-red-700">Less: Expenses</td>
                                            <td class="py-0.5 px-1 text-right font-semibold text-xs text-red-700">
                                                {{ number_format($totalExpenses, 2) }}
                                            </td>
                                        </tr>
                                        <tr
                                            class="bg-gradient-to-r from-emerald-100 to-teal-100 border-t-2 border-emerald-400">
                                            <td class="py-1 px-1 font-bold text-xs text-emerald-900">Net Profit (After
                                                Expenses)</td>
                                            <td class="py-1 px-1 text-right font-bold text-xs text-emerald-900">
                                                {{ number_format($netProfit, 2) }}
                                            </td>
                                        </tr>
                                        <tr class="border-t border-gray-200">
                                            <td class="py-0.5 px-1 text-xs text-gray-500 pl-2">Net Margin</td>
                                            <td class="py-0.5 px-1 text-right text-xs font-semibold">
                                                {{ number_format($netMargin, 2) }}%
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- Cash Reconciliation Summary --}}
                    <div class="mb-6">
                        <div class="flex items-center mb-4">
                            <svg class="w-6 h-6 mr-2 text-indigo-600" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z">
                                </path>
                            </svg>
                            <h4 class="text-lg font-bold text-black">Cash Reconciliation Summary</h4>
                        </div>



                            {{-- Financial Performance Cards --}}
                            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 mb-6">
                            @php
                                $totalSalesValue = $netSale;
                                $grossProfitMargin = $netSale > 0 ? ($grossProfit / $netSale) * 100 : 0;
                                $netProfitMargin = $netSale > 0 ? ($netProfit / $netSale) * 100 : 0;
                            @endphp

                            {{-- Sales Performance Card --}}
                            <div class="bg-white rounded-lg border border-blue-300 overflow-hidden">
                                <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-3 py-2 text-center">
                                    <h4 class="text-base font-bold text-black">Sales Performance</h4>
                                </div>
                                <table style="border-collapse: collapse; width: 100%; font-size: 14px;">
                                    <thead>
                                        <tr style="background-color: #f8fafc; color: #000;">
                                            <th
                                                style="padding: 4px 6px; text-align: left; font-weight: bold; color: #374151;">
                                                Description</th>
                                            <th
                                                style="padding: 4px 6px; text-align: right; font-weight: bold; color: #374151;">
                                                Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr style="color: #000;">
                                            <td style="padding: 3px 6px;">Total Sales Value</td>
                                            <td style="padding: 3px 6px; text-align: right; font-weight: 600;">
                                                {{
    number_format($totalSalesValue, 0) }}</td>
                                        </tr>
                                        <tr style="color: #000;">
                                            <td style="padding: 3px 6px;">Credit Extended</td>
                                            <td
                                                style="padding: 3px 6px; text-align: right; font-weight: 600; color: #ea580c;">
                                                {{ number_format($creditSalesAmount, 0) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="border-t-2 border-gray-300">
                                        <tr class="bg-blue-50">
                                            <td class="py-1.5 px-1 text-right font-semibold text-blue-900 text-xs">
                                                Net Cash Sales:</td>
                                            <td class="py-1.5 px-1 text-right font-bold text-blue-700 text-xs">{{
    number_format($totalSalesValue - $creditSalesAmount, 0) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            {{-- Profitability Card --}}
                            <div class="bg-white rounded-lg border border-green-300 overflow-hidden">
                                <div class="bg-gradient-to-r from-green-500 to-green-600 px-3 py-2 text-center">
                                    <h4 class="text-base font-bold text-black">Profitability Analysis</h4>
                                </div>
                                <table style="border-collapse: collapse; width: 100%; font-size: 14px;">
                                    <thead>
                                        <tr style="background-color: #f8fafc; color: #000;">
                                            <th
                                                style="padding: 4px 6px; text-align: left; font-weight: bold; color: #374151;">
                                                Metric</th>
                                            <th
                                                style="padding: 4px 6px; text-align: right; font-weight: bold; color: #374151;">
                                                Value</th>
                                            <th
                                                style="padding: 4px 6px; text-align: right; font-weight: bold; color: #374151;">
                                                %</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr style="color: #000;">
                                            <td style="padding: 3px 6px;">Total COGS</td>
                                            <td
                                                style="padding: 3px 6px; text-align: right; font-weight: 600; color: #dc2626;">
                                                {{
    number_format($totalCOGS, 0) }}</td>
                                            <td style="padding: 3px 6px; text-align: right;">-
                                            </td>
                                        </tr>
                                        <tr style="color: #000;">
                                            <td style="padding: 3px 6px;">Gross Profit</td>
                                            <td
                                                style="padding: 3px 6px; text-align: right; font-weight: 600; color: {{ $grossProfit >= 0 ? '#059669' : '#dc2626' }};">
                                                {{ number_format($grossProfit, 0) }}
                                            </td>
                                            <td
                                                style="padding: 3px 6px; text-align: right; font-weight: 600; color: {{ $grossProfitMargin >= 0 ? '#059669' : '#dc2626' }};">
                                                {{ number_format($grossProfitMargin, 1) }}%
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 3px 6px;">Total Expenses</td>
                                            <td
                                                style="padding: 3px 6px; text-align: right; font-weight: 600; color: #dc2626;">
                                                {{ number_format($expensesTotal, 0) }}</td>
                                            <td style="padding: 3px 6px; text-align: right;">-
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr
                                            style="background-color: #f0fdf4; border-top: 2px solid #059669; color: #000;">
                                            <td style="padding: 4px 6px; font-weight: bold; color: #047857;">
                                                Net Profit:</td>
                                            <td
                                                style="padding: 4px 6px; text-align: right; font-weight: bold; color: {{ $netProfit >= 0 ? '#047857' : '#dc2626' }};">
                                                {{ number_format($netProfit, 0) }}
                                            </td>
                                            <td
                                                style="padding: 4px 6px; text-align: right; font-weight: bold; color: {{ $netProfitMargin >= 0 ? '#047857' : '#dc2626' }};">
                                                {{ number_format($netProfitMargin, 1) }}%
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            {{-- Final Summary Card --}}
                            <div
                                class="bg-white rounded-lg border {{ $netProfit >= 0 ? 'border-emerald-300' : 'border-red-300' }} overflow-hidden">
                                <div
                                    class="bg-gradient-to-r {{ $netProfit >= 0 ? 'from-emerald-500 to-emerald-600' : 'from-red-500 to-red-600' }} px-3 py-2 text-center">
                                    <h4 class="text-base font-bold text-black">Final Summary</h4>
                                </div>
                                <table style="border-collapse: collapse; width: 100%; font-size: 14px;">
                                    <thead>
                                        <tr style="background-color: #f8fafc; color: #000;">
                                            <th
                                                style="padding: 4px 6px; text-align: left; font-weight: bold; color: #374151;">
                                                Summary Item</th>
                                            <th
                                                style="padding: 4px 6px; text-align: right; font-weight: bold; color: #374151;">
                                                Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr style="color: #000;">
                                            <td style="padding: 3px 6px;">Gross Profit</td>
                                            <td
                                                style="padding: 3px 6px; text-align: right; font-weight: 600; color: {{ $grossProfit >= 0 ? '#059669' : '#dc2626' }};">
                                                {{ number_format($grossProfit, 0) }}
                                            </td>
                                        </tr>
                                        <tr style="color: #000;">
                                            <td style="padding: 3px 6px;">Less: Expenses
                                            </td>
                                            <td
                                                style="padding: 3px 6px; text-align: right; font-weight: 600; color: #dc2626;">
                                                {{ number_format($expensesTotal, 0) }}</td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr
                                            style="background-color: {{ $netProfit >= 0 ? '#ecfdf5' : '#fef2f2' }}; border-top: 2px solid {{ $netProfit >= 0 ? '#059669' : '#dc2626' }}; color: #000;">
                                            <td
                                                style="padding: 5px 6px; font-weight: bold; color: {{ $netProfit >= 0 ? '#047857' : '#991b1b' }};">
                                                Net Profit After Expenses:
                                            </td>
                                            <td
                                                style="padding: 5px 6px; text-align: right; font-weight: bold; color: {{ $netProfit >= 0 ? '#047857' : '#991b1b' }}; font-size: 12px;">
                                                {{ number_format($netProfit, 0) }}
                                            </td>
                                        </tr>
                                        <tr
                                            style="background-color: {{ $netProfit >= 0 ? '#f0fdf4' : '#fee2e2' }}; color: #000;">
                                            <td
                                                style="padding: 3px 6px; font-weight: 600; color: {{ $netProfit >= 0 ? '#065f46' : '#7f1d1d' }};">
                                                Net Margin:
                                            </td>
                                            <td
                                                style="padding: 3px 6px; text-align: right; font-weight: 600; color: {{ $netProfitMargin >= 0 ? '#065f46' : '#7f1d1d' }};">
                                                {{ number_format($netProfitMargin, 1) }}%
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            {{-- Cash Summary Card --}}
                            <div class="bg-white rounded-lg border border-emerald-300 overflow-hidden">
                                <div class="bg-gradient-to-r from-emerald-500 to-emerald-600 px-3 py-2 text-center">
                                    <h4 class="text-base font-bold text-black">Cash Summary</h4>
                                </div>
                                <table style="border-collapse: collapse; width: 100%; font-size: 14px;">
                                    <thead>
                                        <tr style="background-color: #f8fafc; color: #000;">
                                            <th
                                                style="padding: 4px 6px; text-align: left; font-weight: bold; color: #374151;">
                                                Item</th>
                                            <th
                                                style="padding: 4px 6px; text-align: right; font-weight: bold; color: #374151;">
                                                Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr style="color: #000;">
                                            <td style="padding: 3px 6px;">Cash Collected</td>
                                            <td
                                                style="padding: 3px 6px; text-align: right; font-weight: 600; color: #059669;">
                                                {{ number_format($settlement->cash_collected ?? 0, 0) }}
                                            </td>
                                        </tr>
                                        <tr style="color: #000;">
                                            <td style="padding: 3px 6px;">Credit Recovery</td>
                                            <td
                                                style="padding: 3px 6px; text-align: right; font-weight: 600; color: #1e40af;">
                                                {{ number_format($recoveryTotal, 0) }}
                                            </td>
                                        </tr>
                                        <tr style="color: #000;">
                                            <td style="padding: 3px 6px;">Total Expenses</td>
                                            <td
                                                style="padding: 3px 6px; text-align: right; font-weight: 600; color: #dc2626;">
                                                {{ number_format($expensesTotal, 0) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr
                                            style="background-color: #ecfdf5; border-top: 2px solid #059669; color: #000;">
                                            <td style="padding: 4px 6px; font-weight: bold; color: #047857;">
                                                Net Cash to Deposit:</td>
                                            <td
                                                style="padding: 4px 6px; text-align: right; font-weight: bold; color: #047857; font-size: 12px;">
                                                {{ number_format($settlement->cash_to_deposit ?? 0, 0) }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    @if ($settlement->notes)
                        <div class="mt-6">
                            <h3 class="text-sm font-semibold text-black uppercase mb-2">Notes</h3>
                            <p class="text-sm text-black">{{ $settlement->notes }}</p>
                        </div>
                    @endif

                    @if ($settlement->posted_at)
                        <div class="mt-6 p-4 bg-green-50 border border-green-200 rounded-md">
                            <p class="text-sm text-green-800">
                                This settlement was posted on {{ $settlement->posted_at->format('d M Y, h:i A') }}
                            </p>
                            @if ($settlement->journalEntry)
                                <p class="text-sm text-green-800 mt-2">
                                    Journal Entry: <a href="{{ route('journal-entries.show', $settlement->journalEntry) }}"
                                        class="font-semibold underline">{{ $settlement->journalEntry->entry_number }}</a>
                                </p>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
