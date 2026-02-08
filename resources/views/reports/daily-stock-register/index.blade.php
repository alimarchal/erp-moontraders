<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Daily Stock Register" :showSearch="true" :showRefresh="true" backRoute="reports.index" />
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
                    size: landscape;

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

    <x-filter-section :action="route('reports.daily-stock-register.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="date" value="Date" />
                <x-input id="date" name="date" type="date" class="mt-1 block w-full" :value="$date" />
            </div>

            <div class="relative">
                <div class="flex justify-between items-center">
                    <x-label for="supplier_id" value="Supplier" />
                    @if($selectedSupplierId)
                        <a href="{{ route('reports.daily-stock-register.index', array_merge(request()->except(['supplier_id', 'product_id', 'page']), ['supplier_id' => ''])) }}"
                            class="text-xs text-red-600 hover:text-red-800 underline">
                            Clear
                        </a>
                    @endif
                </div>
                <select id="supplier_id" name="supplier_id"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Suppliers</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ $selectedSupplierId == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->supplier_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="relative">
                <div class="flex justify-between items-center">
                    <x-label for="product_id" value="Product" />
                    @if($selectedProductId)
                        <a href="{{ route('reports.daily-stock-register.index', array_merge(request()->except(['product_id', 'page']), ['product_id' => ''])) }}"
                            class="text-xs text-red-600 hover:text-red-800 underline">
                            Clear
                        </a>
                    @endif
                </div>
                <select id="product_id" name="product_id"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Products</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}" {{ $selectedProductId == $product->id ? 'selected' : '' }}>
                            {{ $product->product_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end">
                <x-button class="w-full justify-center">Filter</x-button>
            </div>
        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 mt-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                <p class="text-center font-extrabold mb-2">
                    Moon Traders<br>
                    Daily Stock Register<br>
                    Date: {{ \Carbon\Carbon::parse($date)->format('d-M-Y') }}
                </p>

                <table class="report-table">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="w-10 text-center font-bold">#</th>
                            <th class="text-left font-bold px-2 whitespace-nowrap">SKU</th>
                            <th class="w-20 text-center font-bold" title="Opening Stock">Opening</th>
                            <th class="w-20 text-center font-bold" title="New Purchases">Purchase</th>
                            <th class="w-20 text-center font-bold" title="Brought Forward">BF</th>
                            <th class="w-20 text-center font-bold" title="Issued to Vans">Issue</th>
                            <th class="w-20 text-center font-bold bg-green-50" title="Total Issue (BF + Issue)">Total
                                Issue</th>
                            <th class="w-20 text-center font-bold" title="Returns">Return</th>
                            <th class="w-20 text-center font-bold" title="Sales">Sales</th>
                            <th class="w-20 text-center font-bold" title="Shortage">Shortage</th>
                            <th class="w-20 text-center font-bold" title="In Hand Stock (System Total: WH + Van)">In
                                Hand</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reportData as $row)
                            <tr>
                                <td class="text-center">{{ $loop->iteration }}</td>
                                <td class="text-left font-semibold px-2 whitespace-nowrap">{{ $row->sku }}</td>
                                <td class="text-center">{{ rtrim(rtrim(number_format($row->opening, 2), '0'), '.') }}</td>
                                <td class="text-center">{{ rtrim(rtrim(number_format($row->purchase, 2), '0'), '.') }}</td>
                                <td class="text-center">{{ rtrim(rtrim(number_format($row->bf, 2), '0'), '.') }}</td>
                                <td class="text-center">{{ rtrim(rtrim(number_format($row->issue, 2), '0'), '.') }}</td>
                                <td class="text-center bg-green-50 font-semibold">
                                    {{ rtrim(rtrim(number_format($row->total_issue, 2), '0'), '.') }}</td>
                                <td class="text-center">{{ rtrim(rtrim(number_format($row->return, 2), '0'), '.') }}</td>
                                <td class="text-center">{{ rtrim(rtrim(number_format($row->sale, 2), '0'), '.') }}</td>
                                <td class="text-center">{{ rtrim(rtrim(number_format($row->shortage, 2), '0'), '.') }}</td>
                                <td class="text-center bg-gray-100 font-bold">
                                    {{ rtrim(rtrim(number_format($row->in_hand, 2), '0'), '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center py-4">No data found based on filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-100 font-bold border-t-2 border-black">
                            <td colspan="2" class="text-right px-2">Grand Total:</td>
                            <td class="text-center">
                                {{ rtrim(rtrim(number_format($reportData->sum('opening'), 2), '0'), '.') }}</td>
                            <td class="text-center">
                                {{ rtrim(rtrim(number_format($reportData->sum('purchase'), 2), '0'), '.') }}</td>
                            <td class="text-center">
                                {{ rtrim(rtrim(number_format($reportData->sum('bf'), 2), '0'), '.') }}</td>
                            <td class="text-center">
                                {{ rtrim(rtrim(number_format($reportData->sum('issue'), 2), '0'), '.') }}</td>
                            <td class="text-center">
                                {{ rtrim(rtrim(number_format($reportData->sum('total_issue'), 2), '0'), '.') }}</td>
                            <td class="text-center">
                                {{ rtrim(rtrim(number_format($reportData->sum('return'), 2), '0'), '.') }}</td>
                            <td class="text-center">
                                {{ rtrim(rtrim(number_format($reportData->sum('sale'), 2), '0'), '.') }}</td>
                            <td class="text-center">
                                {{ rtrim(rtrim(number_format($reportData->sum('shortage'), 2), '0'), '.') }}</td>
                            <td class="text-center">
                                {{ rtrim(rtrim(number_format($reportData->sum('in_hand'), 2), '0'), '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>