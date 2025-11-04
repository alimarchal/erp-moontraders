<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Income Statement" :createRoute="null" createLabel="" :showSearch="true"
            :showRefresh="true" backRoute="reports.index" />
    </x-slot>

    <x-filter-section :action="route('reports.income-statement.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <x-label for="filter_account_code" value="Account Code" />
                <x-input id="filter_account_code" name="filter[account_code]" type="text"
                    class="mt-1 block w-full uppercase" :value="request('filter.account_code')"
                    placeholder="e.g., 4000" />
            </div>

            <div>
                <x-label for="filter_account_name" value="Account Name" />
                <x-input id="filter_account_name" name="filter[account_name]" type="text" class="mt-1 block w-full"
                    :value="request('filter.account_name')" placeholder="Search by account name" />
            </div>

            <div>
                <x-label for="filter_account_type" value="Account Type" />
                <x-input id="filter_account_type" name="filter[account_type]" type="text" class="mt-1 block w-full"
                    :value="request('filter.account_type')" placeholder="e.g., Revenue" />
            </div>

            <div>
                <x-label for="sort" value="Sort By" />
                <select id="sort" name="sort"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                    <option value="account_code" {{ request('sort')=='account_code' || !request('sort') ? 'selected'
                        : '' }}>Account Code (A-Z)</option>
                    <option value="-account_code" {{ request('sort')=='-account_code' ? 'selected' : '' }}>Account Code
                        (Z-A)</option>
                    <option value="account_name" {{ request('sort')=='account_name' ? 'selected' : '' }}>Account Name
                        (A-Z)</option>
                    <option value="-account_name" {{ request('sort')=='-account_name' ? 'selected' : '' }}>Account Name
                        (Z-A)</option>
                    <option value="account_type" {{ request('sort')=='account_type' ? 'selected' : '' }}>Account Type
                        (A-Z)</option>
                    <option value="-account_type" {{ request('sort')=='-account_type' ? 'selected' : '' }}>Account Type
                        (Z-A)</option>
                    <option value="-balance" {{ request('sort')=='-balance' ? 'selected' : '' }}>Balance (High-Low)
                    </option>
                    <option value="balance" {{ request('sort')=='balance' ? 'selected' : '' }}>Balance (Low-High)
                    </option>
                </select>
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
            $revenue = $groupedAccounts->filter(function($items, $type) {
            return str_contains(strtolower($type), 'revenue') || str_contains(strtolower($type), 'income');
            });
            $expenses = $groupedAccounts->filter(function($items, $type) {
            return str_contains(strtolower($type), 'expense') || str_contains(strtolower($type), 'cost');
            });

            $totalRevenue = $accounts->filter(function($item) {
            return str_contains(strtolower($item->account_type), 'revenue') ||
            str_contains(strtolower($item->account_type), 'income');
            })->sum('balance');

            $totalExpenses = $accounts->filter(function($item) {
            return str_contains(strtolower($item->account_type), 'expense') ||
            str_contains(strtolower($item->account_type), 'cost');
            })->sum('balance');

            $netIncome = $totalRevenue - $totalExpenses;
            @endphp

            <!-- Revenue Section -->
            <div class="mb-8">
                <h3 class="text-xl font-bold mb-4 text-gray-800 dark:text-gray-200 border-b-2 border-gray-300 pb-2">
                    REVENUE
                </h3>
                @foreach($revenue as $accountType => $items)
                <div class="mb-4">
                    <h4 class="font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ $accountType }}</h4>
                    @foreach($items as $account)
                    <div class="flex justify-between py-1 px-2 text-sm">
                        <span class="text-gray-600 dark:text-gray-400">
                            <span class="font-mono">{{ $account->account_code }}</span> - {{ $account->account_name }}
                        </span>
                        <span class="font-mono font-semibold">{{ number_format((float) $account->balance, 2) }}</span>
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
                    class="flex justify-between py-2 px-2 font-bold text-lg border-t-2 border-gray-400 dark:border-gray-500 mt-4 bg-green-50 dark:bg-green-900">
                    <span>TOTAL REVENUE</span>
                    <span class="font-mono">{{ number_format($totalRevenue, 2) }}</span>
                </div>
            </div>

            <!-- Expenses Section -->
            <div class="mb-8">
                <h3 class="text-xl font-bold mb-4 text-gray-800 dark:text-gray-200 border-b-2 border-gray-300 pb-2">
                    EXPENSES
                </h3>
                @foreach($expenses as $accountType => $items)
                <div class="mb-4">
                    <h4 class="font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ $accountType }}</h4>
                    @foreach($items as $account)
                    <div class="flex justify-between py-1 px-2 text-sm">
                        <span class="text-gray-600 dark:text-gray-400">
                            <span class="font-mono">{{ $account->account_code }}</span> - {{ $account->account_name }}
                        </span>
                        <span class="font-mono font-semibold">{{ number_format((float) $account->balance, 2) }}</span>
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
                    class="flex justify-between py-2 px-2 font-bold text-lg border-t-2 border-gray-400 dark:border-gray-500 mt-4 bg-red-50 dark:bg-red-900">
                    <span>TOTAL EXPENSES</span>
                    <span class="font-mono">{{ number_format($totalExpenses, 2) }}</span>
                </div>
            </div>

            <!-- Net Income -->
            <div
                class="flex justify-between py-3 px-2 font-bold text-2xl border-t-4 border-gray-600 dark:border-gray-400 mt-8 {{ $netIncome >= 0 ? 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200' : 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200' }}">
                <span>NET {{ $netIncome >= 0 ? 'INCOME' : 'LOSS' }}</span>
                <span class="font-mono">{{ number_format($netIncome, 2) }}</span>
            </div>

        </div>
    </div>
</x-app-layout>