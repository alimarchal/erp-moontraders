<x-app-layout>
    <x-slot name="header">
        <x-page-header title="AMR Dispose Register" :showSearch="true" :showRefresh="true" backRoute="reports.index" />
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

    <x-filter-section :action="route('reports.amr-dispose-register.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">

            {{-- Supplier --}}
            <div class="relative">
                <div class="flex justify-between items-center">
                    <x-label for="filter_supplier_id" value="Supplier" />
                    @if($selectedSupplierId)
                        <a href="{{ route('reports.amr-dispose-register.index', array_merge(request()->except(['filter.supplier_id', 'page']), ['filter[supplier_id]' => ''])) }}"
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
                        <a href="{{ route('reports.amr-dispose-register.index', array_merge(request()->except(['filter.employee_id', 'page']), ['filter[employee_id]' => ''])) }}"
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

            {{-- Type --}}
            <div>
                <x-label for="filter_type" value="Type" />
                <select id="filter_type" name="filter[type]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="both" {{ $selectedType === 'both' ? 'selected' : '' }}>Liquids & Powders</option>
                    <option value="liquids" {{ $selectedType === 'liquids' ? 'selected' : '' }}>Liquids Only</option>
                    <option value="powders" {{ $selectedType === 'powders' ? 'selected' : '' }}>Powders Only</option>
                </select>
            </div>

            {{-- Dispose Status --}}
            <div>
                <x-label for="filter_is_disposed" value="Dispose Status" />
                <select id="filter_is_disposed" name="filter[is_disposed]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="0" {{ $selectedIsDisposed === '0' ? 'selected' : '' }}>Not Disposed</option>
                    <option value="1" {{ $selectedIsDisposed === '1' ? 'selected' : '' }}>Disposed</option>
                    <option value="all" {{ $selectedIsDisposed === 'all' ? 'selected' : '' }}>All</option>
                </select>
            </div>

            {{-- Product Name --}}
            <div>
                <x-label for="filter_product_name" value="Product Name" />
                <x-input id="filter_product_name" name="filter[product_name]" type="text" class="mt-1 block w-full"
                    :value="$productName" placeholder="Search product..." />
            </div>

            {{-- Settlement Date From --}}
            <div>
                <x-label for="filter_settlement_date_from" value="Settlement Date From" />
                <x-input id="filter_settlement_date_from" name="filter[settlement_date_from]" type="date"
                    class="mt-1 block w-full" :value="$settlementDateFrom" />
            </div>

            {{-- Settlement Date To --}}
            <div>
                <x-label for="filter_settlement_date_to" value="Settlement Date To" />
                <x-input id="filter_settlement_date_to" name="filter[settlement_date_to]" type="date"
                    class="mt-1 block w-full" :value="$settlementDateTo" />
            </div>

            {{-- Disposed At From --}}
            <div>
                <x-label for="filter_disposed_at_from" value="Disposed At From" />
                <x-input id="filter_disposed_at_from" name="filter[disposed_at_from]" type="date"
                    class="mt-1 block w-full" :value="$disposedAtFrom" />
            </div>

            {{-- Disposed At To --}}
            <div>
                <x-label for="filter_disposed_at_to" value="Disposed At To" />
                <x-input id="filter_disposed_at_to" name="filter[disposed_at_to]" type="date"
                    class="mt-1 block w-full" :value="$disposedAtTo" />
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

    <div class="max-w-8xl mx-auto sm:px-6 lg:px-8 pb-16"
        x-data="amrDisposeRegister('{{ route('reports.amr-dispose-register.bulk-update-disposed') }}')">

        @if(session('success'))
            <div class="mb-4 mt-4 bg-green-50 border border-green-300 text-green-800 px-4 py-3 rounded-lg text-sm no-print">
                {{ session('success') }}
            </div>
        @endif

        {{-- Bulk Action Bar --}}
        @can('report-audit-amr-dispose-register-manage')
            <div x-show="selected.length > 0" x-cloak
                class="no-print mb-3 mt-4 flex items-center gap-3 bg-indigo-50 border border-indigo-200 rounded-lg px-4 py-2">
                <span class="text-sm font-semibold text-indigo-700" x-text="selected.length + ' record(s) selected'"></span>
                <div class="flex gap-2 ml-auto">
                    <button type="button" @click="bulkUpdate(1)"
                        class="px-3 py-1.5 text-xs font-semibold text-white bg-green-600 rounded-md hover:bg-green-700 transition">
                        Mark Disposed
                    </button>
                    <button type="button" @click="bulkUpdate(0)"
                        class="px-3 py-1.5 text-xs font-semibold text-white bg-yellow-600 rounded-md hover:bg-yellow-700 transition">
                        Mark Pending
                    </button>
                    <button type="button" @click="clearSelection()"
                        class="px-3 py-1.5 text-xs font-semibold text-gray-600 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition">
                        Clear
                    </button>
                </div>
            </div>
        @endcan

        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 mt-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                <p class="text-center font-extrabold mb-2">
                    Moon Traders<br>
                    AMR Dispose Register<br>
                    <span class="print-only print-info text-xs text-center">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                <table class="report-table">
                    <thead>
                        @php
                            $sortUrl = fn(string $col) => route('reports.amr-dispose-register.index', array_merge(request()->query(), [
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
                            @can('report-audit-amr-dispose-register-manage')
                                <th class="w-8 text-center no-print">
                                    <input type="checkbox" @change="toggleAll($event)" :checked="allSelected"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                </th>
                            @endcan
                            <th class="w-10 text-center font-bold">#</th>
                            <th class="text-center font-bold px-2 whitespace-nowrap">
                                <a href="{{ $sortUrl('settlement_date') }}" class="hover:text-indigo-700">
                                    Settlement Date {!! $sortIcon('settlement_date') !!}
                                </a>
                            </th>
                            <th class="text-center font-bold px-2 whitespace-nowrap">Settlement No</th>
                            <th class="text-center font-bold px-2">Salesman</th>
                            <th class="text-center font-bold px-2">Product</th>
                            <th class="text-center font-bold px-2">Type</th>
                            <th class="text-center font-bold px-2">Batch Code</th>
                            <th class="text-center font-bold px-2 whitespace-nowrap">
                                <a href="{{ $sortUrl('quantity') }}" class="hover:text-indigo-700">
                                    Qty {!! $sortIcon('quantity') !!}
                                </a>
                            </th>
                            <th class="text-center font-bold px-2 whitespace-nowrap">
                                <a href="{{ $sortUrl('amount') }}" class="hover:text-indigo-700">
                                    Amount {!! $sortIcon('amount') !!}
                                </a>
                            </th>
                            <th class="text-center font-bold px-2">Notes</th>
                            <th class="text-center font-bold px-2 whitespace-nowrap">
                                <a href="{{ $sortUrl('disposed_at') }}" class="hover:text-indigo-700">
                                    Disposed At {!! $sortIcon('disposed_at') !!}
                                </a>
                            </th>
                            @can('report-audit-amr-dispose-register-manage')
                                <th class="text-center font-bold px-2 no-print">Action</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($records as $index => $record)
                            @php $recordType = strtolower($record->record_type); @endphp
                            <tr>
                                @can('report-audit-amr-dispose-register-manage')
                                    <td class="text-center no-print"
                                        data-row-type="{{ $recordType }}"
                                        data-row-id="{{ $record->id }}">
                                        <input type="checkbox"
                                            @change="toggle('{{ $recordType }}', {{ $record->id }}, $event)"
                                            :checked="isSelected('{{ $recordType }}', {{ $record->id }})"
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                    </td>
                                @endcan
                                <td class="text-center text-black">
                                    {{ ($records->currentPage() - 1) * $records->perPage() + $index + 1 }}
                                </td>
                                <td class="text-center px-2 whitespace-nowrap text-black">
                                    {{ $record->settlement_date ? \Carbon\Carbon::parse($record->settlement_date)->format('d-M-y') : '—' }}
                                </td>
                                <td class="px-2 whitespace-nowrap">
                                    <a href="{{ route('sales-settlements.show', $record->sales_settlement_id) }}"
                                        target="_blank"
                                        class="text-indigo-600 hover:text-indigo-800 hover:underline font-mono">
                                        {{ $record->settlement_number }}
                                    </a>
                                </td>
                                <td class="px-2 text-black">{{ $record->employee_name ?? '—' }}</td>
                                <td class="px-2 text-black">{{ $record->product_name }}</td>
                                <td class="text-center px-2">
                                    <span @class([
                                        'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold',
                                        'bg-blue-100 text-blue-800' => $record->record_type === 'Liquid',
                                        'bg-purple-100 text-purple-800' => $record->record_type === 'Powder',
                                    ])>
                                        {{ $record->record_type }}
                                    </span>
                                </td>
                                <td class="px-2 text-black font-mono">{{ $record->batch_code ?? '—' }}</td>
                                <td class="text-right font-mono px-2 text-black">
                                    {{ number_format($record->quantity, 2) }}
                                </td>
                                <td class="text-right font-mono px-2 text-black">
                                    {{ number_format($record->amount, 2) }}
                                </td>
                                <td class="px-2 text-black text-xs">{{ $record->notes ?? '—' }}</td>
                                <td class="text-center px-2 whitespace-nowrap text-black">
                                    {{ $record->disposed_at ? \Carbon\Carbon::parse($record->disposed_at)->format('d-M-y') : '—' }}
                                </td>
                                @can('report-audit-amr-dispose-register-manage')
                                    <td class="text-center px-2 no-print">
                                        <button type="button"
                                            @click="openModal('{{ route('reports.amr-dispose-register.update-disposed', [$recordType, $record->id]) }}', {{ $record->is_disposed ? 'true' : 'false' }})"
                                            @class([
                                                'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold transition',
                                                'bg-green-100 text-green-800 hover:bg-green-200' => $record->is_disposed,
                                                'bg-yellow-100 text-yellow-800 hover:bg-yellow-200' => ! $record->is_disposed,
                                            ])>
                                            @if($record->is_disposed)
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                                        d="M5 13l4 4L19 7" />
                                                </svg>
                                                Disposed
                                            @else
                                                Pending
                                            @endif
                                        </button>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="text-center py-8 text-gray-500">No records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-100 font-extrabold">
                        <tr>
                            @can('report-audit-amr-dispose-register-manage')
                                <td class="no-print"></td>
                            @endcan
                            <td colspan="7" class="text-right px-2 py-1 text-black">Total</td>
                            <td class="text-right font-mono px-2 py-1 text-black">
                                {{ number_format($totalQuantity, 2) }}
                            </td>
                            <td class="text-right font-mono px-2 py-1 text-black">
                                {{ number_format($totalAmount, 2) }}
                            </td>
                            <td colspan="2" class="px-2 py-1"></td>
                            @can('report-audit-amr-dispose-register-manage')
                                <td class="no-print"></td>
                            @endcan
                        </tr>
                    </tfoot>
                </table>

                @if($records->hasPages())
                    <div class="mt-4 no-print">
                        {{ $records->links() }}
                    </div>
                @endif
            </div>
        </div>

        {{-- Single Update Modal --}}
        @can('report-audit-amr-dispose-register-manage')
            <div x-show="modal.show" x-cloak x-on:keydown.escape.window="if (modal.show) { closeModal() }"
                class="fixed inset-0 z-50 overflow-y-auto no-print" style="display: none;">

                <div x-show="modal.show" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-150"
                    x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                    class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm transition-all" @click="closeModal()">
                </div>

                <div class="fixed inset-0 z-10 flex items-center justify-center overflow-y-auto p-4">
                    <div x-show="modal.show" x-transition:enter="ease-out duration-200"
                        x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
                        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave="ease-in duration-150"
                        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave-end="opacity-0 translate-y-4 sm:scale-95"
                        class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-2xl transition-all sm:w-full sm:max-w-sm"
                        @click.outside="closeModal()">

                        <div class="bg-gray-800 text-white px-6 py-3 rounded-t-xl flex justify-between items-center">
                            <h3 class="text-base font-bold">Update Dispose Status</h3>
                            <button type="button" @click="closeModal()" class="text-gray-300 hover:text-white">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <form :action="modal.url" method="POST" class="p-6 space-y-4">
                            @csrf

                            <div>
                                <x-label value="Dispose Status" />
                                <select name="is_disposed" x-model="modal.isDisposed"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="0">Not Disposed (Pending)</option>
                                    <option value="1">Disposed</option>
                                </select>
                            </div>

                            <div class="flex justify-end gap-3 pt-2">
                                <button type="button" @click="closeModal()"
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

            {{-- Hidden Bulk Form --}}
            <form id="bulk-dispose-form" method="POST" action="" style="display:none;">
                @csrf
                <div id="bulk-form-items"></div>
                <input type="hidden" name="is_disposed" id="bulk-is-disposed-value" value="1" />
            </form>
        @endcan

    </div>

    @can('report-audit-amr-dispose-register-manage')
        @push('scripts')
            <script>
                function amrDisposeRegister(bulkUrl) {
                    return {
                        selected: [],
                        modal: {
                            show: false,
                            url: '',
                            isDisposed: '0',
                        },

                        get allSelected() {
                            const checkboxes = document.querySelectorAll('tbody input[type=checkbox]');
                            return checkboxes.length > 0 && this.selected.length === checkboxes.length;
                        },

                        toggle(type, id, event) {
                            const key = type + ':' + id;
                            if (event.target.checked) {
                                if (! this.selected.find(s => s.key === key)) {
                                    this.selected.push({ key, type, id });
                                }
                            } else {
                                this.selected = this.selected.filter(s => s.key !== key);
                            }
                        },

                        isSelected(type, id) {
                            return !! this.selected.find(s => s.key === type + ':' + id);
                        },

                        toggleAll(event) {
                            if (event.target.checked) {
                                this.selected = [];
                                document.querySelectorAll('tbody [data-row-type]').forEach(el => {
                                    const type = el.dataset.rowType;
                                    const id = parseInt(el.dataset.rowId);
                                    this.selected.push({ key: type + ':' + id, type, id });
                                });
                            } else {
                                this.selected = [];
                            }
                        },

                        clearSelection() {
                            this.selected = [];
                        },

                        openModal(url, isDisposed) {
                            this.modal.url = url;
                            this.modal.isDisposed = isDisposed ? '1' : '0';
                            this.modal.show = true;
                        },

                        closeModal() {
                            this.modal.show = false;
                        },

                        bulkUpdate(isDisposed) {
                            if (this.selected.length === 0) { return; }

                            const form = document.getElementById('bulk-dispose-form');
                            form.action = bulkUrl;
                            document.getElementById('bulk-is-disposed-value').value = isDisposed;

                            const container = document.getElementById('bulk-form-items');
                            container.innerHTML = '';
                            this.selected.forEach((item, index) => {
                                container.innerHTML +=
                                    `<input type="hidden" name="items[${index}][type]" value="${item.type}" />` +
                                    `<input type="hidden" name="items[${index}][id]" value="${item.id}" />`;
                            });

                            form.submit();
                        },
                    };
                }
            </script>
        @endpush
    @endcan

</x-app-layout>
