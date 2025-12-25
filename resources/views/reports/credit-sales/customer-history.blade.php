<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Customer Credit Sales History" :createRoute="null" createLabel="" :showSearch="true"
            :showRefresh="true" backRoute="reports.index" />
    </x-slot>

    <x-filter-section :action="route('reports.credit-sales.customer-history')">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <x-label for="search" value="Search Customer" />
                <x-input id="search" name="search" type="text" class="mt-1 block w-full"
                    placeholder="Name, code, or phone" :value="request('search')" />
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$customers" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Customer'],
        ['label' => 'Contact'],
        ['label' => 'Total Credit Sales', 'align' => 'text-right'],
        ['label' => 'Recoveries', 'align' => 'text-right'],
        ['label' => 'Balance', 'align' => 'text-right'],
        ['label' => 'Number of Sales', 'align' => 'text-right'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No credit sales found for any customer">
        @foreach ($customers as $index => $customer)
        <tr class="border-b border-gray-200 text-sm hover:bg-gray-50">
            <td class="py-1 px-2 text-center">
                {{ $customers->firstItem() + $index }}
            </td>
            <td class="py-1 px-2">
                <div class="font-semibold text-gray-900">{{ $customer->customer_name }}</div>
                <div class="text-xs text-gray-500">{{ $customer->customer_code }}</div>
                @if($customer->business_name)
                <div class="text-xs text-gray-600">{{ $customer->business_name }}</div>
                @endif
            </td>
            <td class="py-1 px-2">
                <div class="text-gray-900">{{ $customer->phone ?? '-' }}</div>
                <div class="text-xs text-gray-500">{{ $customer->city ?? '-' }}</div>
            </td>
            <td class="py-1 px-2 text-right">
                <span class="font-mono font-bold text-orange-700">
                    {{ number_format($customer->credit_sales_sum_sale_amount, 2) }}
                </span>
            </td>
            <td class="py-1 px-2 text-right">
                <span class="font-mono text-gray-700">
                    {{ number_format($customer->recoveries_sum_amount, 2) }}
                </span>
            </td>
            <td class="py-1 px-2 text-right">
                <span class="font-mono font-semibold text-gray-900">
                    {{ number_format($customer->credit_sales_sum_sale_amount - $customer->recoveries_sum_amount, 2) }}
                </span>
            </td>
            <td class="py-1 px-2 text-right">
                <span class="px-2 py-1 text-sm font-semibold bg-blue-100 text-blue-800 rounded-full">
                    {{ $customer->credit_sales_count }}
                </span>
            </td>
            <td class="py-1 px-2 text-center">
                <a href="{{ route('reports.credit-sales.customer-details', $customer) }}"
                    class="text-indigo-600 hover:text-indigo-900 font-semibold">
                    View Details
                </a>
            </td>
        </tr>
        @endforeach
        @if($customers->count() > 0)
        <tr class="border-t-2 border-gray-400 bg-gray-100 font-bold">
            <td colspan="3" class="py-2 px-2 text-right">
                Page Total ({{ $customers->count() }} rows):
            </td>
            <td class="py-2 px-2 text-right font-mono text-orange-700">
                {{ number_format($customers->sum('credit_sales_sum_sale_amount'), 2) }}
            </td>
            <td class="py-2 px-2 text-right font-mono text-gray-700">
                {{ number_format($customers->sum('recoveries_sum_amount'), 2) }}
            </td>
            <td class="py-2 px-2 text-right font-mono text-gray-900">
                {{ number_format($customers->sum('credit_sales_sum_sale_amount') - $customers->sum('recoveries_sum_amount'), 2) }}
            </td>
            <td class="py-2 px-2 text-right">
                <span class="px-2 py-1 text-sm font-bold bg-blue-200 text-blue-900 rounded-full">
                    {{ $customers->sum('credit_sales_count') }}
                </span>
            </td>
            <td></td>
        </tr>
        @endif
    </x-data-table>
</x-app-layout>
