<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-black leading-tight inline-block">
            Sales Settlement: {{ $settlement->settlement_number }}
        </h2>
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

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4 shadow-md" />

            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    {{-- Professional Settlement Header --}}
                    <table style="width: 100%; border-collapse: collapse; font-size: 14px; margin-bottom: 20px;"
                        border="1">
                        <thead>
                            <tr style="background-color: #1e3a5f;">
                                <th colspan="6"
                                    style="border: 1px solid #000; padding: 12px; text-align: center; color: white; font-size: 18px; font-weight: bold; letter-spacing: 1px;">
                                    SALES SETTLEMENT
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td
                                    style="border: 1px solid #000; padding: 8px; background-color: #f3f4f6; font-weight: bold; width: 15%;">
                                    Settlement No.</td>
                                <td
                                    style="border: 1px solid #000; padding: 8px; width: 18%; font-weight: bold; color: #1e3a5f;">
                                    {{ $settlement->settlement_number }}</td>
                                <td
                                    style="border: 1px solid #000; padding: 8px; background-color: #f3f4f6; font-weight: bold; width: 15%;">
                                    Settlement Date</td>
                                <td style="border: 1px solid #000; padding: 8px; width: 18%;">{{
                                    \Carbon\Carbon::parse($settlement->settlement_date)->format('d M Y') }}</td>
                                <td
                                    style="border: 1px solid #000; padding: 8px; background-color: #f3f4f6; font-weight: bold; width: 15%;">
                                    Status</td>
                                <td style="border: 1px solid #000; padding: 8px; width: 19%;">
                                    <span
                                        style="padding: 4px 12px; border-radius: 4px; font-weight: bold; 
                                        {{ $settlement->status === 'draft' ? 'background-color: #fef3c7; color: #92400e;' : '' }}
                                        {{ $settlement->status === 'posted' ? 'background-color: #d1fae5; color: #065f46;' : '' }}">
                                        {{ strtoupper($settlement->status) }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td
                                    style="border: 1px solid #000; padding: 8px; background-color: #f3f4f6; font-weight: bold;">
                                    Goods Issue</td>
                                <td style="border: 1px solid #000; padding: 8px; font-weight: bold; color: #1e3a5f;">{{
                                    $settlement->goodsIssue->issue_number }}</td>
                                <td
                                    style="border: 1px solid #000; padding: 8px; background-color: #f3f4f6; font-weight: bold;">
                                    Salesman</td>
                                <td style="border: 1px solid #000; padding: 8px;">{{ $settlement->employee->full_name }}
                                </td>
                                <td
                                    style="border: 1px solid #000; padding: 8px; background-color: #f3f4f6; font-weight: bold;">
                                    Vehicle</td>
                                <td style="border: 1px solid #000; padding: 8px;">{{
                                    $settlement->vehicle->vehicle_number }}</td>
                            </tr>
                            <tr>
                                <td
                                    style="border: 1px solid #000; padding: 8px; background-color: #f3f4f6; font-weight: bold;">
                                    Warehouse</td>
                                <td style="border: 1px solid #000; padding: 8px;" colspan="5">{{
                                    $settlement->warehouse->warehouse_name }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <x-detail-table title="Product-wise Settlement" :headers="[
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
                        </x-slot>
                    </x-detail-table>

                    {{-- Spacing after Product-wise Settlement --}}
                    <div class="mb-6"></div>

                    {{-- Payment Details Cards - Moved here after Product-wise Settlement --}}
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
                        {{-- Credit Sales Detail Card --}}
                        <div class="bg-white rounded-lg border border-orange-300 overflow-hidden">
                            <div class="bg-gradient-to-r from-orange-500 to-orange-600 px-3 py-2">
                                <h4 class="text-sm font-bold text-white">Credit Sales Detail</h4>
                            </div>
                            <div class="p-3">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="border-b-2 border-gray-300">
                                            <th class="py-1 px-1 text-left text-black">Customer</th>
                                            <th class="py-1 px-1 text-right text-black">Sale</th>
                                            <th class="py-1 px-1 text-right text-black" title="Recovery">REC</th>
                                            <th class="py-1 px-1 text-right text-black"
                                                title="Balance with this Salesman">BAL</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($settlement->creditSales as $creditSale)
                                        @php
                                        $saleAmount = $creditSale->sale_amount ?? 0;
                                        $recoveryAmount = $creditSale->recovery_amount ?? 0;
                                        $salesmanBalance = $saleAmount - $recoveryAmount;
                                        @endphp
                                        <tr class="hover:bg-gray-50">
                                            <td class="py-1 px-1 text-xs">{{ $creditSale->customer->customer_name ??
                                                'N/A' }}</td>
                                            <td class="py-1 px-1 text-right font-semibold text-xs"> {{
                                                number_format($saleAmount, 0) }}</td>
                                            <td class="py-1 px-1 text-right font-semibold text-xs text-green-600"
                                                title="Recovery:  {{ number_format($recoveryAmount, 2) }}"> {{
                                                number_format($recoveryAmount, 0) }}</td>
                                            <td class="py-1 px-1 text-right font-semibold text-xs text-blue-600"
                                                title="Balance with Salesman:  {{ number_format($salesmanBalance, 2) }}">
                                                {{ number_format($salesmanBalance, 0) }}</td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="4" class="py-2 px-1 text-center text-black text-xs italic">
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
                                            <td class="py-1.5 px-1 text-right font-bold text-green-700 text-xs"> {{
                                                number_format($settlement->creditSales->sum('recovery_amount'), 0) }}
                                            </td>
                                            <td class="py-1.5 px-1 text-right font-bold text-blue-700 text-xs"> {{
                                                number_format($settlement->creditSales->sum('sale_amount') -
                                                $settlement->creditSales->sum('recovery_amount'), 0) }}</td>
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
                            <div class="p-3">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="border-b-2 border-gray-300">
                                            <th class="py-1 px-1 text-left text-black">Cheque #</th>
                                            <th class="py-1 px-1 text-left text-black">Bank</th>
                                            <th class="py-1 px-1 text-right text-black">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($settlement->cheques as $cheque)
                                        <tr class="hover:bg-gray-50">
                                            <td class="py-1 px-1 text-xs">{{ $cheque->cheque_number ?? 'N/A' }}</td>
                                            <td class="py-1 px-1 text-xs">{{ $cheque->bank_name ?? 'N/A' }}</td>
                                            <td class="py-1 px-1 text-right font-semibold text-xs"> {{
                                                number_format($cheque->amount, 2) }}</td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="3" class="py-2 px-1 text-center text-black text-xs italic">
                                                No cheque payments</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                    <tfoot class="border-t-2 border-gray-300">
                                        <tr class="bg-purple-50">
                                            <td colspan="2"
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
                            <div class="p-3">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="border-b-2 border-gray-300">
                                            <th class="py-1 px-1 text-left text-black">Bank</th>
                                            <th class="py-1 px-1 text-left text-black">Ref #</th>
                                            <th class="py-1 px-1 text-right text-black">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($settlement->bankTransfers as $transfer)
                                        <tr class="hover:bg-gray-50">
                                            <td class="py-1 px-1 text-xs">{{ $transfer->bankAccount->bank_name ??
                                                'Online' }}</td>
                                            <td class="py-1 px-1 text-xs">{{ $transfer->reference_number ?? 'Transfer'
                                                }}</td>
                                            <td class="py-1 px-1 text-right font-semibold text-xs"> {{
                                                number_format($transfer->amount, 2) }}</td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="3" class="py-2 px-1 text-center text-black text-xs italic">
                                                No bank transfers</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                    <tfoot class="border-t-2 border-gray-300">
                                        <tr class="bg-blue-50">
                                            <td colspan="2"
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



                    {{-- Cash Reconciliation Section --}}
                    <hr class="my-6 border-gray-200">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800 flex items-center">
                        <svg class="w-6 h-6 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                        Cash Reconciliation & Settlement
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        {{-- Left Column: Cash Denomination Breakdown --}}
                        <div class="bg-white border border-gray-300 rounded-lg overflow-hidden">
                            <div class="bg-gradient-to-r from-green-500 to-green-600 px-4 py-2">
                                <h4 class="text-sm font-bold text-white">Cash Detail (Denomination Breakdown)</h4>
                            </div>
                            <div class="p-3">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="border-b-2 border-gray-300">
                                            <th class="py-1 px-2 text-left text-black">Denomination</th>
                                            <th class="py-1 px-2 text-right text-black">Quantity</th>
                                            <th class="py-1 px-2 text-right text-black">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        @php
                                        $denomData = $settlement->cashDenominations->first();
                                        $denominations = [
                                        ['label' => '5,000 Notes', 'qty' => $denomData?->denom_5000 ?? 0, 'value' =>
                                        5000],
                                        ['label' => '1,000 Notes', 'qty' => $denomData?->denom_1000 ?? 0, 'value' =>
                                        1000],
                                        ['label' => '500 Notes', 'qty' => $denomData?->denom_500 ?? 0, 'value' =>
                                        500],
                                        ['label' => '100 Notes', 'qty' => $denomData?->denom_100 ?? 0, 'value' =>
                                        100],
                                        ['label' => '50 Notes', 'qty' => $denomData?->denom_50 ?? 0, 'value' => 50],
                                        ['label' => '20 Notes', 'qty' => $denomData?->denom_20 ?? 0, 'value' => 20],
                                        ['label' => '10 Notes', 'qty' => $denomData?->denom_10 ?? 0, 'value' => 10],
                                        ['label' => 'Loose Cash/Coins', 'qty' => '-', 'value' =>
                                        $denomData?->denom_coins ?? 0, 'is_coins' => true],
                                        ];
                                        $totalCash = 0;
                                        @endphp
                                        @foreach($denominations as $denom)
                                        @php
                                        if(isset($denom['is_coins']) && $denom['is_coins']) {
                                        $amount = $denom['value'];
                                        } else {
                                        $amount = $denom['qty'] * $denom['value'];
                                        }
                                        $totalCash += $amount;
                                        @endphp
                                        <tr class="{{ $amount > 0 ? 'bg-white' : 'bg-gray-50 text-black' }}">
                                            <td class="py-0.5 px-2 text-xs">{{ $denom['label'] }}</td>
                                            <td class="py-0.5 px-2 text-right text-xs">
                                                @if(isset($denom['is_coins']) && $denom['is_coins'])
                                                -
                                                @else
                                                {{ number_format($denom['qty'], 0) }}
                                                @endif
                                            </td>
                                            <td class="py-0.5 px-2 text-right font-semibold text-xs">
                                                {{ number_format($amount, 2) }}
                                            </td>
                                        </tr>
                                        @endforeach
                                        <tr class="bg-green-100 border-t-2 border-green-300">
                                            <td colspan="2"
                                                class="py-1 px-2 text-left font-bold text-green-900 text-xs">Total
                                                Physical Cash</td>
                                            <td class="py-1 px-2 text-right font-bold text-green-900 text-xs">
                                                {{ number_format($totalCash, 2) }}
                                            </td>
                                        </tr>

                                        {{-- Bank Transfer --}}
                                        @if($settlement->bank_transfer_amount > 0)
                                        <tr class="bg-blue-50">
                                            <td colspan="2" class="py-0.5 px-2">
                                                <span class="font-semibold text-blue-900 text-xs">Bank Transfer / Online
                                                    Payment</span>
                                                @if($settlement->bankAccount)
                                                <div class="text-xs text-black mt-0.5">
                                                    {{ $settlement->bankAccount->account_name }} - {{
                                                    $settlement->bankAccount->bank_name }}
                                                </div>
                                                @endif
                                            </td>
                                            <td class="py-0.5 px-2 text-right font-semibold text-blue-900 text-xs">
                                                {{ number_format($settlement->bank_transfer_amount, 2) }}
                                            </td>
                                        </tr>
                                        @endif

                                        {{-- Cheques --}}
                                        @if($settlement->cheque_count > 0 && $settlement->cheque_details)
                                        <tr class="bg-purple-50">
                                            <td colspan="3" class="py-1 px-2">
                                                <div class="font-semibold text-purple-900 mb-1 text-xs">Cheque Details
                                                    ({{
                                                    $settlement->cheque_count }} cheque(s))</div>
                                                <div class="space-y-1">
                                                    @foreach($settlement->cheque_details as $cheque)
                                                    <div class="text-xs bg-white p-1 rounded border border-purple-200">
                                                        <div class="grid grid-cols-2 gap-1">
                                                            <div class="text-xs"><span class="font-medium">Cheque
                                                                    #:</span> {{
                                                                $cheque['cheque_number'] ?? 'N/A' }}</div>
                                                            <div class="text-xs"><span
                                                                    class="font-medium">Amount:</span> {{
                                                                number_format($cheque['amount'] ?? 0, 0) }}</div>
                                                            <div class="text-xs"><span class="font-medium">Bank:</span>
                                                                {{
                                                                $cheque['bank_name'] ?? 'N/A' }}</div>
                                                            <div class="text-xs"><span class="font-medium">Date:</span>
                                                                {{
                                                                isset($cheque['cheque_date']) ?
                                                                \Carbon\Carbon::parse($cheque['cheque_date'])->format('d
                                                                M Y') : 'N/A' }}</div>
                                                        </div>
                                                    </div>
                                                    @endforeach
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="bg-purple-100">
                                            <td colspan="2" class="py-1 px-2 font-semibold text-purple-900 text-xs">
                                                Total
                                                Cheques</td>
                                            <td class="py-1 px-2 text-right font-semibold text-purple-900 text-xs">
                                                {{ number_format($settlement->cheques_collected, 2) }}
                                            </td>
                                        </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Right Column: Expense Detail --}}
                        <div class="bg-white border border-gray-300 rounded-lg overflow-hidden">
                            <div class="bg-gradient-to-r from-red-500 to-red-600 px-4 py-2">
                                <h4 class="text-sm font-bold text-white">Expense Detail</h4>
                            </div>
                            <div class="p-3">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="border-b-2 border-gray-300">
                                            <th class="py-1 px-2 text-left text-black">Expense Account</th>
                                            <th class="py-1 px-2 text-right text-black">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        @php $totalExpenses = 0; @endphp
                                        @forelse($settlement->expenses as $expense)
                                        @php $totalExpenses += $expense->amount; @endphp
                                        <tr class="{{ $expense->amount > 0 ? 'bg-white' : 'bg-gray-50 text-black' }}">
                                            <td class="py-0.5 px-2 text-xs">
                                                @if($expense->expenseAccount)
                                                {{ $expense->expenseAccount->account_name }}
                                                <span class="text-xs text-black">({{
                                                    $expense->expenseAccount->account_code }})</span>
                                                @else
                                                {{ $expense->description ?? 'Unknown Account' }}
                                                @endif
                                            </td>
                                            <td class="py-0.5 px-2 text-right font-semibold text-xs">
                                                {{ number_format($expense->amount, 2) }}
                                            </td>
                                        </tr>
                                        @empty
                                        <tr class="bg-gray-50 text-black">
                                            <td class="py-0.5 px-2 text-xs" colspan="2">No expenses recorded</td>
                                        </tr>
                                        @endforelse

                                        {{-- Advance Tax Details moved here --}}
                                        @if($settlement->advanceTaxes->count() > 0)
                                        @foreach($settlement->advanceTaxes as $tax)
                                        @php $totalExpenses += $tax->tax_amount; @endphp
                                        <tr class="bg-yellow-25 hover:bg-yellow-50">
                                            <td class="py-0.5 px-2 text-xs">
                                                Advance Tax - {{ $tax->customer->customer_name ?? 'N/A' }}
                                                @if($tax->invoice_number)
                                                <span class="text-black text-xs">(Inv: {{ $tax->invoice_number
                                                    }})</span>
                                                @endif
                                                <span class="text-xs text-black">(A/C 1171)</span>
                                            </td>
                                            <td class="py-0.5 px-2 text-right font-semibold text-xs">
                                                {{ number_format($tax->tax_amount, 0) }}
                                            </td>
                                        </tr>
                                        @endforeach
                                        @endif

                                        <tr class="bg-red-100 border-t-2 border-red-300">
                                            <td class="py-1 px-2 text-left font-bold text-red-900 text-xs">Total
                                                Expenses</td>
                                            <td class="py-1 px-2 text-right font-bold text-red-900 text-xs">
                                                {{ number_format($totalExpenses, 2) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Third Column: Sales Summary --}}
                        <div class="bg-white border border-gray-300 rounded-lg overflow-hidden">
                            <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-2">
                                <h4 class="text-sm font-bold text-white">Sales Summary</h4>
                            </div>
                            <div class="p-3">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="border-b-2 border-gray-300">
                                            <th class="py-1 px-2 text-left text-black">Description</th>
                                            <th class="py-1 px-2 text-right text-black">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <tr>
                                            <td class="py-0.5 px-2 text-xs">Net Sale (Sold Items Value)</td>
                                            <td class="py-0.5 px-2 text-right font-semibold text-xs"> {{
                                                number_format($settlement->items->sum('total_sales_value'), 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="py-0.5 px-2 text-xs">Recovery (From Customers)</td>
                                            <td class="py-0.5 px-2 text-right font-semibold text-xs text-teal-700"> {{
                                                number_format($settlement->credit_recoveries ?? 0, 2) }}</td>
                                        </tr>
                                        <tr class="bg-blue-50">
                                            <td class="py-1 px-2 font-semibold text-xs text-blue-900">Total Sale</td>
                                            <td class="py-1 px-2 text-right font-bold text-xs text-blue-900"> {{
                                                number_format($settlement->items->sum('total_sales_value') +
                                                ($settlement->credit_recoveries ?? 0), 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="py-0.5 px-2 text-xs">Credit Extended</td>
                                            <td class="py-0.5 px-2 text-right font-semibold text-xs text-orange-700">
                                                {{ number_format($settlement->creditSales->sum('sale_amount'), 2) }}
                                            </td>
                                        </tr>
                                        <tr class="bg-gray-50">
                                            <td class="py-1 px-2 font-semibold text-xs">Balance</td>
                                            <td class="py-1 px-2 text-right font-bold text-xs"> {{
                                                number_format(($settlement->items->sum('total_sales_value') +
                                                ($settlement->credit_recoveries ?? 0)) -
                                                $settlement->creditSales->sum('sale_amount'), 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="py-0.5 px-2 text-xs text-red-700">Less: Expenses</td>
                                            <td class="py-0.5 px-2 text-right font-semibold text-xs text-red-700"> {{
                                                number_format($settlement->expenses->sum('amount') ?? 0, 2) }}</td>
                                        </tr>
                                        <tr class="bg-indigo-50">
                                            <td class="py-1 px-2 font-bold text-xs text-indigo-900">Net Balance</td>
                                            <td class="py-1 px-2 text-right font-bold text-xs text-indigo-900"> {{
                                                number_format((($settlement->items->sum('total_sales_value') +
                                                ($settlement->credit_recoveries ?? 0)) -
                                                $settlement->creditSales->sum('sale_amount')) -
                                                ($settlement->expenses->sum('amount') ?? 0), 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="py-0.5 px-2 text-xs">
                                                Cash Received (counted)
                                                <div class="text-xs text-black italic">Physical + Bank + Cheques
                                                </div>
                                            </td>
                                            <td class="py-0.5 px-2 text-right font-semibold text-xs text-green-700"> {{
                                                number_format($settlement->cash_to_deposit ?? 0, 2) }}</td>
                                        </tr>
                                        <tr
                                            class="bg-gradient-to-r from-purple-100 to-purple-50 border-t-2 border-purple-300">
                                            <td class="py-1 px-2 font-bold text-xs text-purple-900">Short/Excess</td>
                                            <td class="py-1 px-2 text-right font-bold text-xs text-purple-900"> {{
                                                number_format(($settlement->cash_to_deposit ?? 0) -
                                                ((($settlement->items->sum('total_sales_value') +
                                                ($settlement->credit_recoveries ?? 0)) -
                                                $settlement->creditSales->sum('sale_amount')) -
                                                ($settlement->expenses->sum('amount') ?? 0)), 2) }}</td>
                                        </tr>
                                        {{-- Profit Analysis --}}
                                        <tr class="bg-gray-100 border-t-2 border-gray-400">
                                            <td colspan="2"
                                                class="py-1 px-2 text-center font-bold text-black text-xs uppercase tracking-wide">
                                                Profit Analysis</td>
                                        </tr>
                                        @php
                                        $totalCOGS = $settlement->items->sum('total_cogs') ?? 0;
                                        $totalSalesValue = $settlement->items->sum('total_sales_value');
                                        $grossProfit = $totalSalesValue - $totalCOGS;
                                        $grossMargin = $totalSalesValue > 0 ? ($grossProfit / $totalSalesValue) * 100 :
                                        0;
                                        $totalExpenses = $settlement->expenses->sum('amount') ?? 0;
                                        $netProfit = $grossProfit - $totalExpenses;
                                        $netMargin = $totalSalesValue > 0 ? ($netProfit / $totalSalesValue) * 100 : 0;
                                        @endphp
                                        <tr>
                                            <td class="py-0.5 px-2 text-xs text-gray-700">Total COGS</td>
                                            <td class="py-0.5 px-2 text-right font-semibold text-xs text-black"> {{
                                                number_format($totalCOGS, 2) }}</td>
                                        </tr>
                                        <tr class="bg-green-50">
                                            <td class="py-0.5 px-2 font-semibold text-xs text-green-800">Gross Profit
                                                (Sales - COGS)</td>
                                            <td class="py-0.5 px-2 text-right font-bold text-xs text-green-700"> {{
                                                number_format($grossProfit, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="py-0.5 px-2 text-xs text-gray-500 pl-4">Gross Margin</td>
                                            <td class="py-0.5 px-2 text-right text-xs font-semibold">{{
                                                number_format($grossMargin, 2) }}%</td>
                                        </tr>
                                        <tr>
                                            <td class="py-0.5 px-2 text-xs text-red-700">Less: Expenses</td>
                                            <td class="py-0.5 px-2 text-right font-semibold text-xs text-red-700"> {{
                                                number_format($totalExpenses, 2) }}</td>
                                        </tr>
                                        <tr
                                            class="bg-gradient-to-r from-emerald-100 to-teal-100 border-t-2 border-emerald-400">
                                            <td class="py-1 px-2 font-bold text-xs text-emerald-900">Net Profit (After
                                                Expenses)</td>
                                            <td class="py-1 px-2 text-right font-bold text-xs text-emerald-900"> {{
                                                number_format($netProfit, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="py-0.5 px-2 text-xs text-gray-500 pl-4">Net Margin</td>
                                            <td class="py-0.5 px-2 text-right text-xs font-semibold">{{
                                                number_format($netMargin, 2) }}%</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- Cards moved above to after Product-wise Settlement table --}}


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

                        {{-- Cash Reconciliation Table --}}
                        <table style="border-collapse: collapse; width: 100%; border: 1px solid #000;" class="mb-6">
                            <thead>
                                <tr style="background-color: #f8fafc;">
                                    <th
                                        style="border: 1px solid #000; padding: 12px; text-align: left; font-weight: bold; color: #374151;">
                                        Description</th>
                                    <th
                                        style="border: 1px solid #000; padding: 12px; text-align: right; font-weight: bold; color: #374151;">
                                        Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr style="background-color: #f0fdf4;">
                                    <td
                                        style="border: 1px solid #000; padding: 10px; font-weight: 600; color: #166534;">
                                        <span class="flex items-center">
                                            <svg class="w-4 h-4 mr-2 text-green-600" fill="currentColor"
                                                viewBox="0 0 20 20">
                                                <path
                                                    d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4zM18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z">
                                                </path>
                                            </svg>
                                            Cash Collected
                                        </span>
                                    </td>
                                    <td
                                        style="border: 1px solid #000; padding: 10px; text-align: right; font-weight: bold; color: #166534;">
                                        {{ number_format($settlement->cash_collected ?? 0, 0) }}
                                    </td>
                                </tr>
                                <tr style="background-color: #eff6ff;">
                                    <td
                                        style="border: 1px solid #000; padding: 10px; font-weight: 600; color: #1e40af;">
                                        <span class="flex items-center">
                                            <svg class="w-4 h-4 mr-2 text-blue-600" fill="currentColor"
                                                viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z"
                                                    clip-rule="evenodd"></path>
                                            </svg>
                                            Credit Recovery
                                        </span>
                                    </td>
                                    <td
                                        style="border: 1px solid #000; padding: 10px; text-align: right; font-weight: bold; color: #1e40af;">
                                        {{ number_format($settlement->credit_recoveries ?? 0, 0) }}
                                    </td>
                                </tr>
                                <tr style="background-color: #fef2f2;">
                                    <td
                                        style="border: 1px solid #000; padding: 10px; font-weight: 600; color: #dc2626;">
                                        <span class="flex items-center">
                                            <svg class="w-4 h-4 mr-2 text-red-600" fill="currentColor"
                                                viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                                    clip-rule="evenodd"></path>
                                            </svg>
                                            Total Expenses
                                        </span>
                                    </td>
                                    <td
                                        style="border: 1px solid #000; padding: 10px; text-align: right; font-weight: bold; color: #dc2626;">
                                        {{ number_format($settlement->expenses->sum('amount') ?? 0, 0) }}
                                    </td>
                                </tr>
                                <tr style="background-color: #ecfdf5; border-top: 2px solid #059669;">
                                    <td
                                        style="border: 1px solid #000; padding: 12px; font-weight: bold; color: #047857; font-size: 16px;">
                                        <span class="flex items-center">
                                            <svg class="w-5 h-5 mr-2 text-emerald-600" fill="currentColor"
                                                viewBox="0 0 20 20">
                                                <path
                                                    d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z">
                                                </path>
                                                <path fill-rule="evenodd"
                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z"
                                                    clip-rule="evenodd"></path>
                                            </svg>
                                            Net Cash to Deposit
                                        </span>
                                    </td>
                                    <td
                                        style="border: 1px solid #000; padding: 12px; text-align: right; font-weight: bold; color: #047857; font-size: 18px;">
                                        {{ number_format($settlement->cash_to_deposit ?? 0, 0) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        {{-- Detailed Financial Metrics --}}
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            @php
                            $totalSalesValue = $settlement->items->sum('total_sales_value');
                            $totalCOGS = $settlement->items->sum('total_cogs') ?? 0;
                            $grossProfit = $totalSalesValue - $totalCOGS;
                            $grossProfitMargin = $totalSalesValue > 0 ? ($grossProfit / $totalSalesValue) * 100 : 0;
                            $totalExpenses = $settlement->expenses->sum('amount') ?? 0;
                            $netProfit = $grossProfit - $totalExpenses;
                            $netProfitMargin = $totalSalesValue > 0 ? ($netProfit / $totalSalesValue) * 100 : 0;
                            @endphp

                            <div class="bg-white rounded-lg p-4 border border-gray-200 shadow-sm">
                                <h5 class="font-semibold text-sm text-black mb-3 border-b border-gray-200 pb-2">Sales
                                    Performance</h5>
                                <div class="space-y-2">
                                    <div class="flex justify-between items-center">
                                        <span class="text-xs text-black">Total Sales Value</span>
                                        <span class="font-semibold text-sm"> {{ number_format($totalSalesValue, 0)
                                            }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-xs text-black">Credit Extended</span>
                                        <span class="font-semibold text-sm text-orange-600"> {{
                                            number_format($settlement->creditSales->sum('sale_amount'), 0) }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white rounded-lg p-4 border border-gray-200 shadow-sm">
                                <h5 class="font-semibold text-sm text-gray-700 mb-3 border-b border-gray-200 pb-2">
                                    Profitability</h5>
                                <div class="space-y-2">
                                    <div class="flex justify-between items-center">
                                        <span class="text-xs text-gray-600">Gross Profit</span>
                                        <span
                                            class="font-semibold text-sm {{ $grossProfit >= 0 ? 'text-green-600' : 'text-red-600' }}">₨
                                            {{ number_format($grossProfit, 0) }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-xs text-gray-500">Margin</span>
                                        <span
                                            class="text-xs font-medium {{ $grossProfitMargin >= 0 ? 'text-green-600' : 'text-red-600' }}">{{
                                            number_format($grossProfitMargin, 1) }}%</span>
                                    </div>
                                    <div class="flex justify-between items-center pt-2 border-t border-gray-100">
                                        <span class="text-xs text-gray-600">Net Profit</span>
                                        <span
                                            class="font-bold text-sm {{ $netProfit >= 0 ? 'text-emerald-600' : 'text-red-600' }}">₨
                                            {{ number_format($netProfit, 0) }}</span>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="bg-gradient-to-br {{ $netProfit >= 0 ? 'from-emerald-50 to-green-50 border-emerald-200' : 'from-red-50 to-rose-50 border-red-200' }} rounded-lg p-4 border-2">
                                <h5
                                    class="font-semibold text-sm {{ $netProfit >= 0 ? 'text-emerald-700' : 'text-red-700' }} mb-3">
                                    Final Summary</h5>
                                <div class="text-center">
                                    <p class="text-xs {{ $netProfit >= 0 ? 'text-emerald-600' : 'text-red-600' }} mb-1">
                                        Net Profit After Expenses</p>
                                    <p
                                        class="text-2xl font-bold {{ $netProfit >= 0 ? 'text-emerald-800' : 'text-red-800' }}">
                                        {{ number_format($netProfit, 0) }}</p>
                                    <p
                                        class="text-xs {{ $netProfitMargin >= 0 ? 'text-emerald-600' : 'text-red-600' }} mt-1">
                                        Net Margin: {{ number_format($netProfitMargin, 1) }}%</p>
                                </div>
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