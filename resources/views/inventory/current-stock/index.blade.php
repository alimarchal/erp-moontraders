<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Current Stock Inventory" :showSearch="true" :showRefresh="true" />
    </x-slot>

    <x-filter-section :action="route('inventory.current-stock.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_product_id" value="Product" />
                <select id="filter_product_id" name="filter[product_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Products</option>
                    @foreach($products as $product)
                    <option value="{{ $product->id }}" {{ request('filter.product_id')==$product->id ? 'selected' : ''
                        }}>
                        {{ $product->product_code }} - {{ $product->product_name }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_warehouse_id" value="Warehouse" />
                <select id="filter_warehouse_id" name="filter[warehouse_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Warehouses</option>
                    @foreach($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" {{ request('filter.warehouse_id')==$warehouse->id ? 'selected'
                        : '' }}>
                        {{ $warehouse->warehouse_name }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_has_promotional" value="Stock Type" />
                <select id="filter_has_promotional" name="filter[has_promotional]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Stock</option>
                    <option value="1" {{ request('filter.has_promotional')=='1' ? 'selected' : '' }}>Promotional Only
                    </option>
                </select>
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$stocks" :headers="[
            ['label' => 'Product'],
            ['label' => 'Warehouse'],
            ['label' => 'Qty On Hand', 'align' => 'text-right'],
            ['label' => 'Qty Available', 'align' => 'text-right'],
            ['label' => 'Avg Cost', 'align' => 'text-right'],
            ['label' => 'Total Value', 'align' => 'text-right'],
            ['label' => 'Batches', 'align' => 'text-center'],
            ['label' => 'Actions', 'align' => 'text-center'],
        ]" emptyMessage="No stock found." emptyLinkText="">

        @foreach($stocks as $stock)
        <tr class="border-b border-gray-200 text-sm">
            <td class="py-1 px-2">
                <div class="text-sm font-medium text-gray-900">{{ $stock->product->product_code }}
                </div>
                <div class="text-xs text-gray-500">{{ $stock->product->product_name }}</div>
            </td>
            <td class="py-1 px-2">
                {{ $stock->warehouse->warehouse_name }}
            </td>
            <td class="py-1 px-2 text-right">
                <span class="font-semibold text-gray-900">{{ number_format($stock->quantity_on_hand,
                    2) }}</span>
            </td>
            <td class="py-1 px-2 text-right">
                {{ number_format($stock->quantity_available, 2) }}
            </td>
            <td class="py-1 px-2 text-right">
                ₨ {{ number_format($stock->average_cost, 2) }}
            </td>
            <td class="py-1 px-2 text-right">
                <span class="font-semibold text-gray-900">₨ {{ number_format($stock->total_value, 2)
                    }}</span>
            </td>
            <td class="py-1 px-2 text-center">
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
            <td class="py-1 px-2 text-center">
                <a href="{{ route('inventory.current-stock.by-batch', ['product_id' => $stock->product_id, 'warehouse_id' => $stock->warehouse_id]) }}"
                    class="text-blue-600 hover:text-blue-900">
                    View Batches
                </a>
            </td>
        </tr>
        @endforeach
    </x-data-table>
</x-app-layout>