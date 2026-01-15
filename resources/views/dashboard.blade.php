<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-12 gap-6">

                <!-- Supply Chain Workflow Section -->
                <div class="col-span-12">
                    <h3 class="text-2xl font-bold text-gray-800 border-b-2 border-indigo-600 pb-2 mb-6">
                        Supply Chain Operations
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
                        <!-- 1. Goods Receipt (GRN) -->
                        @can('goods-receipt-note-list')
                            <a href="{{ route('goods-receipt-notes.index') }}"
                                class="transform hover:scale-105 transition duration-300 shadow-xl rounded-xl bg-white border-l-8 border-blue-600 block">
                                <div class="p-6">
                                    <div class="flex items-center justify-between mb-4">
                                        <div class="p-3 bg-blue-100 rounded-lg text-blue-600">
                                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                            </svg>
                                        </div>
                                        <span
                                            class="text-2xl font-bold text-gray-700">{{ \App\Models\GoodsReceiptNote::count() }}</span>
                                    </div>
                                    <h4 class="text-lg font-bold text-gray-900">Goods Receipt</h4>
                                    <p class="text-sm text-gray-500 mt-1">Receive inventory (GRN)</p>
                                </div>
                            </a>
                        @endcan

                        <!-- 2. Supplier Payments -->
                        @can('supplier-payment-list')
                            <a href="{{ route('supplier-payments.index') }}"
                                class="transform hover:scale-105 transition duration-300 shadow-xl rounded-xl bg-white border-l-8 border-emerald-600 block">
                                <div class="p-6">
                                    <div class="flex items-center justify-between mb-4">
                                        <div class="p-3 bg-emerald-100 rounded-lg text-emerald-600">
                                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                            </svg>
                                        </div>
                                        <span
                                            class="text-2xl font-bold text-gray-700">{{ \App\Models\SupplierPayment::count() }}</span>
                                    </div>
                                    <h4 class="text-lg font-bold text-gray-900">Payments</h4>
                                    <p class="text-sm text-gray-500 mt-1">Vendor settlements</p>
                                </div>
                            </a>
                        @endcan

                        <!-- 3. Inventory (Current Stock) -->
                        @can('inventory-view')
                            <a href="{{ route('inventory.current-stock.index') }}"
                                class="transform hover:scale-105 transition duration-300 shadow-xl rounded-xl bg-white border-l-8 border-orange-600 block">
                                <div class="p-6">
                                    <div class="flex items-center justify-between mb-4">
                                        <div class="p-3 bg-orange-100 rounded-lg text-orange-600">
                                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                            </svg>
                                        </div>
                                        <span
                                            class="text-2xl font-bold text-gray-700">{{ \App\Models\CurrentStock::count() }}</span>
                                    </div>
                                    <h4 class="text-lg font-bold text-gray-900">Inventory</h4>
                                    <p class="text-sm text-gray-500 mt-1">Stock levels & history</p>
                                </div>
                            </a>
                        @endcan

                        <!-- 4. Distribution (Goods Issue) -->
                        @can('goods-issue-list')
                            <a href="{{ route('goods-issues.index') }}"
                                class="transform hover:scale-105 transition duration-300 shadow-xl rounded-xl bg-white border-l-8 border-purple-600 block">
                                <div class="p-6">
                                    <div class="flex items-center justify-between mb-4">
                                        <div class="p-3 bg-purple-100 rounded-lg text-purple-600">
                                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                            </svg>
                                        </div>
                                        <span
                                            class="text-2xl font-bold text-gray-700">{{ \App\Models\GoodsIssue::count() }}</span>
                                    </div>
                                    <h4 class="text-lg font-bold text-gray-900">Distribution</h4>
                                    <p class="text-sm text-gray-500 mt-1">Goods issue & transfers</p>
                                </div>
                            </a>
                        @endcan

                        <!-- 5. Sales Settlements -->
                        @can('sales-settlement-list')
                            <a href="{{ route('sales-settlements.index') }}"
                                class="transform hover:scale-105 transition duration-300 shadow-xl rounded-xl bg-white border-l-8 border-pink-600 block">
                                <div class="p-6">
                                    <div class="flex items-center justify-between mb-4">
                                        <div class="p-3 bg-pink-100 rounded-lg text-pink-600">
                                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <span
                                            class="text-2xl font-bold text-gray-700">{{ \App\Models\SalesSettlement::count() }}</span>
                                    </div>
                                    <h4 class="text-lg font-bold text-gray-900">Sales</h4>
                                    <p class="text-sm text-gray-500 mt-1">Sales settlements & revenue</p>
                                </div>
                            </a>
                        @endcan
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>