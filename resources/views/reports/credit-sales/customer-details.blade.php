<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Credit Sales Details - {{ $customer->customer_name }}" :createRoute="null" createLabel=""
            :showSearch="true" :showRefresh="true" backRoute="reports.credit-sales.customer-history" />
    </x-slot>

    <!-- Customer Info Card -->
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-2">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-4">
            <div class="p-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Customer Code</p>
                        <p class="font-semibold">{{ $customer->customer_code }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Customer Name</p>
                        <p class="font-semibold">{{ $customer->customer_name }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Contact</p>
                        <p class="font-semibold">{{ $customer->phone ?? 'N/A' }}</p>
                        <p class="text-xs text-gray-500">{{ $customer->city ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Total Credit Sales</p>
                        <p class="text-xl font-bold text-orange-700 font-mono">
                            {{ number_format($creditSales->sum('debit'), 2) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($salesmenBreakdown->count() > 0)
        <!-- Salesman Breakdown -->
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mb-2">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-4">
                    <h3 class="text-lg font-semibold mb-2">Credit Sales by Salesman</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full table-auto text-sm">
                            <thead>
                                <tr class="bg-green-800 text-white uppercase text-sm">
                                    <th class="py-2 px-2 text-left">Salesman</th>
                                    <th class="py-2 px-2 text-left">Supplier</th>
                                    <th class="py-2 px-2 text-right">Total Amount</th>
                                    <th class="py-2 px-2 text-right">Number of Sales</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($salesmenBreakdown as $breakdown)
                                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                                        <td class="py-1 px-2">
                                            <div class="font-semibold text-blue-900">{{ $breakdown->employee->full_name }}</div>
                                            <div class="text-xs text-gray-500">{{ $breakdown->employee->employee_code }}</div>
                                        </td>
                                        <td class="py-1 px-2">
                                            <div class="font-medium">{{ $breakdown->supplier->supplier_name ?? 'N/A' }}</div>
                                            <div class="text-xs text-gray-500">{{ $breakdown->supplier->supplier_code ?? '-' }}
                                            </div>
                                        </td>
                                        <td class="py-1 px-2 text-right font-mono font-bold text-orange-700">
                                            {{ number_format($breakdown->total_amount, 2) }}
                                        </td>
                                        <td class="py-1 px-2 text-right">
                                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full">
                                                {{ $breakdown->sales_count }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <x-data-table :items="$creditSales" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Date'],
        ['label' => 'Salesman'],
        ['label' => 'Supplier'],
        ['label' => 'Settlement'],
        ['label' => 'Invoice #'],
        ['label' => 'Amount', 'align' => 'text-right'],
        ['label' => 'Notes'],
    ]" emptyMessage="No credit sales found">
        @foreach ($creditSales as $index => $sale)
            <tr class="border-b border-gray-200 text-sm hover:bg-gray-50">
                <td class="py-1 px-2 text-center">
                    {{ $creditSales->firstItem() + $index }}
                </td>
                <td class="py-1 px-2 whitespace-nowrap">
                    {{ \Carbon\Carbon::parse($sale->created_at)->format('d-m-Y') }}
                </td>
                <td class="py-1 px-2">
                    <div class="font-semibold text-blue-900">{{ $sale->employee->full_name }}</div>
                    <div class="text-xs text-gray-500">{{ $sale->employee->employee_code }}</div>
                </td>
                <td class="py-1 px-2">
                    <div class="font-medium">{{ $sale->supplier->supplier_name ?? 'N/A' }}</div>
                </td>
                <td class="py-1 px-2">
                    <a href="{{ route('sales-settlements.show', $sale->salesSettlement) }}"
                        class="text-indigo-600 hover:text-indigo-900 font-semibold">
                        {{ $sale->salesSettlement->settlement_number }}
                    </a>
                </td>
                <td class="py-1 px-2 font-mono">{{ $sale->invoice_number ?? '-' }}</td>
                <td class="py-1 px-2 text-right font-mono font-semibold text-orange-700">
                    {{ number_format($sale->debit, 2) }}
                </td>
                <td class="py-1 px-2 text-gray-600">{{ $sale->notes ?? '-' }}</td>
            </tr>
        @endforeach
        @if($creditSales->count() > 0)
            <tr class="border-t-2 border-gray-400 bg-gray-100 font-bold">
                <td colspan="6" class="py-2 px-2 text-right">
                    Page Total ({{ $creditSales->count() }} rows):
                </td>
                <td class="py-2 px-2 text-right font-mono text-orange-700">
                    {{ number_format($creditSales->sum('debit'), 2) }}
                </td>
                <td></td>
            </tr>
        @endif
    </x-data-table>
</x-app-layout>