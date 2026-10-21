{{--
    Settings -> Products list (/settings/products). Same layout as Settings -> Users:
    KPI cards, Active / Inactive tabs, one search box, removable filter chips and a
    dense sortable table. Query parameters (filter[...], sort, per_page) are unchanged.
--}}
@php
    $filters = array_filter((array) request('filter', []), fn ($v) => $v !== null && $v !== '');
    $sort = (string) request('sort', 'product_name');
    $tab = $filters['is_active'] ?? '';
    $chips = \Illuminate\Support\Arr::except($filters, 'is_active');

    $withFilters = fn (array $f) => request()->fullUrlWithQuery(['filter' => $f ?: null, 'page' => null]);
    $tabUrl = function (string $key) use ($filters, $withFilters) {
        $f = \Illuminate\Support\Arr::except($filters, 'is_active');
        if ($key !== '') {
            $f['is_active'] = $key;
        }

        return $withFilters($f);
    };
    $sortUrl = fn (string $field) => request()->fullUrlWithQuery(['sort' => $sort === $field ? '-'.$field : $field, 'page' => null]);
    $ariaSort = fn (string $field) => $sort === $field ? 'ascending' : ($sort === '-'.$field ? 'descending' : 'none');
    $sortMark = fn (string $field) => $sort === $field ? "\u{25B2}" : ($sort === '-'.$field ? "\u{25BC}" : '');

    $supplierNames = $supplierOptions->pluck('supplier_name', 'id');
    $categoryNames = $categoryOptions->pluck('name', 'id');
    $chipLabels = [
        'search' => 'Search', 'supplier_id' => 'Supplier', 'category_id' => 'Category', 'valuation_method' => 'Valuation',
        'is_powder' => 'Powder', 'brand' => 'Brand', 'barcode' => 'Barcode', 'product_name' => 'Name', 'product_code' => 'Code',
    ];
    $chipValue = fn (string $key, $value) => match ($key) {
        'supplier_id' => $supplierNames[$value] ?? $value,
        'category_id' => $categoryNames[$value] ?? $value,
        'is_powder' => $value === '1' ? 'Yes' : 'No',
        default => $value,
    };
    $advancedCount = collect(['brand', 'valuation_method', 'is_powder'])->filter(fn ($k) => isset($filters[$k]))->count();
    $sortNames = ['product_name' => 'Name', 'product_code' => 'Code', 'brand' => 'Brand', 'unit_sell_price' => 'Selling price', 'cost_price' => 'Cost price'];
    $sortField = ltrim($sort, '-');
    $authUser = auth()->user();
    $canDeactivate = $authUser->can('product-delete');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('settings.index') }}">Settings</a><span aria-hidden="true">›</span><span>Products</span>
                </nav>
                <h1 class="ak-title">Products</h1>
                <p class="ak-sub">SKUs you buy, stock and sell.</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('settings.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
                <a href="{{ route('products.export.excel', request()->query()) }}" class="ak-btn ak-btn-outline">Export Excel</a>
                @can('product-create')
                    <a href="{{ route('products.import.form') }}" class="ak-btn ak-btn-outline">Import Excel</a>
                    <a href="{{ route('products.create') }}" class="ak-btn ak-btn-primary"><span aria-hidden="true">＋</span> Add product</a>
                @endcan
            </div>
        </div>
    </x-slot>

    @include('settings.partials.ui-style')

    <div class="ak-page" x-data="productList()">
        <x-status-message />

        <section class="ak-kpis" aria-label="Product summary" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr))">
            <a href="{{ $tabUrl('') }}" class="ak-kpi{{ $tab === '' ? ' is-active' : '' }}">
                <span class="ak-kpi-icon ak-tone-navy" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.35M3.75 21V9.35m0 0a3 3 0 0 0 3.75-.62A3 3 0 0 0 12 9.35a3 3 0 0 0 4.5-.62 3 3 0 0 0 3.75.62M3.75 9.35 5.25 3h13.5l1.5 6.35" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Products</span>
                    <span class="ak-kpi-value">{{ number_format($stats['total']) }}</span>
                    <span class="ak-kpi-hint">{{ $chips ? 'matching the filters' : 'all SKUs' }}</span>
                </span>
            </a>
            <a href="{{ $tabUrl('1') }}" class="ak-kpi{{ $tab === '1' ? ' is-active' : '' }}">
                <span class="ak-kpi-icon ak-tone-green" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">Active</span>
                    <span class="ak-kpi-value">{{ number_format($stats['active']) }}</span>
                    <span class="ak-kpi-hint">{{ number_format($stats['inactive']) }} inactive</span>
                </span>
            </a>
        </section>

        <section class="ak-card" aria-label="Products">
            <div class="ak-tabs" role="tablist">
                @foreach (['' => ['All', $stats['total']], '1' => ['Active', $stats['active']], '0' => ['Inactive', $stats['inactive']]] as $key => [$label, $count])
                    <a href="{{ $tabUrl((string) $key) }}" role="tab" aria-selected="{{ $tab === (string) $key ? 'true' : 'false' }}" class="ak-tab {{ $tab === (string) $key ? 'is-active' : '' }}">
                        {{ $label }} <span class="ak-count">{{ number_format($count) }}</span>
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('products.index') }}" class="ak-filters"
                x-data="{ advanced: {{ $advancedCount ? 'true' : 'false' }}, busy: false }" @submit="busy = true">
                @if ($tab !== '') <input type="hidden" name="filter[is_active]" value="{{ $tab }}"> @endif
                @if (request('sort')) <input type="hidden" name="sort" value="{{ request('sort') }}"> @endif

                <div class="ak-filter-row ak-filter-row-users">
                    <div class="ak-field">
                        <label for="f_search">Search</label>
                        <div class="ak-search">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                            <input id="f_search" type="search" name="filter[search]" value="{{ $filters['search'] ?? '' }}" autocomplete="off"
                                placeholder="Name, code, brand or barcode" x-ref="search"
                                @keydown.window.slash="if (! ['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) { $event.preventDefault(); $refs.search.focus(); }">
                            <kbd aria-hidden="true">/</kbd>
                        </div>
                    </div>
                    <div class="ak-field">
                        <label for="f_supplier">Supplier</label>
                        <select id="f_supplier" name="filter[supplier_id]">
                            <option value="">All suppliers</option>
                            @foreach ($supplierOptions as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) ($filters['supplier_id'] ?? '') === (string) $supplier->id)>{{ $supplier->supplier_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_category">Category</label>
                        <select id="f_category" name="filter[category_id]">
                            <option value="">All categories</option>
                            @foreach ($categoryOptions as $category)
                                <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_per_page">Rows per page</label>
                        <select id="f_per_page" name="per_page" onchange="this.form.requestSubmit()">
                            @foreach ($perPageOptions as $n)
                                <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-filter-buttons">
                        <button type="button" class="ak-btn ak-btn-ghost" @click="advanced = !advanced" :aria-expanded="advanced" aria-controls="ak-advanced">
                            More filters @if ($advancedCount)<span class="ak-count ak-count-dark">{{ $advancedCount }}</span>@endif
                        </button>
                        <button type="submit" class="ak-btn ak-btn-primary" :disabled="busy">
                            <span x-show="!busy">Apply</span><span x-show="busy" x-cloak>Searching…</span>
                        </button>
                    </div>
                </div>

                <div id="ak-advanced" class="ak-filter-advanced ak-filter-advanced-users" x-show="advanced" x-cloak x-transition>
                    <div class="ak-field">
                        <label for="f_brand">Brand</label>
                        <input id="f_brand" type="text" name="filter[brand]" value="{{ $filters['brand'] ?? '' }}">
                    </div>
                    <div class="ak-field">
                        <label for="f_valuation">Valuation method</label>
                        <select id="f_valuation" name="filter[valuation_method]">
                            <option value="">Any</option>
                            @foreach ($valuationMethods as $method)
                                <option value="{{ $method }}" @selected(($filters['valuation_method'] ?? '') === $method)>{{ $method }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-field">
                        <label for="f_powder">Powder product</label>
                        <select id="f_powder" name="filter[is_powder]">
                            <option value="">Any</option>
                            <option value="1" @selected(($filters['is_powder'] ?? '') === '1')>Yes</option>
                            <option value="0" @selected(($filters['is_powder'] ?? '') === '0')>No</option>
                        </select>
                    </div>
                </div>
            </form>

            @if ($chips)
                <div class="ak-chips" aria-label="Active filters">
                    <span class="ak-chips-label">Filtered by</span>
                    @foreach ($chips as $key => $value)
                        <a href="{{ $withFilters(\Illuminate\Support\Arr::except($filters, $key)) }}" class="ak-chip" aria-label="Remove filter {{ $chipLabels[$key] ?? $key }}">
                            <b>{{ $chipLabels[$key] ?? \Illuminate\Support\Str::headline($key) }}:</b> {{ $chipValue($key, $value) }} <span aria-hidden="true">×</span>
                        </a>
                    @endforeach
                    <a href="{{ $withFilters($tab !== '' ? ['is_active' => $tab] : []) }}" class="ak-chips-clear">Clear all</a>
                </div>
            @endif

            @if ($products->count() > 0)
                <div class="ak-table-wrap">
                    <div class="ak-dt-toolbar">
                        <p>
                            <b>{{ number_format($products->total()) }}</b> {{ \Illuminate\Support\Str::plural('product', $products->total()) }}
                            &middot; sorted by <b>{{ $sortNames[$sortField] ?? 'Name' }}</b> ({{ str_starts_with($sort, '-') ? 'Z–A / high first' : 'A–Z / low first' }})
                        </p>
                    </div>
                    <div class="ak-dt-scroll" tabindex="0" aria-label="Products table">
                        <table class="ak-dt">
                            <caption class="sr-only">Products, {{ $products->total() }} results</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="ak-c">#</th>
                                    <th scope="col" aria-sort="{{ $ariaSort('product_name') }}"><a href="{{ $sortUrl('product_name') }}">Product <span aria-hidden="true">{{ $sortMark('product_name') }}</span></a></th>
                                    <th scope="col">Supplier &amp; UOM</th>
                                    <th scope="col" class="ak-c" aria-sort="{{ $ariaSort('cost_price') }}"><a href="{{ $sortUrl('cost_price') }}">Cost <span aria-hidden="true">{{ $sortMark('cost_price') }}</span></a></th>
                                    <th scope="col" class="ak-c" aria-sort="{{ $ariaSort('unit_sell_price') }}"><a href="{{ $sortUrl('unit_sell_price') }}">Selling <span aria-hidden="true">{{ $sortMark('unit_sell_price') }}</span></a></th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="ak-c"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($products as $product)
                                    <tr>
                                        <td class="ak-c" data-label=""><span class="ak-muted">{{ $products->firstItem() + $loop->index }}</span></td>
                                        <td data-label="Product">
                                            <a href="{{ route('products.show', $product) }}" class="ak-primary-link">{{ $product->product_name }}</a>
                                            <div class="ak-muted ak-mono">{{ $product->product_code }}@if ($product->category) · {{ $product->category->name }}@endif</div>
                                        </td>
                                        <td data-label="Supplier &amp; UOM">
                                            <div>{{ $product->supplier?->supplier_name ?? '—' }}</div>
                                            <div class="ak-muted">{{ $product->uom?->uom_name ?? '—' }}@if ($product->salesUom) → {{ $product->salesUom->uom_name }}@endif</div>
                                        </td>
                                        <td class="ak-c ak-mono" data-label="Cost">{{ number_format((float) $product->cost_price, 2) }}</td>
                                        <td class="ak-c ak-mono ak-strong" data-label="Selling">{{ number_format((float) $product->unit_sell_price, 2) }}</td>
                                        <td data-label="Status">
                                            @if ($product->is_active)
                                                <span class="ak-status ak-status-green"><i aria-hidden="true"></i>Active</span>
                                            @else
                                                <span class="ak-status ak-status-red"><i aria-hidden="true"></i>Inactive</span>
                                            @endif
                                            @if ($product->is_powder) <span class="ak-pill">Powder</span> @endif
                                        </td>
                                        <td data-label="">
                                            <div class="ak-actions">
                                                <a href="{{ route('products.show', $product) }}" class="ak-icon ak-icon-view" title="View product" aria-label="View {{ $product->product_name }}">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                                </a>
                                                <span class="ak-slot">
                                                    @can('product-edit')
                                                        <a href="{{ route('products.edit', $product) }}" class="ak-icon" title="Edit product" aria-label="Edit {{ $product->product_name }}">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m16.86 4.49 2.65 2.65M4 20l4.2-.9 10.9-10.9a1.9 1.9 0 0 0-2.7-2.7L5.5 16.4 4 20Z" /></svg>
                                                        </a>
                                                    @endcan
                                                </span>
                                                <span class="ak-slot">
                                                    @if ($canDeactivate && $product->is_active)
                                                        <form method="POST" action="{{ route('products.destroy', $product) }}" @submit.prevent="askDelete($el, @js($product->product_name))">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="ak-icon ak-icon-danger" title="Deactivate product" aria-label="Deactivate {{ $product->product_name }}">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M18.36 6.64A9 9 0 1 1 5.64 6.64M12 3v9" /></svg>
                                                            </button>
                                                        </form>
                                                    @endif
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="ak-pager">
                    <p>Showing <b>{{ number_format($products->firstItem()) }}–{{ number_format($products->lastItem()) }}</b> of <b>{{ number_format($products->total()) }}</b> products</p>
                    <div class="ak-pager-right">{{ $products->onEachSide(1)->links() }}</div>
                </div>
            @else
                <div class="ak-empty">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                    @if ($filters)
                        <h2>No products match these filters</h2>
                        <p>Remove a filter or search with part of a name, code or barcode.</p>
                        <a href="{{ route('products.index') }}" class="ak-btn ak-btn-primary">Clear all filters</a>
                    @else
                        <h2>No products yet</h2>
                        <p>Add a product, or import many SKUs at once from Excel.</p>
                        @can('product-create') <a href="{{ route('products.import.form') }}" class="ak-btn ak-btn-primary">Import Excel</a> @endcan
                    @endif
                </div>
            @endif
        </section>

        <div class="uf-modal" x-show="confirm.open" x-cloak style="display:none" @keydown.escape.window="confirm.open = false" role="dialog" aria-modal="true" aria-labelledby="cl-confirm-title">
            <div class="uf-modal-bg" x-show="confirm.open" x-transition.opacity @click="confirm.open = false"></div>
            <div class="uf-modal-box" x-show="confirm.open" x-transition>
                <div class="uf-modal-body">
                    <span class="uf-modal-icon ak-pill-red" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.01" /></svg>
                    </span>
                    <div>
                        <h3 id="cl-confirm-title" x-text="confirm.title"></h3>
                        <p style="margin:8px 0 0; font-size:14px; color:#334155">The product will be marked Inactive and can be re-activated from Edit. Nothing is deleted.</p>
                    </div>
                </div>
                <div class="uf-modal-foot">
                    <button type="button" class="ak-btn ak-btn-outline" @click="confirm.open = false">Cancel</button>
                    <button type="button" class="ak-btn ak-btn-danger-outline" @click="runConfirm()" :disabled="confirm.busy" x-text="confirm.busy ? 'Working…' : 'Deactivate product'"></button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function productList() {
                return {
                    confirm: { open: false, title: '', busy: false, form: null },
                    askDelete(form, name) { this.confirm = { open: true, title: 'Deactivate ' + name + '?', busy: false, form }; },
                    runConfirm() {
                        if (this.confirm.busy || !this.confirm.form) return;
                        this.confirm.busy = true;
                        this.confirm.form.submit();
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>
