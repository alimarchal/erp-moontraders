<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Customer Ledger - {{ $customer->customer_name }}" :createRoute="null" createLabel=""
            :showSearch="true" :showRefresh="true" backRoute="reports.creditors-ledger.index" />
    </x-slot>

    {{-- Customer Info Card --}}
    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mb-6">
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <p class="text-sm text-gray-500">Customer Code</p>
                    <p class="font-semibold font-mono">{{ $customer->customer_code }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Customer Name</p>
                    <p class="font-semibold">{{ $customer->customer_name }}</p>
                    @if($customer->business_name)
                    <p class="text-xs text-gray-600">{{ $customer->business_name }}</p>
                    @endif
                </div>
                <div>
                    <p class="text-sm text-gray-500">Contact</p>
                    <p class="font-semibold">{{ $customer->phone ?? 'N/A' }}</p>
                    <p class="text-xs text-gray-500">{{ $customer->city ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Credit Limit</p>
                    <p class="font-semibold">₨ {{ number_format($customer->credit_limit ?? 0, 2) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Current Balance</p>
                    <p
                        class="text-xl font-bold {{ $summary['closing_balance'] > 0 ? 'text-orange-700' : 'text-green-700' }}">
                        ₨ {{ number_format($summary['closing_balance'], 2) }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <x-filter-section :action="route('reports.creditors-ledger.customer-ledger', $customer)">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <x-label for="filter_date_from" value="Date From" />
                <x-input id="filter_date_from" name="filter[date_from]" type="date" class="mt-1 block w-full"
                    :value="$dateFrom" />
            </div>

            <div>
                <x-label for="filter_date_to" value="Date To" />
                <x-input id="filter_date_to" name="filter[date_to]" type="date" class="mt-1 block w-full"
                    :value="$dateTo" />
            </div>

            <div>
                <x-label for="filter_transaction_type" value="Transaction Type" />
                <select id="filter_transaction_type" name="filter[transaction_type]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Types</option>
                    @foreach($transactionTypes as $type)
                    <option value="{{ $type }}" {{ request('filter.transaction_type')===$type ? 'selected' : '' }}>
                        {{ ucwords(str_replace('_', ' ', $type)) }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="per_page" value="Show Per Page" />
                <select id="per_page" name="per_page"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="25" {{ request('per_page')==25 ? 'selected' : '' }}>25</option>
                    <option value="50" {{ request('per_page')==50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ request('per_page', 100)==100 ? 'selected' : '' }}>100</option>
                    <option value="250" {{ request('per_page')==250 ? 'selected' : '' }}>250</option>
                </select>
            </div>
        </div>
    </x-filter-section>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-gray-500">
            <div class="text-sm text-gray-500">Opening Balance</div>
            <div class="text-xl font-bold text-gray-700">₨ {{ number_format($summary['opening_balance'], 2) }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
            <div class="text-sm text-gray-500">Total Debits (Credit Sales)</div>
            <div class="text-xl font-bold text-blue-700">₨ {{ number_format($summary['total_debits'], 2) }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
            <div class="text-sm text-gray-500">Total Credits (Recoveries)</div>
            <div class="text-xl font-bold text-green-700">₨ {{ number_format($summary['total_credits'], 2) }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-orange-500">
            <div class="text-sm text-gray-500">Closing Balance</div>
            <div class="text-xl font-bold {{ $summary['closing_balance'] > 0 ? 'text-orange-700' : 'text-green-700' }}">
                ₨ {{ number_format($summary['closing_balance'], 2) }}
            </div>
        </div>
    </div>

    <x-data-table :items="$entries" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Date'],
        ['label' => 'Type'],
        ['label' => 'Reference'],
        ['label' => 'Description'],
        ['label' => 'Salesman'],
        ['label' => 'Debit', 'align' => 'text-right'],
        ['label' => 'Credit', 'align' => 'text-right'],
        ['label' => 'Balance', 'align' => 'text-right'],
    ]" emptyMessage="No ledger entries found.">
        @if($summary['opening_balance'] != 0)
        <tr class="border-b border-gray-300 bg-gray-50 font-semibold">
            <td class="py-2 px-2 text-center">-</td>
            <td class="py-2 px-2" colspan="5">Opening Balance</td>
            <td class="py-2 px-2 text-right font-mono">-</td>
            <td class="py-2 px-2 text-right font-mono">-</td>
            <td
                class="py-2 px-2 text-right font-mono font-bold {{ $summary['opening_balance'] > 0 ? 'text-orange-700' : 'text-green-700' }}">
                {{ number_format($summary['opening_balance'], 2) }}
            </td>
        </tr>
        @endif
        @foreach ($entries as $index => $entry)
        <tr class="border-b border-gray-200 text-sm hover:bg-gray-50">
            <td class="py-2 px-2 text-center">
                {{ $entries->firstItem() + $index }}
            </td>
            <td class="py-2 px-2 whitespace-nowrap">
                {{ $entry->transaction_date->format('d-m-Y') }}
            </td>
            <td class="py-2 px-2">
                @php
                $typeColors = [
                'credit_sale' => 'bg-blue-100 text-blue-800',
                'cash_recovery' => 'bg-green-100 text-green-800',
                'cheque_recovery' => 'bg-emerald-100 text-emerald-800',
                'bank_recovery' => 'bg-teal-100 text-teal-800',
                ];
                $typeColor = $typeColors[$entry->transaction_type] ?? 'bg-gray-100 text-gray-800';
                @endphp
                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $typeColor }}">
                    {{ ucwords(str_replace('_', ' ', $entry->transaction_type)) }}
                </span>
            </td>
            <td class="py-2 px-2">
                @if($entry->salesSettlement)
                <a href="{{ route('sales-settlements.show', $entry->salesSettlement) }}"
                    class="text-blue-600 hover:text-blue-800 font-semibold" target="_blank">
                    {{ $entry->reference_number }}
                </a>
                @else
                {{ $entry->reference_number ?? '—' }}
                @endif
            </td>
            <td class="py-2 px-2">
                <div class="max-w-xs truncate" title="{{ $entry->description }}">
                    {{ $entry->description ?? '—' }}
                </div>
                @if($entry->notes)
                <div class="text-xs text-gray-500 truncate" title="{{ $entry->notes }}">{{ $entry->notes }}</div>
                @endif
            </td>
            <td class="py-2 px-2">
                @if($entry->employee)
                <div class="text-sm">{{ $entry->employee->full_name }}</div>
                <div class="text-xs text-gray-500">{{ $entry->employee->employee_code }}</div>
                @else
                <span class="text-gray-400">—</span>
                @endif
            </td>
            <td class="py-2 px-2 text-right font-mono {{ $entry->debit > 0 ? 'text-blue-700' : '' }}">
                {{ $entry->debit > 0 ? number_format($entry->debit, 2) : '—' }}
            </td>
            <td class="py-2 px-2 text-right font-mono {{ $entry->credit > 0 ? 'text-green-700' : '' }}">
                {{ $entry->credit > 0 ? number_format($entry->credit, 2) : '—' }}
            </td>
            <td
                class="py-2 px-2 text-right font-mono font-bold {{ $entry->balance > 0 ? 'text-orange-700' : 'text-green-700' }}">
                {{ number_format($entry->balance, 2) }}
            </td>
        </tr>
        @endforeach
        <tr class="border-t-2 border-gray-400 bg-gray-100 font-bold">
            <td colspan="6" class="py-2 px-2 text-right">
                Page Total ({{ $entries->count() }} entries):
            </td>
            <td class="py-2 px-2 text-right font-mono text-blue-700">
                {{ number_format($entries->sum('debit'), 2) }}
            </td>
            <td class="py-2 px-2 text-right font-mono text-green-700">
                {{ number_format($entries->sum('credit'), 2) }}
            </td>
            <td class="py-2 px-2 text-right font-mono text-orange-700">
                {{ number_format($summary['closing_balance'], 2) }}
            </td>
        </tr>
    </x-data-table>
</x-app-layout>