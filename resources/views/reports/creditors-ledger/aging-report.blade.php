<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Credit Aging Report (Salesman-wise)" :createRoute="null" createLabel=""
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

    @php
        $baseQuery = request()->except(['page', 'filter.bucket']);
        $bucketUrl = function (?string $bucket) use ($baseQuery) {
            $query = $baseQuery;
            $query['filter'] = array_filter(array_merge($query['filter'] ?? [], ['bucket' => $bucket]));

            return route('reports.creditors-ledger.aging-report', array_filter($query));
        };
        $activeBucket = request('filter.bucket');
        $tones = ['current' => 'border-green-500 text-green-700', '31_60' => 'border-yellow-500 text-yellow-700', '61_90' => 'border-orange-500 text-orange-700', 'over_90' => 'border-red-500 text-red-700'];
    @endphp

    <x-filter-section :action="route('reports.creditors-ledger.aging-report')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="as_of_date" value="As of Date" />
                <x-input id="as_of_date" name="as_of_date" type="date" class="mt-1 block w-full" :value="$asOfDate" />
            </div>

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
                <x-label for="filter_employee_id" value="Salesman" />
                <select id="filter_employee_id" name="filter[employee_id]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Salesmen</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" {{ request('filter.employee_id') == (string) $employee->id ? 'selected' : '' }}>{{ $employee->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_bucket" value="Days Since Last Payment" />
                <select id="filter_bucket" name="filter[bucket]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    @foreach ($buckets as $key => $label)
                        <option value="{{ $key }}" {{ $activeBucket === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                    <option value="60_plus" {{ $activeBucket === '60_plus' ? 'selected' : '' }}>Over 60 days</option>
                </select>
            </div>

            <div>
                <x-label for="filter_customer" value="Customer (name or code)" />
                <x-input id="filter_customer" name="filter[customer]" type="text" class="mt-1 block w-full"
                    :value="request('filter.customer')" placeholder="Search customer..." />
            </div>

            <div>
                <x-label for="per_page" value="Records Per Page" />
                <select id="per_page" name="per_page"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    @foreach (['50', '100', '250', 'all'] as $size)
                        <option value="{{ $size }}" {{ (string) request('per_page', '100') === $size ? 'selected' : '' }}>{{ $size === 'all' ? 'All' : $size }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

    {{-- Bucket cards: click to show only that bucket --}}
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 no-print">
        <div class="aging-cards grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            @foreach ($totals as $key => $bucket)
                <a href="{{ $bucketUrl($activeBucket === $key ? null : $key) }}" class="bg-white rounded-lg shadow p-4 border-l-4 {{ $tones[$key] }} {{ $activeBucket === $key ? 'is-on' : '' }}">
                    <div class="text-sm text-gray-500">{{ $bucket['label'] }}</div>
                    <div class="text-2xl font-bold">{{ number_format($bucket['amount'], 0) }}</div>
                    <div class="text-xs text-gray-500">{{ number_format($bucket['count']) }} accounts</div>
                </a>
            @endforeach
            <a href="{{ $bucketUrl($activeBucket === '60_plus' ? null : '60_plus') }}" class="bg-white rounded-lg shadow p-4 border-l-4 border-red-700 text-red-800 {{ $activeBucket === '60_plus' ? 'is-on' : '' }}">
                <div class="text-sm text-gray-500">No payment 60+ days</div>
                <div class="text-2xl font-bold">{{ number_format($totals['61_90']['amount'] + $totals['over_90']['amount'], 0) }}</div>
                <div class="text-xs text-gray-500">{{ number_format($totals['61_90']['count'] + $totals['over_90']['count']) }} accounts</div>
            </a>
        </div>
    </div>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                <p class="text-center font-extrabold mb-2">
                    Moon Traders<br>
                    Credit Aging Report (Salesman-wise)<br>
                    As of {{ \Carbon\Carbon::parse($asOfDate)->format('d-M-Y') }}
                    @if ($activeBucket) &middot; {{ $activeBucket === '60_plus' ? 'Over 60 days' : ($buckets[$activeBucket] ?? '') }} @endif<br>
                    Accounts: {{ number_format($accounts->total()) }} | Outstanding: {{ number_format($filteredTotal, 2) }}
                    <br>
                    <span class="print-only print-info text-xs text-center">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>
                <p class="text-xs text-gray-500 mb-2 no-print">Days are counted from the account's last payment (or its credit sale if it never paid). Click a customer to open that salesman's ledger for the customer; "All" opens the full statement.</p>

                <table class="report-table">
                    <thead>
                        <tr class="bg-gray-50">
                            <th style="width: 40px;">Sr#</th>
                            <th style="width: 100px;">Code</th>
                            <th>Customer</th>
                            <th style="width: 90px;">City</th>
                            @if ($canViewAllSuppliers && ! $supplierIdFilter)
                                <th style="width: 110px;">Supplier</th>
                            @endif
                            <th style="width: 120px;">Salesman</th>
                            <th style="width: 85px;">Last Paid</th>
                            <th style="width: 50px;">Days</th>
                            <th style="width: 95px;">0-30</th>
                            <th style="width: 95px;">31-60</th>
                            <th style="width: 95px;">61-90</th>
                            <th style="width: 95px;">Over 90</th>
                            <th style="width: 100px;">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($accounts as $index => $row)
                            <tr>
                                <td class="text-center">{{ $accounts->firstItem() + $index }}</td>
                                <td class="font-mono">{{ $row->customer_code }}</td>
                                <td>
                                    <a href="{{ route('reports.creditors-ledger.customer-ledger', ['customer' => $row->customer_id, 'filter' => ['employee_id' => $row->employee_id]]) }}" title="{{ $row->salesman }}'s ledger for this customer">{{ $row->customer_name }}</a>
                                    <a href="{{ route('reports.creditors-ledger.customer-ledger', $row->customer_id) }}" class="no-print text-xs" style="color:#6b7280" title="Full statement, all salesmen">(All)</a>
                                </td>
                                <td>{{ $row->city ?? '-' }}</td>
                                @if ($canViewAllSuppliers && ! $supplierIdFilter)
                                    <td>{{ $row->supplier ?? '-' }}</td>
                                @endif
                                <td><a href="{{ route('reports.creditors-ledger.index', ['filter' => array_filter(['employee_id' => $row->employee_id, 'supplier_id' => $supplierIdFilter, 'has_balance' => 'yes'])]) }}" title="All customers of this salesman">{{ $row->salesman }}</a></td>
                                <td class="text-center">{{ $row->last_recovery ? \Carbon\Carbon::parse($row->last_recovery)->format('d-M-y') : 'Never' }}</td>
                                <td class="text-center">{{ $row->days >= 9999 ? '-' : $row->days }}</td>
                                @foreach (array_keys($buckets) as $key)
                                    <td class="text-right font-mono">{{ $row->bucket === $key ? number_format($row->balance, 2) : '' }}</td>
                                @endforeach
                                <td class="text-right font-mono font-bold">{{ number_format($row->balance, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="text-center py-4 text-gray-500">No outstanding customer balances.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-100 font-extrabold">
                        <tr>
                            <td colspan="{{ $canViewAllSuppliers && ! $supplierIdFilter ? 8 : 7 }}" class="text-center px-2 py-1">Page Total ({{ $accounts->count() }} accounts)</td>
                            @foreach (array_keys($buckets) as $key)
                                <td class="text-right font-mono px-2 py-1">{{ number_format($accounts->getCollection()->where('bucket', $key)->sum('balance'), 2) }}</td>
                            @endforeach
                            <td class="text-right font-mono px-2 py-1">{{ number_format($accounts->getCollection()->sum('balance'), 2) }}</td>
                        </tr>
                    </tfoot>
                </table>

                @if ($accounts->hasPages())
                    <div class="mt-4 no-print">
                        {{ $accounts->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
