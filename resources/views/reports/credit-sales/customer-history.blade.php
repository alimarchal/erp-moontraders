<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Customer Credit Sales History" :createRoute="null" createLabel="" :showSearch="true"
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
                table-layout: auto;
            }

            .report-table th,
            .report-table td {
                border: 1px solid black;
                padding: 3px 4px;
                word-wrap: break-word;
            }

            .report-table th {
                text-align: center;
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
                .text-orange-700,
                .text-purple-700 {
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

    <x-filter-section :action="route('reports.credit-sales.customer-history')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_customer_name" value="Customer Name" />
                <x-input id="filter_customer_name" name="filter[customer_name]" type="text" class="mt-1 block w-full"
                    :value="request('filter.customer_name')" placeholder="Customer name..." />
            </div>

            <div>
                <x-label for="filter_customer_code" value="Customer Code" />
                <x-input id="filter_customer_code" name="filter[customer_code]" type="text" class="mt-1 block w-full"
                    :value="request('filter.customer_code')" placeholder="Customer code..." />
            </div>

            <div>
                <x-label for="filter_business_name" value="Business Name" />
                <x-input id="filter_business_name" name="filter[business_name]" type="text" class="mt-1 block w-full"
                    :value="request('filter.business_name')" placeholder="Business name..." />
            </div>

            <div>
                <x-label for="filter_phone" value="Phone" />
                <x-input id="filter_phone" name="filter[phone]" type="text" class="mt-1 block w-full"
                    :value="request('filter.phone')" placeholder="03XX-XXXXXXX" />
            </div>

            <div>
                <x-label for="filter_city" value="City" />
                <select id="filter_city" name="filter[city]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Cities</option>
                    @foreach($cities as $city)
                        <option value="{{ $city }}" {{ request('filter.city')===$city ? 'selected' : '' }}>
                            {{ $city }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_sub_locality" value="Sub Locality" />
                <select id="filter_sub_locality" name="filter[sub_locality]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Sub Localities</option>
                    @foreach($subLocalities as $subLocality)
                        <option value="{{ $subLocality }}" {{ request('filter.sub_locality')===$subLocality ? 'selected' : '' }}>
                            {{ $subLocality }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_channel_type" value="Channel" />
                <select id="filter_channel_type" name="filter[channel_type]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Channels</option>
                    @foreach($channelTypes as $type)
                        <option value="{{ $type }}" {{ request('filter.channel_type')===$type ? 'selected' : '' }}>
                            {{ $type }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_customer_category" value="Category" />
                <select id="filter_customer_category" name="filter[customer_category]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Categories</option>
                    @foreach(['A', 'B', 'C', 'D'] as $cat)
                        <option value="{{ $cat }}" {{ request('filter.customer_category')===$cat ? 'selected' : '' }}>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_employee_id" value="Salesman" />
                <select id="filter_employee_id" name="filter[employee_id]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Salesmen</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" {{ request('filter.employee_id')==$employee->id ? 'selected' : '' }}>
                            {{ $employee->name }} ({{ $employee->employee_code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_is_active" value="Status" />
                <select id="filter_is_active" name="filter[is_active]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    <option value="1" {{ request('filter.is_active')==='1' ? 'selected' : '' }}>Active</option>
                    <option value="0" {{ request('filter.is_active')==='0' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>

            <div>
                <x-label for="filter_it_status" value="IT Status" />
                <select id="filter_it_status" name="filter[it_status]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    <option value="1" {{ request('filter.it_status')==='1' ? 'selected' : '' }}>Yes</option>
                    <option value="0" {{ request('filter.it_status')==='0' ? 'selected' : '' }}>No</option>
                </select>
            </div>

            <div>
                <x-label for="filter_has_balance" value="Has Balance" />
                <select id="filter_has_balance" name="filter[has_balance]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    <option value="yes" {{ request('filter.has_balance')==='yes' ? 'selected' : '' }}>Yes (Balance > 0)</option>
                    <option value="no" {{ request('filter.has_balance')==='no' ? 'selected' : '' }}>No (Balance <= 0)</option>
                </select>
            </div>

            <div>
                <x-label for="filter_credit_limit_min" value="Credit Limit Min" />
                <x-input id="filter_credit_limit_min" name="filter[credit_limit_min]" type="number" class="mt-1 block w-full"
                    :value="request('filter.credit_limit_min')" placeholder="Min limit..." />
            </div>

            <div>
                <x-label for="filter_credit_limit_max" value="Credit Limit Max" />
                <x-input id="filter_credit_limit_max" name="filter[credit_limit_max]" type="number" class="mt-1 block w-full"
                    :value="request('filter.credit_limit_max')" placeholder="Max limit..." />
            </div>

            <div>
                <x-label for="filter_balance_min" value="Balance Min" />
                <x-input id="filter_balance_min" name="filter[balance_min]" type="number" class="mt-1 block w-full"
                    :value="request('filter.balance_min')" placeholder="Min balance..." />
            </div>

            <div>
                <x-label for="filter_balance_max" value="Balance Max" />
                <x-input id="filter_balance_max" name="filter[balance_max]" type="number" class="mt-1 block w-full"
                    :value="request('filter.balance_max')" placeholder="Max balance..." />
            </div>

            <div>
                <x-label for="sort" value="Sort By" />
                <select id="sort" name="sort"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="-closing_balance" {{ request('sort', '-closing_balance')==='-closing_balance' ? 'selected' : '' }}>Closing Balance (High to Low)</option>
                    <option value="closing_balance" {{ request('sort')==='closing_balance' ? 'selected' : '' }}>Closing Balance (Low to High)</option>
                    <option value="-credit_sales_amount" {{ request('sort')==='-credit_sales_amount' ? 'selected' : '' }}>Credit Sales (High to Low)</option>
                    <option value="credit_sales_amount" {{ request('sort')==='credit_sales_amount' ? 'selected' : '' }}>Credit Sales (Low to High)</option>
                    <option value="-opening_balance" {{ request('sort')==='-opening_balance' ? 'selected' : '' }}>Opening Balance (High to Low)</option>
                    <option value="-recoveries_amount" {{ request('sort')==='-recoveries_amount' ? 'selected' : '' }}>Recoveries (High to Low)</option>
                    <option value="customer_name" {{ request('sort')==='customer_name' ? 'selected' : '' }}>Name (A-Z)</option>
                    <option value="-customer_name" {{ request('sort')==='-customer_name' ? 'selected' : '' }}>Name (Z-A)</option>
                    <option value="-credit_sales_count" {{ request('sort')==='-credit_sales_count' ? 'selected' : '' }}>Sales Count (High)</option>
                </select>
            </div>

            <div>
                <x-label for="per_page" value="Records Per Page" />
                <select id="per_page" name="per_page"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="10" {{ request('per_page')==10 ? 'selected' : '' }}>10</option>
                    <option value="25" {{ request('per_page')==25 ? 'selected' : '' }}>25</option>
                    <option value="50" {{ request('per_page', 50)==50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ request('per_page')==100 ? 'selected' : '' }}>100</option>
                    <option value="250" {{ request('per_page')==250 ? 'selected' : '' }}>250</option>
                    <option value="all" {{ request('per_page')==='all' ? 'selected' : '' }}>All</option>
                </select>
            </div>
        </div>
    </x-filter-section>

    {{-- Summary Cards --}}
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 no-print">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
                <div class="text-sm text-gray-500">Opening Balance</div>
                <div class="text-2xl font-bold text-purple-700">{{ number_format($totals->total_opening_balance ?? 0, 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
                <div class="text-sm text-gray-500">Credit Sales</div>
                <div class="text-2xl font-bold text-blue-700">{{ number_format($totals->total_credit_sales ?? 0, 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
                <div class="text-sm text-gray-500">Recoveries</div>
                <div class="text-2xl font-bold text-green-700">{{ number_format($totals->total_recoveries ?? 0, 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-orange-500">
                <div class="text-sm text-gray-500">Closing Balance</div>
                <div class="text-2xl font-bold text-orange-700">{{ number_format(($totals->total_opening_balance ?? 0) + ($totals->total_credit_sales ?? 0) - ($totals->total_recoveries ?? 0), 2) }}</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 border-l-4 border-gray-500">
                <div class="text-sm text-gray-500">Total Customers</div>
                <div class="text-2xl font-bold text-gray-700">{{ $customers->total() }}</div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                <p class="text-center font-extrabold mb-2">
                    Moon Traders<br>
                    <span class="text-sm font-normal text-gray-600">Customer-wise credit sales, recoveries and outstanding balance report</span><br>
                    Customer Credit Sales History<br>
                    Total Customers: {{ number_format($customers->total()) }} |
                    Outstanding: {{ number_format(($totals->total_opening_balance ?? 0) + ($totals->total_credit_sales ?? 0) - ($totals->total_recoveries ?? 0), 2) }}
                    <br>
                    <span class="print-only print-info text-xs text-center">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                <table class="report-table">
                    <thead>
                        <tr class="bg-gray-50">
                            <th>Sr#</th>
                            <th>Code</th>
                            <th>Customer Name</th>
                            <th>City</th>
                            <th>Opening Balance</th>
                            <th>Credit Sales</th>
                            <th>Recoveries</th>
                            <th>Closing Balance</th>
                            <th>Txns</th>
                            <th class="no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers as $index => $customer)
                            @php
                                $closingBalance = ($customer->opening_balance ?? 0) + ($customer->credit_sales_amount ?? 0) - ($customer->recoveries_amount ?? 0);
                            @endphp
                            <tr>
                                <td class="text-center" style="vertical-align: middle;">{{ $customers->firstItem() + $index }}</td>
                                <td class="font-mono" style="vertical-align: middle;">{{ $customer->customer_code }}</td>
                                <td style="vertical-align: middle;">
                                    {{ $customer->customer_name }}
                                    @if($customer->phone)
                                        <span class="text-xs text-gray-500">({{ $customer->phone }})</span>
                                    @endif
                                    @if($customer->business_name)
                                        <div class="text-xs text-gray-600">{{ $customer->business_name }}</div>
                                    @endif
                                </td>
                                <td style="vertical-align: middle;">{{ $customer->city ?? '-' }}</td>
                                <td class="text-right font-mono text-purple-700" style="vertical-align: middle;">
                                    {{ number_format($customer->opening_balance ?? 0, 2) }}
                                </td>
                                <td class="text-right font-mono text-blue-700" style="vertical-align: middle;">
                                    {{ number_format($customer->credit_sales_amount ?? 0, 2) }}
                                </td>
                                <td class="text-right font-mono text-green-700" style="vertical-align: middle;">
                                    {{ number_format($customer->recoveries_amount ?? 0, 2) }}
                                </td>
                                <td class="text-right font-mono font-bold {{ $closingBalance > 0 ? 'text-orange-700' : 'text-green-700' }}" style="vertical-align: middle;">
                                    {{ number_format($closingBalance, 2) }}
                                </td>
                                <td class="text-center" style="vertical-align: middle;">
                                    {{ $customer->credit_sales_count }}
                                </td>
                                <td class="text-center no-print" style="vertical-align: middle;">
                                    <div class="flex justify-center gap-1">
                                        <a href="{{ route('reports.credit-sales.customer-details', $customer) }}"
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
                                <td colspan="10" class="text-center py-4 text-gray-500">No credit sales found for any customer.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-100 font-extrabold">
                        <tr>
                            <td colspan="4" class="text-center px-2 py-1">Page Total ({{ $customers->count() }} customers)</td>
                            <td class="text-right font-mono px-2 py-1 text-purple-700">
                                {{ number_format($customers->sum('opening_balance'), 2) }}
                            </td>
                            <td class="text-right font-mono px-2 py-1 text-blue-700">
                                {{ number_format($customers->sum('credit_sales_amount'), 2) }}
                            </td>
                            <td class="text-right font-mono px-2 py-1 text-green-700">
                                {{ number_format($customers->sum('recoveries_amount'), 2) }}
                            </td>
                            <td class="text-right font-mono px-2 py-1 text-orange-700">
                                {{ number_format($customers->sum('opening_balance') + $customers->sum('credit_sales_amount') - $customers->sum('recoveries_amount'), 2) }}
                            </td>
                            <td class="text-center px-2 py-1">
                                {{ $customers->sum('credit_sales_count') }}
                            </td>
                            <td class="no-print"></td>
                        </tr>
                    </tfoot>
                </table>

                @if ($customers->hasPages())
                    <div class="mt-4 no-print">
                        {{ $customers->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
