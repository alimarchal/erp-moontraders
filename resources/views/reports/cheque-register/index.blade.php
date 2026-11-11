<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Cheque Register" :showSearch="true" :showRefresh="true" backRoute="reports.index" />
    </x-slot>

    {{-- Apple-style skin shared with the Bank Register (grey canvas, pill buttons, system font); the rules below finish this page --}}
    <script>document.documentElement.classList.add('apl-on');</script>
    <x-bank.apple-style />

    @push('header')
        <style>
            /* ---------- Screen: Apple-style register. Paper keeps the black grid below (@media print). ---------- */
            @media screen {
                .crx-card { border-radius: 18px !important; box-shadow: 0 1px 1px rgba(0,0,0,.02), 0 4px 18px rgba(0,0,0,.05) !important; padding: 20px 0 8px !important; }
                .crx-title { font-family: var(--apl-font); font-size: 13px; font-weight: 500; color: var(--apl-label-2); letter-spacing: 0; }
                .crx-title b { display: block; font-size: 22px; font-weight: 700; letter-spacing: -.02em; color: var(--apl-label); }
                .report-table.crx-table { border: 0; border-collapse: separate; border-spacing: 0; font-family: var(--apl-font); font-size: 14px; line-height: 1.35; letter-spacing: -.005em; }
                .report-table.crx-table th, .report-table.crx-table td { border: 0; border-bottom: 1px solid var(--apl-sep); padding: 11px 10px; color: var(--apl-label); vertical-align: middle; }
                .report-table.crx-table thead tr { background: transparent !important; }
                .report-table.crx-table thead th { position: sticky; top: 0; z-index: 2; background: rgba(255,255,255,.88); -webkit-backdrop-filter: saturate(180%) blur(12px); backdrop-filter: saturate(180%) blur(12px);
                    font-size: 12px; font-weight: 500 !important; color: var(--apl-label-2); text-align: left !important; white-space: nowrap; }
                .report-table.crx-table thead th a { color: inherit; }
                .report-table.crx-table th:first-child, .report-table.crx-table td:first-child { padding-left: 20px; color: var(--apl-label-3); }
                .report-table.crx-table th:last-child, .report-table.crx-table td:last-child { padding-right: 20px; }
                .report-table.crx-table tbody tr { transition: background-color .15s ease; }
                .report-table.crx-table tbody tr:hover td { background: #fafafc; }
                .report-table.crx-table tbody td.text-right, .report-table.crx-table tfoot td.text-right { font-variant-numeric: tabular-nums; font-family: var(--apl-font); }
                .report-table.crx-table thead th.crx-amount { text-align: right !important; }
                .report-table.crx-table td.crx-check, .report-table.crx-table th.crx-check { width: 36px; padding-left: 16px !important; padding-right: 0 !important; }
                .crx-check input { width: 18px; height: 18px; border-radius: 6px; border: 1.5px solid var(--apl-label-3); color: var(--apl-blue); cursor: pointer; }
                .crx-check input:focus { box-shadow: 0 0 0 3px rgba(0,113,227,.25); outline: none; }
                .report-table.crx-table tbody tr.is-picked td { background: rgba(0,113,227,.06); }
                .crx-bar { position: fixed; left: 50%; bottom: 20px; z-index: 40; transform: translateX(-50%); display: flex; align-items: center; gap: 14px; padding: 10px 10px 10px 20px; border-radius: 999px;
                    background: rgba(29,29,31,.86); color: #fff; -webkit-backdrop-filter: saturate(180%) blur(20px); backdrop-filter: saturate(180%) blur(20px); box-shadow: 0 10px 40px rgba(0,0,0,.25); font-size: 14px; max-width: calc(100vw - 32px); }
                .crx-bar b { font-variant-numeric: tabular-nums; }
                .crx-bar .crx-btn { height: 36px; }
                .crx-bar .crx-bar-link { color: rgba(255,255,255,.75); background: none; border: 0; font-size: 13px; cursor: pointer; padding: 0 6px; }
                .crx-bar .crx-bar-link:hover { color: #fff; }
                .crx-readonly { display: flex; align-items: center; justify-content: space-between; height: 44px; padding: 0 14px; border-radius: 12px; background: var(--apl-fill-2, #f2f2f7); font-variant-numeric: tabular-nums; font-weight: 600; color: var(--apl-label); }
                .crx-readonly small { font-weight: 400; color: var(--apl-label-3); font-size: 12px; }
                .report-table.crx-table tfoot tr { background: transparent !important; }
                .report-table.crx-table tfoot td { border-bottom: 0; border-top: 1px solid var(--apl-sep); padding-top: 14px; padding-bottom: 14px; font-weight: 600; }
                .report-table.crx-table a.font-mono { font-family: var(--apl-font); font-variant-numeric: tabular-nums; font-weight: 600; color: var(--apl-blue); text-decoration: none; }
                .report-table.crx-table a.font-mono:hover { text-decoration: underline; }
                .report-table.crx-table td.text-center { text-align: left; }
                .report-table.crx-table td.text-center:first-child, .report-table.crx-table td:last-child { text-align: center; }
                .crx-card .overflow-x-auto { overflow: auto; max-height: calc(100vh - 220px); }
            }

            /* Status capsules: tinted system colours, press feedback */
            .crx-pill { display: inline-flex; align-items: center; gap: 4px; padding: 4px 11px; border-radius: 980px; font-family: var(--apl-font); font-size: 12px; font-weight: 600; letter-spacing: -.005em; cursor: pointer;
                transition: background-color .2s ease, transform .16s var(--apl-ease-out); }
            .crx-pill:active { transform: scale(.96); }
            .crx-pill-pending { background: var(--apl-orange-tint); color: var(--apl-orange); } .crx-pill-pending:hover { background: rgba(255,149,0,.26); }
            .crx-pill-cleared { background: var(--apl-green-tint); color: var(--apl-green); } .crx-pill-cleared:hover { background: rgba(52,199,89,.24); }
            .crx-pill-bounced { background: var(--apl-red-tint); color: var(--apl-red); } .crx-pill-bounced:hover { background: rgba(255,59,48,.2); }
            .crx-pill-cancelled { background: var(--apl-fill-2); color: #48484a; } .crx-pill-cancelled:hover { background: var(--apl-fill); }
            .crx-icon-btn { display: inline-flex; width: 22px; height: 22px; align-items: center; justify-content: center; border-radius: 980px; color: var(--apl-label-3); transition: background-color .15s ease, color .15s ease; }
            .crx-icon-btn:hover { background: var(--apl-blue-tint); color: var(--apl-blue); }

            /* ---------- Sheets (status and history windows) ---------- */
            .crx-scrim { background: rgba(0,0,0,.28) !important; -webkit-backdrop-filter: blur(8px) saturate(120%); backdrop-filter: blur(8px) saturate(120%); }
            .crx-sheet { border-radius: 22px !important; font-family: var(--apl-font); box-shadow: 0 0 0 .5px rgba(0,0,0,.1), 0 30px 80px rgba(0,0,0,.25) !important; }
            .crx-sheet-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 18px 18px 14px 24px; border-bottom: 1px solid var(--apl-sep);
                background: rgba(255,255,255,.9); -webkit-backdrop-filter: saturate(180%) blur(20px); backdrop-filter: saturate(180%) blur(20px); color: var(--apl-label); }
            .crx-sheet-head h3 { margin: 0; font-size: 19px; font-weight: 600; letter-spacing: -.02em; color: var(--apl-label); }
            .crx-sheet-head p { margin: 2px 0 0; font-size: 13px; color: var(--apl-label-2); }
            .crx-sheet-head .crx-tag { display: inline-flex; margin-inline-end: 6px; padding: 1px 8px; border-radius: 980px; background: var(--apl-green-tint); color: var(--apl-green); font-size: 11px; font-weight: 600; vertical-align: 2px; }
            .crx-close { display: inline-flex; width: 30px; height: 30px; flex: none; align-items: center; justify-content: center; border-radius: 980px; background: var(--apl-fill); color: var(--apl-label-2);
                transition: background-color .15s ease, transform .16s var(--apl-ease-out); }
            .crx-close:hover { background: rgba(118,118,128,.22); color: var(--apl-label); }
            .crx-close:active { transform: scale(.94); }
            .crx-label { display: block; margin-bottom: 6px; font-size: 12px; font-weight: 500; color: var(--apl-label-2); }
            .crx-seg { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 2px; padding: 3px; border-radius: 12px; background: var(--apl-fill); }
            .crx-seg-option { display: block; padding: 8px 6px; border-radius: 9px; text-align: center; font-size: 14px; font-weight: 500; color: var(--apl-label); cursor: pointer;
                transition: background-color .2s ease, box-shadow .2s ease, transform .16s var(--apl-ease-out); }
            .crx-seg-option:hover { background: rgba(255,255,255,.55); }
            .crx-seg-option:active { transform: scale(.97); }
            .crx-seg-option.is-on { background: #fff; font-weight: 600; box-shadow: 0 3px 8px rgba(0,0,0,.12), 0 3px 1px rgba(0,0,0,.04); }
            .crx-seg-option.is-on.tone-cleared { color: var(--apl-green); } .crx-seg-option.is-on.tone-bounced { color: var(--apl-red); } .crx-seg-option.is-on.tone-pending { color: var(--apl-orange); }
            .crx-sheet input[type="date"], .crx-sheet input[type="number"], .crx-sheet input[type="text"] {
                height: 42px; width: 100%; padding: 0 12px; border: 0 !important; border-radius: 10px; background: var(--apl-fill-2); box-shadow: none !important;
                font-family: var(--apl-font); font-size: 16px; color: var(--apl-label); transition: box-shadow .2s ease, background-color .2s ease; }
            .crx-sheet input:focus { outline: none; background: #fff; box-shadow: 0 0 0 4px rgba(0,113,227,.25), 0 0 0 1px var(--apl-blue) !important; }
            .crx-sheet input[type="number"] { font-variant-numeric: tabular-nums; }
            .crx-notes { padding: 16px; border-radius: 16px; background: var(--apl-bg); }
            .crx-notes .crx-note-sub { font-size: 11px; color: var(--apl-label-3); text-align: right; font-variant-numeric: tabular-nums; }
            .crx-total { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 8px; margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--apl-sep); font-size: 14px; }
            .crx-total b { font-size: 20px; font-weight: 600; letter-spacing: -.02em; font-variant-numeric: tabular-nums; }
            .crx-btn { display: inline-flex; align-items: center; justify-content: center; height: 40px; padding: 0 20px; border: 0; border-radius: 980px; font-family: var(--apl-font); font-size: 15px; font-weight: 500;
                transition: background-color .2s ease, transform .16s var(--apl-ease-out); }
            .crx-btn:active { transform: scale(.97); }
            .crx-btn-secondary { background: var(--apl-fill); color: var(--apl-label); } .crx-btn-secondary:hover { background: rgba(118,118,128,.2); }
            .crx-btn-primary { background: var(--apl-blue); color: #fff; } .crx-btn-primary:hover { background: var(--apl-blue-hover); }
            .crx-btn-primary.is-cleared { background: #34c759; } .crx-btn-primary.is-cleared:hover { background: var(--apl-green); }
            .crx-foot { display: flex; justify-content: flex-end; gap: 10px; padding: 14px 20px; border-top: 1px solid var(--apl-sep); background: var(--apl-bg); }
            .crx-history th { font-size: 12px; font-weight: 500; color: var(--apl-label-2); text-transform: none; letter-spacing: 0; padding: 8px 10px; border-bottom: 1px solid var(--apl-sep); text-align: left; }
            .crx-history td { padding: 10px; border-bottom: 1px solid var(--apl-sep); font-size: 13px; }
            @media (prefers-reduced-motion: reduce) { .crx-pill, .crx-seg-option, .crx-btn, .crx-close { transition: none; } .crx-pill:active, .crx-seg-option:active, .crx-btn:active { transform: none; } }
            @media print { .crx-pill { padding: 0; background: none !important; color: #000 !important; } }

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
                    @if ($canViewAllSuppliers)
                        <option value="">All Suppliers</option>
                    @endif
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

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 pb-16" x-data="chequeSelection()">

        @if(session('success'))
            <div class="mb-4 mt-4 bg-green-50 border border-green-300 text-green-800 px-4 py-3 rounded-lg text-sm no-print">
                {{ session('success') }}
            </div>
        @endif

        <div class="crx-card bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 mt-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                <p class="crx-title text-center font-extrabold mb-2">
                    Moon Traders<br>
                    <b>Cheque Register</b>
                    <span class="print-only print-info text-xs text-center">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                <table class="report-table crx-table">
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
                            @can('report-audit-cheque-register-manage')
                                <th class="crx-check no-print">
                                    <input type="checkbox" aria-label="Select all pending cheques on this page" :checked="allPicked()" :indeterminate="picked.length > 0 && ! allPicked()" :disabled="pendingIds.length === 0" @change="toggleAll($event.target.checked)">
                                </th>
                            @endcan
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
                            <th class="crx-amount text-center font-bold px-2 whitespace-nowrap">
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
                            <tr x-data :class="isPicked({{ $cheque->id }}) && 'is-picked'">
                                @can('report-audit-cheque-register-manage')
                                    <td class="crx-check no-print">
                                        @if($cheque->status === 'pending')
                                            <input type="checkbox" value="{{ $cheque->id }}" x-model.number="picked" aria-label="Select cheque {{ $cheque->cheque_number }}">
                                        @endif
                                    </td>
                                @endcan
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
                                    <button type="button" class="crx-icon-btn no-print ml-1 align-middle"
                                        title="Status history"
                                        @click="$dispatch('open-cheque-history-modal', { url: '{{ route('reports.cheque-register.history', $cheque->id) }}' })">
                                        <svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    </button>
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
                                    @if($cheque->status === 'cleared' && $cheque->cleared_via)
                                        <div class="text-[11px]" style="color: var(--apl-label-3)">{{ $cheque->cleared_via === 'bank' ? 'By bank' : 'Cash back' }}</div>
                                    @endif
                                    @if($cheque->status === 'cleared' && $cheque->cleared_amount !== null && (float) $cheque->cleared_amount !== (float) $cheque->amount)
                                        <div class="text-[10px] font-mono text-amber-700" title="Amount actually cleared">Rs {{ number_format($cheque->cleared_amount, 2) }}</div>
                                    @endif
                                </td>
                                @can('report-audit-cheque-register-manage')
                                    <td class="text-center px-2 no-print">
                                        @php
                                            $statusConfig = match ($cheque->status) {
                                                'cleared' => ['label' => 'Cleared', 'class' => 'crx-pill-cleared', 'tick' => true],
                                                'bounced' => ['label' => 'Bounced', 'class' => 'crx-pill-bounced', 'tick' => false],
                                                'cancelled' => ['label' => 'Cancelled', 'class' => 'crx-pill-cancelled', 'tick' => false],
                                                default => ['label' => 'Pending', 'class' => 'crx-pill-pending', 'tick' => false],
                                            };
                                        @endphp
                                        <button type="button" @click="$dispatch('open-cheque-status-modal', {
                                                        url: '{{ route('reports.cheque-register.update-status', $cheque->id) }}',
                                                        status: '{{ $cheque->status }}',
                                                        number: @js($cheque->cheque_number),
                                                        clearedDate: '{{ $cheque->cleared_date?->format('Y-m-d') }}',
                                                        amount: '{{ $cheque->amount }}',
                                                        via: '{{ $cheque->cleared_via ?? 'cash' }}',
                                                        bankId: '{{ $cheque->cleared_bank_account_id }}',
                                                        denom: {{ Js::from(collect(\App\Models\SalesSettlementCheque::CLEARED_NOTES)->mapWithKeys(fn ($note) => [$note => $cheque->{'cleared_denom_'.$note}])->filter()->put('coins', $cheque->cleared_denom_coins)->filter()->all()) }}
                                                    })"
                                            class="crx-pill {{ $statusConfig['class'] }}">
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
                                <td colspan="{{ auth()->user()->can('report-audit-cheque-register-manage') ? 11 : 9 }}" class="text-center py-8 text-gray-500">No cheques found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-100 font-extrabold">
                        <tr>
                            @can('report-audit-cheque-register-manage')
                                <td class="no-print"></td>
                            @endcan
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

        @can('report-audit-cheque-register-manage')
            <div class="crx-bar no-print" x-show="picked.length > 0" x-cloak
                x-transition:enter="duration-200 [transition-timing-function:cubic-bezier(.23,1,.32,1)]" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="duration-150 ease-out" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" role="region" aria-label="Selected cheques">
                <span><b x-text="picked.length"></b> selected · Rs <b x-text="pickedTotal().toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></b></span>
                <button type="button" class="crx-bar-link" @click="picked = []">Deselect</button>
                <button type="button" class="crx-btn crx-btn-primary" @click="$dispatch('open-cheque-status-modal', { bulk: true, ids: [...picked], amount: pickedTotal(), url: @js(route('reports.cheque-register.bulk-clear')) })">Clear selected</button>
            </div>
        @endcan
    </div>

    @push('scripts')
        <script>
            function chequeSelection() {
                const amounts = @js($cheques->getCollection()->where('status', 'pending')->mapWithKeys(fn ($cheque) => [$cheque->id => (float) $cheque->amount]));

                return {
                    picked: [],
                    pendingIds: Object.keys(amounts).map(Number),
                    isPicked(id) { return this.picked.includes(id); },
                    allPicked() { return this.pendingIds.length > 0 && this.pendingIds.every((id) => this.picked.includes(id)); },
                    toggleAll(on) { this.picked = on ? [...this.pendingIds] : []; },
                    pickedTotal() { return Math.round(this.picked.reduce((sum, id) => sum + (amounts[id] || 0), 0) * 100) / 100; },
                };
            }
        </script>
    @endpush

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
                    class="crx-scrim fixed inset-0 transition-all" @click="close()">
                </div>

                {{-- Panel --}}
                <div class="fixed inset-0 z-10 flex items-center justify-center overflow-y-auto p-4">
                    {{-- strong ease-out in (Apple sheet), quicker out; the reduced-motion rule of the skin removes the movement --}}
                    <div x-show="show" x-transition:enter="duration-300 [transition-timing-function:cubic-bezier(.23,1,.32,1)]"
                        x-transition:enter-start="opacity-0 translate-y-3 scale-[.97]"
                        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                        x-transition:leave="ease-out duration-150"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-[.98]"
                        class="crx-sheet relative transform overflow-hidden bg-white text-left transition-all w-full sm:max-w-3xl"
                        role="dialog" aria-modal="true" aria-labelledby="crx-status-title"
                        @click.outside="close()">

                        <div class="crx-sheet-head">
                            <div>
                                <h3 id="crx-status-title" x-text="title()"></h3>
                                <p>
                                    <span class="crx-tag" x-show="clearing()" x-text="byCash() ? 'Cash back' : 'By bank'"></span>
                                    <template x-if="bulk"><span><b x-text="ids.length"></b> cheques</span></template>
                                    <template x-if="! bulk"><span>Cheque <b x-text="form.number"></b></span></template>
                                    · Rs <span x-text="money(form.amount)"></span>
                                </p>
                            </div>
                            <button type="button" @click="close()" class="crx-close" aria-label="Close">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <form :action="formAction" method="POST" class="p-6 space-y-5 max-h-[72vh] overflow-y-auto" @submit="if (clearing() && byCash() && ! notesMatch()) { $event.preventDefault(); notesError = true; }">
                            @csrf
                            <template x-for="id in ids" :key="id"><input type="hidden" name="cheque_ids[]" :value="id"></template>

                            <div x-show="! bulk">
                                <span class="crx-label">Status</span>
                                <div class="crx-seg" role="radiogroup" aria-label="Cheque status">
                                    <template x-for="option in [['pending','Pending','yellow'],['cleared','Cleared','green'],['bounced','Bounced','red'],['cancelled','Cancelled','gray']]" :key="option[0]">
                                        <label class="crx-seg-option" :class="[form.status === option[0] ? 'is-on' : '', 'tone-' + option[0]]">
                                            <input type="radio" name="status" :value="option[0]" x-model="form.status" class="sr-only">
                                            <span x-text="option[1]"></span>
                                        </label>
                                    </template>
                                </div>
                            </div>

                            <div x-show="clearing()" class="space-y-5">
                                <div>
                                    <span class="crx-label">How was it cleared?</span>
                                    <div class="crx-seg" style="grid-template-columns: repeat(2, minmax(0, 1fr))" role="radiogroup" aria-label="How the cheque was cleared">
                                        <label class="crx-seg-option tone-cleared" :class="form.via === 'cash' && 'is-on'">
                                            <input type="radio" name="cleared_via" value="cash" x-model="form.via" class="sr-only">
                                            <span>Cash brought back</span>
                                        </label>
                                        <label class="crx-seg-option tone-cleared" :class="form.via === 'bank' && 'is-on'">
                                            <input type="radio" name="cleared_via" value="bank" x-model="form.via" class="sr-only">
                                            <span>Deposited in bank</span>
                                        </label>
                                    </div>
                                    <p class="mt-1 text-xs" style="color: var(--apl-label-3)" x-text="byCash() ? 'The salesman brought the cash and took the cheque back. Booked: Cash ← Cheques in Hand.' : 'The cheque was deposited and the bank cleared it. Booked: Bank ← Cheques in Hand.'"></p>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="crx-label" for="crx-cleared-date">Cleared date</label>
                                        <input id="crx-cleared-date" type="date" name="cleared_date" x-model="form.cleared_date" max="{{ now()->toDateString() }}"
                                            x-bind:required="clearing()">
                                    </div>
                                    <div>
                                        <span class="crx-label">Amount cleared</span>
                                        <div class="crx-readonly" aria-live="polite"><span>Rs <span x-text="money(form.amount)"></span></span><small>full cheque amount</small></div>
                                    </div>
                                </div>

                                <div x-show="! byCash()">
                                    <label class="crx-label" for="crx-bank">Bank it was deposited in</label>
                                    <select id="crx-bank" name="cleared_bank_account_id" x-model="form.bank_id" :disabled="byCash()" x-bind:required="clearing() && ! byCash()">
                                        <option value="">Choose a bank…</option>
                                        @foreach($depositBanks as $bank)
                                            <option value="{{ $bank->id }}">{{ $bank->account_name }}{{ $bank->account_number ? ' · '.$bank->account_number : '' }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="crx-notes" x-show="byCash()">
                                    <div class="flex items-start justify-between gap-3 mb-3">
                                        <div>
                                            <p class="text-[15px] font-semibold" style="color: var(--apl-label)">Cash notes <span style="color: var(--apl-red)">*</span></p>
                                            <p class="text-xs" style="color: var(--apl-label-2)" x-text="bulk ? 'Count all the notes brought back for these cheques together. The total must equal the cheques. They are added to that day\'s Cash Collection report.' : 'Count every note brought back. The total must equal the cheque. These notes are added to the Cash Collection report.'"></p>
                                        </div>
                                        <button type="button" class="crx-btn crx-btn-secondary" style="height: 30px; padding: 0 12px; font-size: 13px" @click="clearNotes()">Clear</button>
                                    </div>
                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                        <template x-for="note in notes" :key="note">
                                            <div>
                                                <label class="crx-label" :for="'crx-note-' + note" x-text="Number(note).toLocaleString() + ' × notes'"></label>
                                                <input :id="'crx-note-' + note" type="number" min="0" step="1" :name="'denom[' + note + ']'" :disabled="! byCash()" x-model.number="form.denom[note]" placeholder="0" class="text-right" inputmode="numeric">
                                                <p class="crx-note-sub mt-1" x-text="'= ' + Number((form.denom[note] || 0) * note).toLocaleString()"></p>
                                            </div>
                                        </template>
                                        <div>
                                            <label class="crx-label" for="crx-coins">Coins (Rs)</label>
                                            <input id="crx-coins" type="number" min="0" step="0.01" name="denom[coins]" :disabled="! byCash()" x-model.number="form.denom.coins" placeholder="0" class="text-right" inputmode="decimal">
                                        </div>
                                    </div>
                                    <p class="mt-2 text-sm font-semibold text-red-600" x-show="notesError && ! notesMatch()" x-cloak>The notes must add up to Rs <span x-text="money(form.amount)"></span> before you can save.</p>
                                    <div class="crx-total" x-show="counted() > 0" aria-live="polite">
                                        <span style="color: var(--apl-label-2)">Counted <b style="color: var(--apl-label)">Rs <span x-text="money(counted())"></span></b></span>
                                        <span :style="notesMatch() ? 'color: var(--apl-green)' : 'color: var(--apl-red)'" class="font-semibold"
                                            x-text="notesMatch() ? 'Matches the cheque amount' : 'Difference: Rs ' + money(counted() - Number(form.amount || 0))"></span>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="crx-label" for="crx-notes-text">Notes (optional)</label>
                                <input id="crx-notes-text" type="text" name="notes" maxlength="500" x-model="form.notes">
                            </div>

                            <div class="crx-foot -mx-6 -mb-6 sticky bottom-[-24px]">
                                <button type="button" @click="close()" class="crx-btn crx-btn-secondary">Cancel</button>
                                <button type="submit" class="crx-btn crx-btn-primary" :class="clearing() && 'is-cleared'" x-text="bulk ? 'Clear ' + ids.length + ' cheques' : (clearing() ? 'Save cleared cheque' : 'Update status')">Update status</button>
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
                        bulk: false,
                        ids: [],
                        formAction: '',
                        form: {
                            status: 'pending',
                            via: 'cash',
                            bank_id: '',
                            cleared_date: '',
                            amount: '',
                            number: '',
                            notes: '',
                            denom: {},
                        },
                        notes: [5000, 1000, 500, 100, 50, 20, 10],
                        notesError: false,

                        clearing() {
                            return this.bulk || this.form.status === 'cleared';
                        },

                        byCash() {
                            return this.form.via !== 'bank';
                        },

                        title() {
                            if (this.bulk) {
                                return this.byCash() ? 'Clear selected cheques: count the cash' : 'Clear selected cheques by bank';
                            }

                            return this.clearing() ? (this.byCash() ? 'Cheque cleared: count the cash' : 'Cheque cleared by bank') : 'Update cheque status';
                        },

                        money(value) {
                            return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
                        },

                        notesMatch() {
                            return this.counted() > 0 && Math.abs(this.counted() - Number(this.form.amount || 0)) < 0.01;
                        },

                        counted() {
                            return this.notes.reduce((sum, note) => sum + (Number(this.form.denom[note]) || 0) * note, 0) + (Number(this.form.denom.coins) || 0);
                        },

                        clearNotes() {
                            this.form.denom = {};
                        },

                        open(detail) {
                            this.bulk = !! detail.bulk;
                            this.ids = this.bulk ? detail.ids : [];
                            this.formAction = detail.url;
                            this.form.status = this.bulk ? 'cleared' : (detail.status || 'pending');
                            this.form.via = detail.via || 'cash';
                            this.form.bank_id = detail.bankId || '';
                            this.form.cleared_date = detail.clearedDate || new Date().toLocaleDateString('en-CA');
                            this.form.amount = detail.amount || '';
                            this.form.notes = '';
                            this.form.denom = detail.denom || {};
                            this.form.number = detail.number || '';
                            this.notesError = false;
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

    {{-- Status History Modal --}}
    <div x-data="chequeHistoryModal()" x-on:open-cheque-history-modal.window="open($event.detail)" x-cloak class="no-print">
        <div x-show="show" x-on:keydown.escape.window="close()" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
            <div class="crx-scrim fixed inset-0" @click="close()"></div>
            <div class="fixed inset-0 z-10 flex items-center justify-center overflow-y-auto p-4">
                <div class="crx-sheet relative w-full max-w-2xl overflow-hidden bg-white" role="dialog" aria-modal="true" @click.outside="close()">
                    <div class="crx-sheet-head">
                        <div>
                            <h3>Status history</h3>
                            <p>Cheque <b x-text="data.cheque_number"></b></p>
                        </div>
                        <button type="button" @click="close()" class="crx-close" aria-label="Close">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <div class="p-4 max-h-[70vh] overflow-y-auto">
                        <p x-show="loading" class="text-sm text-gray-500">Loading...</p>
                        <p x-show="! loading && data.entries.length === 0" class="text-sm text-gray-500">No status change has been recorded for this cheque yet.</p>
                        <table x-show="data.entries.length > 0" class="crx-history w-full text-sm">
                            <thead>
                                <tr>
                                    <th class="px-2 py-1">When</th><th class="px-2 py-1">Change</th><th class="px-2 py-1">Cleared</th><th class="px-2 py-1">By</th><th class="px-2 py-1">Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="entry in data.entries" :key="entry.when + entry.to">
                                    <tr class="border-b align-top">
                                        <td class="px-2 py-1 whitespace-nowrap" x-text="entry.when"></td>
                                        <td class="px-2 py-1 whitespace-nowrap"><span x-text="entry.from"></span> &rarr; <b x-text="entry.to"></b></td>
                                        <td class="px-2 py-1 whitespace-nowrap"><span x-text="entry.cleared_date || '-'"></span><span x-show="entry.cleared_amount"> / Rs <span x-text="entry.cleared_amount"></span></span></td>
                                        <td class="px-2 py-1" x-text="entry.by || '-'"></td>
                                        <td class="px-2 py-1" x-text="entry.notes || ''"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function chequeHistoryModal() {
                return {
                    show: false,
                    loading: false,
                    data: { cheque_number: '', entries: [] },

                    async open(detail) {
                        this.data = { cheque_number: '', entries: [] };
                        this.loading = true;
                        this.show = true;
                        try {
                            const response = await fetch(detail.url, { headers: { Accept: 'application/json' } });
                            this.data = await response.json();
                        } finally {
                            this.loading = false;
                        }
                    },

                    close() {
                        this.show = false;
                    },
                };
            }
        </script>
    @endpush

</x-app-layout>
