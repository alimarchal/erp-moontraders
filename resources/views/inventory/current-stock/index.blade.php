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
        {{-- All products encoded for dynamic supplier→product filtering --}}
        <div id="all-products-data" class="hidden"
            data-products="{{ json_encode($products->map(fn($p) => ['id' => $p->id, 'code' => trim($p->product_code), 'name' => $p->product_name, 'supplier_id' => $p->supplier_id])->values()) }}">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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
                <x-label for="filter_product_id" value="Product" />
                <select id="filter_product_id" name="filter[product_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full select2">
                    <option value="">All Products</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}" data-supplier="{{ $product->supplier_id }}" {{ request('filter.product_id') == $product->id ? 'selected' : '' }}>
                            {{ trim($product->product_code) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_search" value="Search Product" />
                <x-input id="filter_search" name="filter[search]" type="text" class="mt-1 block w-full"
                    :value="request('filter.search')" placeholder="Name, code, or barcode..." />
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

            <div>
                <x-label for="filter_has_reserved" value="Reserved Stock" />
                <select id="filter_has_reserved" name="filter[has_reserved]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    <option value="1" {{ request('filter.has_reserved') == '1' ? 'selected' : '' }}>Has Reserved Qty
                    </option>
                </select>
            </div>

            <div>
                <x-label for="filter_min_value" value="Min Total Value (Rs.)" />
                <x-input id="filter_min_value" name="filter[min_value]" type="number" min="0" step="0.01"
                    class="mt-1 block w-full" :value="request('filter.min_value')" placeholder="e.g. 10000" />
            </div>

            <div>
                <x-label for="filter_max_value" value="Max Total Value (Rs.)" />
                <x-input id="filter_max_value" name="filter[max_value]" type="number" min="0" step="0.01"
                    class="mt-1 block w-full" :value="request('filter.max_value')" placeholder="e.g. 500000" />
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
                    <option value="-average_cost" {{ request('sort') == '-average_cost' ? 'selected' : '' }}>IP
                        (High-Low)</option>
                    <option value="average_cost" {{ request('sort') == 'average_cost' ? 'selected' : '' }}>IP
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

            <div>
                <x-label for="per_page" value="Per Page" />
                <select id="per_page" name="per_page"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    @foreach([50, 100, 200, 500, 1000, 'all'] as $size)
                        <option value="{{ $size }}" {{ (string) $perPage === (string) $size || ($size === 'all' && $perPage >= 9999) ? 'selected' : '' }}>
                            {{ $size === 'all' ? 'All' : $size }}
                        </option>
                    @endforeach
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
                            <th class="text-center font-bold">#</th>
                            <th class="text-left font-bold px-2 whitespace-nowrap">Product</th>
                            <th class="text-left font-bold px-2 whitespace-nowrap">Warehouse</th>
                            <th class="text-center font-bold" title="Quantity On Hand">Qty On Hand</th>
                            <th class="text-center font-bold" title="Quantity Available">Qty Available</th>
                            <th class="text-center font-bold" title="Total Value">Total Value</th>
                            <th class="text-center font-bold" title="Batches">Batches</th>
                            <th class="text-center font-bold no-print" title="Actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if(!$hasFilter)
                            <tr>
                                <td colspan="8" class="text-center py-8 text-gray-500">
                                    Apply filters above to view inventory.
                                </td>
                            </tr>
                        @elseif($stocks->isEmpty())
                            <tr>
                                <td colspan="8" class="text-center py-4">No stock found.</td>
                            </tr>
                        @else
                            @foreach ($stocks as $stock)
                                @php
                                    $batches = $stock->relationLoaded('batches') ? $stock->batches : collect();
                                    $batchCount = $batches->count();
                                @endphp
                                <tr>
                                    <td class="text-center">
                                        {{ $loop->iteration + ($stocks->currentPage() - 1) * $stocks->perPage() }}
                                    </td>
                                    <td class="text-left font-semibold px-2 whitespace-nowrap">
                                        {{ trim($stock->product->product_code) }} - {{ $stock->product->product_name }}
                                    </td>
                                    <td class="text-left px-2 whitespace-nowrap">{{ $stock->warehouse->warehouse_name }}</td>
                                    <td class="text-center font-semibold">
                                        {{ rtrim(rtrim(number_format($stock->quantity_on_hand, 2), '0'), '.') }}
                                    </td>
                                    <td class="text-center">
                                        {{ rtrim(rtrim(number_format($stock->quantity_available, 2), '0'), '.') }}
                                    </td>
                                    @php
                                        $batchesJson = $batches->map(fn($b) => [
                                            'batch_code' => $b->stockBatch?->batch_code ?? '—',
                                            'receipt_date' => $b->stockBatch?->receipt_date ? \Carbon\Carbon::parse($b->stockBatch->receipt_date)->format('d-M-Y') : '—',
                                            'quantity' => rtrim(rtrim(number_format($b->quantity_on_hand, 2), '0'), '.'),
                                            'unit_cost' => $b->quantity_on_hand > 0 ? rtrim(rtrim(number_format($b->total_value / $b->quantity_on_hand, 6), '0'), '.') : rtrim(rtrim(number_format($b->unit_cost, 6), '0'), '.'),
                                            'selling_price' => $b->stockBatch?->selling_price ? number_format($b->stockBatch->selling_price, 2) : null,
                                            'total_value' => number_format($b->total_value, 2),
                                            'is_promotional' => $b->is_promotional,
                                            'promotional_price' => $b->promotional_price ? number_format($b->promotional_price, 2) : null,
                                            'status' => $b->status,
                                            'priority_order' => $b->priority_order,
                                        ])->values()->toArray();
                                        $productLabel = trim($stock->product->product_code) . ' - ' . $stock->product->product_name;
                                    @endphp

                                    {{-- Total Value — clickable if batches loaded --}}
                                    <td class="text-center font-semibold">
                                        @if($batchCount > 0)
                                            <a href="#"
                                                class="batch-cost-link text-blue-700 hover:text-blue-900 hover:underline font-semibold"
                                                data-product="{{ $productLabel }}"
                                                data-warehouse="{{ $stock->warehouse->warehouse_name }}"
                                                data-batches="{{ json_encode($batchesJson) }}">
                                                {{ number_format($stock->total_value, 2) }}
                                            </a>
                                        @else
                                            ₨ {{ number_format($stock->total_value, 2) }}
                                        @endif
                                    </td>
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
                            @endforeach
                        @endif
                    </tbody>
                    @if($hasFilter)
                        <tfoot>
                            <tr class="bg-gray-100 font-bold border-t-2 border-black">
                                <td colspan="3" class="text-right px-2">Grand Total:</td>
                                <td class="text-center">
                                    {{ rtrim(rtrim(number_format($stocks->sum('quantity_on_hand'), 2), '0'), '.') }}
                                </td>
                                <td class="text-center">
                                    {{ rtrim(rtrim(number_format($stocks->sum('quantity_available'), 2), '0'), '.') }}
                                </td>
                                <td class="text-center">₨ {{ number_format($stocks->sum('total_value'), 2) }}</td>
                                <td class="text-center">{{ $stocks->sum('total_batches') }}</td>
                                <td class="no-print"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>

                @if($stocks->hasPages())
                    <div class="mt-4 no-print">
                        {{ $stocks->withQueryString()->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Batch Cost Modal --}}
    <div id="batchCostModal" class="fixed inset-0 z-50 hidden no-print" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-black/20 backdrop-blur-sm" id="batchCostModalOverlay"></div>
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-lg shadow-2xl w-full max-w-3xl max-h-[85vh] flex flex-col">
                <div class="flex items-center justify-between px-5 py-3 border-b">
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">Batch-wise Cost</p>
                        <h3 class="text-base font-bold text-gray-800" id="batchCostModalTitle"></h3>
                    </div>
                    <button id="closeBatchCostModal"
                        class="text-gray-400 hover:text-gray-700 transition-colors rounded-full p-1 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="overflow-auto p-4 flex-1" id="batchCostModalBody"></div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                // ── Batch cost modal ──────────────────────────────────────────
                const modal = document.getElementById('batchCostModal');
                const modalBody = document.getElementById('batchCostModalBody');
                const modalTitle = document.getElementById('batchCostModalTitle');
                const closeBtn = document.getElementById('closeBatchCostModal');
                const overlay = document.getElementById('batchCostModalOverlay');

                function parseNum(str) {
                    return parseFloat(String(str).replace(/,/g, '')) || 0;
                }

                function fmt(n) {
                    return n.toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }

                function openModal(product, warehouse, batches) {
                    modalTitle.textContent = product + '  |  ' + warehouse;

                    const td = 'border:1px solid #d1d5db;padding:4px 8px;';
                    const tdC = td + 'text-align:center;';
                    const th = 'border:1px solid #d1d5db;padding:5px 8px;background:#f3f4f6;';
                    const thC = th + 'text-align:center;';

                    let html = '<table style="width:100%;border-collapse:collapse;font-size:13px;">';
                    html += '<thead><tr>';
                    html += '<th style="' + thC + '">#</th>';
                    html += '<th style="' + th + 'text-align:left;">Batch</th>';
                    html += '<th style="' + thC + '">Receipt Date</th>';
                    html += '<th style="' + thC + '">Qty</th>';
                    html += '<th style="' + thC + '">Unit Cost (GRN)</th>';
                    html += '<th style="' + thC + '">Selling Price</th>';
                    html += '<th style="' + thC + '">Total Value</th>';
                    html += '<th style="' + thC + '">Status</th>';
                    html += '</tr></thead><tbody>';

                    let totalQty = 0, totalValue = 0;

                    batches.forEach(function (b, i) {
                        totalQty += parseNum(b.quantity);
                        totalValue += parseNum(b.total_value);

                        const promo = b.is_promotional
                            ? ' <span style="color:#ea580c;font-size:11px;font-weight:600;">Promo</span>'
                            + (b.promotional_price ? ' <span style="color:#ea580c;font-size:11px;">(₨' + b.promotional_price + ')</span>' : '')
                            : '';
                        const statusColor = b.status === 'active' ? '#16a34a' : '#6b7280';
                        const sellingPrice = b.selling_price ? '₨' + b.selling_price : '<span style="color:#9ca3af;">—</span>';
                        const rowBg = i % 2 === 0 ? '#fff' : '#f9fafb';

                        html += '<tr style="background:' + rowBg + ';">';
                        html += '<td style="' + tdC + '">' + (i + 1) + '</td>';
                        html += '<td style="' + td + 'font-weight:600;">' + b.batch_code + promo + '</td>';
                        html += '<td style="' + tdC + '">' + b.receipt_date + '</td>';
                        html += '<td style="' + tdC + 'font-weight:600;">' + b.quantity + '</td>';
                        html += '<td style="' + tdC + '">₨' + b.unit_cost + '</td>';
                        html += '<td style="' + tdC + '">' + sellingPrice + '</td>';
                        html += '<td style="' + tdC + 'font-weight:700;color:#059669;">₨' + b.total_value + '</td>';
                        html += '<td style="' + tdC + 'color:' + statusColor + ';font-size:12px;font-weight:600;">' + b.status.charAt(0).toUpperCase() + b.status.slice(1) + '</td>';
                        html += '</tr>';
                    });

                    html += '</tbody>';
                    html += '<tfoot><tr style="background:#f3f4f6;font-weight:700;border-top:2px solid #000;">';
                    html += '<td colspan="3" style="' + td + 'text-align:right;">Grand Total</td>';
                    html += '<td style="' + tdC + '">' + fmt(totalQty) + '</td>';
                    html += '<td style="' + tdC + '">—</td>';
                    html += '<td style="' + tdC + '">—</td>';
                    html += '<td style="' + tdC + 'color:#059669;">₨' + fmt(totalValue) + '</td>';
                    html += '<td style="' + tdC + '"></td>';
                    html += '</tr></tfoot></table>';

                    modalBody.innerHTML = html;
                    modal.classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                }

                function closeModal() {
                    modal.classList.add('hidden');
                    document.body.style.overflow = '';
                }

                document.addEventListener('click', function (e) {
                    const link = e.target.closest('.batch-cost-link');
                    if (link) {
                        e.preventDefault();
                        openModal(link.dataset.product, link.dataset.warehouse, JSON.parse(link.dataset.batches));
                    }
                });

                closeBtn.addEventListener('click', closeModal);
                overlay.addEventListener('click', closeModal);
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') { closeModal(); }
                });

                // ── Supplier → Product dynamic filter ────────────────────────
                $(document).ready(function () {
                    const $supplierSelect = $('#filter_supplier_id');
                    const $productSelect = $('#filter_product_id');
                    const allProducts = JSON.parse(
                        document.getElementById('all-products-data').dataset.products
                    );

                    function rebuildProducts(supplierId) {
                        const selected = $productSelect.val();
                        $productSelect.select2('destroy');
                        $productSelect.empty().append('<option value="">All Products</option>');

                        allProducts.forEach(function (p) {
                            if (!supplierId || String(p.supplier_id) === String(supplierId)) {
                                const opt = new Option(p.code, p.id, false, String(p.id) === String(selected));
                                $productSelect.append(opt);
                            }
                        });

                        $productSelect.select2({ placeholder: 'Select an option', allowClear: true, width: '100%' });
                    }

                    $supplierSelect.on('change', function () {
                        rebuildProducts($(this).val());
                    });

                    // On page load: if supplier is already selected, filter products
                    const initialSupplier = $supplierSelect.val();
                    if (initialSupplier) {
                        rebuildProducts(initialSupplier);
                    }
                });
            }());
        </script>
    @endpush
</x-app-layout>