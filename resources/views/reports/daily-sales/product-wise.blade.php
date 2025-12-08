<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Product-wise Sales Report" :createRoute="null" createLabel="" :showSearch="true"
            :showRefresh="true" backRoute="reports.index" />
    </x-slot>

    <x-filter-section :action="route('reports.daily-sales.product-wise')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="start_date" value="Start Date" />
                <x-input id="start_date" name="start_date" type="date" class="mt-1 block w-full" :value="$startDate" />
            </div>
            <div>
                <x-label for="end_date" value="End Date" />
                <x-input id="end_date" name="end_date" type="date" class="mt-1 block w-full" :value="$endDate" />
            </div>
            <div>
                <x-label for="employee_id" value="Employee" />
                <select id="employee_id" name="employee_id"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Employees</option>
                    @foreach($employees as $employee)
                    <option value="{{ $employee->id }}" {{ $employeeId==$employee->id ? 'selected' : '' }}>
                        {{ $employee->name }}
                    </option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-2 pb-16">
        <x-status-message />
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
            @if($productSales->count() > 0)
            <div class="relative overflow-x-auto rounded-lg">
                <table class="min-w-max w-full table-auto text-sm">
                    <thead>
                        <tr class="bg-green-800 text-white uppercase text-sm">
                            <th class="py-2 px-2 text-left">Product Code</th>
                            <th class="py-2 px-2 text-left">Product Name</th>
                            <th class="py-2 px-2 text-right">Issued</th>
                            <th class="py-2 px-2 text-right">Sold</th>
                            <th class="py-2 px-2 text-right">Returned</th>
                            <th class="py-2 px-2 text-right">Shortage</th>
                            <th class="py-2 px-2 text-right">Sales Value</th>
                            <th class="py-2 px-2 text-right">COGS</th>
                            <th class="py-2 px-2 text-right">Gross Profit</th>
                            <th class="py-2 px-2 text-right">Avg Price</th>
                        </tr>
                    </thead>
                    <tbody class="text-black font-extrabold">
                        @foreach($productSales as $product)
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="py-1 px-2 font-mono">{{ $product->product_code }}</td>
                            <td class="py-1 px-2">{{ $product->product_name }}</td>
                            <td class="py-1 px-2 text-right font-mono">{{ number_format($product->total_issued, 2) }}
                            </td>
                            <td class="py-1 px-2 text-right font-mono">{{ number_format($product->total_sold, 2) }}</td>
                            <td class="py-1 px-2 text-right font-mono">{{ number_format($product->total_returned, 2) }}
                            </td>
                            <td class="py-1 px-2 text-right font-mono text-red-600">{{
                                number_format($product->total_shortage, 2) }}</td>
                            <td class="py-1 px-2 text-right font-mono">{{ number_format($product->total_sales_value, 2)
                                }}</td>
                            <td class="py-1 px-2 text-right font-mono">{{ number_format($product->total_cogs, 2) }}</td>
                            <td
                                class="py-1 px-2 text-right font-mono {{ $product->gross_profit >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ number_format($product->gross_profit, 2) }}
                            </td>
                            <td class="py-1 px-2 text-right font-mono">{{ number_format($product->avg_selling_price, 2)
                                }}</td>
                        </tr>
                        @endforeach
                        <tr class="border-t-2 border-gray-400 bg-gray-100 font-bold">
                            <td colspan="2" class="py-2 px-2 text-right">TOTALS</td>
                            <td class="py-2 px-2 text-right font-mono">{{ number_format($totals['total_issued'], 2) }}
                            </td>
                            <td class="py-2 px-2 text-right font-mono">{{ number_format($totals['total_sold'], 2) }}
                            </td>
                            <td class="py-2 px-2 text-right font-mono">{{ number_format($totals['total_returned'], 2) }}
                            </td>
                            <td class="py-2 px-2 text-right font-mono text-red-600">{{
                                number_format($totals['total_shortage'], 2) }}</td>
                            <td class="py-2 px-2 text-right font-mono">{{ number_format($totals['total_sales_value'], 2)
                                }}</td>
                            <td class="py-2 px-2 text-right font-mono">{{ number_format($totals['total_cogs'], 2) }}
                            </td>
                            <td
                                class="py-2 px-2 text-right font-mono {{ $totals['gross_profit'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ number_format($totals['gross_profit'], 2) }}
                            </td>
                            <td class="py-2 px-2"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            @else
            <p class="text-gray-700 text-center py-4">No sales data found</p>
            @endif
        </div>
    </div>
</x-app-layout>