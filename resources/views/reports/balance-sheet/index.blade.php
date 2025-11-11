<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Balance Sheet" :createRoute="null" createLabel="" :showSearch="true" :showRefresh="true"
            backRoute="reports.index" />
    </x-slot>

    <x-filter-section :action="route('reports.balance-sheet.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <!-- Period/Date Selection -->
            <div>
                <x-label for="accounting_period_id" value="Accounting Period" />
                <select id="accounting_period_id" name="accounting_period_id"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
                    onchange="this.form.submit()">
                    <option value="">Custom Date</option>
                    @foreach($accountingPeriods as $period)
                    <option value="{{ $period->id }}" {{ $periodId==$period->id ? 'selected' : '' }}>
                        {{ $period->name }} (As of {{ \Carbon\Carbon::parse($period->end_date)->format('M d, Y') }})
                    </option>
                    @endforeach
                </select>
            </div>

            <!-- As Of Date -->
            <div>
                <x-label for="as_of_date" value="As of Date" />
                <x-input id="as_of_date" name="as_of_date" type="date" class="mt-1 block w-full" :value="$asOfDate" />
            </div>

            <!-- Optional Filters (can be hidden by default) -->
            <div>
                <x-label for="filter_account_code" value="Filter by Account Code (Optional)" />
                <x-input id="filter_account_code" name="filter[account_code]" type="text"
                    class="mt-1 block w-full uppercase" :value="request('filter.account_code')"
                    placeholder="e.g., 1000" />
            </div>

            <div>
                <x-label for="filter_account_name" value="Filter by Account Name (Optional)" />
                <x-input id="filter_account_name" name="filter[account_name]" type="text" class="mt-1 block w-full"
                    :value="request('filter.account_name')" placeholder="Search by account name" />
            </div>

            <div>
                <x-label for="filter_account_type" value="Filter by Account Type (Optional)" />
                <x-input id="filter_account_type" name="filter[account_type]" type="text" class="mt-1 block w-full"
                    :value="request('filter.account_type')" placeholder="e.g., Asset" />
            </div>
        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-2 pb-16">
        <x-status-message />
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">

            <!-- Balance Sheet Header -->
            <div class="mb-6 text-center">
                <h2 class="text-2xl font-bold text-gray-800">Balance Sheet</h2>
                <p class="text-lg text-gray-600 mt-2">
                    As of {{ \Carbon\Carbon::parse($asOfDate)->format('F d, Y') }}
                </p>
            </div>

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

            // Calculate totals correctly by account type, not normal balance
            $totalAssets = $accounts->filter(function($item) {
            return str_contains(strtolower($item->account_type), 'asset');
            })->sum('balance');

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
                    <h3 class="text-xl font-bold mb-4 text-gray-800 border-b-2 border-gray-300 pb-2">
                        ASSETS
                    </h3>
                    @foreach($assets as $accountType => $items)
                    <div class="mb-4">
                        <h4 class="font-semibold text-gray-700 mb-2">{{ $accountType }}</h4>
                        @foreach($items as $account)
                        <div class="flex justify-between py-1 px-2 text-sm">
                            <span class="text-gray-600">
                                <span class="font-mono">{{ $account->account_code }}</span> - {{ $account->account_name
                                }}
                            </span>
                            <span class="font-mono font-semibold">{{ number_format((float) $account->balance, 2)
                                }}</span>
                        </div>
                        @endforeach
                        <div
                            class="flex justify-between py-1 px-2 font-semibold border-t border-gray-200 mt-1">
                            <span>Total {{ $accountType }}</span>
                            <span class="font-mono">{{ number_format((float) $items->sum('balance'), 2) }}</span>
                        </div>
                    </div>
                    @endforeach
                    <div
                        class="flex justify-between py-2 px-2 font-bold text-lg border-t-2 border-gray-400 mt-4 bg-blue-50">
                        <span>TOTAL ASSETS</span>
                        <span class="font-mono">{{ number_format($totalAssets, 2) }}</span>
                    </div>
                </div>

                <!-- Liabilities & Equity Section -->
                <div>
                    <h3 class="text-xl font-bold mb-4 text-gray-800 border-b-2 border-gray-300 pb-2">
                        LIABILITIES & EQUITY
                    </h3>

                    <!-- Liabilities -->
                    @foreach($liabilities as $accountType => $items)
                    <div class="mb-4">
                        <h4 class="font-semibold text-gray-700 mb-2">{{ $accountType }}</h4>
                        @foreach($items as $account)
                        <div class="flex justify-between py-1 px-2 text-sm">
                            <span class="text-gray-600">
                                <span class="font-mono">{{ $account->account_code }}</span> - {{ $account->account_name
                                }}
                            </span>
                            <span class="font-mono font-semibold">{{ number_format((float) $account->balance, 2)
                                }}</span>
                        </div>
                        @endforeach
                        <div
                            class="flex justify-between py-1 px-2 font-semibold border-t border-gray-200 mt-1">
                            <span>Total {{ $accountType }}</span>
                            <span class="font-mono">{{ number_format((float) $items->sum('balance'), 2) }}</span>
                        </div>
                    </div>
                    @endforeach

                    <!-- Equity -->
                    @foreach($equity as $accountType => $items)
                    <div class="mb-4">
                        <h4 class="font-semibold text-gray-700 mb-2">{{ $accountType }}</h4>
                        @foreach($items as $account)
                        <div class="flex justify-between py-1 px-2 text-sm">
                            <span class="text-gray-600">
                                <span class="font-mono">{{ $account->account_code }}</span> - {{ $account->account_name
                                }}
                            </span>
                            <span class="font-mono font-semibold">{{ number_format((float) $account->balance, 2)
                                }}</span>
                        </div>
                        @endforeach
                        <div
                            class="flex justify-between py-1 px-2 font-semibold border-t border-gray-200 mt-1">
                            <span>Total {{ $accountType }}</span>
                            <span class="font-mono">{{ number_format((float) $items->sum('balance'), 2) }}</span>
                        </div>
                    </div>
                    @endforeach

                    <!-- Current Period Net Income -->
                    <div class="mb-4">
                        <h4 class="font-semibold text-gray-700 mb-2">Current Period</h4>
                        <div class="flex justify-between py-1 px-2 text-sm">
                            <span class="text-gray-600">
                                <span class="font-mono">NET</span> - Net Income (Current Period)
                            </span>
                            <span
                                class="font-mono font-semibold {{ $netIncome >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ number_format((float) $netIncome, 2) }}
                            </span>
                        </div>
                    </div>

                    <div
                        class="flex justify-between py-2 px-2 font-bold text-lg border-t-2 border-gray-400 mt-4 bg-blue-50">
                        <span>TOTAL LIABILITIES & EQUITY</span>
                        <span class="font-mono">{{ number_format($totalLiabilities + $totalEquity + $netIncome, 2)
                            }}</span>
                    </div>
                </div>
            </div>

            <!-- Balance Check -->
            @php
            $difference = abs($totalAssets - ($totalLiabilities + $totalEquity + $netIncome));
            @endphp
            @if($difference > 0.01)
            <div class="mt-6 p-4 bg-red-100 border border-red-400 rounded">
                <p class="text-red-700 font-semibold">
                    ⚠️ Balance Sheet does not balance! Difference: {{ number_format($difference, 2) }}
                </p>
                <p class="text-red-600 text-sm mt-2">
                    Assets: {{ number_format($totalAssets, 2) }} |
                    Liabilities + Equity + Net Income: {{ number_format($totalLiabilities + $totalEquity + $netIncome,
                    2) }}
                </p>
            </div>
            @else
            <div class="mt-6 p-4 bg-green-100 border border-green-400 rounded">
                <p class="text-green-700 font-semibold text-center">
                    ✓ Balance Sheet is balanced
                </p>
                <p class="text-green-600 text-sm text-center mt-1">
                    Assets: {{ number_format($totalAssets, 2) }} = Liabilities: {{ number_format($totalLiabilities, 2)
                    }} + Equity: {{ number_format($totalEquity, 2) }} + Net Income: {{ number_format($netIncome, 2) }}
                </p>
            </div>
            @endif

        </div>
    </div>
</x-app-layout>