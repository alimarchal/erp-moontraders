<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Product-wise Sales Report
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                <!-- Filters -->
                <form method="GET" class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Start Date</label>
                        <input type="date" name="start_date" value="{{ $startDate }}"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">End Date</label>
                        <input type="date" name="end_date" value="{{ $endDate }}"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Employee</label>
                        <select name="employee_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <option value="">All Employees</option>
                            @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" {{ $employeeId==$employee->id ? 'selected' : '' }}>
                                {{ $employee->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit"
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Filter
                        </button>
                    </div>
                </form>

                <!-- Results Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product Code
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product Name
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Issued</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Sold</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Returned
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Shortage
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Sales Value
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">COGS</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Gross
                                    Profit</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Avg Price
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($productSales as $product)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $product->product_code }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $product->product_name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{
                                    number_format($product->total_issued, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{
                                    number_format($product->total_sold, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{
                                    number_format($product->total_returned, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{
                                    number_format($product->total_shortage, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{
                                    number_format($product->total_sales_value, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{
                                    number_format($product->total_cogs, 2) }}</td>
                                <td
                                    class="px-6 py-4 whitespace-nowrap text-sm text-right {{ $product->gross_profit >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ number_format($product->gross_profit, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{
                                    number_format($product->avg_selling_price, 2) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="10" class="px-6 py-4 text-center text-gray-500">No sales data found</td>
                            </tr>
                            @endforelse
                        </tbody>
                        @if($productSales->count() > 0)
                        <tfoot class="bg-gray-100 font-bold">
                            <tr>
                                <td colspan="2" class="px-6 py-4 text-sm">TOTALS</td>
                                <td class="px-6 py-4 text-sm text-right">{{ number_format($totals['total_issued'], 2) }}
                                </td>
                                <td class="px-6 py-4 text-sm text-right">{{ number_format($totals['total_sold'], 2) }}
                                </td>
                                <td class="px-6 py-4 text-sm text-right">{{ number_format($totals['total_returned'], 2)
                                    }}</td>
                                <td class="px-6 py-4 text-sm text-right">{{ number_format($totals['total_shortage'], 2)
                                    }}</td>
                                <td class="px-6 py-4 text-sm text-right">{{ number_format($totals['total_sales_value'],
                                    2) }}</td>
                                <td class="px-6 py-4 text-sm text-right">{{ number_format($totals['total_cogs'], 2) }}
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-right {{ $totals['gross_profit'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ number_format($totals['gross_profit'], 2) }}
                                </td>
                                <td class="px-6 py-4"></td>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>