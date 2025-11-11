<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            View Product: {{ $product->product_name }}
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('products.index') }}"
                class="inline-flex items-center ml-2 px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 focus:bg-green-800 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <x-status-message class="mb-4 mt-4" />
                <div class="p-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-label value="Product Code" />
                            <x-input type="text" class="mt-1 block w-full bg-gray-100" :value="$product->product_code"
                                disabled readonly />
                        </div>
                        <div>
                            <x-label value="Product Name" />
                            <x-input type="text" class="mt-1 block w-full bg-gray-100" :value="$product->product_name"
                                disabled readonly />
                        </div>
                    </div>

                    <div>
                        <x-label value="Description" />
                        <textarea rows="3"
                            class="mt-1 block w-full border-gray-300 bg-gray-100 text-gray-700 rounded-md shadow-sm"
                            disabled readonly>{{ $product->description ?? '—' }}</textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <x-label value="Category" />
                            <x-input type="text" class="mt-1 block w-full bg-gray-100"
                                :value="$product->category?->category_name ?? '—'" disabled readonly />
                        </div>
                        <div>
                            <x-label value="Supplier" />
                            <x-input type="text" class="mt-1 block w-full bg-gray-100"
                                :value="$product->supplier?->supplier_name ?? '—'" disabled readonly />
                        </div>
                        <div>
                            <x-label value="Valuation Method" />
                            <x-input type="text" class="mt-1 block w-full bg-gray-100"
                                :value="$product->valuation_method" disabled readonly />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <x-label value="Base UOM (Inventory)" />
                            <x-input type="text" class="mt-1 block w-full bg-gray-100"
                                :value="$product->uom ? $product->uom->uom_name . ($product->uom->symbol ? ' (' . $product->uom->symbol . ')' : '') : '—'"
                                disabled readonly />
                            <p class="text-xs text-gray-500 mt-1">Unit for inventory tracking</p>
                        </div>
                        <div>
                            <x-label value="Sales UOM" />
                            <x-input type="text" class="mt-1 block w-full bg-gray-100"
                                :value="$product->salesUom ? $product->salesUom->uom_name . ($product->salesUom->symbol ? ' (' . $product->salesUom->symbol . ')' : '') : 'Same as Base'"
                                disabled readonly />
                            <p class="text-xs text-gray-500 mt-1">Unit for sales/invoicing</p>
                        </div>
                        <div>
                            <x-label value="Conversion Factor" />
                            <x-input type="text" class="mt-1 block w-full bg-gray-100"
                                :value="$product->uom_conversion_factor ?? '1'" disabled readonly />
                            <p class="text-xs text-gray-500 mt-1">Base units per sales unit</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <x-label value="Brand" />
                            <x-input type="text" class="mt-1 block w-full bg-gray-100" :value="$product->brand ?? '—'"
                                disabled readonly />
                        </div>
                        <div>
                            <x-label value="Barcode" />
                            <x-input type="text" class="mt-1 block w-full bg-gray-100" :value="$product->barcode ?? '—'"
                                disabled readonly />
                        </div>
                        <div>
                            <x-label value="Pack Size" />
                            <x-input type="text" class="mt-1 block w-full bg-gray-100"
                                :value="$product->pack_size ?? '—'" disabled readonly />
                        </div>
                        <div>
                            <x-label value="Weight (kg)" />
                            <x-input type="text" class="mt-1 block w-full bg-gray-100" :value="$product->weight ?? '—'"
                                disabled readonly />
                        </div>
                    </div>

                    <div>
                        <x-label value="Status" />
                        <x-input type="text" class="mt-1 block w-full bg-gray-100"
                            :value="$product->is_active ? 'Active' : 'Inactive'" disabled readonly />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <x-label value="Selling Price" />
                            <x-input type="text" class="mt-1 block w-full bg-gray-100"
                                :value="number_format((float) $product->unit_price, 2)" disabled readonly />
                        </div>
                        <div>
                            <x-label value="Cost Price" />
                            <x-input type="text" class="mt-1 block w-full bg-gray-100"
                                :value="number_format((float) $product->cost_price, 2)" disabled readonly />
                        </div>
                        <div>
                            <x-label value="Reorder Level" />
                            <x-input type="text" class="mt-1 block w-full bg-gray-100"
                                :value="number_format((float) $product->reorder_level, 2)" disabled readonly />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <x-label value="Inventory Account" />
                            <x-input type="text" class="mt-1 block w-full bg-gray-100"
                                :value="$product->inventoryAccount ? $product->inventoryAccount->account_code . ' — ' . $product->inventoryAccount->account_name : 'Not linked'"
                                disabled readonly />
                        </div>
                        <div>
                            <x-label value="COGS Account" />
                            <x-input type="text" class="mt-1 block w-full bg-gray-100"
                                :value="$product->cogsAccount ? $product->cogsAccount->account_code . ' — ' . $product->cogsAccount->account_name : 'Not linked'"
                                disabled readonly />
                        </div>
                        <div>
                            <x-label value="Revenue Account" />
                            <x-input type="text" class="mt-1 block w-full bg-gray-100"
                                :value="$product->salesRevenueAccount ? $product->salesRevenueAccount->account_code . ' — ' . $product->salesRevenueAccount->account_name : 'Not linked'"
                                disabled readonly />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>