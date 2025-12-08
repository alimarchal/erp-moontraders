<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Credit Sales Details - {{ $employee->full_name }}" :createRoute="null" createLabel=""
            :showSearch="true" :showRefresh="true" backRoute="reports.credit-sales.salesman-history" />
    </x-slot>

    <div class="py-2">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4 shadow-md" />

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-4 sm:p-6">
                    <h3 class="text-lg font-semibold mb-4">Salesman Information</h3>
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
                            <p class="text-xl font-bold text-orange-700">
                                Rs {{ number_format($creditSales->sum('sale_amount'), 2) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Credit Sales History</h3>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date
                                    </th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                        Settlement</th>
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
                                @forelse ($creditSales as $sale)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-sm">
                                        {{ \Carbon\Carbon::parse($sale->created_at)->format('d M Y') }}
                                    </td>
                                    <td class="px-3 py-2 text-sm">
                                        <a href="{{ route('sales-settlements.show', $sale->salesSettlement) }}"
                                            class="text-blue-600 hover:text-blue-800 font-semibold">
                                            {{ $sale->salesSettlement->settlement_number }}
                                        </a>
                                    </td>
                                    <td class="px-3 py-2 text-sm">
                                        <div class="font-semibold">{{ $sale->customer->customer_name }}</div>
                                        <div class="text-xs text-gray-500">{{ $sale->customer->customer_code }}</div>
                                    </td>
                                    <td class="px-3 py-2 text-sm">{{ $sale->invoice_number ?? '-' }}</td>
                                    <td class="px-3 py-2 text-sm text-right font-semibold text-orange-700">
                                        Rs {{ number_format($sale->sale_amount, 2) }}
                                    </td>
                                    <td class="px-3 py-2 text-sm text-gray-600">{{ $sale->notes ?? '-' }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="px-3 py-8 text-center text-gray-500">
                                        No credit sales found
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($creditSales->hasPages())
                    <div class="mt-4">
                        {{ $creditSales->links() }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>