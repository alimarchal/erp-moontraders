<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Account Balances" :createRoute="null" createLabel="" :showSearch="true"
            :showRefresh="true" backRoute="reports.index" />
    </x-slot>

    <x-filter-section :action="route('reports.account-balances.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="lg:col-span-2">
                <x-label for="accounting_period_id" value="Accounting Period" />
                <select id="accounting_period_id" name="accounting_period_id"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
                    onchange="this.form.submit()">
                    <option value="">All Time (Custom Date)</option>
                    @foreach($accountingPeriods as $period)
                    <option value="{{ $period->id }}" {{ $periodId==$period->id ? 'selected' : '' }}>
                        {{ $period->name }} ({{ \Carbon\Carbon::parse($period->start_date)->format('M d, Y') }} - {{
                        \Carbon\Carbon::parse($period->end_date)->format('M d, Y') }})
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="as_of_date" value="As of Date" />
                <x-input id="as_of_date" name="as_of_date" type="date" class="mt-1 block w-full" :value="$asOfDate" />
            </div>

            <div>
                <x-label for="per_page" value="Show Per Page" />
                <select id="per_page" name="per_page"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="10" {{ request('per_page')==10 ? 'selected' : '' }}>10</option>
                    <option value="25" {{ request('per_page')==25 ? 'selected' : '' }}>25</option>
                    <option value="50" {{ request('per_page')==50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ request('per_page', 100)==100 ? 'selected' : '' }}>100</option>
                    <option value="250" {{ request('per_page')==250 ? 'selected' : '' }}>250</option>
                </select>
            </div>

            <div>
                <x-label for="filter_account_code" value="Account Code" />
                <select id="filter_account_code" name="filter[account_code]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Accounts</option>
                    @foreach($accounts as $account)
                    <option value="{{ $account->account_code }}" {{ request('filter.account_code')===$account->
                        account_code ? 'selected' : '' }}>
                        {{ $account->account_code }} - {{ $account->account_name }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_account_name" value="Account Name" />
                <select id="filter_account_name" name="filter[account_name]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Accounts</option>
                    @foreach($accounts as $account)
                    <option value="{{ $account->account_name }}" {{ request('filter.account_name')===$account->
                        account_name ? 'selected' : '' }}>
                        {{ $account->account_name }} ({{ $account->account_code }})
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_account_type" value="Account Type" />
                <select id="filter_account_type" name="filter[account_type]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Types</option>
                    @foreach($accountTypes as $type)
                    <option value="{{ $type }}" {{ request('filter.account_type')===$type ? 'selected' : '' }}>
                        {{ $type }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_normal_balance" value="Normal Balance" />
                <select id="filter_normal_balance" name="filter[normal_balance]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    <option value="debit" {{ request('filter.normal_balance')==='debit' ? 'selected' : '' }}>
                        Debit
                    </option>
                    <option value="credit" {{ request('filter.normal_balance')==='credit' ? 'selected' : '' }}>
                        Credit
                    </option>
                </select>
            </div>

            <div>
                <x-label for="filter_is_active" value="Active Status" />
                <select id="filter_is_active" name="filter[is_active]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    <option value="true" {{ request('filter.is_active')==='true' ? 'selected' : '' }}>
                        Active
                    </option>
                    <option value="false" {{ request('filter.is_active')==='false' ? 'selected' : '' }}>
                        Inactive
                    </option>
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
                    <option value="-total_debits" {{ request('sort')=='-total_debits' ? 'selected' : '' }}>Debits
                        (High-Low)</option>
                    <option value="total_debits" {{ request('sort')=='total_debits' ? 'selected' : '' }}>Debits
                        (Low-High)</option>
                    <option value="-total_credits" {{ request('sort')=='-total_credits' ? 'selected' : '' }}>Credits
                        (High-Low)</option>
                    <option value="total_credits" {{ request('sort')=='total_credits' ? 'selected' : '' }}>Credits
                        (Low-High)</option>
                    <option value="-balance" {{ request('sort')=='-balance' ? 'selected' : '' }}>Balance (High-Low)
                    </option>
                    <option value="balance" {{ request('sort')=='balance' ? 'selected' : '' }}>Balance (Low-High)
                    </option>
                </select>
            </div>
        </div>
    </x-filter-section>

    <!-- Account Balances Header -->
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 mb-4">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4 text-center">
            <h3 class="text-xl font-bold text-gray-800">Account Balances</h3>
            <p class="text-gray-600 mt-1">
                As of {{ \Carbon\Carbon::parse($asOfDate)->format('F d, Y') }}
            </p>
        </div>
    </div>

    <x-data-table :items="$balances" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Account Code'],
        ['label' => 'Account Name'],
        ['label' => 'Account Type'],
        ['label' => 'Report Group'],
        ['label' => 'Total Debits', 'align' => 'text-right'],
        ['label' => 'Total Credits', 'align' => 'text-right'],
        ['label' => 'Balance', 'align' => 'text-right'],
        ['label' => 'Status', 'align' => 'text-center'],
    ]" emptyMessage="No account balances found.">
        @foreach ($balances as $index => $balance)
        <tr class="border-b border-gray-200 text-sm">
            <td class="py-1 px-2 text-center">
                {{ $balances->firstItem() + $index }}
            </td>
            <td class="py-1 px-2">
                <div class="font-semibold uppercase">{{ $balance->account_code }}</div>
            </td>
            <td class="py-1 px-2">
                {{ $balance->account_name }}
            </td>
            <td class="py-1 px-2">
                <div class="text-xs">{{ $balance->account_type }}</div>
            </td>
            <td class="py-1 px-2">
                <span
                    class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full 
                    {{ $balance->report_group === 'BalanceSheet' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700' }}">
                    {{ $balance->report_group === 'BalanceSheet' ? 'Balance Sheet' : 'Income Statement' }}
                </span>
            </td>
            <td class="py-1 px-2 text-right font-mono">
                {{ number_format((float) $balance->total_debits, 2) }}
            </td>
            <td class="py-1 px-2 text-right font-mono">
                {{ number_format((float) $balance->total_credits, 2) }}
            </td>
            <td
                class="py-1 px-2 text-right font-mono font-bold {{ $balance->balance < 0 ? 'text-red-600' : 'text-green-600' }}">
                {{ number_format((float) $balance->balance, 2) }}
            </td>
            <td class="py-1 px-2 text-center">
                @if ($balance->is_active)
                <span
                    class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-700">
                    Active
                </span>
                @else
                <span
                    class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600">
                    Inactive
                </span>
                @endif
            </td>
        </tr>
        @endforeach
        <tr class="border-t-2 border-gray-400 bg-gray-100 font-bold">
            <td colspan="5" class="py-2 px-2 text-right">
                Grand Total ({{ $balances->total() }} accounts):
            </td>
            <td class="py-2 px-2 text-right font-mono">
                {{ number_format($balances->sum('total_debits'), 2) }}
            </td>
            <td class="py-2 px-2 text-right font-mono">
                {{ number_format($balances->sum('total_credits'), 2) }}
            </td>
            <td class="py-2 px-2 text-right font-mono">
                {{ number_format($balances->sum('balance'), 2) }}
            </td>
            <td></td>
        </tr>
    </x-data-table>
</x-app-layout>