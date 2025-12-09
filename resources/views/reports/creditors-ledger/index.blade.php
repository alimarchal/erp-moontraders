<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Creditors Ledger (Accounts Receivable)" :createRoute="null" createLabel=""
            :showSearch="true" :showRefresh="true" backRoute="reports.index" />
    </x-slot>

    <x-filter-section :action="route('reports.creditors-ledger.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <x-label for="filter_customer_name" value="Customer Name" />
                <x-input id="filter_customer_name" name="filter[customer_name]" type="text" class="mt-1 block w-full"
                    :value="request('filter.customer_name')" placeholder="Customer name..." />
            </div>

            <div>
                <x-label for="filter_customer_code" value="Customer Code" />
                <x-input id="filter_customer_code" name="filter[customer_code]" type="text" class="mt-1 block w-full"
                    :value="request('filter.customer_code')" placeholder="Customer code..." />
            </div>

            <div>
                <x-label for="filter_city" value="City" />
                <select id="filter_city" name="filter[city]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Cities</option>
                    @foreach($cities as $city)
                    <option value="{{ $city }}" {{ request('filter.city')===$city ? 'selected' : '' }}>
                        {{ $city }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_balance_min" value="Balance (Min)" />
                <x-input id="filter_balance_min" name="filter[balance_min]" type="number" step="0.01"
                    class="mt-1 block w-full" :value="request('filter.balance_min')" placeholder="0.00" />
            </div>

            <div>
                <x-label for="filter_balance_max" value="Balance (Max)" />
                <x-input id="filter_balance_max" name="filter[balance_max]" type="number" step="0.01"
                    class="mt-1 block w-full" :value="request('filter.balance_max')" placeholder="Any" />
            </div>

            <div>
                <x-label for="sort" value="Sort By" />
                <select id="sort" name="sort"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="-balance" {{ request('sort', '-balance' )==='-balance' ? 'selected' : '' }}>Balance
                        (High to Low)</option>
                    <option value="balance" {{ request('sort')==='balance' ? 'selected' : '' }}>Balance (Low to High)
                    </option>
                    <option value="customer_name" {{ request('sort')==='customer_name' ? 'selected' : '' }}>Name (A-Z)
                    </option>
                    <option value="-customer_name" {{ request('sort')==='-customer_name' ? 'selected' : '' }}>Name (Z-A)
                    </option>
                    <option value="-total_debits" {{ request('sort')==='-total_debits' ? 'selected' : '' }}>Total Credit
                        Sales (High)</option>
                    <option value="-ledger_entries_count" {{ request('sort')==='-ledger_entries_count' ? 'selected' : ''
                        }}>Transactions (Most)</option>
                </select>
            </div>

            <div>
                <x-label for="per_page" value="Show Per Page" />
                <select id="per_page" name="per_page"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="10" {{ request('per_page')==10 ? 'selected' : '' }}>10</option>
                    <option value="25" {{ request('per_page')==25 ? 'selected' : '' }}>25</option>
                    <option value="50" {{ request('per_page', 50)==50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ request('per_page')==100 ? 'selected' : '' }}>100</option>
                    <option value="250" {{ request('per_page')==250 ? 'selected' : '' }}>250</option>
                </select>
            </div>
        </div>
    </x-filter-section>

    {{-- Summary Cards --}}

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
                <div class="text-sm text-gray-500">Total Credit Sales</div>
                <div class="text-2xl font-bold text-blue-700">₨ {{ number_format($totals->total_debits ?? 0, 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
                <div class="text-sm text-gray-500">Total Recoveries</div>
                <div class="text-2xl font-bold text-green-700">₨ {{ number_format($totals->total_credits ?? 0, 2) }}
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-orange-500">
                <div class="text-sm text-gray-500">Outstanding Balance</div>
                <div class="text-2xl font-bold text-orange-700">₨ {{ number_format(($totals->total_debits ?? 0) -
                    ($totals->total_credits ?? 0), 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
                <div class="text-sm text-gray-500">Total Customers</div>
                <div class="text-2xl font-bold text-purple-700">{{ $customers->total() }}</div>
            </div>
        </div>
    </div>

    <x-data-table :items="$customers" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Customer'],
        ['label' => 'City'],
        ['label' => 'Total Credit Sales', 'align' => 'text-right'],
        ['label' => 'Total Recoveries', 'align' => 'text-right'],
        ['label' => 'Outstanding Balance', 'align' => 'text-right'],
        ['label' => 'Transactions', 'align' => 'text-center'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No creditors found.">
        @foreach ($customers as $index => $customer)
        @php
        $balance = ($customer->total_debits ?? 0) - ($customer->total_credits ?? 0);
        @endphp
        <tr class="border-b border-gray-200 text-sm hover:bg-gray-50">
            <td class="py-2 px-2 text-center">
                {{ $customers->firstItem() + $index }}
            </td>
            <td class="py-2 px-2">
                <a href="{{ route('reports.creditors-ledger.customer-ledger', $customer) }}"
                    class="text-blue-600 hover:text-blue-800 font-semibold" target="_blank">
                    {{ $customer->customer_name }}
                </a>
                <div class="text-xs text-gray-500 font-mono">{{ $customer->customer_code }}</div>
                @if($customer->business_name)
                <div class="text-xs text-gray-600">{{ $customer->business_name }}</div>
                @endif
            </td>
            <td class="py-2 px-2">
                {{ $customer->city ?? '—' }}
            </td>
            <td class="py-2 px-2 text-right font-mono text-blue-700">
                {{ number_format($customer->total_debits ?? 0, 2) }}
            </td>
            <td class="py-2 px-2 text-right font-mono text-green-700">
                {{ number_format($customer->total_credits ?? 0, 2) }}
            </td>
            <td
                class="py-2 px-2 text-right font-mono font-bold {{ $balance > 0 ? 'text-orange-700' : 'text-green-700' }}">
                {{ number_format($balance, 2) }}
            </td>
            <td class="py-2 px-2 text-center">
                <span class="px-2 py-1 text-xs font-semibold bg-gray-100 text-gray-700 rounded-full">
                    {{ $customer->ledger_entries_count }}
                </span>
            </td>
            <td class="py-2 px-2 text-center">
                <div class="flex justify-center gap-2">
                    <a href="{{ route('reports.creditors-ledger.customer-ledger', $customer) }}"
                        class="inline-flex items-center px-2 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700"
                        title="View Ledger" target="_blank">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </a>
                    <a href="{{ route('reports.creditors-ledger.customer-credit-sales', $customer) }}"
                        class="inline-flex items-center px-2 py-1 bg-orange-600 text-white text-xs rounded hover:bg-orange-700"
                        title="Credit Sales" target="_blank">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </a>
                    <a href="{{ route('customers.show', $customer) }}"
                        class="inline-flex items-center px-2 py-1 bg-gray-600 text-white text-xs rounded hover:bg-gray-700"
                        title="Customer Profile" target="_blank">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </a>
                </div>
            </td>
        </tr>
        @endforeach
        <tr class="border-t-2 border-gray-400 bg-gray-100 font-bold">
            <td colspan="3" class="py-2 px-2 text-right">
                Page Total ({{ $customers->count() }} customers):
            </td>
            <td class="py-2 px-2 text-right font-mono text-blue-700">
                {{ number_format($customers->sum('total_debits'), 2) }}
            </td>
            <td class="py-2 px-2 text-right font-mono text-green-700">
                {{ number_format($customers->sum('total_credits'), 2) }}
            </td>
            <td class="py-2 px-2 text-right font-mono text-orange-700">
                {{ number_format($customers->sum('total_debits') - $customers->sum('total_credits'), 2) }}
            </td>
            <td colspan="2"></td>
        </tr>
    </x-data-table>
</x-app-layout>