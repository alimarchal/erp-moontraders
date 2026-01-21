<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Shop List" :showSearch="true" :showRefresh="true" backRoute="reports.index" />
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
        </style>
    @endpush

    <x-filter-section :action="route('reports.shop-list.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_customer_name" value="Store Name" />
                <x-input id="filter_customer_name" name="filter[customer_name]" type="text"
                    class="mt-1 block w-full" :value="request('filter.customer_name')" placeholder="Search by name" />
            </div>

            <div>
                <x-label for="filter_customer_code" value="Store Code" />
                <x-input id="filter_customer_code" name="filter[customer_code]" type="text"
                    class="mt-1 block w-full uppercase" :value="request('filter.customer_code')" placeholder="CUST-001" />
            </div>

            <div>
                <x-label for="filter_channel_type" value="Channel" />
                <select id="filter_channel_type" name="filter[channel_type]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Channels</option>
                    @foreach ($channelTypes as $type)
                        <option value="{{ $type }}" {{ request('filter.channel_type') === $type ? 'selected' : '' }}>
                            {{ $type }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_it_status" value="I.T Status" />
                <select id="filter_it_status" name="filter[it_status]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    @foreach ($itStatusOptions as $value => $label)
                        <option value="{{ $value }}" {{ request('filter.it_status') === (string) $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_ntn" value="NTN" />
                <x-input id="filter_ntn" name="filter[ntn]" type="text"
                    class="mt-1 block w-full" :value="request('filter.ntn')" placeholder="Search NTN" />
            </div>

            <div>
                <x-label for="filter_city" value="City" />
                <x-input id="filter_city" name="filter[city]" type="text"
                    class="mt-1 block w-full" :value="request('filter.city')" placeholder="Muzaffarabad" />
            </div>

            <div>
                <x-label for="filter_sub_locality" value="Area / Sub Locality" />
                <x-input id="filter_sub_locality" name="filter[sub_locality]" type="text"
                    class="mt-1 block w-full" :value="request('filter.sub_locality')" placeholder="Satellite Town" />
            </div>

            <div>
                <x-label for="filter_customer_category" value="Category" />
                <select id="filter_customer_category" name="filter[customer_category]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Categories</option>
                    @foreach ($customerCategories as $category)
                        <option value="{{ $category }}" {{ request('filter.customer_category') === $category ? 'selected' : '' }}>
                            Category {{ $category }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_sales_rep_id" value="Sales Rep" />
                <select id="filter_sales_rep_id" name="filter[sales_rep_id]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Reps</option>
                    @foreach ($salesRepOptions as $salesRep)
                        <option value="{{ $salesRep->id }}" {{ request('filter.sales_rep_id') === (string) $salesRep->id ? 'selected' : '' }}>
                            {{ $salesRep->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_is_active" value="Status" />
                <select id="filter_is_active" name="filter[is_active]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" {{ request('filter.is_active') === (string) $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_phone" value="Phone" />
                <x-input id="filter_phone" name="filter[phone]" type="text" class="mt-1 block w-full"
                    :value="request('filter.phone')" placeholder="03XX-XXXXXXX" />
            </div>

            <div>
                <x-label for="per_page" value="Records Per Page" />
                <select id="per_page" name="per_page"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    @foreach ($perPageOptions as $option)
                        <option value="{{ $option }}" {{ $currentPerPage === $option ? 'selected' : '' }}>
                            {{ number_format($option) }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 mt-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                <p class="text-center font-extrabold mb-2">
                    Moon Traders<br>
                    Shop List Report<br>
                    Total Records: {{ number_format($customers->total()) }}
                    <br>
                    <span class="print-only print-info text-xs text-center">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                <table class="report-table">
                    <thead>
                        <tr class="bg-gray-50">
                            <th style="width: 40px;">Sr#</th>
                            <th style="width: 100px;">Store Code</th>
                            <th style="width: 180px;">Store Name</th>
                            <th style="width: 100px;">Channel</th>
                            <th style="width: 120px;">NTN</th>
                            <th style="width: 250px;">Address</th>
                            <th style="width: 80px;">I.T Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers as $index => $customer)
                            <tr>
                                <td class="text-center" style="vertical-align: middle;">{{ $customers->firstItem() + $index }}</td>
                                <td style="vertical-align: middle;">{{ $customer->customer_code }}</td>
                                <td style="vertical-align: middle;">{{ $customer->customer_name }}</td>
                                <td style="vertical-align: middle;">{{ $customer->channel_type }}</td>
                                <td style="vertical-align: middle;">{{ $customer->ntn ?? '-' }}</td>
                                <td style="vertical-align: middle;">{{ $customer->address ?? '-' }}</td>
                                <td class="text-center {{ $customer->it_status ? 'text-green-600' : 'text-red-600' }}" style="vertical-align: middle; font-weight: bold;">
                                    {{ $customer->it_status ? 'Filer' : 'Non-Filer' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-gray-500">No records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
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
