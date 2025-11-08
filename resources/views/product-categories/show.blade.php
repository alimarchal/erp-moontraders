<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            View Product Category: {{ $category->category_name }}
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('product-categories.index') }}"
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
                            <x-label value="Category Code" />
                            <x-input type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$category->category_code" disabled readonly />
                        </div>
                        <div>
                            <x-label value="Category Name" />
                            <x-input type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$category->category_name" disabled readonly />
                        </div>
                    </div>

                    <div>
                        <x-label value="Description" />
                        <textarea rows="3"
                            class="mt-1 block w-full cursor-not-allowed border-gray-300 bg-gray-100 text-gray-700 rounded-md shadow-sm"
                            disabled readonly>{{ $category->description ?? '—' }}</textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-label value="Parent Category" />
                            <x-input type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$category->parent?->category_name ?? 'Top level'" disabled readonly />
                        </div>
                        <div>
                            <x-label value="Status" />
                            <x-input type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$category->is_active ? 'Active' : 'Inactive'" disabled readonly />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <x-label value="Default Inventory Account" />
                            <x-input type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$category->defaultInventoryAccount ? $category->defaultInventoryAccount->account_code . ' — ' . $category->defaultInventoryAccount->account_name : 'Not linked'"
                                disabled readonly />
                        </div>
                        <div>
                            <x-label value="Default COGS Account" />
                            <x-input type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$category->defaultCogsAccount ? $category->defaultCogsAccount->account_code . ' — ' . $category->defaultCogsAccount->account_name : 'Not linked'"
                                disabled readonly />
                        </div>
                        <div>
                            <x-label value="Default Revenue Account" />
                            <x-input type="text" class="mt-1 block w-full cursor-not-allowed bg-gray-100"
                                :value="$category->defaultSalesRevenueAccount ? $category->defaultSalesRevenueAccount->account_code . ' — ' . $category->defaultSalesRevenueAccount->account_name : 'Not linked'"
                                disabled readonly />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        input:disabled,
        textarea:disabled {
            cursor: not-allowed !important;
        }
    </style>
</x-app-layout>