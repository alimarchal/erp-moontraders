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

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6 bg-blue-50 p-4 rounded-md">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase">Total Sales</h3>
                            <p class="text-2xl font-bold text-blue-900">
                                Rs {{ number_format($settlement->total_sales_amount, 2) }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase">Cash Sales</h3>
                            <p class="text-xl font-bold text-green-700">
                                Rs {{ number_format($settlement->cash_sales_amount, 2) }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase">Cheque Sales</h3>
                            <p class="text-xl font-bold text-purple-700">
                                Rs {{ number_format($settlement->cheque_sales_amount, 2) }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase">Credit Sales</h3>
                            <p class="text-xl font-bold text-orange-700">
                                Rs {{ number_format($settlement->credit_sales_amount, 2) }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase">Cash Collected</h3>
                            <p class="text-lg font-semibold text-gray-900">
                                Rs {{ number_format($settlement->cash_collected, 2) }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase">Cheques Collected</h3>
                            <p class="text-lg font-semibold text-gray-900">
                                Rs {{ number_format($settlement->cheques_collected, 2) }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase">Credit Recoveries</h3>
                            <p class="text-lg font-semibold text-blue-700">
                                Rs {{ number_format($settlement->credit_recoveries, 2) }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase">Expenses Claimed</h3>
                            <p class="text-lg font-semibold text-red-700">
                                Rs {{ number_format($settlement->expenses_claimed, 2) }}</p>
                        </div>
                    </div>

                    <div class="bg-green-50 p-4 rounded-lg mb-6">
                        <div class="text-center">
                            <h3 class="text-sm font-semibold text-gray-500 uppercase">Cash to Deposit</h3>
                            <p class="text-2xl font-bold text-green-900">
                                Rs {{ number_format($settlement->cash_to_deposit, 2) }}</p>
                            <p class="text-xs text-gray-600 mt-1">Cash Collected + Cheques Collected + Credit Recoveries - Expenses</p>
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

                    @if ($settlement->sales->count() > 0)
                    <hr class="my-6 border-gray-200">
                    <h3 class="text-lg font-semibold mb-4">Sales Details</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Customer
                                    </th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Invoice
                                        #</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Payment
                                        Type</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Amount
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($settlement->sales as $sale)
                                <tr>
                                    <td class="px-3 py-2 text-sm">{{ $sale->customer->customer_name }}</td>
                                    <td class="px-3 py-2 text-sm">{{ $sale->invoice_number ?? '-' }}</td>
                                    <td class="px-3 py-2 text-sm">
                                        <span
                                            class="px-2 py-1 text-xs rounded-full
                                            {{ $sale->payment_type === 'cash' ? 'bg-green-100 text-green-800' : '' }}
                                            {{ $sale->payment_type === 'cheque' ? 'bg-purple-100 text-purple-800' : '' }}
                                            {{ $sale->payment_type === 'credit' ? 'bg-orange-100 text-orange-800' : '' }}">
                                            {{ ucfirst($sale->payment_type) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-sm text-right font-semibold">
                                        Rs {{ number_format($sale->sale_amount, 2) }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif

                    @if ($settlement->creditSales->count() > 0)
                    <hr class="my-6 border-gray-200">
                    <h3 class="text-lg font-semibold mb-4">Credit Sales Breakdown</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Salesman
                                        / Supplier
                                    </th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Customer
                                    </th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Invoice
                                        #</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Amount
                                    </th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Notes
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($settlement->creditSales as $creditSale)
                                <tr>
                                    <td class="px-3 py-2 text-sm">
                                        <div class="font-semibold text-blue-900">{{ $creditSale->employee->full_name }}
                                        </div>
                                        <div class="text-xs text-gray-600">{{ $creditSale->supplier->supplier_name ??
                                            'N/A' }}</div>
                                    </td>
                                    <td class="px-3 py-2 text-sm font-semibold">
                                        <div>{{ $creditSale->customer->customer_name }}</div>
                                        <div class="text-xs text-gray-500">{{ $creditSale->customer->customer_code }}
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-sm">{{ $creditSale->invoice_number ?? '-' }}</td>
                                    <td class="px-3 py-2 text-sm text-right font-semibold text-orange-700">
                                        Rs {{ number_format($creditSale->sale_amount, 2) }}
                                    </td>
                                    <td class="px-3 py-2 text-sm text-gray-600">{{ $creditSale->notes ?? '-' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <td colspan="3" class="px-3 py-2 text-sm font-semibold">Total Credit Sales:</td>
                                    <td class="px-3 py-2 text-sm text-right font-bold text-orange-700">
                                        Rs {{ number_format($settlement->creditSales->sum('sale_amount'), 2) }}
                                    </td>
                                    <td class="px-3 py-2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    @endif

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