<x-app-layout>
    <x-slot name="header">
        <x-page-header title="FMR vs AMR Comparison" :showSearch="true" :showRefresh="true" backRoute="reports.index" />
    </x-slot>

    @push('header')
        <style>
            .report-table {
                width: 100%;
                border-collapse: collapse;
                border: 1px solid black;
                font-size: 14px;
                line-height: 1.2;
            }

            .report-table th,
            .report-table td {
                border: 1px solid black;
                padding: 3px 4px;
                word-wrap: break-word;
            }

            .print-only {
                display: none;
            }

            @media print {
                @page {
                    margin: 15mm 10mm 20mm 10mm;

                    @bottom-center {
                        content: "Page " counter(page) " of " counter(pages);
                    }
                }

                .no-print {
                    display: none !important;
                }

                body {
                    margin: 0 !important;
                    padding: 0 !important;
                    counter-reset: page 1;
                }

                .max-w-7xl {
                    max-width: 100% !important;
                    width: 100% !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }

                .bg-white {
                    margin: 0 !important;
                    padding: 10px !important;
                    box-shadow: none !important;
                }

                .overflow-x-auto {
                    overflow: visible !important;
                }

                .report-table {
                    font-size: 11px !important;
                    width: 100% !important;
                }

                .report-table th,
                .report-table td {
                    padding: 2px 3px !important;
                    color: #000 !important;
                }

                .text-green-600,
                .text-red-600 {
                    color: #000 !important;
                }

                p {
                    margin-top: 0 !important;
                    margin-bottom: 8px !important;
                }

                .print-info {
                    font-size: 9px !important;
                    margin-top: 5px !important;
                    margin-bottom: 10px !important;
                    color: #000 !important;
                }

                .print-only {
                    display: block !important;
                }

                .page-footer {
                    display: none;
                }
            }
        </style>
    @endpush

    <x-filter-section :action="route('reports.fmr-amr-comparison.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <x-label for="filter_supplier_ids" value="Supplier(s)" />
                <select id="filter_supplier_ids" name="filter[supplier_ids][]" multiple
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ in_array($supplier->id, $selectedSupplierIds) ? 'selected' : '' }}>
                            {{ $supplier->supplier_name }} ({{ $supplier->short_name }})
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1">Leave empty to show all suppliers</p>
            </div>

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

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 mt-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                <p class="text-center font-extrabold mb-2">
                    Moon Traders<br>
                    FMR vs AMR Comparison Report<br>
                    <span class="text-sm font-semibold">Supplier: {{ $selectedSupplierNames }}</span><br>
                    For the period {{ \Carbon\Carbon::parse($startDate)->format('d-M-Y') }} to
                    {{ \Carbon\Carbon::parse($endDate)->format('d-M-Y') }}
                    <br>
                    <span class="print-only print-info text-xs text-center">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                <table class="report-table">
                    <thead>
                        <tr class="bg-gray-50">
                            <th style="width: 40px;">Sr#</th>
                            <th style="width: 150px;">Supplier</th>
                            <th style="width: 120px;">Month - Year</th>
                            <th style="width: 100px;">FMR Liquid<br>(4210)</th>
                            <th style="width: 100px;">FMR Powder<br>(4220)</th>
                            <th style="width: 100px;">AMR Liquid<br>(5262)</th>
                            <th style="width: 100px;">AMR Powder<br>(5252)</th>
                            <th style="width: 120px;">Net Benefit/(Cost)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $counter = 1; @endphp
                        @foreach ($reportData as $row)
                            <tr>
                                <td class="text-center" style="vertical-align: middle;">{{ $counter++ }}</td>
                                <td style="vertical-align: middle;">{{ $row->supplier_name }}</td>
                                <td style="vertical-align: middle;">{{ $row->month_year }}</td>
                                <td class="text-right font-mono" style="vertical-align: middle;">
                                    {{ number_format($row->fmr_liquid_total, 2) }}
                                </td>
                                <td class="text-right font-mono" style="vertical-align: middle;">
                                    {{ number_format($row->fmr_powder_total, 2) }}
                                </td>
                                <td class="text-right font-mono" style="vertical-align: middle;">
                                    {{ number_format($row->amr_liquid_total, 2) }}
                                </td>
                                <td class="text-right font-mono" style="vertical-align: middle;">
                                    {{ number_format($row->amr_powder_total, 2) }}
                                </td>
                                <td class="text-right  font-mono {{ $row->difference >= 0 ? 'text-green-600' : 'text-red-600' }}"
                                    style="vertical-align: middle; font-weight: bold;">
                                    {{ number_format($row->difference, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-100 font-extrabold">
                        <tr>
                            <td colspan="3" class="text-center px-2 py-1">Grand Total</td>
                            <td class="text-right  font-mono px-2 py-1">
                                {{ number_format($grandTotals->fmr_liquid_total, 2) }}
                            </td>
                            <td class="text-right  font-mono px-2 py-1">
                                {{ number_format($grandTotals->fmr_powder_total, 2) }}
                            </td>
                            <td class="text-right  font-mono px-2 py-1">
                                {{ number_format($grandTotals->amr_liquid_total, 2) }}
                            </td>
                            <td class="text-right  font-mono px-2 py-1">
                                {{ number_format($grandTotals->amr_powder_total, 2) }}
                            </td>
                            <td
                                class="text-right  font-mono px-2 py-1 {{ $grandTotals->difference >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ number_format($grandTotals->difference, 2) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>