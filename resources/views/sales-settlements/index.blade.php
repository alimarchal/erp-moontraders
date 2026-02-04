<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Sales Settlements" :createRoute="route('sales-settlements.create')"
            createLabel="New Settlement" :showSearch="true" :showRefresh="true" />
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

    <x-filter-section :action="route('sales-settlements.index')" class="no-print" maxWidth="max-w-7xl">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_settlement_number" value="Settlement #" />
                <x-input id="filter_settlement_number" name="filter[settlement_number]" type="text"
                    class="mt-1 block w-full" :value="request('filter.settlement_number')" placeholder="Search..." />
            </div>
            <div>
                <x-label for="filter_status" value="Status" />
                <select id="filter_status" name="filter[status]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Statuses</option>
                    <option value="draft" {{ request('filter.status') === 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="posted" {{ request('filter.status') === 'posted' ? 'selected' : '' }}>Posted</option>
                </select>
            </div>
            <div>
                <x-label for="filter_settlement_date_from" value="Date From" />
                <x-input id="filter_settlement_date_from" name="filter[settlement_date_from]" type="date"
                    class="mt-1 block w-full" :value="request('filter.settlement_date_from')" />
            </div>
            <div>
                <x-label for="filter_settlement_date_to" value="Date To" />
                <x-input id="filter_settlement_date_to" name="filter[settlement_date_to]" type="date"
                    class="mt-1 block w-full" :value="request('filter.settlement_date_to')" />
            </div>
            <div>
                <x-label for="filter_employee_id" value="Salesman" />
                <select id="filter_employee_id" name="filter[employee_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full select2">
                    <option value="">All Salesmen</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" {{ request('filter.employee_id') == $employee->id ? 'selected' : '' }}>
                            {{ $employee->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-label for="filter_vehicle_id" value="Vehicle" />
                <select id="filter_vehicle_id" name="filter[vehicle_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full select2">
                    <option value="">All Vehicles</option>
                    @foreach($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}" {{ request('filter.vehicle_id') == $vehicle->id ? 'selected' : '' }}>
                            {{ $vehicle->registration_number }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-label for="filter_warehouse_id" value="Warehouse" />
                <select id="filter_warehouse_id" name="filter[warehouse_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Warehouses</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" {{ request('filter.warehouse_id') == $warehouse->id ? 'selected' : '' }}>
                            {{ $warehouse->warehouse_name }}
                        </option>
                    @endforeach
                </select>
            </div>

        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 mt-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                {{-- Report Header --}}
                <p class="text-center font-extrabold mb-2 text-xl report-header">
                    Moon Traders<br>
                    <span class="text-lg">Sales Settlements</span><br>
                    @if(request('filter.settlement_date_from') || request('filter.settlement_date_to'))
                        <span class="text-xs font-normal">
                            Period:
                            {{ \Carbon\Carbon::parse(request('filter.settlement_date_from', now()))->format('d-M-Y') }} to
                            {{ \Carbon\Carbon::parse(request('filter.settlement_date_to', now()))->format('d-M-Y') }}
                        </span>
                    @endif
                    @php
                        $filtersText = [];
                        if (request('filter.employee_id'))
                            $filtersText[] = 'Employee: ' . ($employees->firstWhere('id', request('filter.employee_id'))->name ?? '');
                        if (request('filter.vehicle_id'))
                            $filtersText[] = 'Vehicle: ' . ($vehicles->firstWhere('id', request('filter.vehicle_id'))->registration_number ?? '');
                        if (request('filter.warehouse_id'))
                            $filtersText[] = 'Warehouse: ' . ($warehouses->firstWhere('id', request('filter.warehouse_id'))->warehouse_name ?? '');
                        if (request('filter.status'))
                            $filtersText[] = 'Status: ' . ucfirst(request('filter.status'));
                    @endphp
                    @if(count($filtersText) > 0)
                        <br>
                        <span class="text-xs font-normal">
                            {!! implode(' | ', $filtersText) !!}
                        </span>
                    @endif
                    <br>
                    <span class="print-only text-xs text-center hidden">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                @if($settlements->count() > 0)
                    <table class="report-table">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="text-center">Sr#</th>
                                <th class="text-center">Date</th>
                                <th class="text-center">Settlement #</th>
                                <th class="text-center">Goods Issue #</th>
                                <th class="text-center">
                                    <x-tooltip text="Salesman">
                                        <span class="print:hidden">SM</span>
                                        <span class="print-only">Salesman</span>
                                    </x-tooltip>
                                </th>
                                <th class="text-center">
                                    <x-tooltip text="CMV (Commercial Motor Vehicle)">
                                        <span class="print:hidden">CMV</span>
                                        <span class="print-only">Vehicle</span>
                                    </x-tooltip>
                                </th>

                                <th class="text-center">
                                    <x-tooltip text="COGS (Cost of Good Sold)">
                                        <span class="print:hidden">COGS</span>
                                        <span class="print-only">COGS</span>
                                    </x-tooltip>
                                </th>
                                <th class="text-center">Total Sales</th>
                                <!-- <th class="text-center">GP</th> -->
                                <th class="text-center">Exp</th>
                                <th class="text-center">Net Profit</th>

                                <th class="text-center no-print">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($settlements as $index => $settlement)
                                <tr>
                                    <td class="text-center text-black">
                                        {{ $settlements->firstItem() + $index }}
                                    </td>

                                    <td class="text-center text-black">
                                        {{ $settlement->settlement_date->format('d-m-y') }}
                                    </td>

                                    <td class="text-center text-black print:text-black">
                                        <x-tooltip :text="$settlement->settlement_number">
                                            <a href="{{ route('sales-settlements.show', $settlement) }}"
                                                class="text-blue-600 hover:underline"
                                                oncopy="event.preventDefault(); event.clipboardData.setData('text/plain', '{{ $settlement->settlement_number }}');">
                                                <span class="print:hidden">
                                                    {{ preg_replace('/^SETTLE-\d{2}(\d{2})-(\d+)$/', 'SI-$1-$2', $settlement->settlement_number) }}
                                                </span>
                                                <span class="print-only">
                                                    {{ preg_replace('/^SETTLE-\d{2}(\d{2})-(\d+)$/', 'SI-$1-$2', $settlement->settlement_number) }}
                                                </span>
                                            </a>
                                        </x-tooltip>
                                    </td>

                                    <td class="text-center text-black">

                                        @if($settlement->goodsIssue)
                                            <x-tooltip :text="$settlement->goodsIssue->issue_number">
                                                <a href="{{ route('goods-issues.show', $settlement->goodsIssue) }}"
                                                    class="text-blue-600 hover:underline"
                                                    oncopy="event.preventDefault(); event.clipboardData.setData('text/plain', '{{ $settlement->goodsIssue->issue_number }}');">
                                                    <span class="print:hidden">
                                                        {{ preg_replace('/^GI-\d{2}(\d{2})-(\d+)$/', 'GI-$1-$2', $settlement->goodsIssue->issue_number) }}
                                                    </span>
                                                    <span class="print-only">
                                                        {{ preg_replace('/^GI-\d{2}(\d{2})-(\d+)$/', 'GI-$1-$2', $settlement->goodsIssue->issue_number) }}
                                                    </span>
                                                </a>
                                            </x-tooltip>
                                        @else
                                            -
                                        @endif
                                    </td>


                                    <td class="text-left text-black">

                                        {{ $settlement->employee->name ?? 'N/A' }}
                                        <!-- {{ str_replace(['Warehouse - I', 'Warehouse - II', 'Warehouse'], ['W-I', 'W-II', 'W'], $settlement->warehouse->warehouse_name) }} -->

                                    </td>


                                    <td class="text-left text-black">
                                        {{ $settlement->vehicle->registration_number ?? $settlement->vehicle->vehicle_number ?? 'N/A' }}
                                        <!-- {{ str_replace(['Warehouse - I', 'Warehouse - II', 'Warehouse'], ['W-I', 'W-II', 'W'], $settlement->warehouse->warehouse_name) }} -->

                                    </td>


                                    <td class="text-right font-mono text-black-500">
                                        {{ number_format($settlement->total_cogs, 2) }}
                                    </td>

                                    <td class="text-right font-mono font-bold">
                                        {{ number_format($settlement->total_sales_amount, 2) }}
                                    </td>
                                    @php
                                        $netProfit = $settlement->gross_profit - $settlement->expenses_claimed;
                                    @endphp
                                    <!-- <td class="text-right font-mono">
                                                                                        {{ number_format($settlement->gross_profit, 2) }}
                                                                                    </td> -->
                                    <td class="text-right font-mono text-orange-600">
                                        {{ number_format($settlement->expenses_claimed, 2) }}
                                    </td>
                                    <td
                                        class="text-right font-mono font-bold {{ $netProfit > 0 ? 'text-green-700' : 'text-red-700' }}">
                                        {{ number_format($netProfit, 2) }}
                                    </td>

                                    <td class="text-center no-print relative overflow-visible group">
                                        <x-tooltip :text="ucfirst($settlement->status)">
                                            <span
                                                class="cursor-help font-bold {{ $settlement->status === 'posted' ? 'text-green-600' : 'text-gray-600' }}">
                                                {{ $settlement->status === 'posted' ? 'Posted' : 'Draft' }}
                                            </span>
                                        </x-tooltip>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-100 font-extrabold sticky bottom-0">
                            <tr>
                                <td colspan="6" class="py-2 px-2 text-right font-mono">
                                    Total ({{ $settlements->total() }}):
                                </td>
                                <td class="py-2 px-2 text-right font-mono">
                                    {{ number_format($totals->total_cogs, 2) }}
                                </td>
                                <td class="py-2 px-2 text-right font-mono">
                                    {{ number_format($totals->total_sales_amount, 2) }}
                                </td>

                                <!-- <td class="py-2 px-2 text-right font-mono">
                                                            {{ number_format($totals->total_gross_profit, 2) }}
                                                        </td> -->
                                <td class="py-2 px-2 text-right font-mono">
                                    {{ number_format($totals->total_expenses, 2) }}
                                </td>
                                <td class="py-2 px-2 text-right font-mono">
                                    {{ number_format($totals->total_net_profit, 2) }}
                                </td>

                            </tr>
                        </tfoot>
                    </table>

                    <div class="mt-4 no-print">
                        {{ $settlements->links() }}
                    </div>

                    <!-- Summary Tables -->
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
                                            {{ number_format($totals->total_sold_qty, 2) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="font-semibold text-black">Returned</td>
                                        <td class="text-right font-mono font-bold text-black">
                                            {{ number_format($totals->total_returned_qty, 2) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="font-semibold text-black">Shortage</td>
                                        <td class="text-right font-mono font-bold text-red-600 print:text-black">
                                            {{ number_format($totals->total_shortage_qty, 2) }}
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
                                        <td class="font-semibold text-black">Cash Sales (Gross)</td>
                                        <td class="text-right font-mono font-bold text-black">
                                            {{ number_format($totals->total_cash_sales, 2) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="font-semibold text-black">Expenses</td>
                                        <td class="text-right font-mono font-bold text-black">
                                            {{ number_format($totals->total_expenses, 2) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="font-semibold text-black">To Deposit</td>
                                        <td class="text-right font-mono font-bold text-green-600 print:text-black">
                                            {{ number_format($totals->total_cash_deposit, 2) }}
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
                                            {{ number_format($totals->total_cash_sales, 2) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="font-semibold text-black">Credit</td>
                                        <td class="text-right font-mono font-bold text-black">
                                            {{ number_format($totals->total_credit_sales, 2) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="font-semibold text-black">Recoveries</td>
                                        <td class="text-right font-mono font-bold text-black">
                                            {{ number_format($totals->total_recoveries, 2) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="font-semibold text-black">Cheque</td>
                                        <td class="text-right font-mono font-bold text-black">
                                            {{ number_format($totals->total_cheque_sales, 2) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="font-semibold text-black">Bank Transfer</td>
                                        <td class="text-right font-mono font-bold text-black">
                                            {{ number_format($totals->total_bank_transfer, 2) }}
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
                                            {{ number_format($totals->total_sales_amount, 2) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="font-semibold text-black">Gross Profit</td>
                                        <td class="text-right font-mono font-bold text-black">
                                            {{ number_format($totals->total_gross_profit, 2) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="font-semibold text-black">GP Margin</td>
                                        <td class="text-right font-mono font-bold text-black">
                                            @php
                                                $gpMargin = $totals->total_sales_amount > 0 ? ($totals->total_gross_profit / $totals->total_sales_amount) * 100 : 0;
                                            @endphp
                                            {{ number_format($gpMargin, 2) }}%
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="font-semibold text-black">Net Profit</td>
                                        <td class="text-right font-mono font-bold text-black">
                                            {{ number_format($totals->total_net_profit, 2) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="font-semibold text-black">NP Margin</td>
                                        <td class="text-right font-mono font-bold text-black">
                                            @php
                                                $npMargin = $totals->total_sales_amount > 0 ? ($totals->total_net_profit / $totals->total_sales_amount) * 100 : 0;
                                            @endphp
                                            {{ number_format($npMargin, 2) }}%
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <div class="p-6 text-center text-gray-500">
                        No sales settlements found for the selected period.
                    </div>
                @endif
            </div>
        </div>
    </div>
    @push('scripts')
        <script>
            $(document).ready(function () {
                // Re-initialize specific select2s with allowClear to enable resetting
                $('#filter_employee_id').select2({
                    width: '100%',
                    placeholder: "All Salesmen",
                    allowClear: true
                });
                $('#filter_vehicle_id').select2({
                    width: '100%',
                    placeholder: "All Vehicles",
                    allowClear: true
                });
            });
        </script>
    @endpush
</x-app-layout>