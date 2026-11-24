{{--
    Daily Sales & Trade Offer Dashboard (/reports/sales-trade-offer): one column per company with its sale,
    trade offer (TO) and promotion received and passed, percentage and balance for the period.
    All companies by default; Balance = Total TO Received − Total TO Passed − Percentage, negatives in brackets.
    Phones: the headline figures stack two to a row and the table scrolls sideways with Particulars pinned.
--}}
@php
    $money = fn ($value) => abs((float) $value) < 0.005 ? '-' : number_format((float) $value, 2);
    $signed = fn ($value) => abs((float) $value) < 0.005
        ? '-'
        : ((float) $value < 0 ? '('.number_format(abs((float) $value), 2).')' : number_format((float) $value, 2));
    $columns = $report['columns'];
    $totals = $report['totals'];
    $columnCount = $columns->count() + 2;
    $rowClasses = [
        'total_sale' => 'row-highlight',
        'total_to_received' => 'row-highlight',
        'total_to_passed' => 'row-highlight',
        'balance' => 'row-balance',
    ];
    $canSeeTts = auth()->user()->can('report-sales-tts-summary');
    $canSeeSchemeReceived = auth()->user()->can('report-sales-scheme-received');
    $allCompaniesValue = \App\Http\Controllers\Reports\SalesTradeOfferReportController::ALL_COMPANIES;
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Daily Sales & Trade Offer Dashboard" :showSearch="true" :showRefresh="true"
            backRoute="reports.index" />
    </x-slot>

    @push('header')
        <style>
            .report-table {
                width: 100%;
                border-collapse: separate;
                border-spacing: 0;
                border-top: 1px solid black;
                border-left: 1px solid black;
                font-size: 12px;
                line-height: 1.2;
            }

            .report-table th,
            .report-table td {
                border-right: 1px solid black;
                border-bottom: 1px solid black;
                padding: 3px 4px;
                background: #fff;
            }

            .report-table .num {
                text-align: right;
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
                white-space: nowrap;
            }

            .report-table thead th {
                background: #1e3a5f;
                color: #fff;
                text-align: center;
                white-space: nowrap;
            }

            .report-table .particular {
                text-align: left;
                font-weight: 700;
                white-space: nowrap;
            }

            /* Particulars stays in view while the companies scroll sideways on a phone. */
            .report-table .sticky-col {
                position: sticky;
                left: 0;
                z-index: 1;
            }

            .report-table .row-highlight td {
                background: #dbeafe;
                font-weight: 800;
            }

            .report-table .row-balance td {
                background: #fde2e2;
                font-weight: 800;
                color: #b91c1c;
                border-top: 1px solid black;
            }

            .report-table .grand-total {
                background: #f3f4f6;
                font-weight: 800;
            }

            .report-table .negative {
                color: #b91c1c;
            }

            .kpi-grid {
                display: grid;
                grid-template-columns: 1.2fr 1.1fr 1.1fr 1.1fr 0.6fr;
                gap: 2px;
            }

            .kpi-card {
                display: flex;
                flex-direction: column;
                text-align: center;
                min-width: 0;
            }

            .kpi-label {
                font-size: 12px;
                font-weight: 700;
                text-transform: uppercase;
                color: #fff;
                padding: 4px 6px;
            }

            .kpi-value {
                flex: 1;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 4px;
                flex-wrap: wrap;
                font-size: 18px;
                font-weight: 800;
                background: #f3f4f6;
                padding: 6px;
                font-variant-numeric: tabular-nums;
            }

            .kpi-value .currency {
                font-size: 0.75em;
            }

            .kpi-sales .kpi-label { background: #1e3a5f; }
            .kpi-received .kpi-label { background: #6aa84f; }
            .kpi-passed .kpi-label { background: #f4b183; }
            .kpi-balance .kpi-label { background: #c00000; }
            .kpi-dated .kpi-label { background: #2f5597; }

            .report-banner {
                background: #1e3a5f;
                color: #fff;
            }

            .report-banner .sub {
                background: #0f766e;
            }

            .print-only {
                display: none;
            }

            @media (max-width: 767px) {
                .kpi-grid {
                    grid-template-columns: 1fr 1fr;
                }

                .kpi-sales,
                .kpi-balance,
                .kpi-dated {
                    grid-column: span 2;
                }

                .kpi-dated {
                    flex-direction: row;
                }

                .kpi-dated .kpi-label {
                    display: flex;
                    align-items: center;
                    flex: 0 0 50%;
                    justify-content: center;
                }

                .kpi-label {
                    font-size: 11px;
                }

                .kpi-value {
                    font-size: 15px;
                }

                .report-table {
                    font-size: 11px;
                }
            }

            @media (max-width: 374px) {
                .kpi-label {
                    font-size: 10px;
                    padding: 3px 4px;
                }

                .kpi-value {
                    font-size: 13px;
                    padding: 5px 4px;
                }

                .report-table {
                    font-size: 10.5px;
                }

                .report-table th,
                .report-table td {
                    padding: 3px;
                }
            }

            @media print {
                @page {
                    size: A4 landscape;
                    margin: 10mm 8mm 12mm 8mm;

                    @bottom-center {
                        content: "Page " counter(page) " of " counter(pages);
                    }
                }

                .report-banner,
                .report-banner *,
                .kpi-grid,
                .kpi-grid *,
                .report-table,
                .report-table * {
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }

                html,
                body,
                .min-h-screen {
                    background: #fff !important;
                    min-height: 0 !important;
                }

                .pb-16 {
                    padding-bottom: 0 !important;
                }

                .no-print {
                    display: none !important;
                }

                body {
                    margin: 0 !important;
                    padding: 0 !important;
                }

                .max-w-8xl {
                    max-width: 100% !important;
                    width: 100% !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }

                .bg-white {
                    margin: 0 !important;
                    padding: 6px !important;
                    box-shadow: none !important;
                }

                .overflow-x-auto {
                    overflow: visible !important;
                }

                .kpi-grid {
                    grid-template-columns: 1.2fr 1.1fr 1.1fr 1.1fr 0.6fr !important;
                }

                .kpi-sales,
                .kpi-balance,
                .kpi-dated {
                    grid-column: auto !important;
                    flex-direction: column !important;
                }

                .kpi-label {
                    font-size: 10px !important;
                }

                .kpi-value {
                    font-size: 14px !important;
                }

                .report-table {
                    font-size: 10.5px !important;
                    width: 100% !important;
                }

                .report-table .sticky-col {
                    position: static;
                }

                .report-table tr {
                    page-break-inside: avoid;
                }

                .report-table th,
                .report-table td {
                    padding: 3px 4px !important;
                }

                .print-info {
                    font-size: 9px !important;
                    margin-top: 4px !important;
                    color: #000 !important;
                }

                .print-only {
                    display: block !important;
                }
            }
        </style>
    @endpush

    <x-filter-section :action="route('reports.sales-trade-offer.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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

            <div>
                <x-label for="filter_supplier_ids" value="Company" />
                <select id="filter_supplier_ids" name="filter[supplier_ids][]" multiple @disabled(! $canChooseCompany)
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    @if ($canChooseCompany)
                        <option value="{{ $allCompaniesValue }}" @selected($allCompanies)>All Companies</option>
                    @endif
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected(in_array($supplier->id, $selectedSupplierIds))>
                            {{ $supplier->supplier_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_sort" value="Column Order" />
                <select id="filter_sort" name="filter[sort]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="{{ \App\Services\SalesTradeOfferService::SORT_BY_NAME }}" @selected($sort === \App\Services\SalesTradeOfferService::SORT_BY_NAME)>Company Name (A-Z)</option>
                    <option value="{{ \App\Services\SalesTradeOfferService::SORT_BY_SALES }}" @selected($sort === \App\Services\SalesTradeOfferService::SORT_BY_SALES)>Total Sale (High to Low)</option>
                </select>
            </div>
        </div>
    </x-filter-section>

    <div class="max-w-8xl mx-auto px-2 sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden p-2 sm:p-4 shadow-xl rounded-lg mb-4 mt-4 print:shadow-none print:pb-0">
            <div class="report-banner text-center mb-3">
                <p class="font-extrabold text-base sm:text-xl py-1 px-2">MOON TRADERS &amp; SHAHZAIN TRADERS</p>
                <p class="sub font-bold text-xs sm:text-sm py-1 px-2">DAILY SALES &amp; TRADE OFFER DASHBOARD</p>
            </div>
            <p class="text-center text-xs mb-2">
                Period: {{ \Carbon\Carbon::parse($startDate)->format('d-M-Y') }} to
                {{ \Carbon\Carbon::parse($endDate)->format('d-M-Y') }}
                <span class="print-only print-info">
                    Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                </span>
            </p>

            {{-- Headline figures --}}
            <div class="kpi-grid mb-3">
                <div class="kpi-card kpi-sales">
                    <div class="kpi-label">Total Sales</div>
                    <div class="kpi-value font-mono"><span><span class="currency">PKR</span> {{ number_format($totals['total_sale'], 2) }}</span></div>
                </div>
                <div class="kpi-card kpi-received">
                    <div class="kpi-label">TO Received</div>
                    <div class="kpi-value font-mono"><span><span class="currency">PKR</span> {{ number_format($totals['total_to_received'], 2) }}</span></div>
                </div>
                <div class="kpi-card kpi-passed">
                    <div class="kpi-label">TO Passed</div>
                    <div class="kpi-value font-mono"><span><span class="currency">PKR</span> {{ number_format($totals['total_to_passed'], 2) }}</span></div>
                </div>
                <div class="kpi-card kpi-balance">
                    <div class="kpi-label">Net Balance</div>
                    <div class="kpi-value font-mono {{ $totals['balance'] < 0 ? 'text-red-700' : 'text-green-700' }}">
                        @if ($totals['balance'] < 0)
                            <span>(<span class="currency">PKR</span> {{ number_format(abs($totals['balance']), 2) }})</span>
                        @else
                            <span><span class="currency">PKR</span> {{ number_format($totals['balance'], 2) }}</span>
                        @endif
                    </div>
                </div>
                <div class="kpi-card kpi-dated">
                    <div class="kpi-label">Dated</div>
                    <div class="kpi-value">{{ \Carbon\Carbon::parse($endDate)->format('d-M') }}</div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="report-table tabular-nums">
                    <thead>
                        <tr>
                            <th class="text-left sticky-col">Particulars</th>
                            @foreach ($columns as $column)
                                <th title="{{ $column->name }}">
                                    @if ($canSeeTts)
                                        <a href="{{ route('reports.tts-summary.index', ['filter[supplier_id]' => $column->id, 'filter[start_date]' => $startDate, 'filter[end_date]' => $endDate]) }}"
                                            class="hover:underline" target="_blank">{{ $column->label }}</a>
                                    @else
                                        {{ $column->label }}
                                    @endif
                                </th>
                            @endforeach
                            <th>Grand Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($columns->isEmpty())
                            <tr>
                                <td colspan="{{ $columnCount }}" class="text-center py-4 text-gray-500">
                                    No data found for the selected criteria.
                                </td>
                            </tr>
                        @else
                            @foreach ($rows as $key => $label)
                                <tr class="{{ $rowClasses[$key] ?? '' }}">
                                    <td class="particular sticky-col">{{ $label }}</td>
                                    @foreach ($columns as $column)
                                        @php $value = $column->figures[$key]; @endphp
                                        <td class="num {{ $value < 0 ? 'negative' : '' }}">
                                            @if ($canSeeSchemeReceived && in_array($key, ['to_received', 'promotion_received'], true) && abs($value) >= 0.005)
                                                <a href="{{ route('reports.scheme-received.index', ['supplier_id' => $column->id, 'date_from' => $startDate, 'date_to' => $endDate, 'category' => $key === 'to_received' ? 'tts_received' : 'promo_received']) }}"
                                                    class="hover:underline" target="_blank">{{ $money($value) }}</a>
                                            @else
                                                {{ $key === 'balance' ? $signed($value) : $money($value) }}
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="num grand-total {{ $totals[$key] < 0 ? 'negative' : '' }}">
                                        {{ $key === 'balance' ? $signed($totals[$key]) : $money($totals[$key]) }}
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>

            <p class="mt-2 text-xs text-gray-500 no-print">
                Total Sale, TO Passed (5292), Promotion Passed (5288 Promotion OFF) and Percentage (5223) are from posted sales
                settlements; TO Received and Promotion Received are active Scheme Received entries.
                Balance = Total TO Received − Total TO Passed − Percentage. Click a company name for its TTS Summary.
                <span class="md:hidden">Swipe the table sideways to see every company.</span>
            </p>
        </div>
    </div>

    @push('scripts')
        <script>
            $(document).ready(function () {
                const $companies = $('#filter_supplier_ids');
                $companies.select2({ width: '100%', placeholder: 'All Companies', allowClear: true });

                // "All Companies" and specific companies are either/or.
                $companies.on('select2:select', function (event) {
                    const all = '{{ $allCompaniesValue }}';
                    const values = $companies.val() || [];
                    const next = event.params.data.id === all ? [all] : values.filter((value) => value !== all);
                    if (next.length !== values.length) {
                        $companies.val(next).trigger('change');
                    }
                });
            });
        </script>
    @endpush
</x-app-layout>
