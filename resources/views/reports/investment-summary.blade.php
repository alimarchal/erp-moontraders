<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Investment Summary" :createRoute="null" createLabel="" :showSearch="true"
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

    <x-filter-section :action="route('reports.investment-summary.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-4 gap-4">
            {{-- Date --}}
            <div>
                <x-label for="date" value="{{ __('Date') }}" />
                <x-input id="date" class="block mt-1 w-full" type="date" name="date" :value="$date" required />
            </div>

            {{-- Supplier Filter --}}
            <div>
                <x-label for="supplier_id" value="{{ __('Supplier') }}" />
                <select id="supplier_id" name="supplier_id" class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Suppliers</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ $supplierId == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->supplier_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Designation Filter --}}
            <div>
                <x-label for="designation" value="{{ __('Designation') }}" />
                <select id="designation" name="designation" class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Designations</option>
                    @foreach($designations as $desig)
                        <option value="{{ $desig }}" {{ $designation == $desig ? 'selected' : '' }}>
                            {{ $desig }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Multi-Select Salesman --}}
            <div>
                <x-label for="employee_ids" value="Salesman (Multi-Select)" class="pb-1" />
                <select id="employee_ids" name="employee_ids[]" multiple
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    @foreach($allEmployees as $employee)
                        <option value="{{ $employee->id }}" {{ in_array($employee->id, $employeeIds) ? 'selected' : '' }}>
                            {{ $employee->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16 mt-4">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                {{-- Report Header --}}
                <p class="text-center font-extrabold mb-2">
                    SHAHZAIN TRADERS - MUZAFFRABAD<br>
                    INVESTMENT SUMMARY<br>
                    @if($selectedSupplier)
                        <span class="text-sm font-semibold">
                            DISTRIBUTOR: {{ $selectedSupplier->supplier_name }}
                        </span><br>
                    @endif
                    <span class="text-sm font-semibold">
                        Date: {{ \Carbon\Carbon::parse($date)->format('d.m.Y') }}
                    </span><br>
                    <span class="print-only print-info text-xs text-center">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                {{-- Part 1: Salesman Credit Table --}}
                <table class="report-table tabular-nums mb-4">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="text-center w-10 px-1 py-0.5">Sr#</th>
                            <th class="text-left px-1 py-0.5">Salesman Name</th>
                            <th class="text-right px-1 py-0.5">Opening Credit</th>
                            <th class="text-right px-1 py-0.5">Credit</th>
                            <th class="text-right px-1 py-0.5">Recovery</th>
                            <th class="text-right px-1 py-0.5">Total Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($salesmanCreditData as $index => $salesman)
                            <tr>
                                <td class="text-center px-1 py-0.5">{{ $loop->iteration }}</td>
                                <td class="px-1 py-0.5">{{ $salesman->name }}</td>
                                <td class="text-right px-1 py-0.5">{{ number_format($salesman->opening_credit, 0) }}</td>
                                <td class="text-right px-1 py-0.5">{{ number_format($salesman->credit_amount, 0) }}</td>
                                <td class="text-right px-1 py-0.5">{{ number_format($salesman->recovery_amount, 0) }}</td>
                                <td class="text-right font-bold px-1 py-0.5">{{ number_format($salesman->total_credit, 0) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center italic text-gray-500 px-1 py-0.5">No data found</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-50 font-bold">
                        <tr>
                            <td colspan="2" class="text-right px-1 py-0.5 border-t border-black">Total:</td>
                            <td class="text-right px-1 py-0.5 border-t border-black">{{ number_format($creditGrandTotals->opening_credit, 0) }}</td>
                            <td class="text-right px-1 py-0.5 border-t border-black">{{ number_format($creditGrandTotals->credit_amount, 0) }}</td>
                            <td class="text-right px-1 py-0.5 border-t border-black">{{ number_format($creditGrandTotals->recovery_amount, 0) }}</td>
                            <td class="text-right px-1 py-0.5 border-t border-black">{{ number_format($creditGrandTotals->total_credit, 0) }}</td>
                        </tr>
                    </tfoot>
                </table>

                {{-- Part 2: Investment Summary --}}
                <div class="mt-6">
                    <table class="report-table tabular-nums" style="max-width: 500px;">
                        <thead>
                            <tr class="bg-gray-100">
                                <th colspan="2" class="text-center px-2 py-1 text-base font-extrabold">
                                    SHAHZAIN TRADERS INVESTMENT
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="px-2 py-1">Investment {{ \Carbon\Carbon::parse($previousDate)->format('d.m.Y') }}</td>
                                <td class="text-right px-2 py-1 font-bold">{{ number_format($previousTotal, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="px-2 py-1">Powder Expiry</td>
                                <td class="text-right px-2 py-1">{{ number_format($powderExpiry, 0) }}</td>
                            </tr>
                            <tr>
                                <td class="px-2 py-1">Liquid Expiry</td>
                                <td class="text-right px-2 py-1">{{ number_format($liquidExpiry, 0) }}</td>
                            </tr>
                            <tr>
                                <td class="px-2 py-1">Claim Amount</td>
                                <td class="text-right px-2 py-1">{{ number_format($claimAmount, 0) }}</td>
                            </tr>
                            <tr>
                                <td class="px-2 py-1">Stock Amount</td>
                                <td class="text-right px-2 py-1">{{ number_format($stockAmount, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="px-2 py-1">Credit Amount</td>
                                <td class="text-right px-2 py-1">{{ number_format($creditAmount, 0) }}</td>
                            </tr>
                            <tr>
                                <td class="px-2 py-1">Ledger Amount</td>
                                <td class="text-right px-2 py-1">{{ number_format($ledgerAmount, 0) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50 font-extrabold">
                            <tr>
                                <td class="px-2 py-1 border-t-2 border-black">Total:</td>
                                <td class="text-right px-2 py-1 border-t-2 border-black">{{ number_format($currentTotal, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>

                    {{-- Comparison with Previous Date --}}
                    <div class="mt-4 p-3 border border-gray-300 rounded-md" style="max-width: 500px;">
                        <h4 class="font-bold text-sm mb-2">Comparison with {{ \Carbon\Carbon::parse($previousDate)->format('d-M-Y') }}</h4>
                        <div class="grid grid-cols-2 gap-2 text-sm tabular-nums">
                            <div>Previous Total:</div>
                            <div class="text-right font-bold">{{ number_format($previousTotal, 2) }}</div>

                            <div>Current Total:</div>
                            <div class="text-right font-bold">{{ number_format($currentTotal, 2) }}</div>

                            <div class="border-t border-gray-300 pt-1">Difference:</div>
                            <div class="text-right font-bold border-t border-gray-300 pt-1 {{ $difference >= 0 ? 'text-red-600' : 'text-green-600' }}">
                                {{ $difference >= 0 ? '+' : '' }}{{ number_format($difference, 2) }}
                            </div>

                            <div>Change (%):</div>
                            <div class="text-right font-bold {{ $differencePercent >= 0 ? 'text-red-600' : 'text-green-600' }}">
                                {{ $differencePercent >= 0 ? '+' : '' }}{{ number_format($differencePercent, 2) }}%
                            </div>
                        </div>
                    </div>
                </div>
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

                $('#designation').select2({
                    width: '100%',
                    placeholder: 'All Designations',
                    allowClear: true
                });

                $('#employee_ids').select2({
                    width: '100%',
                    placeholder: 'Select Salesmen',
                    allowClear: true
                });
            });
        </script>
    @endpush
</x-app-layout>
