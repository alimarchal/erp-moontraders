@push('header')
    <style>
        @media print {
            @page {
                size: landscape;
                margin: 10mm;
            }

            body {
                margin: 0;
                padding: 0;
                font-size: 10px;
            }

            .print\:hidden {
                display: none !important;
            }

            .hidden.print\:block {
                display: block !important;
            }

            table {
                width: 100% !important;
                border-collapse: collapse;
                font-size: 9px;
                page-break-inside: auto;
            }

            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            thead {
                display: table-header-group;
            }

            tfoot {
                display: table-footer-group;
            }

            th,
            td {
                padding: 2px 4px !important;
            }

            .text-green-600,
            .text-red-600 {
                color: #000 !important;
            }

            .hover\:bg-gray-50:hover {
                background-color: transparent !important;
            }
        }
    </style>
@endpush

<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <x-page-header title="FMR vs AMR Comparison" :createRoute="null" createLabel="" :showSearch="true"
                :showRefresh="true" backRoute="reports.index" />
            <button onclick="window.print()"
                class="print:hidden inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition"
                title="Print Report">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z">
                    </path>
                </svg>
                Print
            </button>
        </div>
    </x-slot>

    <div class="print:hidden">
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
    </div>

    <div class="hidden print:block mb-6 text-center">
        <h1 class="text-2xl font-bold mb-2">FMR vs AMR Comparison Report</h1>
        <p class="text-sm font-semibold">Period: {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} to
            {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}
        </p>
        <p class="text-xs mt-1">Printed on: {{ now()->format('d M Y h:i A') }}</p>
    </div>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
            @if ($reportData->count() > 0)
                <div class="relative overflow-x-auto rounded-lg">
                    <table class="min-w-max w-full table-auto text-sm">
                        <thead>
                            <tr class="bg-green-800 text-white uppercase text-sm">
                                <th class="py-2 px-2 text-center">Sr#</th>
                                <th class="py-2 px-2 text-left">Month - Year</th>
                                <th class="py-2 px-2 text-right">FMR Liquid (4210)</th>
                                <th class="py-2 px-2 text-right">FMR Powder (4220)</th>
                                <th class="py-2 px-2 text-right">AMR Liquid (5262)</th>
                                <th class="py-2 px-2 text-right">AMR Powder (5252)</th>
                                <th class="py-2 px-2 text-right">
                                    <span
                                        title="FMR received minus AMR paid. Positive = Net Benefit, Negative = Net Cost">Net
                                        Benefit/(Cost)</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="text-black text-md leading-normal font-extrabold">
                            @foreach ($reportData as $index => $row)
                                <tr class="border-b border-gray-200 text-sm hover:bg-gray-50">
                                    <td class="py-2 px-3 text-center">
                                        {{ $index + 1 }}
                                    </td>
                                    <td class="py-2 px-3 font-medium">
                                        {{ $row->month_year }}
                                    </td>
                                    <td class="py-2 px-3 text-right font-mono">
                                        {{ number_format($row->fmr_liquid_total, 2) }}
                                    </td>
                                    <td class="py-2 px-3 text-right font-mono">
                                        {{ number_format($row->fmr_powder_total, 2) }}
                                    </td>
                                    <td class="py-2 px-3 text-right font-mono">
                                        {{ number_format($row->amr_liquid_total, 2) }}
                                    </td>
                                    <td class="py-2 px-3 text-right font-mono">
                                        {{ number_format($row->amr_powder_total, 2) }}
                                    </td>
                                    <td
                                        class="py-2 px-3 text-right font-mono {{ $row->difference >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ number_format($row->difference, 2) }}
                                    </td>
                                </tr>
                            @endforeach

                            <tr class="bg-gray-100 font-semibold text-sm border-t-2 border-gray-300">
                                <td class="py-2 px-3 text-center" colspan="2">Grand Total</td>
                                <td class="py-2 px-3 text-right font-mono">
                                    {{ number_format($grandTotals->fmr_liquid_total, 2) }}
                                </td>
                                <td class="py-2 px-3 text-right font-mono">
                                    {{ number_format($grandTotals->fmr_powder_total, 2) }}
                                </td>
                                <td class="py-2 px-3 text-right font-mono">
                                    {{ number_format($grandTotals->amr_liquid_total, 2) }}
                                </td>
                                <td class="py-2 px-3 text-right font-mono">
                                    {{ number_format($grandTotals->amr_powder_total, 2) }}
                                </td>
                                <td
                                    class="py-2 px-3 text-right font-mono {{ $grandTotals->difference >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ number_format($grandTotals->difference, 2) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-gray-700 text-center py-4">
                    No data found for the selected date range.
                </p>
            @endif
        </div>
    </div>
</x-app-layout>