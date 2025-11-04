<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Balance Sheet" :createRoute="null" createLabel="" :showSearch="true" :showRefresh="true"
            backRoute="reports.index" />
    </x-slot>

    <x-filter-section :action="route('reports.balance-sheet.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <x-label for="filter_account_code" value="Account Code" />
                <x-input id="filter_account_code" name="filter[account_code]" type="text"
                    class="mt-1 block w-full uppercase" :value="request('filter.account_code')"
                    placeholder="e.g., 1000" />
            </div>

            <div>
                <x-label for="filter_account_name" value="Account Name" />
                <x-input id="filter_account_name" name="filter[account_name]" type="text" class="mt-1 block w-full"
                    :value="request('filter.account_name')" placeholder="Search by account name" />
            </div>

            <div>
                <x-label for="filter_account_type" value="Account Type" />
                <x-input id="filter_account_type" name="filter[account_type]" type="text" class="mt-1 block w-full"
                    :value="request('filter.account_type')" placeholder="e.g., Asset" />
            </div>

            <div>
                <x-label for="per_page" value="Show Per Page" />
                <select id="per_page" name="per_page"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                    <option value="10" {{ request('per_page')==10 ? 'selected' : '' }}>10</option>
                    <option value="25" {{ request('per_page')==25 ? 'selected' : '' }}>25</option>
                    <option value="50" {{ request('per_page', 50)==50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ request('per_page')==100 ? 'selected' : '' }}>100</option>
                    <option value="250" {{ request('per_page')==250 ? 'selected' : '' }}>250</option>
                </select>
            </div>
        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-2 pb-16">
        <x-status-message />
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">

            @php
            $assets = $groupedAccounts->filter(function($items, $type) {
            return str_contains(strtolower($type), 'asset');
            });
            $liabilities = $groupedAccounts->filter(function($items, $type) {
            return str_contains(strtolower($type), 'liability');
            });
            $equity = $groupedAccounts->filter(function($items, $type) {
            return str_contains(strtolower($type), 'equity');
            });

            $totalAssets = $accounts->where('normal_balance', 'debit')->sum('balance');
            $totalLiabilities = $accounts->filter(function($item) {
            return str_contains(strtolower($item->account_type), 'liability');
            })->sum('balance');
            $totalEquity = $accounts->filter(function($item) {
            return str_contains(strtolower($item->account_type), 'equity');
            })->sum('balance');
            @endphp

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Assets Section -->
                <div>
                    <h3 class="text-xl font-bold mb-4 text-gray-800 dark:text-gray-200 border-b-2 border-gray-300 pb-2">
                        ASSETS
                    </h3>
                    @foreach($assets as $accountType => $items)
                    <div class="mb-4">
                        <h4 class="font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ $accountType }}</h4>
                        @foreach($items as $account)
                        <div class="flex justify-between py-1 px-2 text-sm">
                            <span class="text-gray-600 dark:text-gray-400">
                                <span class="font-mono">{{ $account->account_code }}</span> - {{ $account->account_name
                                }}
                            </span>
                            <span class="font-mono font-semibold">{{ number_format((float) $account->balance, 2)
                                }}</span>
                        </div>
                        @endforeach
                        <div
                            class="flex justify-between py-1 px-2 font-semibold border-t border-gray-200 dark:border-gray-600 mt-1">
                            <span>Total {{ $accountType }}</span>
                            <span class="font-mono">{{ number_format((float) $items->sum('balance'), 2) }}</span>
                        </div>
                    </div>
                    @endforeach
                    <div
                        class="flex justify-between py-2 px-2 font-bold text-lg border-t-2 border-gray-400 dark:border-gray-500 mt-4 bg-blue-50 dark:bg-blue-900">
                        <span>TOTAL ASSETS</span>
                        <span class="font-mono">{{ number_format($totalAssets, 2) }}</span>
                    </div>
                </div>

                <!-- Liabilities & Equity Section -->
                <div>
                    <h3 class="text-xl font-bold mb-4 text-gray-800 dark:text-gray-200 border-b-2 border-gray-300 pb-2">
                        LIABILITIES & EQUITY
                    </h3>

                    <!-- Liabilities -->
                    @foreach($liabilities as $accountType => $items)
                    <div class="mb-4">
                        <h4 class="font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ $accountType }}</h4>
                        @foreach($items as $account)
                        <div class="flex justify-between py-1 px-2 text-sm">
                            <span class="text-gray-600 dark:text-gray-400">
                                <span class="font-mono">{{ $account->account_code }}</span> - {{ $account->account_name
                                }}
                            </span>
                            <span class="font-mono font-semibold">{{ number_format((float) $account->balance, 2)
                                }}</span>
                        </div>
                        @endforeach
                        <div
                            class="flex justify-between py-1 px-2 font-semibold border-t border-gray-200 dark:border-gray-600 mt-1">
                            <span>Total {{ $accountType }}</span>
                            <span class="font-mono">{{ number_format((float) $items->sum('balance'), 2) }}</span>
                        </div>
                    </div>
                    @endforeach

                    <!-- Equity -->
                    @foreach($equity as $accountType => $items)
                    <div class="mb-4">
                        <h4 class="font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ $accountType }}</h4>
                        @foreach($items as $account)
                        <div class="flex justify-between py-1 px-2 text-sm">
                            <span class="text-gray-600 dark:text-gray-400">
                                <span class="font-mono">{{ $account->account_code }}</span> - {{ $account->account_name
                                }}
                            </span>
                            <span class="font-mono font-semibold">{{ number_format((float) $account->balance, 2)
                                }}</span>
                        </div>
                        @endforeach
                        <div
                            class="flex justify-between py-1 px-2 font-semibold border-t border-gray-200 dark:border-gray-600 mt-1">
                            <span>Total {{ $accountType }}</span>
                            <span class="font-mono">{{ number_format((float) $items->sum('balance'), 2) }}</span>
                        </div>
                    </div>
                    @endforeach

                    <div
                        class="flex justify-between py-2 px-2 font-bold text-lg border-t-2 border-gray-400 dark:border-gray-500 mt-4 bg-blue-50 dark:bg-blue-900">
                        <span>TOTAL LIABILITIES & EQUITY</span>
                        <span class="font-mono">{{ number_format($totalLiabilities + $totalEquity, 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Balance Check -->
            @php
            $difference = abs($totalAssets - ($totalLiabilities + $totalEquity));
            @endphp
            @if($difference > 0.01)
            <div class="mt-6 p-4 bg-red-100 dark:bg-red-900 border border-red-400 rounded">
                <p class="text-red-700 dark:text-red-200 font-semibold">
                    ⚠️ Balance Sheet does not balance! Difference: {{ number_format($difference, 2) }}
                </p>
            </div>
            @else
            <div class="mt-6 p-4 bg-green-100 dark:bg-green-900 border border-green-400 rounded">
                <p class="text-green-700 dark:text-green-200 font-semibold text-center">
                    ✓ Balance Sheet is balanced
                </p>
            </div>
            @endif

        </div>
    </div>
</x-app-layout>