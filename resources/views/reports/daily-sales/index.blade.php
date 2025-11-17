<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Daily Sales Report
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                <!-- Filters -->
                <form method="GET" class="mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Start Date</label>
                            <input type="date" name="start_date" value="{{ $startDate }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">End Date</label>
                            <input type="date" name="end_date" value="{{ $endDate }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Employee</label>
                            <select name="employee_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Employees</option>
                                @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" {{ $employeeId==$employee->id ? 'selected' : '' }}>
                                    {{ $employee->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Vehicle</label>
                            <select name="vehicle_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Vehicles</option>
                                @foreach($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}" {{ $vehicleId==$vehicle->id ? 'selected' : '' }}>
                                    {{ $vehicle->vehicle_number }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Warehouse</label>
                            <select name="warehouse_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Warehouses</option>
                                @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" {{ $warehouseId==$warehouse->id ? 'selected' : ''
                                    }}>
                                    {{ $warehouse->warehouse_name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="submit"
                                class="w-full bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                Filter
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                        <div class="text-sm text-gray-600">Total Sales</div>
                        <div class="text-2xl font-bold text-green-600">{{ number_format($summary['total_sales'], 2) }}
                        </div>
                    </div>
                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <div class="text-sm text-gray-600">Cash Sales</div>
                        <div class="text-2xl font-bold text-blue-600">{{ number_format($summary['cash_sales'], 2) }}
                        </div>
                    </div>
                    <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
                        <div class="text-sm text-gray-600">Credit Sales</div>
                        <div class="text-2xl font-bold text-purple-600">{{ number_format($summary['credit_sales'], 2) }}
                        </div>
                    </div>
                    <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                        <div class="text-sm text-gray-600">Gross Profit</div>
                        <div class="text-2xl font-bold text-yellow-600">{{ number_format($summary['gross_profit'], 2) }}
                        </div>
                        <div class="text-xs text-gray-500">Margin: {{ number_format($summary['gross_profit_margin'], 2)
                            }}%</div>
                    </div>
                </div>

                <!-- Settlements Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Settlement #
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employee
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vehicle</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qty Sold
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Sales
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cash</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Credit</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cash to
                                    Deposit</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($settlements as $settlement)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm">{{
                                    $settlement->settlement_date->format('Y-m-d') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">{{
                                    $settlement->settlement_number }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $settlement->employee->name ?? 'N/A'
                                    }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $settlement->vehicle->vehicle_number
                                    ?? 'N/A' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{
                                    number_format($settlement->total_quantity_sold, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold">{{
                                    number_format($settlement->total_sales_amount, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{
                                    number_format($settlement->cash_sales_amount, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{
                                    number_format($settlement->credit_sales_amount, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{
                                    number_format($settlement->cash_to_deposit, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <a href="{{ route('sales-settlements.show', $settlement) }}"
                                        class="text-indigo-600 hover:text-indigo-900">View</a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="10" class="px-6 py-4 text-center text-gray-500">No sales settlements found
                                    for the selected criteria</td>
                            </tr>
                            @endforelse
                        </tbody>
                        @if($settlements->count() > 0)
                        <tfoot class="bg-gray-100 font-bold">
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-sm">TOTALS</td>
                                <td class="px-6 py-4 text-sm text-right">{{
                                    number_format($summary['total_quantity_sold'], 2) }}</td>
                                <td class="px-6 py-4 text-sm text-right">{{ number_format($summary['total_sales'], 2) }}
                                </td>
                                <td class="px-6 py-4 text-sm text-right">{{ number_format($summary['cash_sales'], 2) }}
                                </td>
                                <td class="px-6 py-4 text-sm text-right">{{ number_format($summary['credit_sales'], 2)
                                    }}</td>
                                <td class="px-6 py-4 text-sm text-right">{{ number_format($summary['cash_to_deposit'],
                                    2) }}</td>
                                <td class="px-6 py-4"></td>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>

                <!-- Additional Details -->
                @if($settlements->count() > 0)
                <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="text-sm text-gray-600">Quantity Summary</div>
                        <div class="mt-2 space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span>Sold:</span>
                                <span class="font-semibold">{{ number_format($summary['total_quantity_sold'], 2)
                                    }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Returned:</span>
                                <span class="font-semibold">{{ number_format($summary['total_quantity_returned'], 2)
                                    }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Shortage:</span>
                                <span class="font-semibold text-red-600">{{
                                    number_format($summary['total_quantity_shortage'], 2) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="text-sm text-gray-600">Cash Management</div>
                        <div class="mt-2 space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span>Cash Collected:</span>
                                <span class="font-semibold">{{ number_format($summary['cash_collected'], 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Expenses:</span>
                                <span class="font-semibold">{{ number_format($summary['expenses_claimed'], 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>To Deposit:</span>
                                <span class="font-semibold text-green-600">{{ number_format($summary['cash_to_deposit'],
                                    2) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="text-sm text-gray-600">Payment Methods</div>
                        <div class="mt-2 space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span>Cash:</span>
                                <span class="font-semibold">{{ number_format($summary['cash_sales'], 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Credit:</span>
                                <span class="font-semibold">{{ number_format($summary['credit_sales'], 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Cheque:</span>
                                <span class="font-semibold">{{ number_format($summary['cheque_sales'], 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>