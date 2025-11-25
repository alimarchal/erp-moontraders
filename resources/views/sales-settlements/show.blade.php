<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            Sales Settlement: {{ $settlement->settlement_number }}
        </h2>
        <div class="flex justify-center items-center float-right space-x-2">
            @if ($settlement->status === 'draft')
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
            <a href="{{ route('sales-settlements.edit', $settlement->id) }}"
                class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                Edit
            </a>
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
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase">Settlement Number</h3>
                            <p class="text-lg font-bold text-gray-900">{{ $settlement->settlement_number }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase">Settlement Date</h3>
                            <p class="text-lg text-gray-900">
                                {{ \Carbon\Carbon::parse($settlement->settlement_date)->format('d M Y') }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase">Goods Issue</h3>
                            <p class="text-base font-semibold text-blue-900">
                                {{ $settlement->goodsIssue->issue_number }}
                            </p>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase">Status</h3>
                            <span class="inline-flex items-center px-3 py-1 text-sm font-semibold rounded-full
                                {{ $settlement->status === 'draft' ? 'bg-gray-200 text-gray-700' : '' }}
                                {{ $settlement->status === 'posted' ? 'bg-emerald-100 text-emerald-700' : '' }}">
                                {{ ucfirst($settlement->status) }}
                            </span>
                        </div>
                    </div>

                    <hr class="my-6 border-gray-200">

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Salesman</h3>
                            <p class="text-base font-semibold text-gray-900">
                                {{ $settlement->employee->full_name }}</p>
                        </div>

                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Vehicle</h3>
                            <p class="text-base font-semibold text-gray-900">
                                {{ $settlement->vehicle->vehicle_number }}</p>
                        </div>

                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Warehouse</h3>
                            <p class="text-base font-semibold text-gray-900">
                                {{ $settlement->warehouse->warehouse_name }}</p>
                        </div>
                    </div>

                    <hr class="my-6 border-gray-200">

                    <x-detail-table title="Product-wise Settlement" :headers="[
                        ['label' => '#', 'align' => 'text-center'],
                        ['label' => 'Product', 'align' => 'text-left'],
                        ['label' => 'Issued', 'align' => 'text-right'],
                        ['label' => 'Batch Breakdown', 'align' => 'text-left'],
                        ['label' => 'Sold', 'align' => 'text-right'],
                        ['label' => 'Returned', 'align' => 'text-right'],
                        ['label' => 'Shortage', 'align' => 'text-right'],
                        ['label' => 'Sales Value', 'align' => 'text-right'],
                    ]">
                        @foreach ($settlement->items as $item)
                        <tr class="border-b border-gray-200 text-sm">
                            <td class="py-1 px-2 text-center">{{ $item->line_no }}</td>
                            <td class="py-1 px-2">
                                <div class="font-semibold text-gray-900">{{ $item->product->product_code }}</div>
                                <div class="text-xs text-gray-500">{{ $item->product->product_name }}</div>
                            </td>
                            <td class="py-1 px-2 text-right">{{ number_format($item->quantity_issued, 2) }}</td>
                            <td class="py-1 px-2">
                                @if($item->batches->count() > 0)
                                    @if($item->batches->count() === 1)
                                        @php $b = $item->batches->first(); @endphp
                                        <div class="flex items-center space-x-1">
                                            <span class="font-semibold text-green-600">
                                                {{ number_format($b->quantity_issued, 0) }} × ₨{{ number_format($b->selling_price, 2) }}
                                            </span>
                                            @if($b->is_promotional)
                                                <span class="px-2 py-1 ml-1 text-xs font-semibold rounded bg-orange-100 text-orange-800">
                                                    Promotional
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-xs text-gray-600 mt-1">
                                            Sold: {{ number_format($b->quantity_sold, 0) }} |
                                            Returned: {{ number_format($b->quantity_returned, 0) }} |
                                            Shortage: {{ number_format($b->quantity_shortage, 0) }}
                                        </div>
                                    @else
                                        <div class="space-y-1">
                                            @foreach($item->batches as $b)
                                                <div class="text-xs border-l-2 pl-2 {{ $b->is_promotional ? 'border-orange-400' : 'border-gray-300' }}">
                                                    <div class="flex justify-between">
                                                        <span class="text-gray-700 font-medium">
                                                            {{ number_format($b->quantity_issued, 0) }} × ₨{{ number_format($b->selling_price, 2) }}
                                                            @if($b->is_promotional)
                                                                <span title="Promotional">🎁</span>
                                                            @endif
                                                        </span>
                                                        <span class="font-semibold">= ₨{{ number_format($b->quantity_issued * $b->selling_price, 2) }}</span>
                                                    </div>
                                                    <div class="text-gray-600 mt-0.5">
                                                        S: {{ number_format($b->quantity_sold, 0) }} |
                                                        R: {{ number_format($b->quantity_returned, 0) }} |
                                                        Sh: {{ number_format($b->quantity_shortage, 0) }}
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                @else
                                    <span class="text-gray-400 text-xs">No batch data</span>
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
                            <td class="py-1 px-2 text-right font-bold text-emerald-600">
                                ₨ {{ number_format($item->total_sales_value, 2) }}
                            </td>
                        </tr>
                        @endforeach

                        <x-slot name="footer">
                            <tr class="border-t-2 border-gray-300">
                                <td colspan="4" class="py-1 px-2 text-right font-bold text-lg">Totals:</td>
                                <td class="py-1 px-2 text-right font-bold text-lg text-green-700">
                                    {{ number_format($settlement->total_quantity_sold, 2) }}
                                </td>
                                <td class="py-1 px-2 text-right font-bold text-lg text-blue-700">
                                    {{ number_format($settlement->total_quantity_returned, 2) }}
                                </td>
                                <td class="py-1 px-2 text-right font-bold text-lg text-red-700">
                                    {{ number_format($settlement->total_quantity_shortage, 2) }}
                                </td>
                                <td class="py-1 px-2 text-right font-bold text-lg text-emerald-600">
                                    ₨ {{ number_format($settlement->items->sum('total_sales_value'), 2) }}
                                </td>
                            </tr>
                        </x-slot>
                    </x-detail-table>

                    {{-- Credit Sales / Creditors Breakdown --}}
                    @if ($settlement->creditSales->count() > 0)
                    <hr class="my-6 border-gray-200">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800 flex items-center">
                        <svg class="w-6 h-6 mr-2 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        Creditors / Credit Sales Breakdown
                    </h3>

                    <div class="bg-white border-2 border-orange-200 rounded-lg overflow-hidden shadow-sm">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gradient-to-r from-orange-500 to-orange-600">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">
                                            Customer Details
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">
                                            Salesman / Supplier
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">
                                            Invoice #
                                        </th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-white uppercase tracking-wider">
                                            Amount
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">
                                            Notes
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach ($settlement->creditSales as $index => $creditSale)
                                    <tr class="hover:bg-orange-50 transition-colors {{ $index % 2 === 0 ? 'bg-white' : 'bg-gray-50' }}">
                                        <td class="px-4 py-3">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10 bg-orange-100 rounded-full flex items-center justify-center">
                                                    <span class="text-orange-700 font-bold text-sm">{{ substr($creditSale->customer->customer_name, 0, 2) }}</span>
                                                </div>
                                                <div class="ml-3">
                                                    <div class="text-sm font-bold text-gray-900">{{ $creditSale->customer->customer_name }}</div>
                                                    <div class="text-xs text-gray-500">{{ $creditSale->customer->customer_code }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="text-sm font-semibold text-blue-900">{{ $creditSale->employee->full_name }}</div>
                                            <div class="text-xs text-gray-600">{{ $creditSale->supplier->supplier_name ?? 'N/A' }}</div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="text-sm font-mono text-gray-700">{{ $creditSale->invoice_number ?? '-' }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <span class="text-base font-bold text-orange-700">₨ {{ number_format($creditSale->sale_amount, 2) }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="text-sm text-gray-600">{{ $creditSale->notes ?? '-' }}</span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-gradient-to-r from-orange-100 to-orange-50 border-t-2 border-orange-300">
                                    <tr>
                                        <td colspan="3" class="px-4 py-3 text-sm font-bold text-gray-900">Total Credit Sales ({{ $settlement->creditSales->count() }} transactions):</td>
                                        <td class="px-4 py-3 text-right">
                                            <span class="text-xl font-bold text-orange-900">₨ {{ number_format($settlement->creditSales->sum('sale_amount'), 2) }}</span>
                                        </td>
                                        <td class="px-4 py-3"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    @endif

                    {{-- Cheque Details Section --}}
                    @if($settlement->cheque_count > 0 && $settlement->cheque_details)
                    <hr class="my-6 border-gray-200">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800 flex items-center">
                        <svg class="w-6 h-6 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Cheque Payment Details ({{ $settlement->cheque_count }} Cheque{{ $settlement->cheque_count > 1 ? 's' : '' }})
                    </h3>

                    <div class="bg-white border-2 border-purple-200 rounded-lg overflow-hidden shadow-sm">
                        <div class="bg-gradient-to-r from-purple-500 to-purple-600 px-4 py-3">
                            <div class="grid grid-cols-5 gap-4">
                                <div class="text-xs font-bold text-white uppercase">Cheque Number</div>
                                <div class="text-xs font-bold text-white uppercase">Date</div>
                                <div class="text-xs font-bold text-white uppercase">Bank Name</div>
                                <div class="text-xs font-bold text-white uppercase text-right">Amount</div>
                                <div class="text-xs font-bold text-white uppercase">Status</div>
                            </div>
                        </div>
                        <div class="divide-y divide-gray-200">
                            @foreach($settlement->cheque_details as $index => $cheque)
                            <div class="px-4 py-4 hover:bg-purple-50 transition-colors {{ $index % 2 === 0 ? 'bg-white' : 'bg-gray-50' }}">
                                <div class="grid grid-cols-5 gap-4 items-center">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 bg-purple-100 rounded flex items-center justify-center">
                                            <svg class="w-5 h-5 text-purple-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-bold text-gray-900 font-mono">{{ $cheque['cheque_number'] ?? 'N/A' }}</div>
                                            <div class="text-xs text-gray-500">Cheque #{{ $index + 1 }}</div>
                                        </div>
                                    </div>
                                    <div class="text-sm text-gray-700">
                                        {{ isset($cheque['cheque_date']) ? \Carbon\Carbon::parse($cheque['cheque_date'])->format('d M Y') : 'N/A' }}
                                    </div>
                                    <div class="text-sm font-semibold text-gray-900">
                                        {{ $cheque['bank_name'] ?? 'N/A' }}
                                    </div>
                                    <div class="text-right">
                                        <span class="text-base font-bold text-purple-700">₨ {{ number_format($cheque['amount'] ?? 0, 2) }}</span>
                                    </div>
                                    <div>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">
                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                            </svg>
                                            Pending
                                        </span>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        <div class="bg-gradient-to-r from-purple-100 to-purple-50 px-4 py-3 border-t-2 border-purple-300">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-bold text-gray-900">Total Cheques Received:</span>
                                <span class="text-xl font-bold text-purple-900">₨ {{ number_format($settlement->cheques_collected, 2) }}</span>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- Credit Recoveries Section --}}
                    @if($settlement->credit_recoveries > 0)
                    <hr class="my-6 border-gray-200">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800 flex items-center">
                        <svg class="w-6 h-6 mr-2 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z" />
                        </svg>
                        Credit Recoveries / Collections
                    </h3>

                    <div class="bg-gradient-to-r from-teal-50 to-cyan-50 border-2 border-teal-300 rounded-lg p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="bg-teal-100 rounded-full p-4 mr-4">
                                    <svg class="w-8 h-8 text-teal-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 8h6m-5 0a3 3 0 110 6H9l3 3m-3-6h6m6 1a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Total Credit Recovered</p>
                                    <p class="text-xs text-gray-500 mt-1">Previous outstanding amounts collected today</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-4xl font-bold text-teal-900">₨ {{ number_format($settlement->credit_recoveries, 2) }}</p>
                                <p class="text-xs text-teal-700 mt-1 font-semibold">Reduces Accounts Receivable</p>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- Cash Reconciliation Section --}}
                    <hr class="my-6 border-gray-200">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800 flex items-center">
                        <svg class="w-6 h-6 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                        Cash Reconciliation & Settlement
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        {{-- Left Column: Cash Denomination Breakdown --}}
                        <div class="bg-white border border-gray-300 rounded-lg overflow-hidden">
                            <div class="bg-gradient-to-r from-green-500 to-green-600 px-4 py-2">
                                <h4 class="text-sm font-bold text-white">Cash Detail (Denomination Breakdown)</h4>
                            </div>
                            <div class="p-4">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b-2 border-gray-300">
                                            <th class="py-2 px-2 text-left text-gray-700">Denomination</th>
                                            <th class="py-2 px-2 text-right text-gray-700">Quantity</th>
                                            <th class="py-2 px-2 text-right text-gray-700">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        @php
                                            $denominations = [
                                                ['label' => '₨ 5,000 Notes', 'qty' => $settlement->denom_5000 ?? 0, 'value' => 5000],
                                                ['label' => '₨ 1,000 Notes', 'qty' => $settlement->denom_1000 ?? 0, 'value' => 1000],
                                                ['label' => '₨ 500 Notes', 'qty' => $settlement->denom_500 ?? 0, 'value' => 500],
                                                ['label' => '₨ 100 Notes', 'qty' => $settlement->denom_100 ?? 0, 'value' => 100],
                                                ['label' => '₨ 50 Notes', 'qty' => $settlement->denom_50 ?? 0, 'value' => 50],
                                                ['label' => '₨ 20 Notes', 'qty' => $settlement->denom_20 ?? 0, 'value' => 20],
                                                ['label' => '₨ 10 Notes', 'qty' => $settlement->denom_10 ?? 0, 'value' => 10],
                                                ['label' => 'Loose Cash/Coins', 'qty' => '-', 'value' => $settlement->denom_coins ?? 0, 'is_coins' => true],
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
                                            <tr class="{{ $amount > 0 ? 'bg-white' : 'bg-gray-50 text-gray-400' }}">
                                                <td class="py-1.5 px-2">{{ $denom['label'] }}</td>
                                                <td class="py-1.5 px-2 text-right">
                                                    @if(isset($denom['is_coins']) && $denom['is_coins'])
                                                        -
                                                    @else
                                                        {{ number_format($denom['qty'], 0) }}
                                                    @endif
                                                </td>
                                                <td class="py-1.5 px-2 text-right font-semibold">
                                                    ₨ {{ number_format($amount, 2) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                        <tr class="bg-green-100 border-t-2 border-green-300">
                                            <td colspan="2" class="py-2 px-2 text-left font-bold text-green-900">Total Physical Cash</td>
                                            <td class="py-2 px-2 text-right font-bold text-green-900 text-base">
                                                ₨ {{ number_format($totalCash, 2) }}
                                            </td>
                                        </tr>

                                        {{-- Bank Transfer --}}
                                        @if($settlement->bank_transfer_amount > 0)
                                        <tr class="bg-blue-50">
                                            <td colspan="2" class="py-1.5 px-2">
                                                <span class="font-semibold text-blue-900">Bank Transfer / Online Payment</span>
                                                @if($settlement->bankAccount)
                                                <div class="text-xs text-gray-600 mt-0.5">
                                                    {{ $settlement->bankAccount->account_name }} - {{ $settlement->bankAccount->bank_name }}
                                                </div>
                                                @endif
                                            </td>
                                            <td class="py-1.5 px-2 text-right font-semibold text-blue-900">
                                                ₨ {{ number_format($settlement->bank_transfer_amount, 2) }}
                                            </td>
                                        </tr>
                                        @endif

                                        {{-- Cheques --}}
                                        @if($settlement->cheque_count > 0 && $settlement->cheque_details)
                                        <tr class="bg-purple-50">
                                            <td colspan="3" class="py-2 px-2">
                                                <div class="font-semibold text-purple-900 mb-2">Cheque Details ({{ $settlement->cheque_count }} cheque(s))</div>
                                                <div class="space-y-1">
                                                    @foreach($settlement->cheque_details as $cheque)
                                                    <div class="text-xs bg-white p-2 rounded border border-purple-200">
                                                        <div class="grid grid-cols-2 gap-2">
                                                            <div><span class="font-medium">Cheque #:</span> {{ $cheque['cheque_number'] ?? 'N/A' }}</div>
                                                            <div><span class="font-medium">Amount:</span> ₨ {{ number_format($cheque['amount'] ?? 0, 2) }}</div>
                                                            <div><span class="font-medium">Bank:</span> {{ $cheque['bank_name'] ?? 'N/A' }}</div>
                                                            <div><span class="font-medium">Date:</span> {{ isset($cheque['cheque_date']) ? \Carbon\Carbon::parse($cheque['cheque_date'])->format('d M Y') : 'N/A' }}</div>
                                                        </div>
                                                    </div>
                                                    @endforeach
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="bg-purple-100">
                                            <td colspan="2" class="py-1.5 px-2 font-semibold text-purple-900">Total Cheques</td>
                                            <td class="py-1.5 px-2 text-right font-semibold text-purple-900">
                                                ₨ {{ number_format($settlement->cheques_collected, 2) }}
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
                            <div class="p-4">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b-2 border-gray-300">
                                            <th class="py-2 px-2 text-left text-gray-700">Description</th>
                                            <th class="py-2 px-2 text-right text-gray-700">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        @php
                                            $expenses = [
                                                ['label' => 'Toll Tax (52250)', 'amount' => $settlement->expense_toll_tax ?? 0],
                                                ['label' => 'AMR Powder (52230)', 'amount' => $settlement->expense_amr_powder_claim ?? 0],
                                                ['label' => 'AMR Liquid (52240)', 'amount' => $settlement->expense_amr_liquid_claim ?? 0],
                                                ['label' => 'Scheme Discount Expense (52270)', 'amount' => $settlement->expense_scheme ?? 0],
                                                ['label' => 'Advance Tax (1171)', 'amount' => $settlement->expense_advance_tax ?? 0],
                                                ['label' => 'Food/Salesman/Loader Charges (52260)', 'amount' => $settlement->expense_food_charges ?? 0],
                                                ['label' => 'Percentage Expense (52280)', 'amount' => $settlement->expense_percentage ?? 0],
                                                ['label' => 'Miscellaneous Expenses (52110)', 'amount' => $settlement->expense_miscellaneous_amount ?? 0],
                                            ];
                                        @endphp
                                        @foreach($expenses as $expense)
                                            <tr class="{{ $expense['amount'] > 0 ? 'bg-white' : 'bg-gray-50 text-gray-400' }}">
                                                <td class="py-1.5 px-2">{{ $expense['label'] }}</td>
                                                <td class="py-1.5 px-2 text-right font-semibold">
                                                    ₨ {{ number_format($expense['amount'], 2) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                        <tr class="bg-red-100 border-t-2 border-red-300">
                                            <td class="py-2 px-2 text-left font-bold text-red-900">Total Expenses</td>
                                            <td class="py-2 px-2 text-right font-bold text-red-900 text-base">
                                                ₨ {{ number_format($settlement->expenses_claimed, 2) }}
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
                            <div class="p-4">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="border-b-2 border-gray-300">
                                            <th class="py-1.5 px-2 text-left text-gray-700">Description</th>
                                            <th class="py-1.5 px-2 text-right text-gray-700">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <tr>
                                            <td class="py-1 px-2">Cash Sales</td>
                                            <td class="py-1 px-2 text-right font-semibold text-green-700">
                                                ₨ {{ number_format($settlement->cash_sales_amount, 2) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="py-1 px-2">Cheque Sales</td>
                                            <td class="py-1 px-2 text-right font-semibold text-purple-700">
                                                ₨ {{ number_format($settlement->cheque_sales_amount, 2) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="py-1 px-2">Credit Sales</td>
                                            <td class="py-1 px-2 text-right font-semibold text-orange-700">
                                                ₨ {{ number_format($settlement->credit_sales_amount, 2) }}
                                            </td>
                                        </tr>
                                        <tr class="bg-blue-100 border-t-2 border-blue-300">
                                            <td class="py-1.5 px-2 font-bold text-blue-900">Total Sales</td>
                                            <td class="py-1.5 px-2 text-right font-bold text-blue-900 text-base">
                                                ₨ {{ number_format($settlement->total_sales_amount, 2) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="py-1 px-2">Credit Recoveries</td>
                                            <td class="py-1 px-2 text-right font-semibold text-teal-700">
                                                ₨ {{ number_format($settlement->credit_recoveries, 2) }}
                                            </td>
                                        </tr>
                                        <tr class="bg-green-50">
                                            <td class="py-1 px-2 font-semibold">Grand Total</td>
                                            <td class="py-1 px-2 text-right font-bold text-green-900">
                                                ₨ {{ number_format($settlement->total_sales_amount + $settlement->credit_recoveries, 2) }}
                                            </td>
                                        </tr>
                                        <tr class="border-t-2 border-gray-300">
                                            <td class="py-1 px-2 text-red-700">Less: Expenses</td>
                                            <td class="py-1 px-2 text-right font-semibold text-red-700">
                                                ₨ {{ number_format($settlement->expenses_claimed, 2) }}
                                            </td>
                                        </tr>
                                        <tr class="bg-gradient-to-r from-green-100 to-emerald-100 border-t-2 border-green-300">
                                            <td class="py-2 px-2 font-bold text-green-900">Net Cash to Deposit</td>
                                            <td class="py-2 px-2 text-right font-bold text-green-900 text-lg">
                                                ₨ {{ number_format($settlement->cash_to_deposit, 2) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- Cash Reconciliation Summary (MOVED BELOW) --}}
                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-indigo-300 rounded-lg p-4 mb-6">
                        <h4 class="text-base font-bold text-indigo-900 mb-3">Cash Reconciliation Summary</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="bg-white rounded p-3 border border-gray-200">
                                <div class="text-xs text-gray-600 mb-1">Cash Collected (Sales)</div>
                                <div class="text-lg font-bold text-green-700">₨ {{ number_format($settlement->cash_collected, 2) }}</div>
                            </div>
                            <div class="bg-white rounded p-3 border border-gray-200">
                                <div class="text-xs text-gray-600 mb-1">Credit Recoveries</div>
                                <div class="text-lg font-bold text-blue-700">₨ {{ number_format($settlement->credit_recoveries, 2) }}</div>
                            </div>
                            <div class="bg-white rounded p-3 border border-gray-200">
                                <div class="text-xs text-gray-600 mb-1">Total Expenses</div>
                                <div class="text-lg font-bold text-red-700">₨ {{ number_format($settlement->expenses_claimed, 2) }}</div>
                            </div>
                            <div class="bg-gradient-to-r from-green-100 to-emerald-100 rounded p-3 border-2 border-green-500">
                                <div class="text-xs text-gray-700 font-semibold mb-1">Net Cash to Deposit</div>
                                <div class="text-2xl font-bold text-green-900">₨ {{ number_format($settlement->cash_to_deposit, 2) }}</div>
                                <div class="text-xs text-gray-600 mt-1">Cash + Cheques + Recoveries - Expenses</div>
                            </div>
                        </div>
                    </div>

                    @if ($settlement->notes)
                    <div class="mt-6">
                        <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Notes</h3>
                        <p class="text-sm text-gray-700">{{ $settlement->notes }}</p>
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
