<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Daily Sales Report" :createRoute="null" createLabel="" :showSearch="true"
            :showRefresh="true" backRoute="reports.index" />
    </x-slot>

    @push('header')
        <style>
            .report-table {
                width: 100%;
                border-collapse: collapse;
                border: 1px solid black;
                font-size: 12px;
                line-height: 1.2;
            }

            .report-table th,
            .report-table td {
                border: 1px solid black;
                padding: 4px 6px;
                white-space: nowrap;
            }

            .print-only {
                display: none;
            }

            @media print {
                @page {
                    margin: 15mm 5mm 20mm 5mm;

                    @bottom-center {
                        content: "Page " counter(page) " of " counter(pages);
                    }
                }

                .no-print {
                    display: none !important;
                }

                body {
                    margin: 0 !important;
                    padding: 0 !important;
                    counter-reset: page 1;
                    background-color: white !important;
                }

                .max-w-7xl,
                .max-w-8xl {
                    max-width: 100% !important;
                    width: 100% !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }

                .bg-white {
                    background-color: white !important;
                    margin: 0 !important;
                    padding: 10px !important;
                    border: none !important;
                    box-shadow: none !important;
                }

                .shadow-xl,
                .shadow-lg {
                    box-shadow: none !important;
                }

                .rounded-lg,
                .sm\:rounded-lg {
                    border-radius: 0 !important;
                }

                .overflow-x-auto {
                    overflow: visible !important;
                }

                .report-table {
                    font-size: 12px !important;
                    width: 100% !important;
                    table-layout: auto;
                }

                .report-table tr {
                    page-break-inside: avoid;
                }

                .report-table th,
                .report-table td {
                    padding: 1px 2px !important;
                    color: #000 !important;
                    background-color: white !important;
                    white-space: normal !important;
                    overflow-wrap: break-word;
                }

                /* Ensure specific background colors are removed in print */
                .bg-gray-100,
                .bg-gray-50,
                .bg-blue-50,
                .bg-red-50,
                .bg-indigo-50,
                .bg-green-50,
                .bg-orange-50,
                .bg-red-100,
                .bg-red-200,
                .bg-green-100,
                .bg-emerald-100 {
                    background-color: white !important;
                }

                p {
                    margin-top: 0 !important;
                    margin-bottom: 4px !important;
                }

                .print-info {
                    font-size: 8px !important;
                    margin-top: 2px !important;
                    margin-bottom: 5px !important;
                    color: #000 !important;
                }

                /* Header visibility in print */
                .report-header {
                    display: block !important;
                }

                .print-only {
                    display: block !important;
                }

                .page-footer {
                    display: none;
                }

                /* Force grid for summary tables in print */
                .summary-grid {
                    display: grid !important;
                    grid-template-columns: repeat(4, 1fr) !important;
                    gap: 0.5rem !important;
                    margin-top: 0.5rem !important;
                }
            }

            /* Screen styles for header */
            .report-header {
                /* Visible on screen by default now */
            }
        </style>
    @endpush

    <x-filter-section :action="route('reports.daily-sales.index')" class="no-print" maxWidth="max-w-8xl">
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
                <x-label for="employee_id" value="Salesman" />
                <select id="employee_id" name="employee_id"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full select2">
                    <option value="">All Salesmen</option>
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
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full select2">
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
            <div>
                <x-label for="settlement_number" value="Settlement #" />
                <x-input id="settlement_number" name="settlement_number" type="text" class="mt-1 block w-full"
                    placeholder="Search..." value="{{ request('settlement_number') }}" />
            </div>
            <div>
                <x-label for="sort_by" value="Sort By" />
                <select id="sort_by" name="sort_by"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="date_desc" {{ $sortBy == 'date_desc' ? 'selected' : '' }}>Date (Newest First)</option>
                    <option value="date_asc" {{ $sortBy == 'date_asc' ? 'selected' : '' }}>Date (Oldest First)</option>
                    <option value="settlement_no_desc" {{ $sortBy == 'settlement_no_desc' ? 'selected' : '' }}>Settlement
                        # (Desc)</option>
                    <option value="settlement_no_asc" {{ $sortBy == 'settlement_no_asc' ? 'selected' : '' }}>Settlement #
                        (Asc)</option>
                    <option value="salesman_asc" {{ $sortBy == 'salesman_asc' ? 'selected' : '' }}>Salesman (A-Z)</option>
                    <option value="total_sales_desc" {{ $sortBy == 'total_sales_desc' ? 'selected' : '' }}>Total Sales
                        (High-Low)</option>
                    <option value="total_sales_asc" {{ $sortBy == 'total_sales_asc' ? 'selected' : '' }}>Total Sales
                        (Low-High)</option>
                    <option value="gp_margin_desc" {{ $sortBy == 'gp_margin_desc' ? 'selected' : '' }}>GP Margin
                        (High-Low)</option>
                    <option value="gp_margin_asc" {{ $sortBy == 'gp_margin_asc' ? 'selected' : '' }}>GP Margin (Low-High)
                    </option>
                    <option value="net_profit_desc" {{ $sortBy == 'net_profit_desc' ? 'selected' : '' }}>Net Profit
                        (High-Low)</option>
                    <option value="net_profit_asc" {{ $sortBy == 'net_profit_asc' ? 'selected' : '' }}>Net Profit
                        (Low-High)</option>
                </select>
            </div>
        </div>
    </x-filter-section>

    <!-- Summary Cards Removed -->

    <div class="max-w-8xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 mt-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                {{-- Report Header --}}
                <p class="text-center font-extrabold mb-2 text-xl report-header">
                    Moon Traders<br>
                    <span class="text-lg">Daily Sales Report</span><br>
                    <span class="text-xs font-normal">
                        Period: {{ \Carbon\Carbon::parse($startDate)->format('d-M-Y') }} to
                        {{ \Carbon\Carbon::parse($endDate)->format('d-M-Y') }}
                    </span>
                    @php
                        $filters = [];
                        if ($employeeId)
                            $filters[] = 'Employee: ' . ($employees->firstWhere('id', $employeeId)->name ?? '');
                        if ($vehicleId)
                            $filters[] = 'Vehicle: ' . ($vehicles->firstWhere('id', $vehicleId)->vehicle_number ?? '');
                        if ($warehouseId)
                            $filters[] = 'Warehouse: ' . ($warehouses->firstWhere('id', $warehouseId)->warehouse_name ?? '');
                    @endphp
                    @if(count($filters) > 0)
                        <br>
                        <span class="text-xs font-normal">
                            {!! implode(' | ', $filters) !!}
                        </span>
                    @endif
                    <br>
                    <span class="print-only text-xs text-center hidden">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                <table class="report-table">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="text-center">Sr#</th>
                            <th class="text-left">Date</th>
                            <th class="text-left">Setl #</th>
                            <th class="text-left">Salesman</th>
                            <th class="text-left">Vehicle</th>
                            <th class="text-right">Total Sales</th>
                            <th class="text-right">Rtn</th>
                            <th class="text-right">Net Sales</th>
                            <th class="text-right">Cash</th>
                            <th class="text-right">Credit</th>
                            <th class="text-right">Recovery</th>
                            <th class="text-right">Expense</th>
                            <th class="text-right">Shortage</th>
                            <th class="text-right">Net Profit</th>
                            <th class="text-right">Net Deposit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($settlements as $index => $settlement)
                            <tr>
                                <td class="text-center text-black">
                                    {{ $loop->iteration }}
                                </td>

                                <td class="text-left text-black">
                                    {{ $settlement->settlement_date->format('d-m-y') }}
                                    @if($settlement->status === 'posted')
                                        (P)
                                        {{ str_replace(['Warehouse - I', 'Warehouse - II', 'Warehouse'], ['W-I', 'W-II', 'W'], $settlement->warehouse->warehouse_name) }}
                                    @else
                                        (D)
                                        {{ str_replace(['Warehouse - I', 'Warehouse - II', 'Warehouse'], ['W-I', 'W-II', 'W'], $settlement->warehouse->warehouse_name) }}
                                    @endif

                                </td>
                                <td>

                                    <a href="{{ route('sales-settlements.show', $settlement) }}"
                                        class="font-semibold text-indigo-600 hover:text-indigo-900 hover:underline print:text-black"
                                        x-data
                                        @copy.prevent="$event.clipboardData.setData('text/plain', '{{ $settlement->settlement_number }}')">
                                        <span class="print:hidden">
                                            {{ preg_replace('/^SETTLE-\d{4}-(\d+)$/', '$1', $settlement->settlement_number) }}
                                        </span>
                                        <span class="print-only">
                                            {{ preg_replace('/^SETTLE-\d{2}(\d{2})-(\d+)$/', 'S$1$2', $settlement->settlement_number) }}
                                        </span>
                                    </a>
                                </td>
                                <td class="text-right text-black">
                                    <div class="text-xs text-black text-left">{{ $settlement->employee->name ?? 'N/A' }}

                                    </div>
                                </td>
                                <td class="text-right text-black">
                                    <div class="text-xs text-black text-left">
                                        {{ $settlement->vehicle->vehicle_number ?? 'N/A' }}
                                    </div>
                                </td>
                                <td class="text-right font-mono">
                                    {{ number_format($settlement->total_sales_amount, 2) }}
                                </td>
                                <td class="text-right font-mono">
                                    {{ number_format($settlement->total_quantity_returned, 0) }}
                                </td>
                                <td class="text-right font-mono font-bold">
                                    {{ number_format($settlement->net_sales_amount, 2) }}
                                </td>
                                <td class="text-right font-mono">
                                    {{ number_format($settlement->cash_collected, 2) }}
                                </td>
                                <td class="text-right font-mono">
                                    {{ number_format($settlement->credit_sales_amount, 2) }}
                                </td>
                                <td class="text-right font-mono">
                                    {{ number_format($settlement->credit_recoveries, 2) }}
                                </td>
                                <td class="text-right font-mono">
                                    {{ number_format($settlement->expenses_claimed, 2) }}
                                </td>
                                <td class="text-right font-abc font-bold">
                                    {{ number_format($settlement->total_quantity_shortage, 2) }}
                                </td>
                                <td class="text-right font-mono font-bold">
                                    {{ number_format($settlement->gross_profit - $settlement->expenses_claimed, 2) }}
                                </td>
                                <td class="text-right font-mono font-bold">
                                    {{ number_format($settlement->cash_to_deposit, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-100 font-extrabold sticky bottom-0">
                        @if($settlements->count() > 0)
                            <tr>
                                <td colspan="5" class="py-2 px-2 text-right">
                                    Page Total ({{ $settlements->count() }} rows):
                                </td>
                                <td class="py-2 px-2 text-right font-mono">
                                    {{ number_format($settlements->sum('total_sales_amount'), 2) }}
                                </td>
                                <td class="py-2 px-2 text-right font-mono">
                                    {{ number_format($settlements->sum('total_quantity_returned'), 0) }}
                                </td>
                                <td class="py-2 px-2 text-right font-mono">
                                    {{ number_format($summary['total_sales'], 2) }}
                                </td>
                                <td class="py-2 px-2 text-right font-mono">
                                    {{ number_format($summary['cash_collected'], 2) }}
                                </td>
                                <td class="py-2 px-2 text-right font-mono">
                                    {{ number_format($summary['credit_sales'], 2) }}
                                </td>
                                <td class="py-2 px-2 text-right font-mono">
                                    {{ number_format($summary['recoveries'], 2) }}
                                </td>
                                <td class="py-2 px-2 text-right font-mono">
                                    {{ number_format($summary['expenses_claimed'], 2) }}
                                </td>
                                <td class="py-2 px-2 text-right font-mono">
                                    {{ number_format($summary['total_quantity_shortage'], 2) }}
                                </td>
                                <td class="py-2 px-2 text-right font-mono">
                                    {{ number_format($summary['gross_profit'] - $summary['expenses_claimed'], 2) }}
                                </td>
                                <td class="py-2 px-2 text-right font-mono">
                                    {{ number_format($summary['cash_to_deposit'], 2) }}
                                </td>
                            </tr>
                        @endif
                    </tfoot>
                </table>

                <!-- Summary Tables -->
                @if($settlements->count() > 0)
                    <div
                        class="mt-8 print:mt-2 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 align-top summary-grid">

                        <!-- Quantity Summary Table -->
                        <div class="print:break-inside-avoid">
                            <h4 class="font-bold text-md mb-2 text-center text-black">Quantity Summary</h4>
                            <table class="report-table w-full">
                                <thead>
                                    <tr class="bg-gray-100 print:bg-transparent">
                                        <th class="text-left">Metric</th>
                                        <th class="text-right">Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="font-semibold text-black">Sold</td>
                                        <td class="text-right font-mono font-bold text-black">
                                            {{ number_format($summary['total_quantity_sold'], 2) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="font-semibold text-black">Returned</td>
                                        <td class="text-right font-mono font-bold text-black">
                                            {{ number_format($summary['total_quantity_returned'], 2) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="font-semibold text-black">Shortage</td>
                                        <td class="text-right font-mono font-bold text-red-600 print:text-black">
                                            {{ number_format($summary['total_quantity_shortage'], 2) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Cash Management Table -->
                        <div class="print:break-inside-avoid">
                            <h4 class="font-bold text-md mb-2 text-center text-black">Cash Management</h4>
                            <table class="report-table w-full">
                                <thead>
                                    <tr class="bg-gray-100 print:bg-transparent">
                                        <th class="text-left">Metric</th>
                                        <th class="text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="font-semibold text-black">Cash Coll.</td>
                                        <td class="text-right font-mono font-bold text-black">
                                            {{ number_format($summary['cash_collected'], 2) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="font-semibold text-black">Expenses</td>
                                        <td class="text-right font-mono font-bold text-black">
                                            {{ number_format($summary['expenses_claimed'], 2) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="font-semibold text-black">To Deposit</td>
                                        <td class="text-right font-mono font-bold text-green-600 print:text-black">
                                            {{ number_format($summary['cash_to_deposit'], 2) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Payment Methods Table -->
                        <div class="print:break-inside-avoid">
                            <h4 class="font-bold text-md mb-2 text-center text-black">Payment Methods</h4>
                            <table class="report-table w-full">
                                <thead>
                                    <tr class="bg-gray-100 print:bg-transparent">
                                        <th class="text-left">Method</th>
                                        <th class="text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="font-semibold text-black">Cash</td>
                                        <td class="text-right font-mono font-bold text-black">
                                            {{ number_format($summary['cash_sales'], 2) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="font-semibold text-black">Credit</td>
                                        <td class="text-right font-mono font-bold text-black">
                                            {{ number_format($summary['credit_sales'], 2) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="font-semibold text-black">Recoveries</td>
                                        <td class="text-right font-mono font-bold text-black">
                                            {{ number_format($summary['recoveries'], 2) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="font-semibold text-black">Cheque</td>
                                        <td class="text-right font-mono font-bold text-black">
                                            {{ number_format($summary['cheque_sales'], 2) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Profitability Table -->
                        <div class="print:break-inside-avoid">
                            <h4 class="font-bold text-md mb-2 text-center text-black">Profitability</h4>
                            <table class="report-table w-full">
                                <thead>
                                    <tr class="bg-gray-100 print:bg-transparent">
                                        <th class="text-left">Metric</th>
                                        <th class="text-right">Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="font-semibold text-black">Total Sales</td>
                                        <td class="text-right font-mono font-bold text-black">
                                            {{ number_format($summary['total_sales'], 2) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="font-semibold text-black">Gross Profit</td>
                                        <td class="text-right font-mono font-bold text-black">
                                            {{ number_format($summary['gross_profit'], 2) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="font-semibold text-black">GP Margin</td>
                                        <td class="text-right font-mono font-bold text-black">
                                            {{ number_format($summary['gross_profit_margin'], 2) }}%
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="font-semibold text-black">Net Profit</td>
                                        <td class="text-right font-mono font-bold text-black">
                                            {{ number_format($summary['gross_profit'] - $summary['expenses_claimed'], 2) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="font-semibold text-black">NP Margin</td>
                                        <td class="text-right font-mono font-bold text-black">
                                            @php
                                                $netProfit = $summary['gross_profit'] - $summary['expenses_claimed'];
                                                $netProfitMargin = $summary['total_sales'] > 0 ? ($netProfit / $summary['total_sales']) * 100 : 0;
                                            @endphp
                                            {{ number_format($netProfitMargin, 2) }}%
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
    @push('scripts')
        <script>
            $(document).ready(function () {
                // Re-initialize specific select2s with allowClear to enable resetting
                $('#employee_id').select2({
                    width: '100%',
                    placeholder: "All Salesmen",
                    allowClear: true
                });
                $('#vehicle_id').select2({
                    width: '100%',
                    placeholder: "All Vehicles",
                    allowClear: true
                });
            });
        </script>
    @endpush
</x-app-layout>