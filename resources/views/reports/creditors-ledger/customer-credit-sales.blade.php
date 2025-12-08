<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Credit Sales - {{ $customer->customer_name }}" :createRoute="null" createLabel=""
            :showSearch="true" :showRefresh="true" backRoute="reports.creditors-ledger.index" />
    </x-slot>

    {{-- Customer Info & Balance Card --}}
    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mb-6">
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <p class="text-sm text-gray-500">Customer Code</p>
                    <p class="font-semibold font-mono">{{ $customer->customer_code }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Customer Name</p>
                    <p class="font-semibold">{{ $customer->customer_name }}</p>
                    @if($customer->business_name)
                    <p class="text-xs text-gray-600">{{ $customer->business_name }}</p>
                    @endif
                </div>
                <div>
                    <p class="text-sm text-gray-500">Contact</p>
                    <p class="font-semibold">{{ $customer->phone ?? 'N/A' }}</p>
                    <p class="text-xs text-gray-500">{{ $customer->city ?? '-' }}, {{ $customer->address ?? '' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Total Credit Sales</p>
                    <p class="text-xl font-bold text-blue-700">
                        ₨ {{ number_format($creditSales->sum('sale_amount'), 2) }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Current Outstanding</p>
                    <p class="text-xl font-bold {{ $currentBalance > 0 ? 'text-orange-700' : 'text-green-700' }}">
                        ₨ {{ number_format($currentBalance, 2) }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Salesman Breakdown --}}
    @if($salesmenBreakdown->count() > 0)
    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mb-6">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4">Credit Sales by Salesman</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Salesman</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total Amount
                            </th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Number of Sales
                            </th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">% of Total</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @php $totalAmount = $salesmenBreakdown->sum('total_amount'); @endphp
                        @foreach ($salesmenBreakdown as $breakdown)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2">
                                <div class="font-semibold text-blue-900">{{ $breakdown->employee->full_name ?? 'Unknown'
                                    }}</div>
                                <div class="text-xs text-gray-500">{{ $breakdown->employee->employee_code ?? '' }}</div>
                            </td>
                            <td class="px-3 py-2 text-right font-bold text-orange-700">
                                ₨ {{ number_format($breakdown->total_amount, 2) }}
                            </td>
                            <td class="px-3 py-2 text-right">
                                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-semibold">
                                    {{ $breakdown->sales_count }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right text-gray-600">
                                {{ $totalAmount > 0 ? number_format(($breakdown->total_amount / $totalAmount) * 100, 1)
                                : 0 }}%
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <x-filter-section :action="route('reports.creditors-ledger.customer-credit-sales', $customer)">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_date_from" value="Date From" />
                <x-input id="filter_date_from" name="filter[date_from]" type="date" class="mt-1 block w-full"
                    :value="request('filter.date_from')" />
            </div>

            <div>
                <x-label for="filter_date_to" value="Date To" />
                <x-input id="filter_date_to" name="filter[date_to]" type="date" class="mt-1 block w-full"
                    :value="request('filter.date_to')" />
            </div>

            <div>
                <x-label for="per_page" value="Show Per Page" />
                <select id="per_page" name="per_page"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="25" {{ request('per_page')==25 ? 'selected' : '' }}>25</option>
                    <option value="50" {{ request('per_page', 50)==50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ request('per_page')==100 ? 'selected' : '' }}>100</option>
                </select>
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$creditSales" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Date'],
        ['label' => 'Settlement'],
        ['label' => 'Salesman'],
        ['label' => 'Invoice #'],
        ['label' => 'Amount', 'align' => 'text-right'],
        ['label' => 'Notes'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No credit sales found.">
        @foreach ($creditSales as $index => $sale)
        <tr class="border-b border-gray-200 text-sm hover:bg-gray-50">
            <td class="py-2 px-2 text-center">
                {{ $creditSales->firstItem() + $index }}
            </td>
            <td class="py-2 px-2 whitespace-nowrap">
                {{ $sale->created_at->format('d-m-Y') }}
            </td>
            <td class="py-2 px-2">
                @if($sale->salesSettlement)
                <a href="{{ route('sales-settlements.show', $sale->salesSettlement) }}"
                    class="text-blue-600 hover:text-blue-800 font-semibold" target="_blank">
                    {{ $sale->salesSettlement->settlement_number }}
                </a>
                <div class="text-xs text-gray-500">{{ $sale->salesSettlement->settlement_date->format('d M Y') }}</div>
                @else
                <span class="text-gray-400">—</span>
                @endif
            </td>
            <td class="py-2 px-2">
                @if($sale->employee)
                <div class="font-semibold text-blue-900">{{ $sale->employee->full_name }}</div>
                <div class="text-xs text-gray-500">{{ $sale->employee->employee_code }}</div>
                @else
                <span class="text-gray-400">—</span>
                @endif
            </td>
            <td class="py-2 px-2">
                {{ $sale->invoice_number ?? '—' }}
            </td>
            <td class="py-2 px-2 text-right font-mono font-bold text-orange-700">
                ₨ {{ number_format($sale->sale_amount, 2) }}
            </td>
            <td class="py-2 px-2 text-sm text-gray-600">
                {{ Str::limit($sale->notes, 50) ?? '—' }}
            </td>
            <td class="py-2 px-2 text-center">
                @if($sale->salesSettlement)
                <a href="{{ route('sales-settlements.show', $sale->salesSettlement) }}"
                    class="inline-flex items-center px-2 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700"
                    title="View Settlement" target="_blank">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                </a>
                @endif
            </td>
        </tr>
        @endforeach
        <tr class="border-t-2 border-gray-400 bg-gray-100 font-bold">
            <td colspan="5" class="py-2 px-2 text-right">
                Page Total ({{ $creditSales->count() }} sales):
            </td>
            <td class="py-2 px-2 text-right font-mono text-orange-700">
                ₨ {{ number_format($creditSales->sum('sale_amount'), 2) }}
            </td>
            <td colspan="2"></td>
        </tr>
    </x-data-table>

    {{-- Quick Actions --}}
    <div class="mt-6 flex gap-4">
        <a href="{{ route('reports.creditors-ledger.customer-ledger', $customer) }}"
            class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            View Full Ledger
        </a>
        <a href="{{ route('customers.show', $customer) }}"
            class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            Customer Profile
        </a>
    </div>
</x-app-layout>