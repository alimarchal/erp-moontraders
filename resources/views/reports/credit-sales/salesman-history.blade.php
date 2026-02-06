<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Salesman Credit Sales History" :createRoute="null" createLabel="" :showSearch="true"
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
        </style>
    @endpush

    <x-filter-section :action="route('reports.credit-sales.salesman-history')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_name" value="Salesman Name" />
                <x-input id="filter_name" name="filter[name]" type="text" class="mt-1 block w-full"
                    :value="request('filter.name')" placeholder="Salesman name..." />
            </div>

            <div>
                <x-label for="filter_employee_code" value="Employee Code" />
                <x-input id="filter_employee_code" name="filter[employee_code]" type="text" class="mt-1 block w-full"
                    :value="request('filter.employee_code')" placeholder="EMP-001..." />
            </div>

            <div>
                <x-label for="filter_supplier_id" value="Supplier" />
                <select id="filter_supplier_id" name="filter[supplier_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Suppliers</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ request('filter.supplier_id') == (string) $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->supplier_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_balance_min" value="Balance (Min)" />
                <x-input id="filter_balance_min" name="filter[balance_min]" type="number" step="0.01"
                    class="mt-1 block w-full" :value="request('filter.balance_min')" placeholder="0.00" />
            </div>

            <div>
                <x-label for="filter_balance_max" value="Balance (Max)" />
                <x-input id="filter_balance_max" name="filter[balance_max]" type="number" step="0.01"
                    class="mt-1 block w-full" :value="request('filter.balance_max')" placeholder="Any" />
            </div>

            <div>
                <x-label for="sort" value="Sort By" />
                <select id="sort" name="sort"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="-credit_sales" {{ request('sort', '-credit_sales') === '-credit_sales' ? 'selected' : '' }}>Credit Sales (High to Low)</option>
                    <option value="credit_sales" {{ request('sort') === 'credit_sales' ? 'selected' : '' }}>Credit Sales
                        (Low to High)</option>
                    <option value="-balance" {{ request('sort') === '-balance' ? 'selected' : '' }}>Balance (High to Low)
                    </option>
                    <option value="name" {{ request('sort') === 'name' ? 'selected' : '' }}>Name (A-Z)</option>
                    <option value="-name" {{ request('sort') === '-name' ? 'selected' : '' }}>Name (Z-A)</option>
                    <option value="-customers_count" {{ request('sort') === '-customers_count' ? 'selected' : '' }}>
                        Customers (Most)</option>
                    <option value="-sales_count" {{ request('sort') === '-sales_count' ? 'selected' : '' }}>Sales Count
                        (High)</option>
                </select>
            </div>

            <div>
                <x-label for="per_page" value="Records Per Page" />
                <select id="per_page" name="per_page"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                    <option value="50" {{ request('per_page', 50) == 50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                    <option value="250" {{ request('per_page') == 250 ? 'selected' : '' }}>250</option>
                </select>
            </div>
        </div>
    </x-filter-section>

    {{-- Summary Cards --}}
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 no-print">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
                <div class="text-sm text-gray-500">Total Credit Sales</div>
                <div class="text-2xl font-bold text-blue-700">{{ number_format($totals->total_credit_sales ?? 0, 2) }}
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
                <div class="text-sm text-gray-500">Total Recoveries</div>
                <div class="text-2xl font-bold text-green-700">{{ number_format($totals->total_recoveries ?? 0, 2) }}
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-orange-500">
                <div class="text-sm text-gray-500">Outstanding Balance</div>
                <div class="text-2xl font-bold text-orange-700">
                    {{ number_format(($totals->total_credit_sales ?? 0) - ($totals->total_recoveries ?? 0), 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
                <div class="text-sm text-gray-500">Total Salesmen</div>
                <div class="text-2xl font-bold text-purple-700">{{ $salesmen->total() }}</div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                <p class="text-center font-extrabold mb-2">
                    Moon Traders<br>
                    Salesman Credit Sales History<br>
                    Total Salesmen: {{ number_format($salesmen->total()) }} |
                    Outstanding:
                    {{ number_format(($totals->total_credit_sales ?? 0) - ($totals->total_recoveries ?? 0), 2) }}
                    <br>
                    <span class="print-only print-info text-xs text-center">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                <table class="report-table">
                    <thead>
                        <tr class="bg-gray-50">
                            <th style="width: 40px;">Sr#</th>
                            <th style="width: 100px;">Code</th>
                            <th style="width: 150px;">Salesman Name</th>
                            <th style="width: 120px;">Supplier</th>
                            <th style="width: 100px;">Credit Sales</th>
                            <th style="width: 100px;">Recoveries</th>
                            <th style="width: 100px;">Balance</th>
                            <th style="width: 60px;">Customers</th>
                            <th style="width: 60px;">Sales</th>
                            <th style="width: 80px;" class="no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($salesmen as $index => $salesman)
                            @php
                                $balance = ($salesman->credit_sales_sum_sale_amount ?? 0) - ($salesman->recoveries_sum_amount ?? 0);
                            @endphp
                            <tr>
                                <td class="text-center" style="vertical-align: middle;">
                                    {{ $salesmen->firstItem() + $index }}</td>
                                <td class="font-mono" style="vertical-align: middle;">{{ $salesman->employee_code }}</td>
                                <td style="vertical-align: middle;">
                                    {{ $salesman->full_name }}
                                </td>
                                <td style="vertical-align: middle;">
                                    {{ $salesman->supplier->supplier_name ?? 'N/A' }}
                                    <!-- @if($salesman->supplier->short_name ?? null)
                                            <div class="text-xs text-gray-500">{{ $salesman->supplier->short_name }}</div>
                                        @endif -->
                                </td>
                                <td class="text-right font-mono text-blue-700" style="vertical-align: middle;">
                                    {{ number_format($salesman->credit_sales_sum_sale_amount ?? 0, 2) }}
                                </td>
                                <td class="text-right font-mono text-green-700" style="vertical-align: middle;">
                                    {{ number_format($salesman->recoveries_sum_amount ?? 0, 2) }}
                                </td>
                                <td class="text-right font-mono font-bold {{ $balance > 0 ? 'text-orange-700' : 'text-green-700' }}"
                                    style="vertical-align: middle;">
                                    {{ number_format($balance, 2) }}
                                </td>
                                <td class="text-center" style="vertical-align: middle;">
                                    {{ $salesman->customers_count }}
                                </td>
                                <td class="text-center" style="vertical-align: middle;">
                                    {{ $salesman->credit_sales_count }}
                                </td>
                                <td class="text-center no-print" style="vertical-align: middle;">
                                    <div class="flex justify-center gap-1">
                                        <a href="{{ route('reports.credit-sales.salesman-details', $salesman) }}"
                                            class="inline-flex items-center px-2 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700"
                                            title="View Details" target="_blank">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-4 text-gray-500">No credit sales found for any
                                    salesman.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-100 font-extrabold">
                        <tr>
                            <td colspan="4" class="text-center px-2 py-1">Page Total ({{ $salesmen->count() }} salesmen)
                            </td>
                            <td class="text-right font-mono px-2 py-1 text-blue-700">
                                {{ number_format($salesmen->sum('credit_sales_sum_sale_amount'), 2) }}
                            </td>
                            <td class="text-right font-mono px-2 py-1 text-green-700">
                                {{ number_format($salesmen->sum('recoveries_sum_amount'), 2) }}
                            </td>
                            <td class="text-right font-mono px-2 py-1 text-orange-700">
                                {{ number_format($salesmen->sum('credit_sales_sum_sale_amount') - $salesmen->sum('recoveries_sum_amount'), 2) }}
                            </td>
                            <td class="text-center px-2 py-1">
                                {{ $salesmen->sum('customers_count') }}
                            </td>
                            <td class="text-center px-2 py-1">
                                {{ $salesmen->sum('credit_sales_count') }}
                            </td>
                            <td class="no-print"></td>
                        </tr>
                    </tfoot>
                </table>

                @if ($salesmen->hasPages())
                    <div class="mt-4 no-print">
                        {{ $salesmen->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>