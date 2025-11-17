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

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase">Cash Collected</h3>
                            <p class="text-lg font-semibold text-gray-900">
                                Rs {{ number_format($settlement->cash_collected, 2) }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase">Expenses Claimed</h3>
                            <p class="text-lg font-semibold text-red-700">
                                Rs {{ number_format($settlement->expenses_claimed, 2) }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase">Cash to Deposit</h3>
                            <p class="text-lg font-bold text-green-900">
                                Rs {{ number_format($settlement->cash_to_deposit, 2) }}</p>
                        </div>
                    </div>

                    <hr class="my-6 border-gray-200">

                    <h3 class="text-lg font-semibold mb-4">Product-wise Settlement</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Issued</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Sold</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Returned</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Shortage</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Unit Cost</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Selling Price</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Sales Value</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($settlement->items as $item)
                                <tr>
                                    <td class="px-3 py-2 text-sm">{{ $item->line_no }}</td>
                                    <td class="px-3 py-2 text-sm">
                                        <div class="font-semibold">{{ $item->product->product_name }}</div>
                                        <div class="text-xs text-gray-500">{{ $item->product->product_code }}</div>
                                    </td>
                                    <td class="px-3 py-2 text-sm text-right">{{ number_format($item->quantity_issued, 3) }}</td>
                                    <td class="px-3 py-2 text-sm text-right font-semibold text-green-700">
                                        {{ number_format($item->quantity_sold, 3) }}
                                    </td>
                                    <td class="px-3 py-2 text-sm text-right text-blue-700">
                                        {{ number_format($item->quantity_returned, 3) }}
                                    </td>
                                    <td class="px-3 py-2 text-sm text-right text-red-700">
                                        {{ number_format($item->quantity_shortage, 3) }}
                                    </td>
                                    <td class="px-3 py-2 text-sm text-right">Rs {{ number_format($item->unit_cost, 2) }}</td>
                                    <td class="px-3 py-2 text-sm text-right">Rs {{ number_format($item->selling_price, 2) }}</td>
                                    <td class="px-3 py-2 text-sm text-right font-bold">
                                        Rs {{ number_format($item->total_sales_value, 2) }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <td colspan="3" class="px-3 py-2 text-sm">
                                        <span class="font-semibold">Totals:</span>
                                    </td>
                                    <td class="px-3 py-2 text-sm text-right font-bold text-green-700">
                                        {{ number_format($settlement->total_quantity_sold, 3) }}
                                    </td>
                                    <td class="px-3 py-2 text-sm text-right font-bold text-blue-700">
                                        {{ number_format($settlement->total_quantity_returned, 3) }}
                                    </td>
                                    <td class="px-3 py-2 text-sm text-right font-bold text-red-700">
                                        {{ number_format($settlement->total_quantity_shortage, 3) }}
                                    </td>
                                    <td colspan="2" class="px-3 py-2"></td>
                                    <td class="px-3 py-2 text-sm text-right font-bold">
                                        Rs {{ number_format($settlement->items->sum('total_sales_value'), 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    @if ($settlement->sales->count() > 0)
                    <hr class="my-6 border-gray-200">
                    <h3 class="text-lg font-semibold mb-4">Credit Sales Details</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Payment Type</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($settlement->sales as $sale)
                                <tr>
                                    <td class="px-3 py-2 text-sm">{{ $sale->customer->customer_name }}</td>
                                    <td class="px-3 py-2 text-sm">{{ $sale->invoice_number ?? '-' }}</td>
                                    <td class="px-3 py-2 text-sm">
                                        <span class="px-2 py-1 text-xs rounded-full
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
