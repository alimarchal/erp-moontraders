<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            View Product Tax Mapping #{{ $mapping->id }}
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('product-tax-mappings.index') }}"
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
                <div class="p-6">
                    <form>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-label for="product" value="Product" />
                                <x-input id="product" type="text" name="product"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$mapping->product ? $mapping->product->product_code . ' - ' . $mapping->product->product_name : 'N/A'"
                                    disabled readonly />
                            </div>

                            <div>
                                <x-label for="tax_code" value="Tax Code" />
                                <x-input id="tax_code" type="text" name="tax_code"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="$mapping->taxCode ? $mapping->taxCode->tax_code . ' - ' . $mapping->taxCode->name : 'N/A'"
                                    disabled readonly />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-4">
                            <div>
                                <x-label for="transaction_type" value="Transaction Type" />
                                <x-input id="transaction_type" type="text" name="transaction_type"
                                    class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                    :value="ucfirst($mapping->transaction_type)" disabled readonly />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-6">
                            <div class="flex items-center">
                                <input id="is_active" type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 cursor-not-allowed"
                                    {{ $mapping->is_active ? 'checked' : '' }} disabled>
                                <label for="is_active" class="ml-2 text-sm text-gray-700">
                                    Active
                                </label>
                            </div>
                        </div>

                        <div class="flex items-center justify-between mt-6">
                            <div class="text-sm text-gray-500">
                                Created: {{ $mapping->created_at?->format('Y-m-d H:i:s') }}<br>
                                Updated: {{ $mapping->updated_at?->format('Y-m-d H:i:s') }}
                            </div>
                            <a href="{{ route('product-tax-mappings.edit', $mapping) }}"
                                class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Edit Mapping
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
