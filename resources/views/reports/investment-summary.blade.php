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
                            <th class="text-center w-10 px-1 py-0.5">#</th>
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

                {{-- Part 2: Investment Summary & Expenses --}}
                <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- SHAHZAIN TRADERS INVESTMENT Table --}}
                    <div>
                        <table class="report-table tabular-nums w-full">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="text-center w-10 px-1 py-0.5">#</th>
                                    <th colspan="2" class="text-center px-2 py-1 text-base font-extrabold">
                                        SHAHZAIN TRADERS INVESTMENT
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="text-center px-1 py-1">1</td>
                                    <td class="px-2 py-1">Powder Expiry</td>
                                    <td class="text-right px-2 py-1 font-mono">{{ number_format($powderExpiry, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-center px-1 py-1">2</td>
                                    <td class="px-2 py-1">Liquid Expiry</td>
                                    <td class="text-right px-2 py-1 font-mono">{{ number_format($liquidExpiry, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-center px-1 py-1">3</td>
                                    <td class="px-2 py-1">Claim Amount</td>
                                    <td class="text-right px-2 py-1 font-mono">{{ number_format($claimAmount, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-center px-1 py-1">4</td>
                                    <td class="px-2 py-1">Stock Amount</td>
                                    <td class="text-right px-2 py-1 font-mono">{{ number_format($stockAmount, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-center px-1 py-1">5</td>
                                    <td class="px-2 py-1">Credit Amount</td>
                                    <td class="text-right px-2 py-1 font-mono">{{ number_format($creditAmount, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-center px-1 py-1">6</td>
                                    <td class="px-2 py-1">Ledger Amount</td>
                                    <td class="text-right px-2 py-1 font-mono">{{ number_format($ledgerAmount, 2) }}</td>
                                </tr>
                                <tr class="bg-gray-50 font-extrabold">
                                    <td class="text-center px-1 py-1 border-t-2 border-black">7</td>
                                    <td class="px-2 py-1 border-t-2 border-black">Total Main Investment as on {{ $formattedDate }}</td>
                                    <td class="text-right px-2 py-1 border-t-2 border-black font-mono">{{ number_format($currentTotal, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-center px-1 py-1">8</td>
                                    <td class="px-2 py-1">Daily Cash as on {{ $formattedDate }}</td>
                                    <td class="text-right px-2 py-1 font-mono">{{ number_format($dailyCash, 2) }}</td>
                                </tr>
                                <tr class="bg-gray-50 font-bold">
                                    <td class="text-center px-1 py-1 border-t border-black">9</td>
                                    <td class="px-2 py-1 border-t border-black">Total Investment as on {{ $formattedDate }}</td>
                                    <td class="text-right px-2 py-1 border-t border-black font-mono">{{ number_format($totalInvestment, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-center px-1 py-1">10</td>
                                    <td class="px-2 py-1">Total Main Investment as on {{ $formattedPreviousDate }}</td>
                                    <td class="text-right px-2 py-1 font-mono">{{ number_format($previousTotal, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-center px-1 py-1">11</td>
                                    <td class="px-2 py-1">Bank Online as on {{ $formattedDate }}</td>
                                    <td class="text-right px-2 py-1 font-mono">{{ number_format($bankOnline, 2) }}</td>
                                </tr>
                                <tr class="bg-gray-50 font-bold">
                                    <td class="text-center px-1 py-1 border-t border-black">12</td>
                                    <td class="px-2 py-1 border-t border-black">Increase in Investment as on {{ $formattedDate }}</td>
                                    <td class="text-right px-2 py-1 border-t border-black font-mono {{ $increaseInInvestment >= 0 ? 'text-red-600' : 'text-green-600' }}">{{ number_format($increaseInInvestment, 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {{-- Expenses Detail Table --}}
                    <div>
                        <table class="report-table tabular-nums w-full">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="text-center w-10 px-1 py-0.5">#</th>
                                    <th colspan="2" class="text-center px-2 py-1 text-base font-extrabold">
                                        Expenses Detail
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="text-center px-1 py-1">1</td>
                                    <td class="px-2 py-1">Stationary</td>
                                    <td class="text-right px-2 py-1 font-mono">{{ number_format($expenseCategoryTotals['stationary'] ?? 0, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-center px-1 py-1">2</td>
                                    <td class="px-2 py-1">TCS</td>
                                    <td class="text-right px-2 py-1 font-mono">{{ number_format($expenseCategoryTotals['tcs'] ?? 0, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-center px-1 py-1">3</td>
                                    <td class="px-2 py-1">Tonner & IT</td>
                                    <td class="text-right px-2 py-1 font-mono">{{ number_format($expenseCategoryTotals['tonner_it'] ?? 0, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-center px-1 py-1">4</td>
                                    <td class="px-2 py-1">Salaries</td>
                                    <td class="text-right px-2 py-1 font-mono">{{ number_format($expenseCategoryTotals['salaries'] ?? 0, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-center px-1 py-1">5</td>
                                    <td class="px-2 py-1">Fuel</td>
                                    <td class="text-right px-2 py-1 font-mono">{{ number_format($expenseCategoryTotals['fuel'] ?? 0, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-center px-1 py-1">6</td>
                                    <td class="px-2 py-1">Van Work</td>
                                    <td class="text-right px-2 py-1 font-mono">{{ number_format($expenseCategoryTotals['van_work'] ?? 0, 2) }}</td>
                                </tr>
                                <tr class="bg-gray-50 font-extrabold">
                                    <td class="text-center px-1 py-1 border-t-2 border-black">7</td>
                                    <td class="px-2 py-1 border-t-2 border-black">Total Expenses</td>
                                    <td class="text-right px-2 py-1 border-t-2 border-black font-mono">{{ number_format($totalExpensesMonth, 2) }}</td>
                                </tr>
                                @for ($i = 8; $i <= 12; $i++)
                                <tr>
                                    <td class="text-center px-1 py-1">{{ $i }}</td>
                                    <td class="text-center px-2 py-1">-</td>
                                    <td class="text-center px-2 py-1">-</td>
                                </tr>
                                @endfor
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Part 3: Bank/Cash Summary --}}
                <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Bank/Cash Summary Table --}}
                    <div>
                        <table class="report-table tabular-nums w-full">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="text-center w-10 px-1 py-0.5">#</th>
                                    <th colspan="2" class="text-center px-2 py-1 text-base font-extrabold">
                                        Bank / Cash Summary — {{ $currentMonthName }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="text-center px-1 py-1">1</td>
                                    <td class="px-2 py-1">Bank Opening Amount</td>
                                    <td class="text-right px-2 py-1 font-mono">{{ number_format($bankOpeningAmount, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-center px-1 py-1">2</td>
                                    <td class="px-2 py-1">Total Cash Received in Current Month</td>
                                    <td class="text-right px-2 py-1 font-mono">{{ number_format($totalCashReceivedMonth, 2) }}</td>
                                </tr>
                                <tr class="font-bold">
                                    <td class="text-center px-1 py-1 border-t border-black">3</td>
                                    <td class="px-2 py-1 border-t border-black">Total Bank Amount</td>
                                    <td class="text-right px-2 py-1 border-t border-black font-mono">{{ number_format($totalBankAmount, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-center px-1 py-1">4</td>
                                    <td class="px-2 py-1">Total Online Amount in Current Month</td>
                                    <td class="text-right px-2 py-1 font-mono">{{ number_format($totalOnlineAmountMonth, 2) }}</td>
                                </tr>
                                <tr class="font-bold">
                                    <td class="text-center px-1 py-1 border-t border-black">5</td>
                                    <td class="px-2 py-1 border-t border-black">Closing Balance before Expenses</td>
                                    <td class="text-right px-2 py-1 border-t border-black font-mono">{{ number_format($closingBalanceBeforeExpenses, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-center px-1 py-1">6</td>
                                    <td class="px-2 py-1">Total Expenses in Current Month</td>
                                    <td class="text-right px-2 py-1 font-mono">{{ number_format($totalExpensesMonth, 2) }}</td>
                                </tr>
                                <tr class="bg-gray-50 font-extrabold">
                                    <td class="text-center px-1 py-1 border-t-2 border-black">7</td>
                                    <td class="px-2 py-1 border-t-2 border-black">Closing Balance After Expenses</td>
                                    <td class="text-right px-2 py-1 border-t-2 border-black font-mono">{{ number_format($closingBalanceAfterExpenses, 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {{-- Investment Comparison Table --}}
                    <div>
                        <table class="report-table tabular-nums w-full">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="text-center w-10 px-1 py-0.5">#</th>
                                    <th colspan="2" class="text-center px-2 py-1 text-base font-extrabold">
                                        Investment Comparison — {{ $currentMonthName }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="text-center px-1 py-1">1</td>
                                    <td class="px-2 py-1">Last Month Main Investment ({{ $formattedLastDayPrevMonth }})</td>
                                    <td class="text-right px-2 py-1 font-mono">{{ number_format($lastMonthMainInvestment, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-center px-1 py-1">2</td>
                                    <td class="px-2 py-1">Current Month Main Investment ({{ $formattedDate }})</td>
                                    <td class="text-right px-2 py-1 font-mono">{{ number_format($currentMonthMainInvestment, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-center px-1 py-1">3</td>
                                    <td class="px-2 py-1">Net Investment</td>
                                    <td class="text-right px-2 py-1 font-mono">{{ number_format($netInvestment, 2) }}</td>
                                </tr>
                                <tr class="bg-gray-50 font-extrabold">
                                    <td class="text-center px-1 py-1 border-t-2 border-black">4</td>
                                    <td class="px-2 py-1 border-t-2 border-black">Increase In Investment Current Month</td>
                                    <td class="text-right px-2 py-1 border-t-2 border-black font-mono {{ $increaseInInvestmentMonth >= 0 ? 'text-red-600' : 'text-green-600' }}">{{ number_format($increaseInInvestmentMonth, 2) }}</td>
                                </tr>
                                @for ($i = 5; $i <= 7; $i++)
                                <tr>
                                    <td class="text-center px-1 py-1">{{ $i }}</td>
                                    <td class="text-center px-2 py-1">-</td>
                                    <td class="text-center px-2 py-1">-</td>
                                </tr>
                                @endfor
                            </tbody>
                        </table>
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
