<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Salesman-wise Creditors" :createRoute="null" createLabel=""
            :showSearch="true" :showRefresh="true" backRoute="reports.creditors-ledger.index" />
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

                .text-green-700,
                .text-blue-700,
                .text-orange-700 {
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

            .aging-cards a { display:block; text-decoration:none; }
            .aging-cards a.is-on { outline: 2px solid #1e3a8a; outline-offset: 1px; }
            .report-table a { color:#1d4ed8; }
            .report-table a:hover { text-decoration: underline; }
        </style>
    @endpush

    <x-filter-section :action="route('reports.creditors-ledger.salesman-creditors')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_supplier_id" value="Supplier" />
                <select id="filter_supplier_id" name="filter[supplier_id]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    @if ($canViewAllSuppliers)
                        <option value="">All Suppliers</option>
                    @endif
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ (string) $supplierIdFilter === (string) $supplier->id ? 'selected' : '' }}>{{ $supplier->supplier_name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_employee_name" value="Salesman Name" />
                <x-input id="filter_employee_name" name="filter[employee_name]" type="text" class="mt-1 block w-full"
                    :value="request('filter.employee_name')" placeholder="Search salesman..." />
            </div>
        </div>
    </x-filter-section>

    @php
        $filterFor = fn (array $extra) => ['filter' => array_filter(array_merge(['supplier_id' => $supplierIdFilter], $extra))];
    @endphp

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 no-print">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
                <div class="text-sm text-gray-500">Salesmen</div>
                <div class="text-2xl font-bold text-purple-700">{{ number_format($salesmen->count()) }}</div>
            </div>
            <a href="{{ route('reports.creditors-ledger.index', $filterFor(['has_balance' => 'yes'])) }}" class="bg-white rounded-lg shadow p-4 border-l-4 border-orange-500 block">
                <div class="text-sm text-gray-500">Outstanding</div>
                <div class="text-2xl font-bold text-orange-700">{{ number_format($salesmen->sum('balance'), 0) }}</div>
                <div class="text-xs text-gray-500">{{ number_format($salesmen->sum('customers')) }} customer accounts</div>
            </a>
            <a href="{{ route('reports.creditors-ledger.aging-report', $filterFor(['bucket' => '60_plus'])) }}" class="bg-white rounded-lg shadow p-4 border-l-4 border-red-500 block">
                <div class="text-sm text-gray-500">Credit older than 60 days</div>
                <div class="text-2xl font-bold text-red-700">{{ number_format($salesmen->sum('overdue'), 0) }}</div>
                <div class="text-xs text-gray-500">{{ number_format($salesmen->sum('overdue_customers')) }} accounts &middot; aging report →</div>
            </a>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
                <div class="text-sm text-gray-500">Recovered (all time)</div>
                <div class="text-2xl font-bold text-green-700">{{ number_format($salesmen->sum('recoveries'), 0) }}</div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                <p class="text-center font-extrabold mb-2">
                    Moon Traders<br>
                    Salesman-wise Creditors<br>
                    As of {{ now()->format('d-M-Y') }} | Outstanding: {{ number_format($salesmen->sum('balance'), 2) }}
                    <br>
                    <span class="print-only print-info text-xs text-center">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                <table class="report-table">
                    <thead>
                        <tr class="bg-gray-50">
                            <th style="width: 40px;">Sr#</th>
                            <th>Salesman</th>
                            @if ($canViewAllSuppliers && ! $supplierIdFilter)
                                <th style="width: 140px;">Supplier</th>
                            @endif
                            <th style="width: 80px;">Customers Owing</th>
                            <th style="width: 110px;">Credit Sales</th>
                            <th style="width: 110px;">Recoveries</th>
                            <th style="width: 110px;">Outstanding</th>
                            <th style="width: 110px;">60+ Days</th>
                            <th style="width: 90px;">Last Recovery</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($salesmen as $index => $row)
                            <tr>
                                <td class="text-center">{{ $index + 1 }}</td>
                                <td><a href="{{ route('reports.creditors-ledger.index', $filterFor(['employee_id' => $row->employee_id, 'has_balance' => 'yes'])) }}" title="Customers of this salesman">{{ $row->salesman }}</a></td>
                                @if ($canViewAllSuppliers && ! $supplierIdFilter)
                                    <td>{{ $row->supplier ?? '-' }}</td>
                                @endif
                                <td class="text-center">{{ number_format($row->customers) }}</td>
                                <td class="text-right font-mono">{{ number_format($row->credit_sales, 2) }}</td>
                                <td class="text-right font-mono">{{ number_format($row->recoveries, 2) }}</td>
                                <td class="text-right font-mono font-bold">{{ number_format($row->balance, 2) }}</td>
                                <td class="text-right font-mono">
                                    @if ($row->overdue > 0)
                                        <a href="{{ route('reports.creditors-ledger.aging-report', $filterFor(['employee_id' => $row->employee_id, 'bucket' => '60_plus'])) }}" title="{{ $row->overdue_customers }} accounts">{{ number_format($row->overdue, 2) }}</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="text-center">{{ $row->last_recovery ? \Carbon\Carbon::parse($row->last_recovery)->format('d-M-y') : '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-gray-500">No salesman credit found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-100 font-extrabold">
                        <tr>
                            <td colspan="{{ $canViewAllSuppliers && ! $supplierIdFilter ? 3 : 2 }}" class="text-center px-2 py-1">Total</td>
                            <td class="text-center px-2 py-1">{{ number_format($salesmen->sum('customers')) }}</td>
                            <td class="text-right font-mono px-2 py-1">{{ number_format($salesmen->sum('credit_sales'), 2) }}</td>
                            <td class="text-right font-mono px-2 py-1">{{ number_format($salesmen->sum('recoveries'), 2) }}</td>
                            <td class="text-right font-mono px-2 py-1">{{ number_format($salesmen->sum('balance'), 2) }}</td>
                            <td class="text-right font-mono px-2 py-1">{{ number_format($salesmen->sum('overdue'), 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
