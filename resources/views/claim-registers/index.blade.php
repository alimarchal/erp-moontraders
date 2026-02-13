<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Claim Register" :createRoute="route('claim-registers.create')" createLabel="Add Claim"
            createPermission="claim-register-create" :showSearch="true" :showRefresh="true" backRoute="dashboard" />
    </x-slot>

    <x-filter-section :action="route('claim-registers.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Supplier --}}
            <div>
                <x-label for="filter_supplier_id" value="Supplier" />
                <select id="filter_supplier_id" name="filter[supplier_id]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Suppliers</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}"
                            {{ request('filter.supplier_id') == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->supplier_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Transaction Type --}}
            <div>
                <x-label for="filter_transaction_type" value="Transaction Type" />
                <select id="filter_transaction_type" name="filter[transaction_type]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Types</option>
                    <option value="claim" {{ request('filter.transaction_type') === 'claim' ? 'selected' : '' }}>Claim (Debit)</option>
                    <option value="recovery" {{ request('filter.transaction_type') === 'recovery' ? 'selected' : '' }}>Recovery (Credit)</option>
                </select>
            </div>

            {{-- Reference Number --}}
            <div>
                <x-label for="filter_reference_number" value="Reference Number" />
                <x-input id="filter_reference_number" name="filter[reference_number]" type="text"
                    class="mt-1 block w-full" :value="request('filter.reference_number')" placeholder="ST-25-28" />
            </div>

            {{-- Claim Month --}}
            <div>
                <x-label for="filter_claim_month" value="Claim Month" />
                <x-input id="filter_claim_month" name="filter[claim_month]" type="text" class="mt-1 block w-full"
                    :value="request('filter.claim_month')" placeholder="June-Aug, September" />
            </div>

            {{-- Transaction Date From --}}
            <div>
                <x-label for="filter_transaction_date_from" value="Txn Date (From)" />
                <x-input id="filter_transaction_date_from" name="filter[transaction_date_from]" type="date"
                    class="mt-1 block w-full" :value="request('filter.transaction_date_from')" />
            </div>

            {{-- Transaction Date To --}}
            <div>
                <x-label for="filter_transaction_date_to" value="Txn Date (To)" />
                <x-input id="filter_transaction_date_to" name="filter[transaction_date_to]" type="date"
                    class="mt-1 block w-full" :value="request('filter.transaction_date_to')" />
            </div>

            {{-- Adjusted Date From --}}
            <div>
                <x-label for="filter_adjusted_date_from" value="Adjusted Date (From)" />
                <x-input id="filter_adjusted_date_from" name="filter[adjusted_date_from]" type="date"
                    class="mt-1 block w-full" :value="request('filter.adjusted_date_from')" />
            </div>

            {{-- Adjusted Date To --}}
            <div>
                <x-label for="filter_adjusted_date_to" value="Adjusted Date (To)" />
                <x-input id="filter_adjusted_date_to" name="filter[adjusted_date_to]" type="date"
                    class="mt-1 block w-full" :value="request('filter.adjusted_date_to')" />
            </div>

            {{-- Claim Period Start --}}
            <div>
                <x-label for="filter_claim_month_start" value="Period Start (From)" />
                <x-input id="filter_claim_month_start" name="filter[claim_month_start]" type="date"
                    class="mt-1 block w-full" :value="request('filter.claim_month_start')" />
            </div>

            {{-- Claim Period End --}}
            <div>
                <x-label for="filter_claim_month_end" value="Period End (To)" />
                <x-input id="filter_claim_month_end" name="filter[claim_month_end]" type="date"
                    class="mt-1 block w-full" :value="request('filter.claim_month_end')" />
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$claims" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Supplier'],
        ['label' => 'Txn Date', 'align' => 'text-center'],
        ['label' => 'Reference'],
        ['label' => 'Description'],
        ['label' => 'Claim Month', 'align' => 'text-center'],
        ['label' => 'Debit', 'align' => 'text-right'],
        ['label' => 'Credit', 'align' => 'text-right'],
        ['label' => 'Balance', 'align' => 'text-right'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No claim registers found." :emptyRoute="route('claim-registers.create')"
        emptyLinkText="Add a claim">
        @php $runningBalance = $openingBalance ?? 0; @endphp

        {{-- Opening Balance Row --}}
        @if (request('filter.transaction_date_from') && ($openingBalance ?? 0) != 0)
            <tr class="bg-yellow-50 font-semibold border-b border-gray-300">
                <td class="py-2 px-2 text-right" colspan="6">Opening Balance</td>
                <td class="py-2 px-2 text-right">{{ $openingBalance > 0 ? number_format($openingBalance, 2) : '-' }}</td>
                <td class="py-2 px-2 text-right">{{ $openingBalance < 0 ? number_format(abs($openingBalance), 2) : '-' }}</td>
                <td class="py-2 px-2 text-right font-bold {{ $openingBalance > 0 ? 'text-green-700' : ($openingBalance < 0 ? 'text-red-700' : '') }}">
                    {{ number_format($openingBalance, 2) }}
                </td>
                <td class="py-2 px-2"></td>
            </tr>
        @endif

        @foreach ($claims as $index => $claim)
            @php
                $debit = (float) $claim->debit;
                $credit = (float) $claim->credit;
                $runningBalance += $debit - $credit;
            @endphp
            <tr class="border-b border-gray-200 text-sm">
                <td class="py-1 px-2 text-center">
                    {{ $claims->firstItem() + $index }}
                </td>
                <td class="py-1 px-2 font-semibold whitespace-nowrap">
                    {{ $claim->supplier?->short_name ?? '-' }}
                </td>
                <td class="py-1 px-2 text-center whitespace-nowrap">
                    {{ $claim->transaction_date->format('d-m-Y') }}
                </td>
                <td class="py-1 px-2">
                    {{ $claim->reference_number ?? '-' }}
                </td>
                <td class="py-1 px-2">
                    {{ $claim->description ?? '-' }}
                </td>
                <td class="py-1 px-2 text-center whitespace-nowrap">
                    {{ $claim->claim_month ?? '-' }}
                </td>
                <td class="py-1 px-2 text-right whitespace-nowrap">
                    {{ $debit > 0 ? number_format($debit, 2) : '-' }}
                </td>
                <td class="py-1 px-2 text-right whitespace-nowrap">
                    {{ $credit > 0 ? number_format($credit, 2) : '-' }}
                </td>
                <td class="py-1 px-2 text-right whitespace-nowrap font-bold {{ $runningBalance > 0 ? 'text-green-700' : ($runningBalance < 0 ? 'text-red-700' : '') }}">
                    {{ number_format($runningBalance, 2) }}
                </td>
                <td class="py-1 px-2 text-center">
                    <div class="flex justify-center space-x-2">
                        <a href="{{ route('claim-registers.show', $claim) }}"
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
                        @if (!$claim->isPosted())
                            @can('claim-register-edit')
                                <a href="{{ route('claim-registers.edit', $claim) }}"
                                    class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors duration-150"
                                    title="Edit">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </a>
                            @endcan
                            @role('super-admin')
                                <form method="POST" action="{{ route('claim-registers.destroy', $claim) }}"
                                    onsubmit="return confirm('Are you sure you want to delete this claim?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                        class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-md transition-colors duration-150"
                                        title="Delete">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </form>
                            @endrole
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-app-layout>
