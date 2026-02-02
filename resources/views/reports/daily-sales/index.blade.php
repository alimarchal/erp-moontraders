<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Daily Sales Report" :createRoute="null" createLabel="" :showSearch="true"
            :showRefresh="true" backRoute="reports.index" />
    </x-slot>

    <x-filter-section :action="route('reports.daily-sales.index')">
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
                        <option value="{{ $employee->id }}" {{ $employeeId == $employee->id ? 'selected' : '' }}>
                            {{ $employee->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-label for="vehicle_id" value="Vehicle" />
                <select id="vehicle_id" name="vehicle_id"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Vehicles</option>
                    @foreach($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}" {{ $vehicleId == $vehicle->id ? 'selected' : '' }}>
                            {{ $vehicle->vehicle_number }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-label for="warehouse_id" value="Warehouse" />
                <select id="warehouse_id" name="warehouse_id"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Warehouses</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" {{ $warehouseId == $warehouse->id ? 'selected' : '' }}>
                            {{ $warehouse->warehouse_name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

    <!-- Summary Cards -->
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-2">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                <div class="text-sm text-gray-600">Total Sales</div>
                <div class="text-2xl font-bold text-green-600 font-mono">{{ number_format($summary['total_sales'], 2) }}
                </div>
            </div>
            <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                <div class="text-sm text-gray-600">Cash Sales</div>
                <div class="text-2xl font-bold text-blue-600 font-mono">{{ number_format($summary['cash_sales'], 2) }}
                </div>
            </div>
            <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
                <div class="text-sm text-gray-600">Credit Sales</div>
                <div class="text-2xl font-bold text-purple-600 font-mono">
                    {{ number_format($summary['credit_sales'], 2) }}
                </div>
            </div>
            <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                <div class="text-sm text-gray-600">Gross Profit</div>
                <div class="text-2xl font-bold text-yellow-600 font-mono">{{ number_format($summary['gross_profit'], 2)
                    }}</div>
                <div class="text-xs text-gray-500">Margin: {{ number_format($summary['gross_profit_margin'], 2) }}%
                </div>
            </div>
        </div>
    </div>

    <x-data-table :items="$settlements" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Date'],
        ['label' => 'Settlement #'],
        ['label' => 'Employee'],
        ['label' => 'Vehicle'],
        ['label' => 'Qty Sold', 'align' => 'text-right'],
        ['label' => 'Total Sales', 'align' => 'text-right'],
        ['label' => 'Cash', 'align' => 'text-right'],
        ['label' => 'Credit', 'align' => 'text-right'],
        ['label' => 'Recoveries', 'align' => 'text-right'],
        ['label' => 'Cash to Deposit', 'align' => 'text-right'],
    ]" emptyMessage="No sales settlements found for the selected criteria.">
        @foreach ($settlements as $index => $settlement)
            <tr class="border-b border-gray-200 text-sm">
                <td class="py-1 px-2 text-center">
                    {{ $loop->iteration }}
                </td>
                <td class="py-1 px-2 whitespace-nowrap">
                    {{ $settlement->settlement_date->format('d-m-Y') }}
                </td>
                <td class="py-1 px-2">
                    <div class="font-semibold">
                        <a href="{{ route('sales-settlements.show', $settlement) }}"
                            class="text-indigo-600 hover:text-indigo-900 hover:underline">
                            {{ $settlement->settlement_number }} </a>
                    </div>
                </td>
                <td class="py-1 px-2">
                    <div class="font-semibold">{{ $settlement->employee->name ?? 'N/A' }}</div>
                </td>
                <td class="py-1 px-2">
                    <div class="font-mono">{{ $settlement->vehicle->vehicle_number ?? 'N/A' }}</div>
                </td>
                <td class="py-1 px-2 text-right font-mono">
                    {{ number_format($settlement->total_quantity_sold, 2) }}
                </td>
                <td class="py-1 px-2 text-right font-mono font-semibold">
                    {{ number_format($settlement->net_sales_amount, 2) }}
                </td>
                <td class="py-1 px-2 text-right font-mono">
                    {{ number_format($settlement->cash_sales_amount, 2) }}
                </td>
                <td class="py-1 px-2 text-right font-mono">
                    {{ number_format($settlement->credit_sales_amount, 2) }}
                </td>
                <td class="py-1 px-2 text-right font-mono">
                    {{ number_format($settlement->recoveries_amount, 2) }}
                </td>
                <td class="py-1 px-2 text-right font-mono">
                    {{ number_format($settlement->cash_to_deposit, 2) }}
                </td>
            </tr>
        @endforeach
        @if($settlements->count() > 0)
            <tr class="border-t-2 border-gray-400 bg-gray-100 font-bold">
                <td colspan="5" class="py-2 px-2 text-right">
                    Page Total ({{ $settlements->count() }} rows):
                </td>
                <td class="py-2 px-2 text-right font-mono">
                    {{ number_format($summary['total_quantity_sold'], 2) }}
                </td>
                <td class="py-2 px-2 text-right font-mono">
                    {{ number_format($summary['total_sales'], 2) }}
                </td>
                <td class="py-2 px-2 text-right font-mono">
                    {{ number_format($summary['cash_sales'], 2) }}
                </td>
                <td class="py-2 px-2 text-right font-mono">
                    {{ number_format($summary['credit_sales'], 2) }}
                </td>
                <td class="py-2 px-2 text-right font-mono">
                    {{ number_format($summary['recoveries'], 2) }}
                </td>
                <td class="py-2 px-2 text-right font-mono">
                    {{ number_format($summary['cash_to_deposit'], 2) }}
                </td>
            </tr>
        @endif
    </x-data-table>

    <!-- Additional Summary Cards -->
    @if($settlements->count() > 0)
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16 pt-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 rounded-md shadow-lg">
                    <div class="text-sm font-semibold text-gray-700 mb-2">Quantity Summary</div>
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <span>Sold:</span>
                            <span class="font-mono font-semibold">{{ number_format($summary['total_quantity_sold'], 2)
                                                                        }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Returned:</span>
                            <span class="font-mono font-semibold">{{ number_format($summary['total_quantity_returned'], 2)
                                                                        }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Shortage:</span>
                            <span class="font-mono font-semibold text-red-600">{{
            number_format($summary['total_quantity_shortage'], 2) }}</span>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 rounded-md shadow-lg">
                    <div class="text-sm font-semibold text-gray-700 mb-2">Cash Management</div>
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <span>Cash Collected:</span>
                            <span class="font-mono font-semibold">{{ number_format($summary['cash_collected'], 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Expenses:</span>
                            <span class="font-mono font-semibold">{{ number_format($summary['expenses_claimed'], 2)
                                                                        }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>To Deposit:</span>
                            <span class="font-mono font-semibold text-green-600">{{
            number_format($summary['cash_to_deposit'], 2) }}</span>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 rounded-md shadow-lg">
                    <div class="text-sm font-semibold text-gray-700 mb-2">Payment Methods</div>
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <span>Cash:</span>
                            <span class="font-mono font-semibold">{{ number_format($summary['cash_sales'], 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Credit:</span>
                            <span class="font-mono font-semibold">{{ number_format($summary['credit_sales'], 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Recoveries:</span>
                            <span class="font-mono font-semibold">{{ number_format($summary['recoveries'], 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Cheque:</span>
                            <span class="font-mono font-semibold">{{ number_format($summary['cheque_sales'], 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-app-layout>