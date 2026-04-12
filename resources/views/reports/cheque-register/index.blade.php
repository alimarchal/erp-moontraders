<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Cheque Register" :showSearch="true" :showRefresh="true" backRoute="reports.index" />
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
                color: #000;
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
                }

                .max-w-8xl {
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
                    font-size: 8px !important;
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
            }
        </style>
    @endpush

    <x-filter-section :action="route('reports.cheque-register.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">

            {{-- Supplier --}}
            <div class="relative">
                <div class="flex justify-between items-center">
                    <x-label for="filter_supplier_id" value="Supplier" />
                    @if($selectedSupplierId)
                        <a href="{{ route('reports.cheque-register.index', array_merge(request()->except(['filter.supplier_id', 'page']), ['filter[supplier_id]' => ''])) }}"
                            class="text-xs text-red-600 hover:text-red-800 underline">Clear</a>
                    @endif
                </div>
                <select id="filter_supplier_id" name="filter[supplier_id]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Suppliers</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ $selectedSupplierId == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->supplier_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Salesman --}}
            <div class="relative">
                <div class="flex justify-between items-center">
                    <x-label for="filter_employee_id" value="Salesman" />
                    @if($selectedEmployeeId)
                        <a href="{{ route('reports.cheque-register.index', array_merge(request()->except(['filter.employee_id', 'page']), ['filter[employee_id]' => ''])) }}"
                            class="text-xs text-red-600 hover:text-red-800 underline">Clear</a>
                    @endif
                </div>
                <select id="filter_employee_id" name="filter[employee_id]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Salesmen</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" {{ $selectedEmployeeId == $employee->id ? 'selected' : '' }}>
                            {{ $employee->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Customer --}}
            <div class="relative">
                <div class="flex justify-between items-center">
                    <x-label for="filter_customer_id" value="Customer" />
                    @if($selectedCustomerId)
                        <a href="{{ route('reports.cheque-register.index', array_merge(request()->except(['filter.customer_id', 'page']), ['filter[customer_id]' => ''])) }}"
                            class="text-xs text-red-600 hover:text-red-800 underline">Clear</a>
                    @endif
                </div>
                <select id="filter_customer_id" name="filter[customer_id]"
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Customers</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" {{ $selectedCustomerId == $customer->id ? 'selected' : '' }}>
                            {{ $customer->customer_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Status (multi-select) --}}
            <div class="relative">
                <div class="flex justify-between items-center">
                    <x-label for="filter_status" value="Status" />
                    @if(!empty($selectedStatuses))
                        <a href="{{ route('reports.cheque-register.index', array_merge(request()->except(['filter.status', 'page']), ['filter[status]' => ''])) }}"
                            class="text-xs text-red-600 hover:text-red-800 underline">Clear</a>
                    @endif
                </div>
                <select id="filter_status" name="filter[status][]" multiple
                    class="select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    @foreach($availableStatuses as $s)
                        <option value="{{ $s }}" {{ in_array($s, $selectedStatuses) ? 'selected' : '' }}>
                            {{ ucfirst($s) }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Cheque Date From --}}
            <div>
                <x-label for="filter_cheque_date_from" value="Cheque Date From" />
                <x-input id="filter_cheque_date_from" name="filter[cheque_date_from]" type="date"
                    class="mt-1 block w-full" :value="$chequeDateFrom" />
            </div>

            {{-- Cheque Date To --}}
            <div>
                <x-label for="filter_cheque_date_to" value="Cheque Date To" />
                <x-input id="filter_cheque_date_to" name="filter[cheque_date_to]" type="date" class="mt-1 block w-full"
                    :value="$chequeDateTo" />
            </div>

            {{-- Entry Date From --}}
            <div>
                <x-label for="filter_entry_date_from" value="Entry Date From" />
                <x-input id="filter_entry_date_from" name="filter[entry_date_from]" type="date"
                    class="mt-1 block w-full" :value="$entryDateFrom" />
            </div>

            {{-- Entry Date To --}}
            <div>
                <x-label for="filter_entry_date_to" value="Entry Date To" />
                <x-input id="filter_entry_date_to" name="filter[entry_date_to]" type="date" class="mt-1 block w-full"
                    :value="$entryDateTo" />
            </div>

            {{-- Bank Name --}}
            <div>
                <x-label for="filter_bank_name" value="Bank Name" />
                <x-input id="filter_bank_name" name="filter[bank_name]" type="text" class="mt-1 block w-full"
                    :value="$bankName" placeholder="Search bank name..." />
            </div>

            {{-- Cheque Number --}}
            <div>
                <x-label for="filter_cheque_number" value="Cheque Number" />
                <x-input id="filter_cheque_number" name="filter[cheque_number]" type="text" class="mt-1 block w-full"
                    :value="$chequeNumber" placeholder="Search cheque number..." />
            </div>

            {{-- Per Page --}}
            <div>
                <x-label for="per_page" value="Per Page" />
                <select id="per_page" name="per_page"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="10" {{ $perPage == 10 ? 'selected' : '' }}>10</option>
                    <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25</option>
                    <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100</option>
                    <option value="250" {{ $perPage == 250 ? 'selected' : '' }}>250</option>
                    <option value="all" {{ $perPage === 'all' ? 'selected' : '' }}>All</option>
                </select>
            </div>

        </div>
    </x-filter-section>

    <div class="max-w-8xl mx-auto sm:px-6 lg:px-8 pb-16">

        @if(session('success'))
            <div class="mb-4 mt-4 bg-green-50 border border-green-300 text-green-800 px-4 py-3 rounded-lg text-sm no-print">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 mt-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                <p class="text-center font-extrabold mb-2">
                    Moon Traders<br>
                    Cheque Register<br>
                    <span class="print-only print-info text-xs text-center">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                <table class="report-table">
                    <thead>
                        @php
                            $sortUrl = fn(string $col) => route('reports.cheque-register.index', array_merge(request()->query(), [
                                'sort' => $col,
                                'direction' => ($sortBy === $col && $sortDir === 'asc') ? 'desc' : 'asc',
                                'page' => 1,
                            ]));
                            $sortIcon = function (string $col) use ($sortBy, $sortDir): string {
                                if ($sortBy !== $col) {
                                    return '<svg class="inline w-3 h-3 text-gray-400 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/></svg>';
                                }
                                return $sortDir === 'asc'
                                    ? '<svg class="inline w-3 h-3 text-indigo-600 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>'
                                    : '<svg class="inline w-3 h-3 text-indigo-600 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>';
                            };
                        @endphp
                        <tr class="bg-gray-50">
                            <th class="w-10 text-center font-bold">#</th>
                            <th class="text-center font-bold px-2 whitespace-nowrap">
                                <a href="{{ $sortUrl('cheque_date') }}" class="hover:text-indigo-700">
                                    Cheque Date {!! $sortIcon('cheque_date') !!}
                                </a>
                            </th>
                            <th class="text-center font-bold px-2 whitespace-nowrap">Cheque No</th>
                            <th class="text-center font-bold px-2">Bank Name</th>
                            <th class="text-center font-bold px-2">Salesman</th>
                            <th class="text-center font-bold px-2">Shop Name</th>
                            <th class="text-center font-bold px-2">Address</th>
                            <th class="text-center font-bold px-2 whitespace-nowrap">
                                <a href="{{ $sortUrl('amount') }}" class="hover:text-indigo-700">
                                    Cheque Amount {!! $sortIcon('amount') !!}
                                </a>
                            </th>
                            <th class="text-center font-bold px-2 whitespace-nowrap">Cleared Date</th>
                            @can('report-audit-cheque-register-manage')
                                <th class="text-center font-bold px-2 no-print">Action</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cheques as $index => $cheque)
                            <tr x-data>
                                <td class="text-center text-black">
                                    {{ ($cheques->currentPage() - 1) * $cheques->perPage() + $index + 1 }}
                                </td>
                                <td class="text-center px-2 whitespace-nowrap text-black">
                                    {{ $cheque->cheque_date ? \Carbon\Carbon::parse($cheque->cheque_date)->format('d-M-y') : '—' }}
                                </td>
                                <td class="px-2 whitespace-nowrap">
                                    <a href="{{ route('sales-settlements.show', $cheque->sales_settlement_id) }}"
                                        target="_blank"
                                        class="text-indigo-600 hover:text-indigo-800 hover:underline font-mono">
                                        {{ $cheque->cheque_number }}
                                    </a>
                                </td>
                                <td class="px-2 text-black">{{ $cheque->bank_name }}</td>
                                <td class="px-2 text-black">{{ $cheque->employee_name ?? '—' }}</td>
                                <td class="px-2 text-black">{{ $cheque->customer_name ?? '—' }}</td>
                                <td class="px-2 text-black">{{ $cheque->customer_address ?? '—' }}</td>
                                <td class="text-right font-mono px-2 text-black">
                                    {{ number_format($cheque->amount, 2) }}
                                </td>
                                <td class="text-center px-2 whitespace-nowrap text-black">
                                    {{ $cheque->cleared_date ? \Carbon\Carbon::parse($cheque->cleared_date)->format('d-M-y') : '—' }}
                                </td>
                                @can('report-audit-cheque-register-manage')
                                    <td class="text-center px-2 no-print">
                                        @php
                                            $statusConfig = match ($cheque->status) {
                                                'cleared' => ['label' => 'Cleared', 'class' => 'bg-green-100 text-green-800 hover:bg-green-200', 'tick' => true],
                                                'bounced' => ['label' => 'Bounced', 'class' => 'bg-red-100 text-red-800 hover:bg-red-200', 'tick' => false],
                                                'cancelled' => ['label' => 'Cancelled', 'class' => 'bg-gray-100 text-gray-700 hover:bg-gray-200', 'tick' => false],
                                                default => ['label' => 'Pending', 'class' => 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200', 'tick' => false],
                                            };
                                        @endphp
                                        <button type="button" @click="$dispatch('open-cheque-status-modal', {
                                                        url: '{{ route('reports.cheque-register.update-status', $cheque->id) }}',
                                                        status: '{{ $cheque->status }}',
                                                        clearedDate: '{{ $cheque->cleared_date?->format('Y-m-d') }}'
                                                    })"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold transition {{ $statusConfig['class'] }}">
                                            @if($statusConfig['tick'])
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                                        d="M5 13l4 4L19 7" />
                                                </svg>
                                            @endif
                                            {{ $statusConfig['label'] }}
                                        </button>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-8 text-gray-500">No cheques found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-100 font-extrabold">
                        <tr>
                            <td colspan="7" class="text-right px-2 py-1 text-black">Total</td>
                            <td class="text-right font-mono px-2 py-1 text-black">
                                {{ number_format($totalAmount, 2) }}
                            </td>
                            <td class="px-2 py-1"></td>
                            @can('report-audit-cheque-register-manage')
                                <td class="no-print"></td>
                            @endcan
                        </tr>
                    </tfoot>
                </table>

                @if($cheques->hasPages())
                    <div class="mt-4 no-print">
                        {{ $cheques->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Status Update Modal --}}
    @can('report-audit-cheque-register-manage')
        <div x-data="chequeStatusModal()" x-on:open-cheque-status-modal.window="open($event.detail)" x-cloak
            class="no-print">

            <div x-show="show" x-on:keydown.escape.window="if (show) { close() }" class="fixed inset-0 z-50 overflow-y-auto"
                style="display: none;">

                {{-- Backdrop --}}
                <div x-show="show" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-150"
                    x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                    class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm transition-all" @click="close()">
                </div>

                {{-- Panel --}}
                <div class="fixed inset-0 z-10 flex items-center justify-center overflow-y-auto p-4">
                    <div x-show="show" x-transition:enter="ease-out duration-200"
                        x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
                        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave="ease-in duration-150"
                        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave-end="opacity-0 translate-y-4 sm:scale-95"
                        class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-2xl transition-all sm:w-full sm:max-w-md"
                        @click.outside="close()">

                        <div class="bg-gray-800 text-white px-6 py-3 rounded-t-xl flex justify-between items-center">
                            <h3 class="text-base font-bold">Update Cheque Status</h3>
                            <button type="button" @click="close()" class="text-gray-300 hover:text-white">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <form :action="formAction" method="POST" class="p-6 space-y-4">
                            @csrf

                            <div>
                                <x-label value="Status" />
                                <select name="status" x-model="form.status"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="pending">Pending</option>
                                    <option value="cleared">Cleared</option>
                                    <option value="bounced">Bounced</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>

                            <div>
                                <x-label value="Cleared Date" />
                                <x-input type="date" name="cleared_date" x-model="form.cleared_date"
                                    class="mt-1 block w-full" />
                            </div>

                            <div class="flex justify-end gap-3 pt-2">
                                <button type="button" @click="close()"
                                    class="px-4 py-2 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-100">
                                    Cancel
                                </button>
                                <button type="submit"
                                    class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-md hover:bg-indigo-700 shadow-sm">
                                    Update
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        @push('scripts')
            <script>
                function chequeStatusModal() {
                    return {
                        show: false,
                        formAction: '',
                        form: {
                            status: 'pending',
                            cleared_date: '',
                        },

                        open(detail) {
                            this.formAction = detail.url;
                            this.form.status = detail.status || 'pending';
                            this.form.cleared_date = detail.clearedDate || '';
                            this.show = true;
                        },

                        close() {
                            this.show = false;
                        },
                    };
                }
            </script>
        @endpush
    @endcan

</x-app-layout>