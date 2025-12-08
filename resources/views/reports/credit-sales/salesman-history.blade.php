<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Salesman Credit Sales History" :createRoute="null" createLabel="" :showSearch="true"
            :showRefresh="true" backRoute="reports.index" />
    </x-slot>

    <x-filter-section :action="route('reports.credit-sales.salesman-history')">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <x-label for="search" value="Search Salesman" />
                <x-input id="search" name="search" type="text" class="mt-1 block w-full"
                    placeholder="Name or employee code" :value="request('search')" />
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$salesmen" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Salesman'],
        ['label' => 'Supplier'],
        ['label' => 'Total Credit Sales', 'align' => 'text-right'],
        ['label' => 'Number of Sales', 'align' => 'text-right'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No credit sales found for any salesman">
        @foreach ($salesmen as $index => $salesman)
        <tr class="border-b border-gray-200 text-sm hover:bg-gray-50">
            <td class="py-1 px-2 text-center">
                {{ $salesmen->firstItem() + $index }}
            </td>
            <td class="py-1 px-2">
                <div class="font-semibold text-gray-900">{{ $salesman->full_name }}</div>
                <div class="text-xs text-gray-500">{{ $salesman->employee_code }}</div>
            </td>
            <td class="py-1 px-2">
                <div class="text-gray-900">{{ $salesman->supplier->supplier_name ?? 'N/A' }}</div>
                <div class="text-xs text-gray-500">{{ $salesman->supplier->supplier_code ?? '-' }}</div>
            </td>
            <td class="py-1 px-2 text-right">
                <span class="font-mono font-bold text-orange-700">
                    {{ number_format($salesman->credit_sales_sum_sale_amount, 2) }}
                </span>
            </td>
            <td class="py-1 px-2 text-right">
                <span class="px-2 py-1 text-sm font-semibold bg-blue-100 text-blue-800 rounded-full">
                    {{ $salesman->credit_sales_count }}
                </span>
            </td>
            <td class="py-1 px-2 text-center">
                <a href="{{ route('reports.credit-sales.salesman-details', $salesman) }}"
                    class="text-indigo-600 hover:text-indigo-900 font-semibold">
                    View Details
                </a>
            </td>
        </tr>
        @endforeach
        @if($salesmen->count() > 0)
        <tr class="border-t-2 border-gray-400 bg-gray-100 font-bold">
            <td colspan="3" class="py-2 px-2 text-right">
                Page Total ({{ $salesmen->count() }} rows):
            </td>
            <td class="py-2 px-2 text-right font-mono text-orange-700">
                {{ number_format($salesmen->sum('credit_sales_sum_sale_amount'), 2) }}
            </td>
            <td class="py-2 px-2 text-right">
                <span class="px-2 py-1 text-sm font-bold bg-blue-200 text-blue-900 rounded-full">
                    {{ $salesmen->sum('credit_sales_count') }}
                </span>
            </td>
            <td></td>
        </tr>
        @endif
    </x-data-table>
</x-app-layout>