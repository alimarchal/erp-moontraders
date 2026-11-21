{{--
    Daily Business Summary (/reports/daily-business-summary): the management sheet for one company or
    all companies. Empty until a company is chosen in the filters; sales by category and the TTS block
    run from the 1st of the month to the report date, the salesman table is the report date only.
--}}
@php
    $money = fn ($value) => abs((float) $value) < 0.005 ? '-' : number_format((float) $value, 2);
    $signed = fn ($value) => (float) $value < 0 ? '('.number_format(abs((float) $value), 2).')' : number_format((float) $value, 2);
    $reportDate = \Carbon\Carbon::parse($date);
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Daily Business Summary" :createRoute="null" createLabel="" :showSearch="true"
            :showRefresh="true" backRoute="reports.index" />
    </x-slot>

    @push('header')
        <style>
            .report-table { width: 100%; border-collapse: collapse; border: 1px solid black; font-size: 14px; line-height: 1.2; }
            .report-table th, .report-table td { border: 1px solid black; padding: 3px 4px; word-wrap: break-word; }
            .report-table .num { text-align: right; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; white-space: nowrap; }
            .report-table .section-head { background: #f3f4f6; text-align: center; font-weight: 800; }
            .report-table .total-row td { background: #f9fafb; font-weight: 800; border-top: 2px solid black; }
            .report-table .negative { color: #b91c1c; }
            .kpi-table td { text-align: center; }
            .kpi-table .kpi-label { font-size: 12px; font-weight: 700; text-transform: uppercase; background: #f3f4f6; }
            .kpi-table .kpi-value { font-size: 18px; font-weight: 800; }
            .print-only { display: none; }

            @media print {
                @page { margin: 10mm 8mm 12mm 8mm; size: A4 landscape; }
                .no-print { display: none !important; }
                body { margin: 0 !important; padding: 0 !important; }
                .max-w-7xl { max-width: 100% !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
                .bg-white { margin: 0 !important; padding: 6px !important; box-shadow: none !important; }
                .overflow-x-auto { overflow: visible !important; }
                .report-table { font-size: 10px !important; }
                .report-table th, .report-table td { padding: 2px 3px !important; color: #000 !important; }
                .kpi-table .kpi-value { font-size: 13px !important; }
                .print-only { display: block !important; }
                .dbs-grid { display: grid !important; grid-template-columns: 1fr 1fr 1fr !important; gap: 6px !important; }
            }
        </style>
    @endpush

    <x-filter-section :action="route('reports.daily-business-summary.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <x-label for="date" value="{{ __('Date') }}" />
                <x-input id="date" class="block mt-1 w-full" type="date" name="date" :value="$date" required />
            </div>

            <div>
                <x-label for="supplier_id" value="{{ __('Company') }}" />
                <select id="supplier_id" name="supplier_id"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">Select Company</option>
                    @if ($canChooseCompany)
                        <option value="{{ \App\Http\Controllers\Reports\DailyBusinessSummaryController::ALL_COMPANIES }}" @selected($allCompanies)>All Companies</option>
                    @endif
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected($supplierId == $supplier->id)>{{ $supplier->supplier_name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="designation" value="{{ __('Designation') }}" />
                <select id="designation" name="designation"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Designations</option>
                    @foreach ($designations as $desig)
                        <option value="{{ $desig }}" @selected($designation === $desig)>{{ $desig }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="employee_ids" value="Salesman (Multi-Select)" class="pb-1" />
                <select id="employee_ids" name="employee_ids[]" multiple
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected(in_array((string) $employee->id, array_map('strval', $employeeIds), true))>{{ $employee->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

    @unless ($hasQuery)
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16 mt-4 no-print">
            <div class="bg-white shadow-xl sm:rounded-lg p-10 text-center text-gray-600">
                <p class="text-lg font-semibold text-gray-800">No company selected</p>
                <p class="mt-1 text-sm">Open the filter menu, choose a company{{ $canChooseCompany ? ' (or All Companies)' : '' }} and the date, then click Apply to generate the Daily Business Summary.</p>
            </div>
        </div>
    @else
        @php
            $tts = $report['tts'];
            $investment = $report['investment'];
            $totals = $report['totals'];
            $categoryTotal = $report['categories']->sum('amount');
        @endphp
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16 mt-4">
            <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 print:shadow-none print:pb-0">
                <div class="overflow-x-auto">
                    <p class="text-center font-extrabold mb-2">
                        SHAHZAIN TRADERS - MUZAFFRABAD<br>
                        DAILY BUSINESS SUMMARY<br>
                        <span class="text-sm font-semibold">DISTRIBUTOR: {{ $companyLabel }}</span><br>
                        <span class="text-sm font-semibold">Date: {{ $reportDate->format('d.m.Y') }}</span><br>
                        <span class="print-only print-info text-xs text-center">Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}</span>
                    </p>

                    {{-- Headline figures --}}
                    <table class="report-table kpi-table tabular-nums mb-4">
                        <tr>
                            <td class="kpi-label">Total Sales (Month to date)</td>
                            <td class="kpi-label">Today's Cash</td>
                            <td class="kpi-label">Total Credit</td>
                            <td class="kpi-label">Total Discount (Today)</td>
                        </tr>
                        <tr>
                            <td class="kpi-value font-mono">{{ number_format($report['total_sales_mtd'], 2) }}</td>
                            <td class="kpi-value font-mono">{{ number_format($totals['cash'], 2) }}</td>
                            <td class="kpi-value font-mono">{{ number_format($totals['credit'], 2) }}</td>
                            <td class="kpi-value font-mono">{{ number_format($totals['discount'], 2) }}</td>
                        </tr>
                    </table>

                    <div class="dbs-grid grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        {{-- Investment position --}}
                        <div>
                            <table class="report-table tabular-nums">
                                <thead>
                                    <tr><th colspan="2" class="section-head">INVESTMENT POSITION</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td>Powder Expiry</td><td class="num">{{ number_format($investment['powder_expiry'], 2) }}</td></tr>
                                    <tr><td>Liquid Expiry</td><td class="num">{{ number_format($investment['liquid_expiry'], 2) }}</td></tr>
                                    <tr><td>Claim Amount</td><td class="num">{{ number_format($investment['claims'], 2) }}</td></tr>
                                    <tr><td>Stock Amount</td><td class="num">{{ number_format($investment['stock'], 2) }}</td></tr>
                                    <tr><td>Credit Amount</td><td class="num">{{ number_format($investment['credit'], 2) }}</td></tr>
                                    <tr><td>Ledger Amount</td><td class="num">{{ number_format($investment['ledger'], 2) }}</td></tr>
                                    <tr class="total-row"><td>Total Investment</td><td class="num">{{ number_format($investment['total'], 2) }}</td></tr>
                                </tbody>
                            </table>
                        </div>

                        {{-- Category-wise sales and groups, month to date --}}
                        <div>
                            <table class="report-table tabular-nums mb-3">
                                <thead>
                                    <tr><th colspan="2" class="section-head">CATEGORY-WISE SALES ({{ \Carbon\Carbon::parse($report['month_start'])->format('d M') }} – {{ $reportDate->format('d M') }})</th></tr>
                                </thead>
                                <tbody>
                                    @forelse ($report['categories'] as $category)
                                        <tr><td>{{ $category->name }}</td><td class="num">{{ number_format($category->amount, 2) }}</td></tr>
                                    @empty
                                        <tr><td colspan="2" class="text-center italic text-gray-500">No sales this month</td></tr>
                                    @endforelse
                                    <tr class="total-row"><td>TOTAL SALES</td><td class="num">{{ number_format($categoryTotal, 2) }}</td></tr>
                                </tbody>
                            </table>

                            <table class="report-table tabular-nums">
                                <thead>
                                    <tr><th colspan="2" class="section-head">CATEGORY GROUP SUMMARY</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($report['category_groups'] as $group => $amount)
                                        <tr><td class="font-semibold">{{ $group }}</td><td class="num">{{ number_format($amount, 2) }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- TTS, month to date --}}
                        <div>
                            <table class="report-table tabular-nums">
                                <thead>
                                    <tr><th colspan="2" class="section-head">TTS SUMMARY (MONTH TO DATE)</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td>TTS Received</td><td class="num">{{ number_format($tts['tts_received'], 2) }}</td></tr>
                                    <tr><td>Promo Received</td><td class="num">{{ number_format($tts['promo_received'], 2) }}</td></tr>
                                    <tr class="bg-gray-50 font-bold"><td>Total Received</td><td class="num">{{ number_format($tts['total_received'], 2) }}</td></tr>
                                    <tr><td>TTS Passed</td><td class="num">{{ number_format($tts['tts_passed'], 2) }}</td></tr>
                                    <tr><td>Promo Passed</td><td class="num">{{ number_format($tts['promo_passed'], 2) }}</td></tr>
                                    <tr class="bg-gray-50 font-bold"><td>Total Scheme Passed</td><td class="num">{{ number_format($tts['total_passed'], 2) }}</td></tr>
                                    <tr><td>Percentage Passed</td><td class="num">{{ number_format($tts['percentage_passed'], 2) }}</td></tr>
                                    <tr class="total-row"><td>Balance</td><td class="num {{ $tts['balance'] < 0 ? 'negative' : '' }}">{{ $signed($tts['balance']) }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Salesman-wise performance for the day --}}
                    <table class="report-table tabular-nums">
                        <thead>
                            <tr><th colspan="{{ $allCompanies ? 12 : 11 }}" class="section-head">DAILY SALESMAN-WISE PERFORMANCE &amp; CREDIT AND CASH DETAIL</th></tr>
                            <tr class="bg-gray-100">
                                <th class="text-center w-10">#</th>
                                <th class="text-left">Salesman Name</th>
                                @if ($allCompanies)
                                    <th class="text-left">Company</th>
                                @endif
                                <th class="text-right">Sales (PKR)</th>
                                <th class="text-right">%age</th>
                                <th class="text-right">Scheme</th>
                                <th class="text-right">Total Discount</th>
                                <th class="text-right">Expiry Liquid</th>
                                <th class="text-right">Expiry Powder</th>
                                <th class="text-right">Credit (PKR)</th>
                                <th class="text-right">Amount (PKR)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($report['salesmen'] as $row)
                                <tr>
                                    <td class="text-center">{{ $loop->iteration }}</td>
                                    <td>{{ $row->name }}</td>
                                    @if ($allCompanies)
                                        <td>{{ $row->company }}</td>
                                    @endif
                                    <td class="num">{{ $money($row->sales) }}</td>
                                    <td class="num">{{ $money($row->percentage) }}</td>
                                    <td class="num">{{ $money($row->scheme) }}</td>
                                    <td class="num">{{ $money($row->discount) }}</td>
                                    <td class="num">{{ $money($row->expiry_liquid) }}</td>
                                    <td class="num">{{ $money($row->expiry_powder) }}</td>
                                    <td class="num">{{ $money($row->credit) }}</td>
                                    <td class="num">{{ $money($row->cash) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="{{ $allCompanies ? 12 : 11 }}" class="text-center italic text-gray-500">No data found</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="{{ $allCompanies ? 3 : 2 }}" class="text-right">TOTAL</td>
                                <td class="num">{{ number_format($totals['sales'], 2) }}</td>
                                <td class="num">{{ number_format($totals['percentage'], 2) }}</td>
                                <td class="num">{{ number_format($totals['scheme'], 2) }}</td>
                                <td class="num">{{ number_format($totals['discount'], 2) }}</td>
                                <td class="num">{{ number_format($totals['expiry_liquid'], 2) }}</td>
                                <td class="num">{{ number_format($totals['expiry_powder'], 2) }}</td>
                                <td class="num">{{ number_format($totals['credit'], 2) }}</td>
                                <td class="num">{{ number_format($totals['cash'], 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>

                    <p class="mt-2 text-xs text-gray-500 no-print">
                        Sales by category and the TTS summary run from the 1st of the month to the report date. Salesman figures are for the report date; Credit is each salesman's closing market credit. %age = advance tax (1161); Scheme = scheme discount (5292) + promo (5287); Amount = cash counted + bank slips.
                    </p>
                </div>
            </div>
        </div>
    @endunless

    @push('scripts')
        <script>
            $(document).ready(function () {
                $('#supplier_id').select2({ width: '100%', placeholder: 'Select Company', allowClear: true });
                $('#designation').select2({ width: '100%', placeholder: 'All Designations', allowClear: true });
                $('#employee_ids').select2({ width: '100%', placeholder: 'Select Salesmen', allowClear: true });
            });
        </script>
    @endpush
</x-app-layout>
