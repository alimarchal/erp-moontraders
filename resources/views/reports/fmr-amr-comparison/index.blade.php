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

                .no-print-section {
                    display: none !important;
                    visibility: hidden !important;
                    height: 0 !important;
                    overflow: hidden !important;
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
                    font-size: 10px !important;
                    width: 100% !important;
                }

                .report-table th,
                .report-table td {
                    padding: 2px 3px !important;
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

                .grid.md\:grid-cols-2 {
                    display: grid !important;
                    grid-template-columns: 1fr 1fr !important;
                    gap: 8px !important;
                }
            }

            /* Select2 Styling to match Tailwind Inputs */
            .select2-container .select2-selection--multiple {
                min-height: 42px !important;
                border-color: #d1d5db !important;
                border-radius: 0.375rem !important;
                padding-top: 4px !important;
                padding-bottom: 4px !important;
            }

            .select2-container--default.select2-container--focus .select2-selection--multiple {
                border-color: #6366f1 !important;
                box-shadow: 0 0 0 1px #6366f1 !important;
            }
        </style>
    @endpush

    <x-filter-section :action="route('reports.fmr-amr-comparison.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-4 gap-4">
            {{-- Supplier Filter --}}
            <div>
                <x-label for="supplier_id" value="{{ __('Supplier') }}" />
                <select id="supplier_id" name="supplier_id"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Suppliers</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ $supplierId == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->supplier_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Start Date --}}
            <div>
                <x-label for="start_date" value="{{ __('Start Date') }}" />
                <x-input id="start_date" class="block mt-1 w-full" type="date" name="start_date" :value="$startDate" />
            </div>

            {{-- End Date --}}
            <div>
                <x-label for="end_date" value="{{ __('End Date') }}" />
                <x-input id="end_date" class="block mt-1 w-full" type="date" name="end_date" :value="$endDate" />
            </div>

            {{-- Source Filter --}}
            <div>
                <x-label for="source" value="{{ __('Source') }}" />
                <select id="source" name="source"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="all" {{ $sourceFilter === 'all' ? 'selected' : '' }}>All Sources</option>
                    <option value="grn" {{ $sourceFilter === 'grn' ? 'selected' : '' }}>Supplier GRN</option>
                    <option value="settlement" {{ $sourceFilter === 'settlement' ? 'selected' : '' }}>Sales Settlement
                    </option>
                </select>
            </div>
        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 mt-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                @if($reportData->isEmpty())
                    <p class="text-center text-gray-500 py-8">
                        Please select a supplier and date range, then click Search to generate the report.
                    </p>
                @else
                    <p class="text-center font-extrabold mb-2">
                        Moon Traders<br>
                        FMR vs AMR Comparison Report<br>
                        <span class="text-sm font-semibold">Source: {{ $selectedSourceLabel }} | Supplier:
                            {{ $selectedSupplierName }}</span><br>
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
                                <th class="text-center" style="width: 40px;">#</th>
                                <th class="text-center" style="width: 100px;">Date</th>
                                <th class="text-center" style="width: 120px;">Invoice #<br>(GRN)</th>
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
                                    <td class="text-center" style="vertical-align: middle;">{{ $row->date }}</td>
                                    <td class="text-center" style="vertical-align: middle;">
                                        @if($row->grn_id)
                                            <a href="{{ route('goods-receipt-notes.show', $row->grn_id) }}" target="_blank"
                                                class="grn-link">{{ $row->invoice_number }}</a>
                                        @else
                                            {{ $row->invoice_number }}
                                        @endif
                                    </td>
                                    <td class="text-right font-mono" style="vertical-align: middle;">
                                        {{ is_null($row->fmr_liquid_total) ? '-' : number_format($row->fmr_liquid_total, 2) }}
                                    </td>
                                    <td class="text-right font-mono" style="vertical-align: middle;">
                                        {{ is_null($row->fmr_powder_total) ? '-' : number_format($row->fmr_powder_total, 2) }}
                                    </td>
                                    <td class="text-right font-mono" style="vertical-align: middle;">
                                        {{ is_null($row->amr_liquid_total) ? '-' : number_format($row->amr_liquid_total, 2) }}
                                    </td>
                                    <td class="text-right font-mono" style="vertical-align: middle;">
                                        {{ is_null($row->amr_powder_total) ? '-' : number_format($row->amr_powder_total, 2) }}
                                    </td>
                                    <td class="text-right font-mono {{ !$row->is_empty ? ($row->difference >= 0 ? 'text-green-600' : 'text-red-600') : '' }}"
                                        style="vertical-align: middle; font-weight: bold;">
                                        {{ is_null($row->difference) ? '-' : number_format($row->difference, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-100 font-extrabold">
                            <tr>
                                <td colspan="3" class="text-center px-2 py-1">Grand Total</td>
                                <td class="text-right font-mono px-2 py-1">
                                    {{ number_format($grandTotals->fmr_liquid_total, 2) }}
                                </td>
                                <td class="text-right font-mono px-2 py-1">
                                    {{ number_format($grandTotals->fmr_powder_total, 2) }}
                                </td>
                                <td class="text-right font-mono px-2 py-1">
                                    {{ number_format($grandTotals->amr_liquid_total, 2) }}
                                </td>
                                <td class="text-right font-mono px-2 py-1">
                                    {{ number_format($grandTotals->amr_powder_total, 2) }}
                                </td>
                                <td
                                    class="text-right font-mono px-2 py-1 {{ $grandTotals->difference >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ number_format($grandTotals->difference, 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                @endif
            </div>
        </div>
    </div>


    @push('scripts')
        <script>
            $(document).ready(function () {
                $('#supplier_id').select2({
                    width: '100%',
                    placeholder: 'All Suppliers',
                    allowClear: true
                });
            });
        </script>
    @endpush
</x-app-layout>