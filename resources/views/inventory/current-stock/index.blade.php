<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Current Stock Inventory" :showSearch="true" :showRefresh="true" />
    </x-slot>

    @push('header')
        <style>
            .report-table {
                width: 100%;
                border-collapse: collapse;
                border: 1px solid black;
                font-size: 14px;
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
                    font-size: 11px !important;
                    width: 100% !important;
                }

                .report-table th,
                .report-table td {
                    padding: 2px 3px !important;
                    color: #000 !important;
                }
            }
        </style>
    @endpush

    <x-filter-section :action="route('inventory.current-stock.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_search" value="Search Product" />
                <x-input id="filter_search" name="filter[search]" type="text" class="mt-1 block w-full"
                    :value="request('filter.search')" placeholder="Name, code, or barcode..." />
            </div>

            <div>
                <x-label for="filter_product_id" value="Product" />
                <select id="filter_product_id" name="filter[product_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full select2">
                    <option value="">All Products</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}" {{ request('filter.product_id') == $product->id ? 'selected' : '' }}>
                            {{ $product->product_code }} - {{ $product->product_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_warehouse_id" value="Warehouse" />
                <select id="filter_warehouse_id" name="filter[warehouse_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full select2">
                    <option value="">All Warehouses</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" {{ request('filter.warehouse_id') == $warehouse->id ? 'selected' : '' }}>
                            {{ $warehouse->warehouse_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_category_id" value="Category" />
                <select id="filter_category_id" name="filter[category_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full select2">
                    <option value="">All Categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ request('filter.category_id') == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_supplier_id" value="Supplier" />
                <select id="filter_supplier_id" name="filter[supplier_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full select2">
                    <option value="">All Suppliers</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ request('filter.supplier_id') == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->supplier_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_has_promotional" value="Stock Type" />
                <select id="filter_has_promotional" name="filter[has_promotional]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Stock</option>
                    <option value="1" {{ request('filter.has_promotional') == '1' ? 'selected' : '' }}>Promotional Only
                    </option>
                </select>
            </div>

            <div>
                <x-label for="filter_has_priority" value="Priority Batches" />
                <select id="filter_has_priority" name="filter[has_priority]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    <option value="1" {{ request('filter.has_priority') == '1' ? 'selected' : '' }}>Has Priority Batches
                    </option>
                </select>
            </div>

            <div>
                <x-label for="filter_stock_level" value="Stock Level" />
                <select id="filter_stock_level" name="filter[stock_level]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Levels</option>
                    <option value="low" {{ request('filter.stock_level') == 'low' ? 'selected' : '' }}>Low (≤ 10)</option>
                    <option value="medium" {{ request('filter.stock_level') == 'medium' ? 'selected' : '' }}>Medium
                        (11-100)</option>
                    <option value="high" {{ request('filter.stock_level') == 'high' ? 'selected' : '' }}>High (> 100)
                    </option>
                    <option value="zero_available" {{ request('filter.stock_level') == 'zero_available' ? 'selected' : '' }}>Zero Available</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
            <div>
                <x-label for="sort" value="Sort By" />
                <select id="sort" name="sort"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="product_name" {{ request('sort') == 'product_name' || !request('sort') ? 'selected' : '' }}>Product Name (A-Z)</option>
                    <option value="-product_name" {{ request('sort') == '-product_name' ? 'selected' : '' }}>Product Name
                        (Z-A)</option>
                    <option value="-quantity_on_hand" {{ request('sort') == '-quantity_on_hand' ? 'selected' : '' }}>Qty
                        On Hand (High-Low)</option>
                    <option value="quantity_on_hand" {{ request('sort') == 'quantity_on_hand' ? 'selected' : '' }}>Qty On
                        Hand (Low-High)</option>
                    <option value="-quantity_available" {{ request('sort') == '-quantity_available' ? 'selected' : '' }}>
                        Qty Available (High-Low)</option>
                    <option value="quantity_available" {{ request('sort') == 'quantity_available' ? 'selected' : '' }}>Qty
                        Available (Low-High)</option>
                    <option value="-average_cost" {{ request('sort') == '-average_cost' ? 'selected' : '' }}>Avg Cost
                        (High-Low)</option>
                    <option value="average_cost" {{ request('sort') == 'average_cost' ? 'selected' : '' }}>Avg Cost
                        (Low-High)</option>
                    <option value="-total_value" {{ request('sort') == '-total_value' ? 'selected' : '' }}>Total Value
                        (High-Low)</option>
                    <option value="total_value" {{ request('sort') == 'total_value' ? 'selected' : '' }}>Total Value
                        (Low-High)</option>
                    <option value="-total_batches" {{ request('sort') == '-total_batches' ? 'selected' : '' }}>Batches
                        (High-Low)</option>
                    <option value="total_batches" {{ request('sort') == 'total_batches' ? 'selected' : '' }}>Batches
                        (Low-High)</option>
                </select>
            </div>
        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden shadow-xl  mb-4 mt-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                <table class="report-table tabular-nums">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="w-10 text-center font-bold">#</th>
                            <th class="text-left font-bold px-2 whitespace-nowrap">Product</th>
                            <th class="text-left font-bold px-2 whitespace-nowrap">Warehouse</th>
                            <th class="text-center font-bold" title="Quantity On Hand">Qty On Hand</th>
                            <th class="text-center font-bold" title="Quantity Available">Qty Available</th>
                            <th class="text-center font-bold" title="Average Cost">Avg Cost</th>
                            <th class="text-center font-bold" title="Total Value">Total Value</th>
                            <th class="text-center font-bold" title="Batches">Batches</th>
                            <th class="text-center font-bold no-print" title="Actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($stocks as $stock)
                            <tr>
                                <td class="text-center">
                                    {{ $loop->iteration + ($stocks->currentPage() - 1) * $stocks->perPage() }}</td>
                                <td class="text-left font-semibold px-2 whitespace-nowrap">
                                    {{ $stock->product->product_name }}</td>
                                <td class="text-left px-2 whitespace-nowrap">{{ $stock->warehouse->warehouse_name }}</td>
                                <td class="text-center font-semibold">
                                    {{ rtrim(rtrim(number_format($stock->quantity_on_hand, 2), '0'), '.') }}</td>
                                <td class="text-center">
                                    {{ rtrim(rtrim(number_format($stock->quantity_available, 2), '0'), '.') }}</td>
                                <td class="text-center">₨ {{ number_format($stock->average_cost, 2) }}</td>
                                <td class="text-center font-semibold">₨ {{ number_format($stock->total_value, 2) }}</td>
                                <td class="text-center">
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ $stock->total_batches }}
                                    </span>
                                    @if($stock->promotional_batches > 0)
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 ml-1">
                                            P:{{ $stock->promotional_batches }}
                                        </span>
                                    @endif
                                    @if($stock->priority_batches > 0)
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 ml-1">
                                            !:{{ $stock->priority_batches }}
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center no-print">
                                    <a href="{{ route('inventory.current-stock.by-batch', ['product_id' => $stock->product_id, 'warehouse_id' => $stock->warehouse_id]) }}"
                                        class="text-blue-600 hover:text-blue-900">
                                        View Batches
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4">No stock found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-100 font-bold border-t-2 border-black">
                            <td colspan="3" class="text-right px-2">Grand Total:</td>
                            <td class="text-center">
                                {{ rtrim(rtrim(number_format($stocks->sum('quantity_on_hand'), 2), '0'), '.') }}</td>
                            <td class="text-center">
                                {{ rtrim(rtrim(number_format($stocks->sum('quantity_available'), 2), '0'), '.') }}</td>
                            <td class="text-center">-</td>
                            <td class="text-center">₨ {{ number_format($stocks->sum('total_value'), 2) }}</td>
                            <td class="text-center">{{ $stocks->sum('total_batches') }}</td>
                            <td class="no-print"></td>
                        </tr>
                    </tfoot>
                </table>

                @if($stocks->hasPages())
                    <div class="mt-4 no-print">
                        {{ $stocks->withQueryString()->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>