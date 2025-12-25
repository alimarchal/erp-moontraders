<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Credit Sales Details - {{ $employee->full_name }}" :createRoute="null" createLabel=""
            :showSearch="true" :showRefresh="true" backRoute="reports.credit-sales.salesman-history" />
    </x-slot>

    <!-- Salesman Info Card -->
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-2">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-4">
            <div class="p-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Employee Code</p>
                        <p class="font-semibold">{{ $employee->employee_code }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Name</p>
                        <p class="font-semibold">{{ $employee->full_name }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Supplier</p>
                        <p class="font-semibold">{{ $employee->supplier->supplier_name ?? 'N/A' }}</p>
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

    @if($customerSummaries->count() > 0)
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mb-4">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-700">Customers Summary</h3>
                    <p class="text-xs text-gray-500">Click a customer to view their credit sales history for this salesman.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-gray-100 text-gray-700">
                                <th class="py-2 px-3 text-left">Customer</th>
                                <th class="py-2 px-3 text-right">Sales Count</th>
                                <th class="py-2 px-3 text-right">Total Amount</th>
                                <th class="py-2 px-3 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($customerSummaries as $customer)
                                <tr class="border-b border-gray-100">
                                    <td class="py-2 px-3">
                                        <div class="font-semibold">{{ $customer->customer_name }}</div>
                                        <div class="text-xs text-gray-500">{{ $customer->customer_code }}</div>
                                    </td>
                                    <td class="py-2 px-3 text-right font-mono">{{ number_format($customer->sales_count) }}</td>
                                    <td class="py-2 px-3 text-right font-mono font-semibold text-orange-700">
                                        {{ number_format($customer->total_amount, 2) }}
                                    </td>
                                    <td class="py-2 px-3 text-center">
                                        <a class="text-indigo-600 hover:text-indigo-900 font-semibold"
                                            href="{{ route('reports.credit-sales.salesman-details', $employee) }}?customer_id={{ $customer->customer_id }}">
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <x-data-table :items="$creditSales" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Date'],
        ['label' => 'Settlement'],
        ['label' => 'Customer'],
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
                    @if($sale->salesSettlement)
                        <a href="{{ route('sales-settlements.show', $sale->salesSettlement) }}"
                            class="text-indigo-600 hover:text-indigo-900 font-semibold">
                            {{ $sale->salesSettlement->settlement_number }}
                        </a>
                    @else
                        <span class="text-gray-500">-</span>
                    @endif
                </td>
                <td class="py-1 px-2">
                    <div class="font-semibold">{{ $sale->account->customer->customer_name ?? 'N/A' }}</div>
                    <div class="text-xs text-gray-500">{{ $sale->account->customer->customer_code ?? '-' }}</div>
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
                <td colspan="5" class="py-2 px-2 text-right">
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
