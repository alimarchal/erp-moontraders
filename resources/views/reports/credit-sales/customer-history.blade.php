<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Customer Credit Sales History
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-status-message class="mb-4 shadow-md" />

            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold mb-2">Credit Sales by Customer</h3>
                        <p class="text-sm text-gray-600">Click on any customer to view detailed credit sales history
                            from different salesmen</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Customer
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Contact
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Total Credit Sales
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Number of Sales
                                    </th>
                                    <th
                                        class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($customers as $customer)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-gray-900">{{ $customer->customer_name }}</div>
                                        <div class="text-xs text-gray-500">{{ $customer->customer_code }}</div>
                                        @if($customer->business_name)
                                        <div class="text-xs text-gray-600">{{ $customer->business_name }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="text-sm text-gray-900">{{ $customer->phone ?? '-' }}</div>
                                        <div class="text-xs text-gray-500">{{ $customer->city ?? '-' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="text-lg font-bold text-orange-700">
                                            Rs {{ number_format($customer->credit_sales_sum_sale_amount, 2) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span
                                            class="px-3 py-1 text-sm font-semibold bg-blue-100 text-blue-800 rounded-full">
                                            {{ $customer->credit_sales_count }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <a href="{{ route('reports.credit-sales.customer-details', $customer) }}"
                                            class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition">
                                            <svg class="w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                        No credit sales found for any customer
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                            @if($customers->count() > 0)
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <td colspan="2" class="px-4 py-3 text-sm font-bold text-gray-900">
                                        Grand Total:
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="text-xl font-bold text-orange-700">
                                            Rs {{ number_format($customers->sum('credit_sales_sum_sale_amount'), 2) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span
                                            class="px-3 py-1 text-sm font-bold bg-blue-200 text-blue-900 rounded-full">
                                            {{ $customers->sum('credit_sales_count') }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3"></td>
                                </tr>
                            </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>