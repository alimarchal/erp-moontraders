<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Sale Settlement Report" :showSearch="true" :showRefresh="true" backRoute="reports.index" />
    </x-slot>

    @push('header')
        <style>
            .report-table {
                width: 100%;
                border-collapse: collapse;
                border: 1px solid black;
                font-size: 12px;
                line-height: 1.2;
                font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                color: black;
            }

            .report-table th,
            .report-table td {
                border: 1px solid black;
                padding: 4px 6px;
                white-space: nowrap;
            }

            .print-only {
                display: none;
            }

            @media print {
                @page {
                    margin: 10mm 5mm 10mm 5mm;
                    size: landscape;
                }

                body {
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                    background-color: white !important;
                }

                .no-print {
                    display: none !important;
                }

                .max-w-7xl, .max-w-8xl {
                    max-width: 100% !important;
                    width: 100% !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }

                .bg-white, .bg-gray-100, .bg-gray-50 {
                    background-color: white !important;
                    box-shadow: none !important;
                    border: none !important;
                }

                .report-table {
                    font-size: 10px !important;
                    width: 100% !important;
                }

                .report-table th, .report-table td {
                    border: 1px solid black !important;
                    padding: 2px 4px !important;
                    color: black !important;
                }

                .text-red-600, .text-green-600, .text-blue-600 {
                    color: black !important; /* Force black for strict printing if requested, but user said 'font ka color black use karna hai' which generally means main text, usually colors are allowed for indicators unless strictly mono. */
                }
                /* User said: "font ka color use karna hai wo black color use karna hai" - defaulting to black everywhere for safety */
                * {
                    color: black !important;
                }
                
                .report-header {
                    display: block !important;
                }
                
                a { 
                    text-decoration: none !important; 
                    color: black !important; 
                }
            }
        </style>
    @endpush

    <x-filter-section :action="route('reports.sales-settlement.index')" class="no-print" maxWidth="max-w-8xl">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Dates -->
            <div>
                <x-label for="filter_start_date" value="Start Date" />
                <x-input id="filter_start_date" name="filter[start_date]" type="date" class="mt-1 block w-full"
                    :value="$startDate" />
            </div>
            <div>
                <x-label for="filter_end_date" value="End Date" />
                <x-input id="filter_end_date" name="filter[end_date]" type="date" class="mt-1 block w-full"
                    :value="$endDate" />
            </div>

            <!-- Salesman -->
            <div wire:ignore>
                <x-label for="filter_employee_id" value="Salesman number" />
                <select id="filter_employee_id" name="filter[employee_id][]" multiple
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" {{ in_array($employee->id, $filters['employee_id'] ?? []) ? 'selected' : '' }}>
                            {{ $employee->name }} ({{ $employee->code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Vehicle -->
            <div wire:ignore>
                <x-label for="filter_vehicle_id" value="Vehicle" />
                <select id="filter_vehicle_id" name="filter[vehicle_id]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Vehicles</option>
                    @foreach($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}" {{ ($filters['vehicle_id'] ?? '') == $vehicle->id ? 'selected' : '' }}>
                            {{ $vehicle->registration_number }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Warehouse -->
            <div wire:ignore>
                <x-label for="filter_warehouse_id" value="Warehouse" />
                <select id="filter_warehouse_id" name="filter[warehouse_id]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Warehouses</option>
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}" {{ ($filters['warehouse_id'] ?? '') == $wh->id ? 'selected' : '' }}>
                            {{ $wh->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Status -->
            <div>
                <x-label for="filter_status" value="Status" />
                <select id="filter_status" name="filter[status]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Statuses</option>
                    <option value="posted" {{ ($filters['status'] ?? '') == 'posted' ? 'selected' : '' }}>Posted</option>
                    <option value="draft" {{ ($filters['status'] ?? '') == 'draft' ? 'selected' : '' }}>Draft</option>
                </select>
            </div>

            <!-- Settlement Number -->
            <div>
                <x-label for="filter_settlement_number" value="Settlement #" />
                <x-input id="filter_settlement_number" name="filter[settlement_number]" type="text"
                    class="mt-1 block w-full" placeholder="Search Number..."
                    value="{{ $filters['settlement_number'] ?? '' }}" />
            </div>
            
            <!-- Sort By -->
            <div>
                <x-label for="sort" value="Sort By" />
                <select id="sort" name="sort"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="-settlement_date" {{ request('sort') == '-settlement_date' ? 'selected' : '' }}>Date (Newest First)</option>
                    <option value="settlement_date" {{ request('sort') == 'settlement_date' ? 'selected' : '' }}>Date (Oldest First)</option>
                    <option value="-total_sales_amount" {{ request('sort') == '-total_sales_amount' ? 'selected' : '' }}>Total Sales (High to Low)</option>
                    <option value="total_sales_amount" {{ request('sort') == 'total_sales_amount' ? 'selected' : '' }}>Total Sales (Low to High)</option>
                    <option value="-expenses_claimed" {{ request('sort') == '-expenses_claimed' ? 'selected' : '' }}>Expenses (High to Low)</option>
                    <option value="-settlement_number" {{ request('sort') == '-settlement_number' ? 'selected' : '' }}>Number (High to Low)</option>
                </select>
            </div>
        </div>
    </x-filter-section>

    <div class="max-w-8xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 mt-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                <p class="text-center font-extrabold mb-2 text-xl report-header text-black">
                    Moon Traders<br>
                    <span class="text-lg">Sale Settlement Report</span><br>
                    <span class="text-xs font-normal">
                        Period: {{ \Carbon\Carbon::parse($startDate)->format('d-M-Y') }} to
                        {{ \Carbon\Carbon::parse($endDate)->format('d-M-Y') }}
                    </span>
                    @if(isset($filterSummary) && $filterSummary)
                        <br>
                        <span class="text-xs font-normal">
                            {{ $filterSummary }}
                        </span>
                    @endif
                    <br>
                    <span class="print-only text-xs text-center hidden">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                <table class="report-table">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="text-center w-12 text-black">Sr#</th>
                            <th class="text-center w-20 text-black">Date</th>
                            <th class="text-center w-32 text-black">Settlement #</th>
                            <th class="text-left text-black">Salesman / Vehicle</th>
                            <th class="text-right text-black">Total Sales</th>
                            <th class="text-right text-black">Credit Sale</th>
                            <th class="text-right text-black">Recovery</th>
                            <th class="text-right text-black">Expense</th>
                            <th class="text-right text-black">Net Deposit</th>
                            <th class="text-right text-black">G. Profit</th>
                            <th class="text-right text-black">Net Profit</th>
                            <th class="text-center w-16 text-black">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($settlements as $ss)
                            @php
                                $recoveryAmount = $ss->recoveries->sum('amount');
                                $netProfit = ($ss->gross_profit ?? 0) - ($ss->expenses_claimed ?? 0);
                            @endphp
                            <tr>
                                <td class="text-center text-black">{{ $loop->iteration }}</td>
                                <td class="text-center text-black">{{ $ss->settlement_date ? $ss->settlement_date->format('d-M-y') : '-' }}</td>
                                <td class="text-center font-bold font-mono whitespace-nowrap">
                                    <a href="{{ route('sales-settlements.show', $ss->id) }}" class="text-black hover:underline decoration-1" target="_blank">
                                        {{ $ss->settlement_number }}
                                    </a>
                                </td>
                                <td class="text-left text-black">
                                    <div class="font-semibold">{{ $ss->employee->name ?? '-' }}</div>
                                    <div class="text-xs">{{ $ss->vehicle->registration_number ?? '-' }}</div>
                                </td>
                                <td class="text-right font-mono font-bold text-black">{{ number_format($ss->total_sales_amount, 2) }}</td>
                                <td class="text-right font-mono text-black">{{ number_format($ss->credit_sales_amount, 2) }}</td>
                                <td class="text-right font-mono text-black">{{ number_format($recoveryAmount, 2) }}</td>
                                <td class="text-right font-mono text-black">{{ number_format($ss->expenses_claimed, 2) }}</td>
                                <td class="text-right font-mono font-bold bg-gray-50 print:bg-white text-black">{{ number_format($ss->cash_to_deposit, 2) }}</td>
                                <td class="text-right font-mono text-black">{{ number_format($ss->gross_profit ?? 0, 2) }}</td>
                                <td class="text-right font-mono font-bold text-black">{{ number_format($netProfit, 2) }}</td>
                                <td class="text-center">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full border border-black text-black">
                                        {{ ucfirst($ss->status) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="text-center py-4 text-gray-500">No records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-100 font-bold sticky bottom-0 text-black">
                        <tr>
                            <td colspan="4" class="text-right px-2">Total ({{ $settlements->count() }}):</td>
                            <td class="text-right font-mono">{{ number_format($totals->total_sales_amount, 2) }}</td>
                            <td class="text-right font-mono">{{ number_format($totals->credit_sales_amount, 2) }}</td>
                            <td class="text-right font-mono">{{ number_format($totals->credit_recoveries ?? 0, 2) }}</td>
                            <td class="text-right font-mono">{{ number_format($totals->expenses_claimed, 2) }}</td>
                            <td class="text-right font-mono">{{ number_format($totals->cash_to_deposit, 2) }}</td>
                            <td class="text-right font-mono">{{ number_format($totals->gross_profit ?? 0, 2) }}</td>
                            <td class="text-right font-mono">{{ number_format($totals->net_profit ?? 0, 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    @push('scripts')
        <script>
            $(document).ready(function () {
                $('.select2').select2({
                    width: '100%',
                    placeholder: "Select Option",
                    allowClear: true
                });
            });
        </script>
    @endpush
</x-app-layout>