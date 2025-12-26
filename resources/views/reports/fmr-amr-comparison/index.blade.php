<x-app-layout>
    <x-slot name="header">
        <x-page-header title="FMR vs AMR Comparison" :createRoute="null" createLabel="" :showSearch="true"
            :showRefresh="true" backRoute="reports.index" />
    </x-slot>

    <x-filter-section :action="route('reports.fmr-amr-comparison.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <x-label for="filter_start_date" value="Start Date (From)" />
                <x-input id="filter_start_date" name="filter[start_date]" type="date" class="mt-1 block w-full"
                    :value="$startDate" />
            </div>

            <div>
                <x-label for="filter_end_date" value="End Date (To)" />
                <x-input id="filter_end_date" name="filter[end_date]" type="date" class="mt-1 block w-full"
                    :value="$endDate" />
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$reportData" :headers="[
        ['label' => 'Sr#', 'align' => 'text-center'],
        ['label' => 'Month - Year'],
        ['label' => 'FMR (4210)', 'align' => 'text-right'],
        ['label' => 'AMR Powder (5252)', 'align' => 'text-right'],
        ['label' => 'AMR Liquid (5262)', 'align' => 'text-right'],
        ['label' => 'Difference (AMR - FMR)', 'align' => 'text-right'],
    ]" emptyMessage="No data found for the selected date range.">
        @foreach ($reportData as $index => $row)
        <tr class="border-b border-gray-200 text-sm hover:bg-gray-50">
            <td class="py-2 px-3 text-center">
                {{ $index + 1 }}
            </td>
            <td class="py-2 px-3 font-medium">
                {{ $row->month_year }}
            </td>
            <td class="py-2 px-3 text-right font-mono">
                {{ number_format($row->fmr_total, 2) }}
            </td>
            <td class="py-2 px-3 text-right font-mono">
                {{ number_format($row->amr_powder_total, 2) }}
            </td>
            <td class="py-2 px-3 text-right font-mono">
                {{ number_format($row->amr_liquid_total, 2) }}
            </td>
            <td class="py-2 px-3 text-right font-mono {{ $row->difference >= 0 ? 'text-green-600' : 'text-red-600' }}">
                {{ number_format($row->difference, 2) }}
            </td>
        </tr>
        @endforeach

        @if($reportData->count() > 0)
        <tr class="bg-gray-100 font-semibold text-sm border-t-2 border-gray-300">
            <td class="py-2 px-3 text-center" colspan="2">
                Grand Total
            </td>
            <td class="py-2 px-3 text-right font-mono">
                {{ number_format($grandTotals->fmr_total, 2) }}
            </td>
            <td class="py-2 px-3 text-right font-mono">
                {{ number_format($grandTotals->amr_powder_total, 2) }}
            </td>
            <td class="py-2 px-3 text-right font-mono">
                {{ number_format($grandTotals->amr_liquid_total, 2) }}
            </td>
            <td class="py-2 px-3 text-right font-mono {{ $grandTotals->difference >= 0 ? 'text-green-600' : 'text-red-600' }}">
                {{ number_format($grandTotals->difference, 2) }}
            </td>
        </tr>
        @endif
    </x-data-table>
</x-app-layout>
