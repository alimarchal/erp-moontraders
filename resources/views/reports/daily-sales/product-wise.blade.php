{{--
    Product Wise Sales Report. Same toolbar, filter panel and table as the expense vouchers list:
    filters hidden behind a Filters button, a Columns dropdown, PDF (portrait / landscape), Excel and Print.
--}}
@php
    $filterKeys = ['employee_id', 'vehicle_id', 'warehouse_id', 'supplier_id', 'category_id', 'product_id', 'search'];
    $activeFilterCount = collect($filterKeys)->filter(fn ($k) => filled(request($k)))->count();
    $today = now()->toDateString();
    $isCustomPeriod = $startDate !== $today || $endDate !== $today;
    $filterCount = $activeFilterCount + ($isCustomPeriod ? 1 : 0);
    $periodLabel = $startDate === $endDate
        ? \Carbon\Carbon::parse($startDate)->format('d M Y')
        : \Carbon\Carbon::parse($startDate)->format('d M Y').' – '.\Carbon\Carbon::parse($endDate)->format('d M Y');
    $ranges = [
        'Today' => [$today, $today],
        'Yesterday' => [now()->subDay()->toDateString(), now()->subDay()->toDateString()],
        'This week' => [now()->startOfWeek()->toDateString(), $today],
        'This month' => [now()->startOfMonth()->toDateString(), $today],
        'Last 30 days' => [now()->subDays(29)->toDateString(), $today],
    ];
    $rangeUrl = fn (string $from, string $to) => request()->fullUrlWithQuery(['start_date' => $from, 'end_date' => $to]);
    $np = fn ($profit, $sales) => $sales > 0 ? number_format($profit / $sales * 100, 2).'%' : '0.00%';
    $exportQuery = collect(request()->except(['columns', 'orientation', 'page']))->toArray();
    $exportQuery += ['start_date' => $startDate, 'end_date' => $endDate];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('dashboard') }}">Dashboard</a><span aria-hidden="true">›</span><a href="{{ route('reports.index') }}">Reports</a><span aria-hidden="true">›</span><span>Product Wise Sales</span>
                </nav>
                <h1 class="ak-title">Product Wise Sales Report</h1>
                <p class="ak-sub">Posted sales settlements grouped by product &middot; {{ $periodLabel }}</p>
            </div>
            <div class="ak-head-actions" x-data="{ cols: @js($visibleColumns), colsOpen: false, pdfOpen: false,
                    query(extra) { const p = new URLSearchParams(@js($exportQuery)); p.set('columns', this.cols.join(',')); Object.entries(extra || {}).forEach(([k, v]) => p.set(k, v)); return p.toString(); },
                    url(base, extra) { return base + '?' + this.query(extra); },
                    sync() { const u = new URL(window.location); u.searchParams.set('columns', this.cols.join(',')); history.replaceState(null, '', u); window.dispatchEvent(new CustomEvent('pw-columns', { detail: this.cols })); } }"
                 x-init="$nextTick(() => window.dispatchEvent(new CustomEvent('pw-columns', { detail: cols })))" @click.outside="colsOpen = false; pdfOpen = false">
                <button type="button" class="ak-btn ak-btn-outline" x-data @click="$dispatch('pw-toggle-filters')" aria-controls="pw-filter-panel" title="Show or hide the filters">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" /></svg>
                    Filters @if($filterCount)<span class="ak-count ak-count-dark">{{ $filterCount }}</span>@endif
                </button>

                <div class="pw-menu">
                    <button type="button" class="ak-btn ak-btn-outline" @click="colsOpen = !colsOpen; pdfOpen = false" :aria-expanded="colsOpen" aria-haspopup="true">Columns</button>
                    <div class="pw-menu-panel" x-show="colsOpen" x-cloak x-transition>
                        <div class="pw-menu-title">Show columns</div>
                        @foreach($optionalColumns as $key => $label)
                            <label class="pw-check"><input type="checkbox" value="{{ $key }}" x-model="cols" @change="sync()"> {{ $label }}</label>
                        @endforeach
                    </div>
                </div>

                <div class="pw-menu">
                    <button type="button" class="ak-btn ak-btn-outline" @click="pdfOpen = !pdfOpen; colsOpen = false" :aria-expanded="pdfOpen" aria-haspopup="true">PDF ▾</button>
                    <div class="pw-menu-panel" x-show="pdfOpen" x-cloak x-transition>
                        <a :href="url('{{ route('reports.daily-sales.product-wise.pdf') }}', { orientation: 'portrait' })" class="pw-menu-link">Portrait (A4)</a>
                        <a :href="url('{{ route('reports.daily-sales.product-wise.pdf') }}', { orientation: 'landscape' })" class="pw-menu-link">Landscape (A4)</a>
                    </div>
                </div>
                <a :href="url('{{ route('reports.daily-sales.product-wise.excel') }}')" class="ak-btn ak-btn-outline">Excel</a>
                <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')
    @include('settings.partials.select2-init')
    @include('expense-vouchers._form-styles')
    <style>
        @page { margin: 10mm; }
        .pw-menu { position: relative; display: inline-block; }
        .pw-menu-panel { position: absolute; right: 0; top: calc(100% + 6px); min-width: 180px; z-index: 30; background: #fff; border: 1px solid var(--ak-border); border-radius: 10px; box-shadow: 0 10px 24px rgba(15, 23, 42, .12); padding: 8px; }
        .pw-menu-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--ak-muted); padding: 4px 8px; }
        .pw-check { display: flex; align-items: center; gap: 8px; padding: 6px 8px; font-size: 14px; cursor: pointer; border-radius: 6px; }
        .pw-check:hover, .pw-menu-link:hover { background: var(--ak-soft); }
        .pw-menu-link { display: block; padding: 8px 10px; font-size: 14px; color: var(--ak-text); text-decoration: none; border-radius: 6px; white-space: nowrap; }
        .pw-ranges { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 12px; }
        .pw-ranges a { padding: 4px 10px; border: 1px solid var(--ak-border); border-radius: 999px; font-size: 12px; font-weight: 600; color: var(--ak-text); text-decoration: none; background: #fff; }
        .pw-ranges a:hover { background: var(--ak-soft); }
        .pw-ranges a[aria-current] { background: var(--ak-navy); border-color: var(--ak-navy); color: #fff; }
        .pw-grid { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); align-items: end; }
        .pw-grid .ak-field input, .pw-grid .ak-field select { height: 38px; box-sizing: border-box; }
        .pw-table { min-width: 640px; }
        .pw-table tfoot td { font-weight: 700; background: #f8fafc; border-top: 2px solid var(--ak-border); }
        .pw-neg { color: #b91c1c; }
        .ak-filters .select2-container { width: 100% !important; display: block; }
        .ak-filters .select2-container .select2-selection--single { height: 38px; margin-top: 0; padding: 0 30px 0 12px; display: flex; align-items: center; border: 1px solid var(--ak-border); border-radius: 8px; box-shadow: none; font-size: 14px; }
        .ak-filters .select2-container .select2-selection__arrow { top: 50% !important; transform: translateY(-50%); height: 24px !important; }
        .select2-dropdown { border-color: var(--ak-border); border-radius: 8px; font-size: 14px; overflow: hidden; }
        @media print {
            .ak-filters, .pw-ranges, .ak-head-actions, .ak-crumbs { display: none !important; }
            .pw-table { min-width: 0 !important; }
            .pw-table tfoot td { position: static; }
        }
    </style>

    <div class="ak-page" x-data="{ cols: @js($visibleColumns) }" @pw-columns.window="cols = $event.detail">
        <div class="ak-print-head">
            <div class="ak-print-bank">{{ config('app.name') }}</div>
            <div class="ak-print-title">Product Wise Sales Report &middot; {{ $periodLabel }}</div>
            @php
                $printLines = collect([
                    $employeeId ? 'Salesman: '.($employees->firstWhere('id', $employeeId)->name ?? '') : null,
                    $vehicleId ? 'Vehicle: '.($vehicles->firstWhere('id', $vehicleId)->vehicle_number ?? '') : null,
                    $warehouseId ? 'Warehouse: '.($warehouses->firstWhere('id', $warehouseId)->warehouse_name ?? '') : null,
                    $supplierId ? 'Supplier: '.($suppliers->firstWhere('id', $supplierId)->supplier_name ?? '') : null,
                    $categoryId ? 'Category: '.($categories->firstWhere('id', $categoryId)->name ?? '') : null,
                    $productId ? 'Product: '.($products->firstWhere('id', $productId)->product_name ?? '') : null,
                    $search !== '' ? 'Product contains: '.$search : null,
                ])->filter();
            @endphp
            @if($printLines->isNotEmpty())<div style="text-align:center; font-size:10px">{{ $printLines->implode('  |  ') }}</div>@endif
            <div style="text-align:center; font-size:9px; margin-bottom:6px">Printed by {{ auth()->user()->name }} &middot; {{ now()->format('d-M-Y h:i A') }}</div>
        </div>

        <section id="pw-filter-panel" class="ak-card" aria-label="Filters" x-data="{ open: {{ $filterCount ? 'true' : 'false' }} }" @pw-toggle-filters.window="open = ! open" x-show="open" x-cloak x-transition style="{{ $filterCount ? '' : 'display:none' }}">
            <form method="GET" action="{{ route('reports.daily-sales.product-wise') }}" class="ak-filters" style="padding:14px 16px"
                  x-data @submit="const c = new URLSearchParams(window.location.search).get('columns'); if (c !== null) { const i = document.createElement('input'); i.type = 'hidden'; i.name = 'columns'; i.value = c; $el.appendChild(i); }">
                <div class="pw-grid">
                    <div class="ak-field"><label for="f_start">From</label><input id="f_start" type="date" name="start_date" value="{{ $startDate }}"></div>
                    <div class="ak-field"><label for="f_end">To</label><input id="f_end" type="date" name="end_date" value="{{ $endDate }}"></div>
                    <div class="ak-field"><label for="f_supplier">Supplier</label>
                        <select class="js-select2" id="f_supplier" name="supplier_id"><option value="">All suppliers</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected((string) $supplierId === (string) $supplier->id)>{{ $supplier->supplier_name }}</option>@endforeach</select></div>
                    <div class="ak-field"><label for="f_employee">Salesman</label>
                        <select class="js-select2" id="f_employee" name="employee_id"><option value="">All salesmen</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((string) $employeeId === (string) $employee->id)>{{ $employee->name }}</option>@endforeach</select></div>
                    <div class="ak-field"><label for="f_vehicle">Vehicle</label>
                        <select class="js-select2" id="f_vehicle" name="vehicle_id"><option value="">All vehicles</option>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}" @selected((string) $vehicleId === (string) $vehicle->id)>{{ $vehicle->vehicle_number }}</option>@endforeach</select></div>
                    <div class="ak-field"><label for="f_warehouse">Warehouse</label>
                        <select class="js-select2" id="f_warehouse" name="warehouse_id"><option value="">All warehouses</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((string) $warehouseId === (string) $warehouse->id)>{{ $warehouse->warehouse_name }}</option>@endforeach</select></div>
                    <div class="ak-field"><label for="f_category">Category</label>
                        <select class="js-select2" id="f_category" name="category_id"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string) $categoryId === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></div>
                    <div class="ak-field"><label for="f_product">Product</label>
                        <select class="js-select2" id="f_product" name="product_id"><option value="">All products</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected((string) $productId === (string) $product->id)>{{ $product->product_name }}</option>@endforeach</select></div>
                    <div class="ak-field"><label for="f_search">Product name contains</label><input id="f_search" type="search" name="search" value="{{ $search }}" placeholder="e.g. biscuit" autocomplete="off"></div>
                    <div class="ak-filter-buttons" style="display:flex; gap:8px"><button type="submit" class="ak-btn ak-btn-primary" style="height:38px">Apply</button>@if($filterCount)<a href="{{ route('reports.daily-sales.product-wise') }}" class="ak-btn ak-btn-outline" style="height:38px">Clear</a>@endif</div>
                </div>
                <div class="pw-ranges">
                    <span style="font-size:12px; font-weight:600; color:var(--ak-muted)">Quick range:</span>
                    @foreach($ranges as $label => [$from, $to])
                        <a href="{{ $rangeUrl($from, $to) }}" @if($startDate === $from && $endDate === $to) aria-current="true" @endif>{{ $label }}</a>
                    @endforeach
                </div>
            </form>
        </section>

        <section class="ak-card" style="overflow-x:auto">
            <table class="ak-dt pw-table">
                <thead><tr>
                    <th>Sr#</th><th>Product Name</th><th class="ak-num">Avg Price</th><th class="ak-num">Sold Qty</th>
                    <th class="ak-num" x-show="cols.includes('returned')">Rtn Qty</th>
                    <th class="ak-num" x-show="cols.includes('shortage')">Short Qty</th>
                    <th class="ak-num" x-show="cols.includes('net_sales')">Net Sales</th>
                    <th class="ak-num" x-show="cols.includes('net_profit')">Net Profit</th>
                    <th class="ak-num" x-show="cols.includes('np_percent')">NP %</th>
                </tr></thead>
                <tbody>
                    @forelse($productSales as $product)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td class="ak-strong">{{ $product->product_name }}</td>
                            <td class="ak-num">{{ number_format($product->avg_selling_price, 2) }}</td>
                            <td class="ak-num ak-strong">{{ number_format($product->total_sold, 0) }}</td>
                            <td class="ak-num" x-show="cols.includes('returned')">{{ number_format($product->total_returned, 0) }}</td>
                            <td class="ak-num" x-show="cols.includes('shortage')">{{ number_format($product->total_shortage, 0) }}</td>
                            <td class="ak-num ak-strong" x-show="cols.includes('net_sales')">{{ number_format($product->total_sales_value, 2) }}</td>
                            <td class="ak-num ak-strong {{ $product->net_profit < 0 ? 'pw-neg' : '' }}" x-show="cols.includes('net_profit')">{{ number_format($product->net_profit, 2) }}</td>
                            <td class="ak-num" x-show="cols.includes('np_percent')">{{ $np($product->net_profit, $product->total_sales_value) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" style="text-align:center; padding:28px; color:var(--ak-muted)">No posted sales for these filters.</td></tr>
                    @endforelse
                </tbody>
                @if($productSales->isNotEmpty())
                    <tfoot><tr>
                        <td colspan="3">Total ({{ $productSales->count() }} rows)</td>
                        <td class="ak-num">{{ number_format($totals['total_sold'], 0) }}</td>
                        <td class="ak-num" x-show="cols.includes('returned')">{{ number_format($totals['total_returned'], 0) }}</td>
                        <td class="ak-num" x-show="cols.includes('shortage')">{{ number_format($totals['total_shortage'], 0) }}</td>
                        <td class="ak-num" x-show="cols.includes('net_sales')">{{ number_format($totals['total_sales_value'], 2) }}</td>
                        <td class="ak-num" x-show="cols.includes('net_profit')">{{ number_format($totals['net_profit'], 2) }}</td>
                        <td class="ak-num" x-show="cols.includes('np_percent')">{{ $np($totals['net_profit'], $totals['total_sales_value']) }}</td>
                    </tr></tfoot>
                @endif
            </table>
        </section>
    </div>
</x-app-layout>
