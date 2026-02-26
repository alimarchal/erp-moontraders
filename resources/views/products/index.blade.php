<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Products" :createRoute="route('products.create')" createLabel=""
            createPermission="product-create" :showSearch="true" :showRefresh="true" backRoute="settings.index" />
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-2 no-print">
        <div class="flex justify-end">
            <a href="{{ route('products.export.excel', request()->query()) }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-green-700 hover:bg-green-800 text-white text-sm font-semibold rounded-md shadow transition-colors duration-150">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Export to Excel
            </a>
        </div>
    </div>

    <x-filter-section :action="route('products.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <x-label for="filter_product_name" value="Product Name" />
                <select id="filter_product_name" name="filter[product_name]" class="select2 mt-1 block w-full">
                    <option value="">All Products</option>
                    @foreach ($productOptions as $product)
                                    <option value="{{ $product->product_name }}" {{ request('filter.product_name') === $product->
                        product_name ? 'selected' : '' }}>
                                        {{ $product->product_name }}
                                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_product_code" value="Product Code" />
                <select id="filter_product_code" name="filter[product_code]" class="select2 mt-1 block w-full">
                    <option value="">All Codes</option>
                    @foreach ($productOptions as $product)
                                    <option value="{{ $product->product_code }}" {{ request('filter.product_code') === $product->
                        product_code ? 'selected' : '' }}>
                                        {{ $product->product_code }}
                                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_brand" value="Brand" />
                <x-input id="filter_brand" name="filter[brand]" type="text" class="mt-1 block w-full"
                    :value="request('filter.brand')" placeholder="Brand" />
            </div>

            <div>
                <x-label for="filter_barcode" value="Barcode" />
                <x-input id="filter_barcode" name="filter[barcode]" type="text" class="mt-1 block w-full"
                    :value="request('filter.barcode')" placeholder="Barcode" />
            </div>

            <div>
                <x-label for="filter_supplier_id" value="Supplier" />
                <select id="filter_supplier_id" name="filter[supplier_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Suppliers</option>
                    @foreach ($supplierOptions as $supplier)
                                    <option value="{{ $supplier->id }}" {{ request('filter.supplier_id') === (string) $supplier->id ?
                        'selected' : '' }}>
                                        {{ $supplier->supplier_name }}
                                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_category_id" value="Category" />
                <select id="filter_category_id" name="filter[category_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Categories</option>
                    @foreach ($categoryOptions as $category)
                                    <option value="{{ $category->id }}" {{ request('filter.category_id') === (string) $category->id ?
                        'selected' : '' }}>
                                        {{ $category->name }}
                                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_uom_id" value="UOM" />
                <select id="filter_uom_id" name="filter[uom_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Units</option>
                    @foreach ($uomOptions as $uom)
                        <option value="{{ $uom->id }}" {{ request('filter.uom_id') === (string) $uom->id ? 'selected' : '' }}>
                            {{ $uom->uom_name }} {{ $uom->symbol ? '(' . $uom->symbol . ')' : '' }}
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
                    <option value="">Both</option>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" {{ request('filter.is_active') === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_is_powder" value="Is Powder?" />
                <select id="filter_is_powder" name="filter[is_powder]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">Both</option>
                    <option value="1" {{ request('filter.is_powder') === '1' ? 'selected' : '' }}>Yes</option>
                    <option value="0" {{ request('filter.is_powder') === '0' ? 'selected' : '' }}>No</option>
                </select>
            </div>

            <div>
                <x-label for="sort" value="Sort By" />
                <select id="sort" name="sort"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="product_name" {{ request('sort', 'product_name') === 'product_name' ? 'selected' : '' }}>Name (A-Z)</option>
                    <option value="-product_name" {{ request('sort') === '-product_name' ? 'selected' : '' }}>Name (Z-A)</option>
                    <option value="product_code" {{ request('sort') === 'product_code' ? 'selected' : '' }}>Code (A-Z)</option>
                    <option value="-product_code" {{ request('sort') === '-product_code' ? 'selected' : '' }}>Code (Z-A)</option>
                    <option value="-unit_sell_price" {{ request('sort') === '-unit_sell_price' ? 'selected' : '' }}>Price (High-Low)</option>
                    <option value="unit_sell_price" {{ request('sort') === 'unit_sell_price' ? 'selected' : '' }}>Price (Low-High)</option>
                    <option value="-cost_price" {{ request('sort') === '-cost_price' ? 'selected' : '' }}>Cost (High-Low)</option>
                    <option value="cost_price" {{ request('sort') === 'cost_price' ? 'selected' : '' }}>Cost (Low-High)</option>
                </select>
            </div>

            <div>
                <x-label for="per_page" value="Per Page" />
                <select id="per_page" name="per_page"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    @foreach ($perPageOptions as $option)
                        <option value="{{ $option }}" {{ (int) request('per_page', 40) === $option ? 'selected' : '' }}>
                            {{ $option }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$products" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Code', 'align' => 'text-left'],
        ['label' => 'Product', 'align' => 'text-left'],
        ['label' => 'Supplier & UOM', 'align' => 'text-left'],
        ['label' => 'Pricing', 'align' => 'text-left'],
        ['label' => 'Status', 'align' => 'text-center'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No products found." :emptyRoute="route('products.create')"
        emptyLinkText="Add a product">
        @foreach ($products as $index => $product)
            <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-gray-50' }}">
                <td class="py-2 px-2 text-center">
                    {{ $products->firstItem() + $index }}
                </td>
                <td class="py-2 px-2 font-semibold">
                    <div>{{ $product->product_code }}</div>
                    {{-- <div class="text-xs text-gray-500">Barcode: {{ $product->barcode ?? '—' }}</div> --}}
                </td>
                <td class="py-2 px-2">
                    <div class="font-semibold">{{ $product->product_name }}</div>
                    <div class="text-xs text-gray-500">Category: {{ $product->category->name ?? '—' }}</div>
                    <div class="text-xs text-gray-500">Valuation: {{ $product->valuation_method }}</div>
                </td>
                <td class="py-2 px-2 text-sm">
                    <div>{{ $product->supplier?->supplier_name ?? '—' }}</div>
                    <div class="text-xs text-gray-500">UOM: {{ $product->uom?->uom_name ?? '—' }}</div>
                </td>
                <td class="py-2 px-2 text-sm">
                    <div class="font-semibold text-emerald-700">Sell: {{ number_format((float) $product->unit_sell_price, 2)
                                    }}
                    </div>
                    <div class="text-xs text-gray-500">Cost: {{ number_format((float) $product->cost_price, 2) }}</div>
                    <div class="text-xs text-gray-500">Reorder: {{ number_format((float) $product->reorder_level, 2) }}
                    </div>
                </td>
                <td class="py-2 px-2 text-center">
                    <span
                        class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full {{ $product->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                        {{ $product->is_active ? 'Active' : 'Inactive' }}
                    </span>
                    @if($product->is_powder)
                        <span
                            class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-700 mt-1">
                            Powder
                        </span>
                    @endif
                </td>
                <td class="py-2 px-2 text-center">
                    <div class="flex justify-center space-x-2">
                        <a href="{{ route('products.show', $product) }}"
                            class="inline-flex items-center justify-center w-8 h-8 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded-md transition-colors duration-150"
                            title="View">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </a>
                        @can('product-edit')
                            <a href="{{ route('products.edit', $product) }}"
                                class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors duration-150"
                                title="Edit">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </a>
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-app-layout>