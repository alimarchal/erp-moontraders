<x-app-layout>
    <x-slot name="header">
        <x-page-header title="SKU & Rates Report" :showSearch="true" :showRefresh="true" backRoute="reports.index" />
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
            }
        </style>
    @endpush

    <x-filter-section :action="route('reports.sku-rates.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_product_name" value="Product Name" />
                <x-input id="filter_product_name" name="filter[product_name]" type="text" class="mt-1 block w-full"
                    :value="request('filter.product_name')" placeholder="Search by name" />
            </div>

            <div>
                <x-label for="filter_product_code" value="SKU / Product Code" />
                <x-input id="filter_product_code" name="filter[product_code]" type="text"
                    class="mt-1 block w-full uppercase" :value="request('filter.product_code')"
                    placeholder="PROD-001" />
            </div>

            <div>
                <x-label for="filter_supplier_id" value="Supplier" />
                <select id="filter_supplier_id" name="filter[supplier_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Suppliers</option>
                    @foreach ($supplierOptions as $supplier)
                        <option value="{{ $supplier->id }}" {{ request('filter.supplier_id') === (string) $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->supplier_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_brand" value="Brand" />
                <x-input id="filter_brand" name="filter[brand]" type="text" class="mt-1 block w-full"
                    :value="request('filter.brand')" placeholder="Search brand" />
            </div>

            <div>
                <x-label for="filter_barcode" value="Barcode" />
                <x-input id="filter_barcode" name="filter[barcode]" type="text" class="mt-1 block w-full"
                    :value="request('filter.barcode')" placeholder="Search barcode" />
            </div>

            <div>
                <x-label for="filter_pack_size" value="Pack Size" />
                <x-input id="filter_pack_size" name="filter[pack_size]" type="text" class="mt-1 block w-full"
                    :value="request('filter.pack_size')" placeholder="Search pack size" />
            </div>

            <div>
                <x-label for="filter_uom_id" value="Unit of Measure" />
                <select id="filter_uom_id" name="filter[uom_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All UoMs</option>
                    @foreach ($uomOptions as $uom)
                        <option value="{{ $uom->id }}" {{ request('filter.uom_id') === (string) $uom->id ? 'selected' : '' }}>
                            {{ $uom->uom_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_valuation_method" value="Valuation Method" />
                <select id="filter_valuation_method" name="filter[valuation_method]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Methods</option>
                    @foreach ($valuationMethods as $method)
                        <option value="{{ $method }}" {{ request('filter.valuation_method') === $method ? 'selected' : '' }}>
                            {{ $method }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_is_active" value="Status" />
                <select id="filter_is_active" name="filter[is_active]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" {{ request('filter.is_active') === (string) $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="per_page" value="Records Per Page" />
                <select id="per_page" name="per_page"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    @foreach ($perPageOptions as $option)
                        <option value="{{ $option }}" {{ $currentPerPage === $option ? 'selected' : '' }}>
                            {{ number_format($option) }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 mt-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                <p class="text-center font-extrabold mb-2">
                    Moon Traders<br>
                    SKU & Rates Report<br>
                    Total Records: {{ number_format($products->total()) }}
                    <br>
                    <span class="print-only print-info text-xs text-center">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                <table class="report-table">
                    <thead>
                        <tr class="bg-gray-50">
                            <th style="width: 40px;">Sr#</th>
                            <th style="width: 180px;">Supplier Name</th>
                            <th style="width: 120px;">SKU</th>
                            <th style="width: 80px;">Units</th>
                            <th style="width: 100px;">Invoice Price</th>
                            <th style="width: 100px;">Retail Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $groupedProducts = $products->groupBy(fn($p) => $p->supplier_id ?? 0);
                        @endphp
                        @forelse ($groupedProducts as $supplierId => $supplierProducts)
                            @foreach ($supplierProducts as $idx => $product)
                                <tr>
                                    <td class="text-center" style="vertical-align: middle;">
                                        {{ $products->firstItem() + $products->search(fn($p) => $p->id === $product->id) }}</td>
                                    @if ($idx === 0)
                                        <td rowspan="{{ $supplierProducts->count() }}" style="vertical-align: middle; font-weight: 600; background-color: #f9fafb;">
                                            {{ $product->supplier?->supplier_name ?? '-' }}
                                        </td>
                                    @endif
                                    <td style="vertical-align: middle;">{{ $product->product_code }}</td>
                                    <td class="text-center" style="vertical-align: middle;">
                                        {{ number_format($product->uom_conversion_factor, 3) }}</td>
                                    <td class="text-right" style="vertical-align: middle;">
                                        {{ number_format($product->cost_price, 2) }}</td>
                                    <td class="text-right" style="vertical-align: middle;">
                                        {{ number_format($product->unit_sell_price, 2) }}</td>
                                </tr>
                            @endforeach
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-gray-500">No records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                @if ($products->hasPages())
                    <div class="mt-4 no-print">
                        {{ $products->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>