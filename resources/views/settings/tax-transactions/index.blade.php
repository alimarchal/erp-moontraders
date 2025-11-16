<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Tax Transactions" :createRoute="null"
            createLabel="" :showSearch="true" :showRefresh="true" backRoute="settings.index" />
    </x-slot>

    <x-filter-section :action="route('tax-transactions.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_tax_code_id" value="Tax Code" />
                <select id="filter_tax_code_id" name="filter[tax_code_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Tax Codes</option>
                    @foreach ($taxCodes as $taxCode)
                    <option value="{{ $taxCode->id }}" {{ request('filter.tax_code_id')==$taxCode->id ? 'selected' : '' }}>
                        {{ $taxCode->tax_code }} - {{ $taxCode->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_tax_direction" value="Tax Direction" />
                <select id="filter_tax_direction" name="filter[tax_direction]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Directions</option>
                    <option value="payable" {{ request('filter.tax_direction')==='payable' ? 'selected' : '' }}>Payable</option>
                    <option value="receivable" {{ request('filter.tax_direction')==='receivable' ? 'selected' : '' }}>Receivable</option>
                </select>
            </div>

            <div>
                <x-label for="filter_date_from" value="Date From" />
                <x-input id="filter_date_from" name="filter[date_from]" type="date" class="mt-1 block w-full"
                    :value="request('filter.date_from')" />
            </div>

            <div>
                <x-label for="filter_date_to" value="Date To" />
                <x-input id="filter_date_to" name="filter[date_to]" type="date" class="mt-1 block w-full"
                    :value="request('filter.date_to')" />
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$transactions" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Date', 'align' => 'text-center'],
        ['label' => 'Tax Code'],
        ['label' => 'Taxable Amount', 'align' => 'text-right'],
        ['label' => 'Tax Rate', 'align' => 'text-right'],
        ['label' => 'Tax Amount', 'align' => 'text-right'],
        ['label' => 'Direction', 'align' => 'text-center'],
        ['label' => 'Source Type'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No tax transactions found." :emptyRoute="null"
        emptyLinkText="">
        @foreach ($transactions as $index => $transaction)
        <tr class="border-b border-gray-200 text-sm">
            <td class="py-1 px-2 text-center">
                {{ $transactions->firstItem() + $index }}
            </td>
            <td class="py-1 px-2 text-center">
                {{ $transaction->transaction_date?->format('Y-m-d') }}
            </td>
            <td class="py-1 px-2 font-semibold">
                {{ $transaction->taxCode->tax_code ?? 'N/A' }}
            </td>
            <td class="py-1 px-2 text-right">
                {{ number_format($transaction->taxable_amount, 2) }}
            </td>
            <td class="py-1 px-2 text-right">
                {{ number_format($transaction->tax_rate, 2) }}%
            </td>
            <td class="py-1 px-2 text-right font-semibold">
                {{ number_format($transaction->tax_amount, 2) }}
            </td>
            <td class="py-1 px-2 text-center">
                <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full
                        @class([
                            'bg-red-100 text-red-700' => $transaction->tax_direction === 'payable',
                            'bg-green-100 text-green-700' => $transaction->tax_direction === 'receivable',
                        ])">
                    {{ ucfirst($transaction->tax_direction) }}
                </span>
            </td>
            <td class="py-1 px-2">
                {{ class_basename($transaction->taxable_type) ?? 'N/A' }}
            </td>
            <td class="py-1 px-2 text-center">
                <div class="flex justify-center space-x-2">
                    <a href="{{ route('tax-transactions.show', $transaction) }}"
                        class="inline-flex items-center justify-center w-8 h-8 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded-md transition-colors duration-150"
                        title="View">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </a>
                </div>
            </td>
        </tr>
        @endforeach
    </x-data-table>
</x-app-layout>
