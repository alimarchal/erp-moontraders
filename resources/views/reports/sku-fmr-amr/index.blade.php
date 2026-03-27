<x-app-layout>
    <x-slot name="header">
        <x-page-header title="SKU-wise FMR vs AMR Report" :createRoute="null" createLabel="" :showSearch="true"
            :showRefresh="true" backRoute="reports.index" />
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

    <x-filter-section :action="route('reports.sku-fmr-amr.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {{-- Supplier --}}
            <div>
                <x-label for="filter_supplier_id" value="{{ __('Supplier') }}" />
                <select id="filter_supplier_id" name="filter[supplier_id]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ $selectedSupplierId == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->supplier_name }} ({{ $supplier->short_name }})
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Type --}}
            <div>
                <x-label for="filter_type" value="{{ __('Type') }}" />
                <select id="filter_type" name="filter[type]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="all" {{ $typeFilter === 'all' ? 'selected' : '' }}>All (Liquid & Powder)</option>
                    <option value="liquid" {{ $typeFilter === 'liquid' ? 'selected' : '' }}>Liquid Only</option>
                    <option value="powder" {{ $typeFilter === 'powder' ? 'selected' : '' }}>Powder Only</option>
                </select>
            </div>

            {{-- SKU / Products --}}
            <div>
                <x-label for="filter_product_ids" value="SKU / Product(s)" class="pb-1" />
                <select id="filter_product_ids" name="filter[product_ids][]" multiple
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    @foreach($allProducts as $product)
                        <option value="{{ $product->id }}"
                            {{ in_array($product->id, $selectedProductIds) ? 'selected' : '' }}>
                            {{ $product->product_code }} — {{ $product->product_name }}
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1">Leave empty to show all SKUs</p>
            </div>

            {{-- Start Date --}}
            <div>
                <x-label for="filter_start_date" value="{{ __('Start Date (From)') }}" />
                <x-input id="filter_start_date" class="block mt-1 w-full" type="date" name="filter[start_date]"
                    :value="$startDate" />
            </div>

            {{-- End Date --}}
            <div>
                <x-label for="filter_end_date" value="{{ __('End Date (To)') }}" />
                <x-input id="filter_end_date" class="block mt-1 w-full" type="date" name="filter[end_date]"
                    :value="$endDate" />
            </div>
        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16 mt-4">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                <p class="text-center font-extrabold mb-2">
                    Moon Traders<br>
                    SKU-WISE FMR VS AMR REPORT<br>
                    @if($selectedSupplier)
                        <span class="text-sm font-semibold">
                            SUPPLIER: {{ $selectedSupplier->supplier_name }}
                        </span><br>
                    @endif
                    <span class="text-sm font-semibold">
                        Period: {{ \Carbon\Carbon::parse($startDate)->format('d.m.Y') }} — {{ \Carbon\Carbon::parse($endDate)->format('d.m.Y') }}
                        @if($typeFilter !== 'all') | Type: {{ ucfirst($typeFilter) }} @endif
                        @if(!empty($selectedProductIds)) | Filtered SKUs: {{ count($selectedProductIds) }} @endif
                    </span><br>
                    <span class="print-only print-info text-xs text-center">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                @if($reportData->isEmpty())
                    <p class="text-center text-gray-500 py-8 italic">No SKUs found for the selected supplier and filters.</p>
                @else
                    <table class="report-table">
                        <thead>
                            <tr class="bg-gray-50">
                                <th style="width: 40px;">Sr#</th>
                                <th style="width: 280px;">Product</th>
                                <th style="width: 65px;">Type</th>
                                <th style="width: 110px;">FMR<br><span class="font-normal text-xs">(from GRN)</span></th>
                                <th style="width: 110px;">AMR Liquid<br><span class="font-normal text-xs">(Settlement)</span></th>
                                <th style="width: 110px;">AMR Powder<br><span class="font-normal text-xs">(Settlement)</span></th>
                                <th style="width: 110px;">Total AMR</th>
                                <th style="width: 120px;">Net Benefit/(Cost)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($reportData as $index => $row)
                                <tr>
                                    <td class="text-center" style="vertical-align: middle;">{{ $index + 1 }}</td>
                                    <td style="vertical-align: middle;">{{ $row->product_name }}</td>
                                    <td class="text-center" style="vertical-align: middle;">
                                        <span class="{{ $row->is_powder ? 'text-yellow-700' : 'text-blue-600' }} text-xs font-semibold">
                                            {{ $row->type_label }}
                                        </span>
                                    </td>
                                    <td class="text-right font-mono" style="vertical-align: middle;">{{ number_format($row->fmr_amount, 2) }}</td>
                                    <td class="text-right font-mono" style="vertical-align: middle;">{{ number_format($row->amr_liquid_amount, 2) }}</td>
                                    <td class="text-right font-mono" style="vertical-align: middle;">{{ number_format($row->amr_powder_amount, 2) }}</td>
                                    <td class="text-right font-mono" style="vertical-align: middle;">{{ number_format($row->total_amr, 2) }}</td>
                                    <td class="text-right font-mono font-bold {{ $row->difference >= 0 ? 'text-green-600' : 'text-red-600' }}" style="vertical-align: middle;">
                                        {{ number_format($row->difference, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-100 font-extrabold">
                            <tr>
                                <td colspan="3" class="text-center px-2 py-1">Total:</td>
                                <td class="text-right font-mono px-2 py-1">{{ number_format($grandTotals->fmr_amount, 2) }}</td>
                                <td class="text-right font-mono px-2 py-1">{{ number_format($grandTotals->amr_liquid_amount, 2) }}</td>
                                <td class="text-right font-mono px-2 py-1">{{ number_format($grandTotals->amr_powder_amount, 2) }}</td>
                                <td class="text-right font-mono px-2 py-1">{{ number_format($grandTotals->total_amr, 2) }}</td>
                                <td class="text-right font-mono px-2 py-1 {{ $grandTotals->difference >= 0 ? 'text-green-600' : 'text-red-600' }}">
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
                $('#filter_supplier_id').select2({
                    width: '100%',
                    placeholder: 'Select Supplier',
                    allowClear: false
                });

                $('#filter_type').select2({
                    width: '100%',
                    allowClear: false
                });

                $('#filter_product_ids').select2({
                    width: '100%',
                    placeholder: 'All SKUs',
                    allowClear: true
                });
            });
        </script>
    @endpush
</x-app-layout>
