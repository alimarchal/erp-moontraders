<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Stock Availability Report" :createRoute="null" createLabel="" :showSearch="true"
            :showRefresh="true" backRoute="reports.index" />
    </x-slot>

    @push('header')
        <style>
            .report-table {
                width: 100%;
                border-collapse: collapse;
                border: 1px solid black;
                font-size: 13px;
                line-height: 1.3;
            }

            .report-table th,
            .report-table td {
                border: 1px solid black;
                padding: 4px 6px;
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

                .print-only {
                    display: block !important;
                }
            }
        </style>
    @endpush

    <x-filter-section :action="route('reports.stock-availability.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

            {{-- As-Of Date --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">As of Date</label>
                <input type="date" name="as_of_date" value="{{ $asOfDate }}"
                    class="w-full border border-gray-300 rounded-md shadow-sm text-sm px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500" />
            </div>

            {{-- Supplier --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Supplier</label>
                <select name="supplier_id" class="select2 w-full">
                    <option value="">All Suppliers</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected($supplierId == $supplier->id)>
                            {{ $supplier->supplier_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Warehouse --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Warehouse</label>
                <select name="warehouse_id" class="select2 w-full">
                    <option value="">All Warehouses</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected($warehouseId == $warehouse->id)>
                            {{ $warehouse->warehouse_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Category --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Category</label>
                <select name="category_id" class="select2 w-full">
                    <option value="">All Categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected($categoryId == $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Stock Source --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Stock Source</label>
                <select name="stock_source" class="select2 w-full">
                    <option value="all" @selected($stockSource === 'all')>Warehouse + Van</option>
                    <option value="warehouse" @selected($stockSource === 'warehouse')>Warehouse Only</option>
                    <option value="van" @selected($stockSource === 'van')>Van Only</option>
                </select>
            </div>

            {{-- Sort By --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Sort By</label>
                <select name="sort_by" class="select2 w-full">
                    <option value="supplier_name" @selected($sortBy === 'supplier_name')>Supplier Name</option>
                    <option value="quantity_desc" @selected($sortBy === 'quantity_desc')>Quantity (High to Low)</option>
                    <option value="quantity_asc" @selected($sortBy === 'quantity_asc')>Quantity (Low to High)</option>
                    <option value="amount_desc" @selected($sortBy === 'amount_desc')>Amount (High to Low)</option>
                    <option value="amount_asc" @selected($sortBy === 'amount_asc')>Amount (Low to High)</option>
                </select>
            </div>

            {{-- Show Zero Stock --}}
            <div class="flex items-end pb-1">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="show_zero_stock" id="show_zero_stock" value="1"
                        @checked($showZeroStock)
                        class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500" />
                    <span class="text-sm text-gray-700">Show Zero Stock</span>
                </label>
            </div>

        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="bg-white shadow rounded-lg overflow-hidden">

            {{-- Report Header --}}
            <div class="px-6 py-4 border-b border-gray-200 no-print">
                <h2 class="text-base font-semibold text-gray-800">Stock Availability — Supplier Wise</h2>
                <p class="text-xs text-gray-500 mt-0.5">
                    As of: <span class="font-medium text-gray-700">{{ \Carbon\Carbon::parse($asOfDate)->format('d M Y') }}</span>
                    @if (!$isCurrentStock)
                        <span class="ml-2 text-amber-600 font-medium">(Historical)</span>
                    @else
                        <span class="ml-2 text-green-600 font-medium">(Current)</span>
                    @endif
                    &nbsp;|&nbsp;
                    Source: <span class="font-medium text-gray-700">{{ match($stockSource) { 'warehouse' => 'Warehouse Only', 'van' => 'Van Only', default => 'Warehouse + Van' } }}</span>
                    &nbsp;|&nbsp;
                    {{ $stockData->count() }} supplier(s)
                </p>
            </div>

            {{-- Print Header --}}
            <div class="print-only px-4 pt-4 pb-2 text-center">
                <h2 class="text-lg font-bold">Stock Availability Report — Supplier Wise</h2>
                <p class="text-sm">As of: {{ \Carbon\Carbon::parse($asOfDate)->format('d M Y') }} &nbsp;|&nbsp;
                    Source: {{ match($stockSource) { 'warehouse' => 'Warehouse Only', 'van' => 'Van Only', default => 'Warehouse + Van' } }}
                    @if($supplierId) &nbsp;|&nbsp; Supplier: {{ $suppliers->find($supplierId)?->supplier_name }} @endif
                </p>
            </div>

            <div class="overflow-x-auto">
                @if ($stockData->isEmpty())
                    <div class="px-6 py-10 text-center text-sm text-gray-500">
                        No stock data found for the selected filters.
                    </div>
                @else
                    <table class="report-table">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="text-center w-12">Sr#</th>
                                <th class="text-left">Supplier</th>
                                <th class="text-right">Stock In Quantity</th>
                                <th class="text-right">Stock Amount (Rs.)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($stockData as $index => $row)
                                <tr class="{{ $loop->even ? 'bg-gray-50' : 'bg-white' }}">
                                    <td class="text-center">{{ $index + 1 }}</td>
                                    <td>{{ $row->supplier_name }}</td>
                                    <td class="text-right">{{ number_format($row->total_quantity, 2) }}</td>
                                    <td class="text-right">{{ number_format($row->total_amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="font-bold bg-gray-200">
                                <td colspan="2" class="text-right">Grand Total</td>
                                <td class="text-right">{{ number_format($grandTotalQuantity, 2) }}</td>
                                <td class="text-right">{{ number_format($grandTotalAmount, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
