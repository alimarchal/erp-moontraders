<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Trial Balance" :createRoute="null" createLabel="" :showSearch="true" :showRefresh="true"
            backRoute="reports.index" />
    </x-slot>

    <x-filter-section :action="route('reports.trial-balance.index')">
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

            <!-- Optional Filters -->
            <div>
                <x-label for="filter_account_code" value="Filter by Account (Optional)" />
                <select id="filter_account_code" name="filter[account_code]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Accounts</option>
                    @foreach($accountsList as $account)
                    <option value="{{ $account->account_code }}" {{ request('filter.account_code')===$account->
                        account_code ? 'selected' : '' }}>
                        {{ $account->account_code }} - {{ $account->account_name }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_account_type" value="Filter by Type (Optional)" />
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
        </div>
    </x-filter-section>

    <!-- Trial Balance Summary -->
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 mb-4">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
            <div class="mb-4 text-center">
                <h3 class="text-xl font-bold text-gray-800">Trial Balance</h3>
                <p class="text-gray-600 mt-1">
                    As of {{ \Carbon\Carbon::parse($asOfDate)->format('F d, Y') }}
                </p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <div class="text-sm text-gray-600">Total Debits</div>
                    <div class="text-2xl font-bold font-mono text-blue-700">
                        {{ number_format((float) $trialBalance->total_debits, 2) }}
                    </div>
                </div>
                <div class="bg-green-50 p-4 rounded-lg">
                    <div class="text-sm text-gray-600">Total Credits</div>
                    <div class="text-2xl font-bold font-mono text-green-700">
                        {{ number_format((float) $trialBalance->total_credits, 2) }}
                    </div>
                </div>
                <div
                    class="p-4 rounded-lg {{ abs($trialBalance->difference) < 0.01 ? 'bg-emerald-50' : 'bg-red-50' }}">
                    <div class="text-sm text-gray-600">Difference</div>
                    <div
                        class="text-2xl font-bold font-mono {{ abs($trialBalance->difference) < 0.01 ? 'text-emerald-700' : 'text-red-700' }}">
                        {{ number_format((float) $trialBalance->difference, 2) }}
                    </div>
                    @if(abs($trialBalance->difference) < 0.01) <div
                        class="text-xs text-emerald-600 mt-1">✓ Balanced
                </div>
                @else
                <div class="text-xs text-red-600 mt-1">⚠️ Out of Balance</div>
                @endif
            </div>
        </div>
    </div>
    </div>

    <!-- Detailed Account Balances -->
    <x-data-table :items="$accounts" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Account Code'],
        ['label' => 'Account Name'],
        ['label' => 'Account Type'],
        ['label' => 'Debit Balance', 'align' => 'text-right'],
        ['label' => 'Credit Balance', 'align' => 'text-right'],
    ]" emptyMessage="No account balances found.">
        @foreach ($accounts as $index => $account)
        @php
        $balance = (float) $account->balance;
        // For Trial Balance: show positive balance in Debit column, negative in Credit column
        $debitBalance = $balance > 0 ? $balance : 0;
        $creditBalance = $balance < 0 ? abs($balance) : 0; @endphp <tr
            class="border-b border-gray-200 text-sm">
            <td class="py-1 px-2 text-center">
                {{ $index + 1 }}
            </td>
            <td class="py-1 px-2">
                <div class="font-semibold uppercase font-mono">{{ $account->account_code }}</div>
            </td>
            <td class="py-1 px-2">
                {{ $account->account_name }}
            </td>
            <td class="py-1 px-2">
                <div class="text-xs text-gray-600">{{ $account->account_type }}</div>
            </td>
            <td class="py-1 px-2 text-right font-mono {{ $debitBalance > 0 ? 'font-semibold' : 'text-gray-400' }}">
                {{ $debitBalance > 0 ? number_format($debitBalance, 2) : '-' }}
            </td>
            <td class="py-1 px-2 text-right font-mono {{ $creditBalance > 0 ? 'font-semibold' : 'text-gray-400' }}">
                {{ $creditBalance > 0 ? number_format($creditBalance, 2) : '-' }}
            </td>
            </tr>
            @endforeach
            @php
            $totalDebitBalance = collect($accounts)->sum(function($account) {
            $balance = (float) $account->balance;
            return $balance > 0 ? $balance : 0;
            });
            $totalCreditBalance = collect($accounts)->sum(function($account) {
            $balance = (float) $account->balance;
            return $balance < 0 ? abs($balance) : 0; }); @endphp <tr
                class="border-t-2 border-gray-400 bg-gray-100 font-bold">
                <td colspan="4" class="py-2 px-2 text-right">
                    TOTAL ({{ count($accounts) }} accounts):
                </td>
                <td class="py-2 px-2 text-right font-mono">
                    {{ number_format($totalDebitBalance, 2) }}
                </td>
                <td class="py-2 px-2 text-right font-mono">
                    {{ number_format($totalCreditBalance, 2) }}
                </td>
                </tr>
    </x-data-table>
</x-app-layout>