<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Salesman Stock Register" :showSearch="true" :showRefresh="true" backRoute="reports.index" />
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
                padding: 4px;
                word-wrap: break-word;
            }

            .print-only {
                display: none;
            }

            @media print {
                @page {
                    margin: 10mm 10mm 10mm 10mm;
                    size: landscape; 
                }

                .no-print {
                    display: none !important;
                }

                body {
                    margin: 0 !important;
                    padding: 0 !important;
                }

                .max-w-7xl {
                    max-width: 100% !important;
                    width: 100% !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }

                .bg-white {
                    margin: 0 !important;
                    padding: 0 !important;
                    box-shadow: none !important;
                    border: none !important;
                }

                .overflow-x-auto {
                    overflow: visible !important;
                }

                .report-table {
                    font-size: 10px !important;
                    width: 100% !important;
                }

                .print-only {
                    display: block !important;
                }
                
                .break-inside-avoid {
                    break-inside: avoid;
                }
            }
        </style>
    @endpush

    <x-filter-section :action="route('reports.salesman-stock-register.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
            <!-- Date Filter -->
            <div>
                <x-label for="filter_date" value="Date" />
                <x-input id="filter_date" type="date" name="filter[date]" value="{{ request('filter.date', \Carbon\Carbon::today()->toDateString()) }}" class="block mt-1 w-full" />
            </div>

            <!-- Salesman Filter -->
            <div>
                <x-label for="filter_salesman_id" value="Salesman" />
                <select id="filter_salesman_id" name="filter[salesman_id]" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full select2">
                    <option value="">All Salesmen</option>
                    @foreach ($salesmen as $salesman)
                        <option value="{{ $salesman->id }}" {{ request('filter.salesman_id') == $salesman->id ? 'selected' : '' }}>
                            {{ $salesman->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Vehicle Filter -->
            <div>
                <x-label for="filter_vehicle_id" value="Vehicle" />
                <select id="filter_vehicle_id" name="filter[vehicle_id]" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full select2">
                    <option value="">All Vehicles</option>
                    @foreach ($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}" {{ request('filter.vehicle_id') == $vehicle->id ? 'selected' : '' }}>
                            {{ $vehicle->vehicle_number }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Supplier Filter -->
            <div>
                <x-label for="filter_supplier_id" value="Supplier" />
                <select id="filter_supplier_id" name="filter[supplier_id]" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full select2">
                    <option value="">All Suppliers</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ request('filter.supplier_id') == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->supplier_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Category Filter -->
            <div>
                <x-label for="filter_category_id" value="Category" />
                <select id="filter_category_id" name="filter[category_id]" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full select2">
                    <option value="">All Categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" {{ request('filter.category_id') == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Product Filter -->
            <div>
                <x-label for="filter_product_id" value="Product (Optional)" />
                <select id="filter_product_id" name="filter[product_id]" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full select2">
                    <option value="">All Products</option>
                    @foreach ($allProducts as $product)
                        <option value="{{ $product->id }}" {{ request('filter.product_id') == $product->id ? 'selected' : '' }}>
                            {{ $product->product_name }} ({{ $product->product_code }})
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        
        <!-- Summary Cards (Top) -->
        @if(isset($financials) && $selectedVehicleId)
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4 mt-4 print:grid-cols-2 print:gap-4 break-inside-avoid">
                <!-- Breakdown -->
                <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg border border-gray-200">
                    <h3 class="font-bold text-sm mb-2 border-b pb-1">Settlement Summary</h3>
                    <div class="space-y-1 text-xs">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total Sale (Stock):</span>
                            <span class="font-bold">{{ number_format($financials['total_sales_amount'] ?? 0, 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Settlement Sales:</span>
                            <span class="font-bold">{{ number_format($financials['settlement_sales_amount'] ?? 0, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-red-600">
                            <span>Less: Expenses</span>
                            <span>- {{ number_format($financials['expenses'] ?? 0, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-green-600">
                            <span>Add: Recoveries</span>
                            <span>+ {{ number_format($financials['recovery'] ?? 0, 2) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Collections -->
                <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg border border-gray-200">
                    <h3 class="font-bold text-sm mb-2 border-b pb-1">Collections</h3>
                    <div class="space-y-1 text-xs">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Cash:</span>
                            <span class="font-bold">{{ number_format($financials['cash'] ?? 0, 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Bank Transfer:</span>
                            <span class="font-bold">{{ number_format($financials['bank'] ?? 0, 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Cheques:</span>
                            <span class="font-bold">{{ number_format($financials['cheque'] ?? 0, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-sm font-bold text-blue-800 border-t pt-1 mt-1">
                            <span>Total Collected:</span>
                            <span>{{ number_format(($financials['cash'] ?? 0) + ($financials['bank'] ?? 0) + ($financials['cheque'] ?? 0), 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg print:shadow-none print:pb-0 mt-4">
            <div class="overflow-x-auto">
                <p class="text-center font-extrabold mb-2 text-sm">
                    Moon Traders<br>
                    Salesman Stock Register<br>
                    <span class="text-xs font-semibold">
                        Date: {{ \Carbon\Carbon::parse($date)->format('d M, Y') }} <br>
                        Salesman: {{ $selectedSalesmanId ? ($salesmen->find($selectedSalesmanId)->name ?? 'All') : 'All' }} | 
                        Vehicle: {{ $selectedVehicleId ? ($vehicles->find($selectedVehicleId)->vehicle_number ?? 'All') : 'All' }}
                        @if($selectedSupplierId) <br> Supplier: {{ $suppliers->find($selectedSupplierId)->supplier_name ?? '' }} @endif
                        @if($selectedCategoryId) | Category: {{ $categories->find($selectedCategoryId)->name ?? '' }} @endif
                        @if($selectedProductId) <br> Product: {{ $allProducts->find($selectedProductId)->product_name ?? '' }} @endif
                    </span>
                    <br>
                    <span class="print-only text-[10px] text-center">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                <table class="report-table">
                    <thead>
                        <tr class="bg-gray-50 text-xs">
                            <th class="text-center w-8">#</th>
                            <th class="text-left">SKU</th>
                            <th class="text-left font-normal">Brand</th>
                            <th class="text-center font-normal">TP</th>
                            <th class="text-center font-bold bg-yellow-50">B/F In</th>
                            <th class="text-center bg-blue-50">Issue</th>
                            <th class="text-center bg-blue-100 font-bold">Total</th>
                            <th class="text-center text-red-600">Return</th>
                            <th class="text-center text-red-600">Short</th>
                            <th class="text-center bg-green-50 font-bold">Sale</th>
                            <th class="text-right bg-green-100 font-bold">Amount</th>
                            <th class="text-center font-bold bg-gray-50">B/F Out</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php 
                            $totalAmount = 0; 
                            $totalSaleQty = 0; 
                            $totalLoad = 0;
                            $totalReturn = 0;
                            $bgColors = ['bg-white', 'bg-gray-50'];
                        @endphp
                        @forelse ($reportData as $row)
                            @php 
                                $totalAmount += $row->amount; 
                                $totalSaleQty += $row->sale;
                                $totalLoad += $row->load;
                                $totalReturn += $row->return;
                                
                                // Calculate Closing (B/F Out)
                                // Formula: B/F In + Issue - Sale - Return - Short
                                $bfOut = $row->bf + $row->load - $row->sale - $row->return - $row->short;
                            @endphp
                            <tr class="{{ $bgColors[$loop->remaining % 2] }} hover:bg-yellow-50 transition-colors">
                                <td class="text-center text-gray-400">{{ $loop->iteration }}</td>
                                <td class="font-bold text-gray-800">{{ $row->sku }}</td>
                                <td class="text-gray-500 text-[10px]">{{ $row->category }}</td>
                                <td class="text-center text-gray-500">{{ number_format($row->tp, 2) }}</td>
                                <td class="text-center font-bold bg-yellow-50 text-yellow-900 border-l border-r border-yellow-200">{{ number_format($row->bf, 0) }}</td>
                                <td class="text-center bg-blue-50 text-blue-900">{{ number_format($row->load, 0) }}</td>
                                <td class="text-center bg-blue-100 font-extrabold text-blue-900">{{ number_format($row->total, 0) }}</td>
                                <td class="text-center text-red-600 font-medium">{{ number_format($row->return, 0) }}</td>
                                <td class="text-center text-red-600">{{ number_format($row->short, 0) }}</td>
                                <td class="text-center bg-green-50 font-bold text-green-900 border-l border-green-200">{{ number_format($row->sale, 0) }}</td>
                                <td class="text-right bg-green-100 font-bold text-green-900">{{ number_format($row->amount, 2) }}</td>
                                <td class="text-center font-bold bg-gray-100 text-gray-700 border-l border-gray-200">{{ number_format($bfOut, 0) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="text-center p-8 text-gray-400 italic">
                                    No stock data found for the selected criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-100 font-bold border-t-2 border-gray-300">
                        <tr>
                            <td colspan="4" class="text-right pr-2 text-gray-600 uppercase text-xs tracking-wider">Grand Total:</td>
                            <td class="text-center text-gray-500">-</td>
                            <td class="text-center text-blue-800">{{ number_format($totalLoad, 0) }}</td>
                            <td class="text-center text-gray-500">-</td>
                            <td class="text-center text-red-800">{{ number_format($totalReturn, 0) }}</td>
                            <td class="text-center text-gray-500">-</td>
                            <td class="text-center font-extrabold text-green-800 text-lg">{{ number_format($totalSaleQty, 0) }}</td>
                            <td class="text-right font-extrabold text-green-800 text-lg">{{ number_format($totalAmount, 2) }}</td>
                            <td class="text-center text-gray-500">-</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>

@push('scripts')
<script>
    $(document).ready(function() {
        // Re-initialize Select2 with allowClear for specific filters
        $('#filter_salesman_id, #filter_vehicle_id, #filter_supplier_id, #filter_category_id, #filter_product_id').each(function() {
            var $this = $(this);
            // Get the text of the first empty option to use as placeholder
            var placeholderText = $this.find('option[value=""]').text().trim();
            
            // Re-init
            $this.select2({
                width: '100%',
                placeholder: placeholderText,
                allowClear: true
            });
        });
    });
</script>
@endpush
