<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Salesman-wise Sales Performance" :createRoute="null" createLabel="" :showSearch="true"
            :showRefresh="true" backRoute="reports.index" />
    </x-slot>

    <x-filter-section :action="route('reports.daily-sales.salesman-wise')">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <x-label for="start_date" value="Start Date" />
                <x-input id="start_date" name="start_date" type="date" class="mt-1 block w-full" :value="$startDate" />
            </div>
            <div>
                <x-label for="end_date" value="End Date" />
                <x-input id="end_date" name="end_date" type="date" class="mt-1 block w-full" :value="$endDate" />
            </div>
        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-2 pb-16">
        <x-status-message />
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
            @if($salesmanPerformance->count() > 0)
            <div class="relative overflow-x-auto rounded-lg">
                <table class="min-w-max w-full table-auto text-sm">
                    <thead>
                        <tr class="bg-green-800 text-white uppercase text-sm">
                            <th class="py-2 px-2 text-left">Employee Code</th>
                            <th class="py-2 px-2 text-left">Employee Name</th>
                            <th class="py-2 px-2 text-left">Vehicle</th>
                            <th class="py-2 px-2 text-right">Settlements</th>
                            <th class="py-2 px-2 text-right">Qty Sold</th>
                            <th class="py-2 px-2 text-right">Total Sales</th>
                            <th class="py-2 px-2 text-right">Cash Sales</th>
                            <th class="py-2 px-2 text-right">Credit Sales</th>
                            <th class="py-2 px-2 text-right">Cash Collected</th>
                            <th class="py-2 px-2 text-right">COGS</th>
                            <th class="py-2 px-2 text-right">Gross Profit</th>
                            <th class="py-2 px-2 text-right">GP %</th>
                        </tr>
                    </thead>
                    <tbody class="text-black font-extrabold">
                        @foreach($salesmanPerformance as $salesman)
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="py-1 px-2 font-mono">{{ $salesman->employee_code }}</td>
                            <td class="py-1 px-2">{{ $salesman->employee_name }}</td>
                            <td class="py-1 px-2 font-mono">{{ $salesman->vehicle_number }}</td>
                            <td class="py-1 px-2 text-right font-mono">{{ $salesman->settlement_count }}</td>
                            <td class="py-1 px-2 text-right font-mono">{{ number_format($salesman->total_quantity_sold,
                                2) }}</td>
                            <td class="py-1 px-2 text-right font-mono font-bold">{{
                                number_format($salesman->total_sales, 2) }}</td>
                            <td class="py-1 px-2 text-right font-mono">{{ number_format($salesman->cash_sales, 2) }}
                            </td>
                            <td class="py-1 px-2 text-right font-mono">{{ number_format($salesman->credit_sales, 2) }}
                            </td>
                            <td class="py-1 px-2 text-right font-mono">{{ number_format($salesman->cash_collected, 2) }}
                            </td>
                            <td class="py-1 px-2 text-right font-mono">{{ number_format($salesman->total_cogs, 2) }}
                            </td>
                            <td
                                class="py-1 px-2 text-right font-mono {{ $salesman->gross_profit >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ number_format($salesman->gross_profit, 2) }}
                            </td>
                            <td
                                class="py-1 px-2 text-right font-mono {{ $salesman->gross_profit_margin >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ number_format($salesman->gross_profit_margin, 2) }}%
                            </td>
                        </tr>
                        @endforeach
                        <tr class="border-t-2 border-gray-400 bg-gray-100 font-bold">
                            <td colspan="3" class="py-2 px-2 text-right">TOTALS</td>
                            <td class="py-2 px-2 text-right font-mono">{{ number_format($totals['settlement_count']) }}
                            </td>
                            <td class="py-2 px-2 text-right font-mono">{{ number_format($totals['total_quantity_sold'],
                                2) }}</td>
                            <td class="py-2 px-2 text-right font-mono">{{ number_format($totals['total_sales'], 2) }}
                            </td>
                            <td class="py-2 px-2 text-right font-mono">{{ number_format($totals['cash_sales'], 2) }}
                            </td>
                            <td class="py-2 px-2 text-right font-mono">{{ number_format($totals['credit_sales'], 2) }}
                            </td>
                            <td class="py-2 px-2 text-right font-mono">{{ number_format($totals['cash_collected'], 2) }}
                            </td>
                            <td class="py-2 px-2 text-right font-mono"></td>
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