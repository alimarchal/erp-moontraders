<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Sales Settlements" :createRoute="route('sales-settlements.create')"
            createLabel="New Settlement" :showSearch="true" :showRefresh="true" />
    </x-slot>

    <x-filter-section :action="route('sales-settlements.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_settlement_number" value="Settlement Number" />
                <x-input id="filter_settlement_number" name="filter[settlement_number]" type="text"
                    class="mt-1 block w-full" :value="request('filter.settlement_number')"
                    placeholder="SETTLE-2025-0001" />
            </div>

            <div>
                <x-label for="filter_status" value="Status" />
                <select id="filter_status" name="filter[status]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Statuses</option>
                    <option value="draft" {{ request('filter.status') === 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="posted" {{ request('filter.status') === 'posted' ? 'selected' : '' }}>Posted</option>
                </select>
            </div>

            <div>
                <x-label for="filter_settlement_date_from" value="Settlement Date From" />
                <x-input id="filter_settlement_date_from" name="filter[settlement_date_from]" type="date"
                    class="mt-1 block w-full" :value="request('filter.settlement_date_from')" />
            </div>

            <div>
                <x-label for="filter_settlement_date_to" value="Settlement Date To" />
                <x-input id="filter_settlement_date_to" name="filter[settlement_date_to]" type="date"
                    class="mt-1 block w-full" :value="request('filter.settlement_date_to')" />
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$settlements" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Settlement Number'],
        ['label' => 'Date', 'align' => 'text-center'],
        ['label' => 'Salesman'],
        ['label' => 'Vehicle'],
        ['label' => 'Total Sales', 'align' => 'text-right'],
        ['label' => 'Credit Sale', 'align' => 'text-right'],
        ['label' => 'Cheque Sale', 'align' => 'text-right'],
        ['label' => 'Bank Sale', 'align' => 'text-right'],
        ['label' => 'Cash Sale', 'align' => 'text-right'],
        ['label' => 'Profit', 'align' => 'text-right'],
        ['label' => 'Status', 'align' => 'text-center'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No sales settlements found."
        :emptyRoute="route('sales-settlements.create')" emptyLinkText="Create a Settlement">

        @foreach ($settlements as $index => $settlement)
            <tr class="border-b border-gray-200 text-sm">
                <td class="py-1 px-2 text-center">
                    {{ $settlements->firstItem() + $index }}
                </td>
                <td class="py-1 px-2">
                    <div class="font-semibold text-gray-900">
                        {{ $settlement->settlement_number }}
                    </div>
                    <div class="text-xs text-gray-500">
                        GI: {{ $settlement->goodsIssue->issue_number }}
                    </div>
                </td>
                <td class="py-1 px-2 text-center">
                    {{ \Carbon\Carbon::parse($settlement->settlement_date)->format('d M Y') }}
                </td>
                <td class="py-1 px-2">
                    {{ $settlement->employee->full_name }}
                </td>
                <td class="py-1 px-2">
                    {{ $settlement->vehicle->vehicle_number }}
                </td>
                <td class="py-1 px-2 text-right">
                    <div class="font-semibold text-gray-900">
                        Rs {{ number_format($settlement->total_sales_amount, 2) }}
                    </div>
                </td>
                <td class="py-1 px-2 text-right">
                    <div class="text-xs text-orange-700">
                        Rs {{ number_format($settlement->credit_sales_amount, 2) }}
                    </div>
                </td>
                <td class="py-1 px-2 text-right">
                    <div class="text-xs text-blue-700">
                        Rs {{ number_format($settlement->cheque_sales_amount, 2) }}
                    </div>
                </td>
                <td class="py-1 px-2 text-right">
                    <div class="text-xs text-indigo-700">
                        Rs {{ number_format($settlement->bank_sales_amount, 2) }}
                    </div>
                </td>
                <td class="py-1 px-2 text-right">
                    <div class="text-xs text-green-700">
                        Rs {{ number_format($settlement->cash_sales_amount, 2) }}
                    </div>
                </td>
                @php
                    $netProfit = ($settlement->gross_profit ?? 0) - ($settlement->expenses_claimed ?? 0);
                @endphp
                <td class="py-1 px-2 text-right">
                    <div class="text-xs text-blue-700">
                        GP: Rs {{ number_format($settlement->gross_profit ?? 0, 2) }}
                    </div>
                    <div class="text-xs text-orange-600">
                        Exp: Rs {{ number_format($settlement->expenses_claimed ?? 0, 2) }}
                    </div>
                    <div class="font-semibold {{ $netProfit > 0 ? 'text-green-700' : 'text-red-700' }}">
                        Net: Rs {{ number_format($netProfit, 2) }}
                    </div>
                    <div class="text-xs text-gray-500">
                        COGS: Rs {{ number_format($settlement->total_cogs ?? 0, 2) }}
                    </div>
                </td>
                <td class="py-1 px-2 text-center">
                    <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-semibold rounded-full
                                {{ $settlement->status === 'draft' ? 'bg-gray-200 text-gray-700' : '' }}
                                {{ $settlement->status === 'posted' ? 'bg-emerald-100 text-emerald-700' : '' }}">
                        {{ ucfirst($settlement->status) }}
                    </span>
                </td>
                <td class="py-1 px-2 text-center">
                    <a href="{{ route('sales-settlements.show', $settlement) }}"
                        class="text-blue-600 hover:text-blue-900 mr-2">View</a>
                    @if ($settlement->status === 'draft')
                        <a href="{{ route('sales-settlements.edit', $settlement) }}"
                            class="text-indigo-600 hover:text-indigo-900 mr-2">Edit</a>
                    @endif
                </td>
            </tr>
        @endforeach

    </x-data-table>
</x-app-layout>