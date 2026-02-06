<x-app-layout>
    <x-slot name="header">
        <x-page-header title="ROI Report" :showSearch="true" :showRefresh="true" backRoute="reports.index" />
    </x-slot>

    @push('header')
        <style>
            /* Select2 Height & Style Normalization */
            .select2-container .select2-selection--single,
            .select2-container .select2-selection--multiple {
                min-height: 42px !important;
                display: flex !important;
                align-items: center !important;
                border-color: #d1d5db !important; /* border-gray-300 */
                border-radius: 0.375rem !important; /* rounded-md */
                padding: 4px !important;
            }
            
            /* Fix Vertical Alignment for Multiple Select */
             .select2-container .select2-selection--multiple {
                 flex-wrap: wrap; /* Allow tags to wrap */
            }

            .select2-container--default .select2-selection--multiple .select2-selection__rendered {
                padding-left: 0 !important;
                margin: 0 !important;
                display: flex;
                flex-wrap: wrap;
                gap: 4px;
            }
            
            .select2-search__field {
                 margin-top: 0 !important;
                 height: 24px !important; /* Ensure cursor isn't too huge */
            }

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
                padding: 3px 4px;
                word-wrap: break-word;
            }

            .print-only {
                display: none;
            }

            @media print {
                @page {
                    margin: 15mm 10mm 20mm 10mm;

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
                }

                .max-w-7xl {
                    max-width: 100% !important;
                    width: 100% !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }

                .bg-white {
                    margin: 0 !important;
                    padding: 10px !important;
                    box-shadow: none !important;
                }

                .overflow-x-auto {
                    overflow: visible !important;
                }

                .report-table {
                    font-size: 10px !important;
                    width: 100% !important;
                    table-layout: auto;
                }

                .report-table tr {
                    page-break-inside: avoid;
                }

                .report-table .text-right {
                    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace !important;
                }

                .report-table th,
                .report-table td {
                    padding: 2px 3px !important;
                    color: #000 !important;
                }

                .text-green-600,
                .text-red-600 {
                    color: #000 !important;
                }

                p {
                    margin-top: 0 !important;
                    margin-bottom: 8px !important;
                }

                .print-info {
                    font-size: 9px !important;
                    margin-top: 5px !important;
                    margin-bottom: 10px !important;
                    color: #000 !important;
                }

                .print-only {
                    display: block !important;
                }

                .page-footer {
                    display: none;
                }

                /* Force black text for days counts and specific elements */
                .day-count, .product-code, .category-name {
                    color: black !important;
                }
                
                 /* Force white backgrounds for table headers in print if needed, or keep gray for contrast if printer supports it. 
                    User asked "do not use any gray color use black for printing". 
                    This might mean text, but often means backgrounds too to save ink or style.
                    Let's remove bg-gray-* classes in print or override. 
                 */
                .bg-gray-100, .bg-gray-200, .bg-gray-50, .bg-blue-50, .bg-green-50, .bg-yellow-50, .bg-indigo-50 {
                   background-color: transparent !important;
                   /* Add border if background is removed to maintain structure? Table has borders already. */
                }
            }
        </style>
    @endpush

    <x-filter-section :action="route('reports.roi.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <!-- Dates -->
            <div>
                <x-label for="filter_start_date" value="Start Date" />
                <x-input id="filter_start_date" name="filter[start_date]" type="date" class="mt-1 block w-full"
                    :value="$startDate" />
            </div>
            <div>
                <x-label for="filter_end_date" value="End Date" />
                <x-input id="filter_end_date" name="filter[end_date]" type="date" class="mt-1 block w-full"
                    :value="$endDate" />
            </div>

            <!-- Settlement No -->
            <div>
                 <x-label for="filter_settlement_number" value="Settlement No" />
                 <x-input id="filter_settlement_number" name="filter[settlement_number]" type="text" class="mt-1 block w-full"
                    value="{{ $filters['settlement_number'] ?? '' }}" placeholder="Enter #..." />
            </div>

            <!-- Salesman (Employee) -->
            <div>
                <x-label for="filter_employee_id" value="Salesman / Employee" />
                <select id="filter_employee_id" name="filter[employee_id][]" multiple
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" {{ in_array($employee->id, $filters['employee_id'] ?? []) ? 'selected' : '' }}>
                            {{ $employee->name }} ({{ $employee->code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Vehicle -->
            <div>
                <x-label for="filter_vehicle_id" value="Vehicle" />
                <select id="filter_vehicle_id" name="filter[vehicle_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Vehicles</option>
                    @foreach($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}" {{ ($filters['vehicle_id'] ?? '') == $vehicle->id ? 'selected' : '' }}>
                            {{ $vehicle->registration_number }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Warehouse -->
            <div>
                <x-label for="filter_warehouse_id" value="Warehouse" />
                <select id="filter_warehouse_id" name="filter[warehouse_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Warehouses</option>
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}" {{ ($filters['warehouse_id'] ?? '') == $wh->id ? 'selected' : '' }}>
                            {{ $wh->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            
             <!-- Status -->
            <div>
                <x-label for="filter_status" value="Status" />
                <select id="filter_status" name="filter[status]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="posted" {{ ($filters['status'] ?? 'posted') == 'posted' ? 'selected' : '' }}>Posted</option>
                    <option value="draft" {{ ($filters['status'] ?? '') == 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="" {{ ($filters['status'] ?? '') == '' ? 'selected' : '' }}>All Statuses</option>
                </select>
            </div>

            <!-- Supplier Filter -->
            <div class="md:col-span-2 lg:col-span-1">
                <x-label for="filter_supplier_id" value="Supplier" />
                <select id="filter_supplier_id" name="filter[supplier_id]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Suppliers</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ ($filters['supplier_id'] ?? '') == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->supplier_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Product/SKU Filter -->
            <div class="md:col-span-2 lg:col-span-1">
                <x-label for="filter_product_id" value="Product / SKU" />
                <select id="filter_product_id" name="filter[product_id]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Products</option>
                    @foreach($productList as $prod)
                        <option value="{{ $prod->id }}" {{ ($filters['product_id'] ?? '') == $prod->id ? 'selected' : '' }}>
                            {{ $prod->product_name }} ({{ $prod->product_code }})
                        </option>
                    @endforeach
                </select>
            </div>

        </div>
    </x-filter-section>

    <div class="max-w-8xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 mt-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                <p class="text-center font-extrabold mb-2 text-xl">
                    Moon Traders<br>
                    <span class="text-lg">ROI Report</span><br>
                    <span class="text-xs font-normal">
                        Period: {{ \Carbon\Carbon::parse($startDate)->format('d-M-Y') }} to
                        {{ \Carbon\Carbon::parse($endDate)->format('d-M-Y') }}
                        @if($filterSummary)
                            <br>{{ $filterSummary }}
                        @endif
                    </span>
                    <br>
                    <span class="print-only print-info text-xs text-center">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                <table class="report-table">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="w-10">S.No</th>
                            <th class="w-40">SKU</th>
                            <th class="w-24">Category</th>
                            <th class="bg-blue-50">IP</th>
                            <th class="bg-green-50">TP</th>
                            <th class="bg-yellow-50">Margin</th>
                            
                            @foreach($matrixData['dates'] as $date)
                                <th class="w-8 text-center">{{ \Carbon\Carbon::parse($date)->format('j') }}</th>
                            @endforeach
                            
                            <th class="bg-gray-200">Total</th>
                            <th class="bg-green-50">Profit</th>
                             <th class="bg-indigo-50">Sale</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($matrixData['products'] as $product)
                            <tr>
                                <td class="text-center font-mono">{{ $loop->iteration }}</td>
                                <td class="font-bold">
                                    <div class="flex flex-col">
                                        <span class="text-black">{{ $product['product_name'] }}</span>
                                    </div>
                                </td>
                                <td class="text-xs text-black text-center category-name">{{ $product['category_name'] }}</td>
                                
                                <td class="text-right font-mono bg-blue-50 text-black">
                                    {{ number_format($product['ip'], 2) }}
                                </td>
                                <td class="text-right font-mono bg-green-50 text-black">
                                    {{ number_format($product['tp'], 2) }}
                                </td>
                                <td class="text-right font-mono bg-yellow-50 text-black">
                                    {{ number_format($product['margin'], 2) }}
                                </td>

                                @foreach($matrixData['dates'] as $date)
                                    @php
                                        $count = $product['daily_data'][$date]['qty'] ?? 0;
                                    @endphp
                                    <td class="text-center font-mono text-black">
                                        @if($count > 0)
                                            <span class="text-black font-bold day-count">{{ $count + 0 }}</span>
                                        @else
                                            <span class="text-black day-count">0</span>
                                        @endif
                                    </td>
                                @endforeach

                                <td class="text-center font-bold bg-gray-100 font-mono text-black">
                                    {{ $product['totals']['total_sold_qty'] + 0 }}
                                </td>
                                
                                <td class="text-right font-mono bg-green-50 text-black font-bold">
                                     <a href="{{ route('sales-settlements.index', ['filter[settlement_date_from]' => $startDate, 'filter[settlement_date_to]' => $endDate, 'filter[product_id]' => $product['product_id'], 'filter[employee_id]' => $filters['employee_id'] ?? null, 'filter[vehicle_id]' => $filters['vehicle_id'] ?? null, 'filter[warehouse_id]' => $filters['warehouse_id'] ?? null]) }}"
                                        class="hover:underline cursor-pointer text-black" target="_blank">
                                    {{ number_format($product['totals']['net_profit'], 2) }}
                                    </a>
                                </td>

                                <td class="text-right font-mono bg-indigo-50 text-black">
                                    <a href="{{ route('sales-settlements.index', ['filter[settlement_date_from]' => $startDate, 'filter[settlement_date_to]' => $endDate, 'filter[product_id]' => $product['product_id'], 'filter[employee_id]' => $filters['employee_id'] ?? null, 'filter[vehicle_id]' => $filters['vehicle_id'] ?? null, 'filter[warehouse_id]' => $filters['warehouse_id'] ?? null]) }}"
                                        class="hover:underline cursor-pointer text-black" target="_blank">
                                        {{ number_format($product['totals']['total_sale'], 2) }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($matrixData['dates']) + 9 }}" class="text-center py-4 text-gray-500">
                                    No data found for the selected criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-100 font-extrabold sticky bottom-0">
                        <tr>
                            <td colspan="6" class="text-right px-2">Grand Totals:</td>
                            
                            {{-- Daily Totals (Optional? Leaving blank for layout clarity) --}}
                            @foreach($matrixData['dates'] as $date)
                                <td></td>
                            @endforeach

                            <td class="text-center font-mono">{{ $matrixData['grand_totals']['sold_qty'] + 0 }}</td>
                            
                             <td class="text-right font-mono text-green-700 font-bold">
                                {{ number_format($matrixData['grand_totals']['net_profit'], 2) }}
                            </td>
                            
                            <td class="text-right font-mono">
                                {{ number_format($matrixData['grand_totals']['sale_amount'], 2) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>

                <!-- Summaries Section -->
                <div class="mt-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 align-top">
                    
                    <!-- Category Summary Card -->
                    <div class="border rounded-lg p-4 bg-gray-50 border-gray-300 print:break-inside-avoid shadow-sm print:shadow-none print:bg-transparent h-full">
                        <h4 class="font-bold text-lg mb-2 text-center border-b pb-2 text-black">Category Summary</h4>
                        <div class="overflow-x-auto">
                            <table class="report-table">
                                <thead class="bg-gray-200">
                                    <tr>
                                        <th class="text-left p-1 w-10 text-black">Sr #</th>
                                        <th class="text-left p-1 text-black">Category</th>
                                        <th class="text-right p-1 text-black">Qty</th>
                                        <th class="text-right p-1 text-black">Sale</th>
                                        <th class="text-right p-1 text-black">Profit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($categorySummary as $cat)
                                        <tr>
                                            <td class="p-1 text-center font-mono text-black">{{ $loop->iteration }}</td>
                                            <td class="p-1 text-black">{{ $cat['name'] }}</td>
                                            <td class="p-1 text-center font-mono text-black">{{ $cat['count'] }}</td>
                                            <td class="p-1 text-right font-mono text-black">{{ number_format($cat['total_sale'], 2) }}</td>
                                            <td class="p-1 text-right font-mono font-bold {{ $cat['total_profit'] >= 0 ? 'text-green-700' : 'text-red-700' }} text-black">
                                                {{ number_format($cat['total_profit'], 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Expense Analysis Card -->
                    <div class="border rounded-lg p-4 bg-gray-50 border-gray-300 print:break-inside-avoid shadow-sm print:shadow-none print:bg-transparent h-full">
                        <h4 class="font-bold text-lg mb-2 text-center border-b pb-2 text-black">Expense Analysis</h4>
                         <div class="overflow-x-auto">
                            <table class="report-table">
                                <thead class="bg-gray-200">
                                    <tr>
                                        <th class="text-left p-1 w-10 text-black">Sr #</th>
                                        <th class="text-left p-1 text-black">Account</th>
                                        <th class="text-center p-1 text-black">Code</th>
                                        <th class="text-right p-1 text-black">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($expenseBreakdown as $expense)
                                        <tr>
                                            <td class="p-1 text-center font-mono text-black">{{ $loop->iteration }}</td>
                                            <td class="p-1 text-black font-semibold">{{ $expense->account_name }}</td>
                                            <td class="p-1 text-center text-xs font-mono text-black">{{ $expense->account_code }}</td>
                                            <td class="p-1 text-right font-mono text-red-600 text-black">
                                                {{ number_format($expense->total_amount, 2) }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="p-2 text-center italic text-black">No Expenses Recorded</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                <tfoot class="bg-gray-100 font-bold border-t-2 border-black">
                                    <tr>
                                        <td colspan="3" class="p-1 text-right text-black">Total Expenses:</td>
                                        <td class="p-1 text-right font-mono text-red-700 text-black">
                                            {{ number_format($matrixData['grand_totals']['expenses'], 2) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Overall Financial Summary Card -->
                    <div class="border rounded-lg p-4 bg-gray-50 border-gray-300 print:break-inside-avoid shadow-sm print:shadow-none print:bg-transparent h-full">
                         <h4 class="font-bold text-lg mb-2 text-center border-b pb-2 text-black">Financial Summary</h4>
                          <div class="overflow-x-auto">
                             <table class="report-table">
                                <thead class="bg-gray-200">
                                    <tr>
                                        <th class="text-left p-1 w-10 text-black">Sr #</th>
                                        <th class="text-left p-1 text-black">Metric</th>
                                        <th class="text-right p-1 text-black">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="p-1 text-center font-mono text-black">1</td>
                                        <td class="p-1 font-semibold text-black">Total Sales</td>
                                        <td class="p-1 text-right font-mono font-bold text-black">{{ number_format($matrixData['grand_totals']['sale_amount'], 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="p-1 text-center font-mono text-black">2</td>
                                        <td class="p-1 font-semibold text-black">Total COGS</td>
                                        <td class="p-1 text-right font-mono text-black">({{ number_format($matrixData['grand_totals']['cogs'], 2) }})</td>
                                    </tr>
                                    <tr class="bg-gray-100">
                                        <td class="p-1 text-center font-mono text-black border-t">3</td>
                                        <td class="p-1 font-bold text-black border-t">Gross Profit</td>
                                        <td class="p-1 text-right font-mono font-bold text-black border-t">{{ number_format($matrixData['grand_totals']['gross_profit'], 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="p-1 text-center font-mono text-black">4</td>
                                        <td class="p-1 font-semibold text-black">Allocated Expenses</td>
                                        <td class="p-1 text-right font-mono text-black">({{ number_format($matrixData['grand_totals']['expenses'], 2) }})</td>
                                    </tr>
                                    <tr class="bg-green-100">
                                        <td class="p-1 text-center font-mono font-extrabold text-lg border-t-2 border-black text-black">5</td>
                                        <td class="p-1 font-extrabold text-lg text-black border-t-2 border-black">Net Profit</td>
                                        <td class="p-1 text-right font-mono font-extrabold text-lg border-t-2 border-black text-black">
                                            {{ number_format($matrixData['grand_totals']['net_profit'], 2) }}
                                        </td>
                                    </tr>
                                </tbody>
                             </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>
