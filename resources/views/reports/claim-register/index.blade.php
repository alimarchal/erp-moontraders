<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Claim Register Report" :createRoute="null" createLabel="" :showSearch="true"
            :showRefresh="true" backRoute="reports.index">
            <x-slot name="actions">
                <x-register-header-actions page="claims" />
            </x-slot>
        </x-page-header>
    </x-slot>

    @push('header')
        <style>
            .report-table {
                width: 100%;
                border-collapse: collapse;
                border: 1px solid black;
                font-size: 13px;
                line-height: 1.2;
            }

            .report-table th,
            .report-table td {
                border: 1px solid black;
                padding: 3px 4px;
                word-wrap: break-word;
            }

            .report-table th {
                background-color: #f3f4f6;
                font-weight: 600;
                text-align: center;
            }

            .amount-cell {
                text-align: right;
                font-family: ui-monospace, monospace;
                white-space: nowrap;
            }

            .inline-input {
                width: 100%;
                padding: 2px 4px;
                border: 1px solid #d1d5db;
                border-radius: 4px;
                font-size: 12px;
                font-family: ui-monospace, monospace;
            }

            .inline-input:focus {
                outline: none;
                border-color: #6366f1;
                box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
            }

            .inline-select {
                width: 100%;
                padding: 2px 4px;
                border: 1px solid #d1d5db;
                border-radius: 4px;
                font-size: 12px;
            }

            .print-only {
                display: none;
            }

            @media print {
                @page {
                    margin: 15mm 10mm 20mm 10mm;
                    /* no fixed size: the print dialog offers portrait and landscape */

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
                    font-size: 10px !important;
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

    <x-apple-skin />
    <x-register-apple-style />
    <x-register-help page="claims" :has-supplier="(bool) $supplierId" />

    {{-- Status Messages --}}
    @if (session('success'))
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 no-print">
            <div class="rg-flash rg-flash-ok bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg" role="status">
                {{ session('success') }}
            </div>
        </div>
    @endif
    @if (session('error'))
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 no-print">
            <div class="rg-flash rg-flash-err bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg" role="alert">
                {{ session('error') }}
            </div>
        </div>
    @endif

    {{-- Validation Errors --}}
    @if ($errors->any())
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 no-print">
            <div class="rg-flash rg-flash-err bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg" role="alert">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    {{-- Filter Section --}}
    <x-filter-section :action="route('reports.claim-register.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Supplier --}}
            <div>
                <x-label for="supplier_id" value="Supplier" />
                <select id="supplier_id" name="supplier_id" data-placeholder="Choose a supplier"
                    class="select2 rg-supplier-select border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value=""></option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}"
                            {{ (string) $supplierId === (string) $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->supplier_name }}
                            @if ($supplier->short_name)
                                ({{ $supplier->short_name }})
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Date From --}}
            <div>
                <x-label for="date_from" value="Date From" />
                <x-input id="date_from" name="date_from" type="date" class="mt-1 block w-full"
                    :value="$dateFrom" />
            </div>

            {{-- Date To --}}
            <div>
                <x-label for="date_to" value="Date To" />
                <x-input id="date_to" name="date_to" type="date" class="mt-1 block w-full"
                    :value="$dateTo" />
            </div>

            {{-- Transaction Type --}}
            <div>
                <x-label for="transaction_type" value="Transaction Type" />
                <select id="transaction_type" name="transaction_type"
                    class="rg-select border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All transactions</option>
                    @foreach ($transactionTypeOptions as $value => $label)
                        <option value="{{ $value }}" {{ $transactionType === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Reference Number --}}
            <div>
                <x-label for="reference_number" value="Reference Number" />
                <x-input id="reference_number" name="reference_number" type="text" class="mt-1 block w-full"
                    :value="$referenceNumber ?? ''" placeholder="e.g. ST-26-014" />
            </div>

            {{-- Description --}}
            <div>
                <x-label for="description" value="Description" />
                <x-input id="description" name="description" type="text" class="mt-1 block w-full"
                    :value="$description ?? ''" placeholder="Contains…" />
            </div>

            {{-- Claim Month --}}
            <div>
                <x-label for="claim_month" value="Claim Month" />
                <x-input id="claim_month" name="claim_month" type="text" class="mt-1 block w-full"
                    :value="$claimMonth" placeholder="e.g. October 2026" />
            </div>

            {{-- Posted Status --}}
            <div>
                <x-label for="posted_status" value="Posted Status" />
                <select id="posted_status" name="posted_status"
                    class="rg-select border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">Posted and unposted</option>
                    <option value="posted" {{ ($postedStatus ?? '') === 'posted' ? 'selected' : '' }}>Posted</option>
                    <option value="unposted" {{ ($postedStatus ?? '') === 'unposted' ? 'selected' : '' }}>Unposted</option>
                </select>
            </div>

            {{-- Per Page --}}
            <div>
                <x-label for="per_page" value="Records Per Page" />
                <select id="per_page" name="per_page"
                    class="rg-select border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
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

    @if (! $supplierId)
    {{-- Nothing is chosen for the user: the register stays empty until a supplier is picked --}}
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 mb-16 no-print">
        <div class="rg-empty">
            <span class="rg-empty-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" /></svg>
            </span>
            <h2>Choose a supplier to open its claim register</h2>
            <p>Claims raised and recoveries received are listed with a running balance for the selected supplier and period.</p>
            <button type="button" class="rg-pill bg-indigo-600 text-white" onclick="event.stopPropagation(); rgOpenFilters()">Choose supplier</button>
        </div>
    </div>
    @else
    {{-- Summary Cards --}}
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4 no-print">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="rg-kpi bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
                <div class="text-sm text-gray-500">Total Claims (Debit)</div>
                <div class="text-2xl font-bold text-green-700">
                    {{ number_format($totals['debit'], 2) }}</div>
            </div>
            <div class="rg-kpi bg-white rounded-lg shadow p-4 border-l-4 border-red-500">
                <div class="text-sm text-gray-500">Total Recovery (Credit)</div>
                <div class="text-2xl font-bold text-red-700">
                    {{ number_format($totals['credit'], 2) }}</div>
            </div>
            <div class="rg-kpi bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
                <div class="text-sm text-gray-500">Net Balance</div>
                <div class="text-2xl font-bold {{ $totals['net_balance'] >= 0 ? 'text-blue-700' : 'text-red-700' }}">
                    {{ number_format($totals['net_balance'], 2) }}</div>
            </div>
            <div class="rg-kpi bg-white rounded-lg shadow p-4 border-l-4 {{ $closingBalance >= 0 ? 'border-emerald-500' : 'border-red-500' }}">
                <div class="text-sm text-gray-500">Closing Balance</div>
                <div class="text-2xl font-bold {{ $closingBalance >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                    {{ number_format($closingBalance, 2) }}</div>
            </div>
        </div>
    </div>

    @if ($ledgerMatch)
        {{-- Recoveries here and claim adjustments in the Supplier Ledger Register are one event: they must agree --}}
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 -mt-2 mb-4 no-print">
            @php
                $matched = abs($ledgerMatch['difference']) < 1;
            @endphp
            <div class="rg-kpi rg-match bg-white rounded-lg shadow px-4 py-3 border-l-4 {{ $matched ? 'border-emerald-500' : 'border-amber-500' }} flex flex-wrap items-center gap-x-8 gap-y-2 text-sm">
                <span class="font-semibold text-gray-700">Recoveries reconciled with the Supplier Ledger (to {{ \Carbon\Carbon::parse($dateTo)->format('d-M-Y') }}) <button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'match' })" aria-label="How recoveries are reconciled" title="How recoveries are reconciled">i</button></span>
                <span class="text-gray-500">Claim register recoveries <b class="text-gray-900 tabular-nums">{{ number_format($ledgerMatch['recovered'], 2) }}</b></span>
                <span class="text-gray-500">Claim adjustments in the <a href="{{ route('reports.supplier-ledger-register.index', ['filter' => ['supplier_id' => $supplierId]]) }}" class="text-indigo-600 hover:underline">Supplier Ledger</a> <b class="text-gray-900 tabular-nums">{{ number_format($ledgerMatch['adjusted'], 2) }}</b></span>
                <span class="{{ $matched ? 'text-emerald-700' : 'text-amber-700' }} font-semibold">{{ $matched ? 'Reconciled' : 'Difference '.number_format($ledgerMatch['difference'], 2) }}</span>
                <span class="text-gray-400 text-xs w-full">Claim recoveries are settled through the supplier's ledger. Only the Supplier Ledger Register posts them to the books, so each claim is cleared once. Any difference means the two registers were entered with different amounts.</span>
            </div>
        </div>
    @endif

    {{-- Main Report --}}
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <div class="rg-card bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 print:shadow-none print:pb-0">
            <div class="overflow-x-auto">
                {{-- Report Header --}}
                <div class="mb-4">
                    <p class="text-center font-extrabold mb-2">
                        Moon Traders<br>
                        Claim Register Report<br>
                        @if ($dateFrom && $dateTo)
                            <span class="text-sm font-semibold">
                                Period: {{ \Carbon\Carbon::parse($dateFrom)->format('d-M-Y') }} to
                                {{ \Carbon\Carbon::parse($dateTo)->format('d-M-Y') }}
                            </span>
                        @elseif ($dateTo)
                            <span class="text-sm font-semibold">
                                As of: {{ \Carbon\Carbon::parse($dateTo)->format('d-M-Y') }}
                            </span>
                        @endif
                        @if ($selectedSupplier)
                            <br><span class="text-sm font-semibold">{{ $selectedSupplier->supplier_name }}</span>
                        @endif
                    </p>
                    <span class="print-only print-info text-xs text-center block mt-1">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </div>

                {{-- Data Table --}}
                <div x-data="claimRegister()">
                    <table class="report-table rg-table">
                        <thead>
                            <tr>
                                <th style="width: 35px;">Sr#</th>
                                <th style="width: 90px;">Txn Date</th>
                                <th style="width: 120px;">Supplier</th>
                                <th class="rg-text" style="width: 120px;">Reference</th>
                                <th class="rg-text" style="width: 190px;">Description</th>
                                <th class="rg-text" style="width: 100px;">Claim Month</th>
                                <th style="width: 80px;">Type<button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'col-type' })" aria-label="What Type means" title="What Type means">i</button></th>
                                <th style="width: 110px;">Debit (Claim)<button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'col-debit' })" aria-label="What Debit (Claim) means" title="What Debit (Claim) means">i</button></th>
                                <th style="width: 110px;">Credit (Recovery)<button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'col-credit' })" aria-label="What Credit (Recovery) means" title="What Credit (Recovery) means">i</button></th>
                                <th style="width: 120px;">Closing Balance<button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'col-closing' })" aria-label="What Closing Balance means" title="What Closing Balance means">i</button></th>
                                <th style="width: 70px;" class="no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $supplierBalances = [];
                            @endphp

                            {{-- Opening Balance Row --}}
                            @if ($openingBalance != 0)
                                <tr class="bg-yellow-50">
                                    <td class="text-center" colspan="7">
                                        <strong>Opening Balance
                                            @if ($dateFrom)
                                                (Before {{ \Carbon\Carbon::parse($dateFrom)->format('d-M-Y') }})
                                            @endif
                                        </strong>
                                    </td>
                                    <td class="amount-cell">-</td>
                                    <td class="amount-cell">-</td>
                                    <td class="amount-cell font-bold {{ $openingBalance >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                        {{ number_format($openingBalance, 2) }}
                                    </td>
                                    <td class="no-print"></td>
                                </tr>
                            @endif

                            @forelse ($claims as $index => $claim)
                                @php
                                    $claimSupplierId = $claim->supplier_id;

                                    if (!isset($supplierBalances[$claimSupplierId])) {
                                        $supplierBalances[$claimSupplierId] = $openingBalances[$claimSupplierId] ?? 0;
                                    }

                                    $debit = (float) $claim->debit;
                                    $credit = (float) $claim->credit;

                                    $supplierBalances[$claimSupplierId] += $debit - $credit;
                                    $rowClosingBalance = $supplierBalances[$claimSupplierId];
                                @endphp
                                <tr class="{{ $loop->even ? 'bg-gray-50' : '' }}">
                                    <td class="text-center" style="vertical-align: middle;">
                                        {{ $claims->firstItem() + $index }}</td>
                                    <td class="text-center rg-nowrap" style="vertical-align: middle;">
                                        {{ $claim->transaction_date->format('d.m.Y') }}</td>
                                    <td class="font-semibold text-center" style="vertical-align: middle;">
                                        {{ $claim->supplier?->short_name ?? $claim->supplier?->supplier_name ?? '-' }}
                                    </td>
                                    <td class="rg-text" style="vertical-align: middle;">
                                        <span class="rg-clamp" title="{{ $claim->reference_number }}">{{ $claim->reference_number ?? '-' }}</span></td>
                                    <td class="rg-text" style="vertical-align: middle;">
                                        <span class="rg-clamp" title="{{ $claim->description }}">{{ $claim->description ?? '-' }}</span></td>
                                    <td class="rg-text" style="vertical-align: middle;">
                                        <span class="rg-clamp" title="{{ $claim->claim_month }}">{{ $claim->claim_month ?? '-' }}</span></td>
                                    <td class="text-center" style="vertical-align: middle;">
                                        <span class="inline-block px-1.5 py-0.5 rounded text-xs font-semibold {{ $claim->transaction_type === 'claim' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' }}">
                                            {{ $claim->transaction_type === 'claim' ? 'DR' : 'CR' }}
                                        </span>
                                    </td>
                                    <td class="amount-cell {{ $debit > 0 ? 'text-green-700' : '' }}"
                                        style="vertical-align: middle;">
                                        {{ $debit > 0 ? number_format($debit, 2) : '-' }}
                                    </td>
                                    <td class="amount-cell {{ $credit > 0 ? 'text-red-700' : '' }}"
                                        style="vertical-align: middle;">
                                        {{ $credit > 0 ? number_format($credit, 2) : '-' }}
                                    </td>
                                    <td class="amount-cell font-bold {{ $rowClosingBalance >= 0 ? 'text-green-700' : 'text-red-700' }}"
                                        style="vertical-align: middle;">
                                        {{ number_format($rowClosingBalance, 2) }}
                                    </td>
                                    @canany(['claim-register-edit', 'claim-register-post', 'claim-register-delete'])
                                        <td class="text-center no-print" style="vertical-align: middle;">
                                            @if (!$claim->isPosted())
                                                <div class="flex justify-center gap-1">
                                                    @can('claim-register-post')
                                                        <button type="button" x-data
                                                            x-on:click="$dispatch('open-claim-post-modal', { url: '{{ route('reports.claim-register.post', $claim) }}' })"
                                                            class="rg-icon rg-green inline-flex items-center px-1.5 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700"
                                                            title="Post to GL">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                        </button>
                                                    @endcan
                                                    @can('claim-register-edit')
                                                    <button type="button"
                                                        @click="openEditModal({{ json_encode([
                                                            'id' => $claim->id,
                                                            'supplier_id' => $claim->supplier_id,
                                                            'transaction_date' => $claim->transaction_date->format('Y-m-d'),
                                                            'transaction_type' => $claim->transaction_type,
                                                            'amount' => max((float) $claim->debit, (float) $claim->credit),
                                                            'reference_number' => $claim->reference_number,
                                                            'description' => $claim->description,
                                                            'claim_month' => $claim->claim_month,
                                                            'date_of_dispatch' => $claim->date_of_dispatch?->format('Y-m-d'),
                                                            'notes' => $claim->notes,
                                                        ]) }})"
                                                        class="rg-icon rg-blue inline-flex items-center px-1.5 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700"
                                                        title="Edit">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                    </button>
                                                    @endcan
                                                    @can('claim-register-delete')
                                                        <form
                                                            action="{{ route('reports.claim-register.destroy', $claim) }}"
                                                            method="POST"
                                                            onsubmit="return confirm('Delete this claim entry? It has not been posted, so nothing reaches the books.');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit"
                                                                class="rg-icon rg-red inline-flex items-center px-1.5 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700"
                                                                title="Delete">
                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor"
                                                                    viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2"
                                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                </svg>
                                                            </button>
                                                        </form>
                                                    @endcan
                                                </div>
                                            @else
                                                <span class="text-xs text-gray-400" title="Posted">
                                                    <svg class="w-4 h-4 inline text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </span>
                                            @endif
                                        </td>
                                    @endcanany
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center py-4 text-gray-500">No claim entries found for
                                        the selected filters.</td>
                                </tr>
                            @endforelse

                            {{-- Inline Add Form Row --}}
                            @can('claim-register-create')
                                <tr x-show="showAddRow" x-cloak class="bg-indigo-50 no-print">
                                    <td colspan="11" class="p-0">
                                        <form action="{{ route('reports.claim-register.store') }}" method="POST">
                                            @csrf
                                            <table class="w-full border-collapse">
                                                <tr>
                                                    <td style="width: 35px; text-align: center; padding: 4px;">
                                                        <span class="text-indigo-600 font-bold">+</span>
                                                    </td>
                                                    <td style="width: 90px; padding: 4px;">
                                                        <input type="date" name="transaction_date"
                                                            value="{{ old('transaction_date', now()->format('Y-m-d')) }}"
                                                            max="{{ now()->format('Y-m-d') }}"
                                                            @if(!$claimRegisterDateEditable) min="{{ now()->format('Y-m-d') }}" @endif
                                                            class="inline-input" required>
                                                    </td>
                                                    <td style="width: 120px; padding: 4px;">
                                                        <select name="supplier_id" class="rg-select inline-select" required>
                                                            <option value="">Supplier</option>
                                                            @foreach ($suppliers as $supplier)
                                                                <option value="{{ $supplier->id }}"
                                                                    {{ old('supplier_id', $supplierId) == $supplier->id ? 'selected' : '' }}>
                                                                    {{ $supplier->short_name ?? $supplier->supplier_name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td style="width: 90px; padding: 4px;">
                                                        <input type="text" name="reference_number"
                                                            value="{{ old('reference_number') }}" class="inline-input"
                                                            placeholder="e.g. ST-26-014">
                                                    </td>
                                                    <td style="width: 140px; padding: 4px;">
                                                        <input type="text" name="description"
                                                            value="{{ old('description') }}" class="inline-input"
                                                            placeholder="Description">
                                                    </td>
                                                    <td style="width: 90px; padding: 4px;">
                                                        <input type="text" name="claim_month"
                                                            value="{{ old('claim_month') }}" class="inline-input"
                                                            placeholder="e.g. Oct 2026">
                                                    </td>
                                                    <td style="width: 120px; padding: 4px;">
                                                        <select name="transaction_type" class="rg-select inline-select" required>
                                                            @foreach ($transactionTypeOptions as $value => $label)
                                                                <option value="{{ $value }}"
                                                                    {{ old('transaction_type', 'claim') === $value ? 'selected' : '' }}>
                                                                    {{ $value === 'claim' ? 'DR (Claim)' : 'CR (Recovery)' }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td colspan="2" style="padding: 4px;">
                                                        <input type="number" name="amount" step="0.01"
                                                            min="0" value="{{ old('amount') }}"
                                                            class="inline-input" placeholder="Amount" required>
                                                    </td>
                                                    <td style="width: 120px; padding: 4px; text-align: center;">
                                                        <span class="text-xs text-gray-400">-</span>
                                                    </td>
                                                    <td style="width: 70px; padding: 4px; text-align: center;">
                                                        <button type="submit"
                                                            class="rg-pill rg-pill-green inline-flex items-center px-2 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700 font-semibold">
                                                            Save
                                                        </button>
                                                    </td>
                                                </tr>
                                            </table>
                                        </form>
                                    </td>
                                </tr>
                            @endcan
                        </tbody>

                        {{-- Footer Totals --}}
                        <tfoot class="bg-gray-100 font-extrabold">
                            <tr>
                                <td colspan="7" class="text-center px-2 py-1">
                                    Period Totals ({{ $claims->total() }} entries)
                                </td>
                                <td class="amount-cell px-2 py-1 text-green-700">
                                    {{ number_format($totals['debit'], 2) }}
                                </td>
                                <td class="amount-cell px-2 py-1 text-red-700">
                                    {{ number_format($totals['credit'], 2) }}
                                </td>
                                <td class="amount-cell px-2 py-1">
                                    {{ number_format($closingBalance, 2) }}
                                </td>
                                <td class="no-print"></td>
                            </tr>
                        </tfoot>
                    </table>

                    {{-- Add Entry Toggle Button --}}
                    @can('claim-register-create')
                        <div class="mt-3 no-print flex items-center gap-2">
                            <button type="button" @click="showAddRow = !showAddRow"
                                class="rg-pill inline-flex items-center px-3 py-1.5 text-white text-sm rounded-md transition-colors"
                                :class="showAddRow ? 'bg-gray-500 hover:bg-gray-600' : 'bg-indigo-600 hover:bg-indigo-700'">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                <span x-text="showAddRow ? 'Cancel' : 'Add Entry'"></span>
                            </button>
                            <button type="button" class="rg-info no-print" x-data @click.stop="$dispatch('rg-help', { topic: 'entry' })" aria-label="How entries are added and posted" title="How entries are added and posted">i</button>
                        </div>
                    @endcan
                </div>

                {{-- Claim Summary --}}
                @if ($selectedSupplier)
                    <div class="mt-4 flex justify-end">
                        <div class="rg-summary border border-black rounded-lg overflow-hidden" style="min-width: 280px;">
                            <div class="rg-summary-head bg-gray-800 text-white text-center py-1.5 font-bold text-sm">
                                Claim summary · {{ $selectedSupplier->short_name ?? $selectedSupplier->supplier_name }}
                            </div>
                            <table class="w-full text-sm">
                                <tr class="border-b border-gray-300">
                                    <td class="px-3 py-1.5 font-semibold">Claims (debit)</td>
                                    <td class="px-3 py-1.5 text-right font-mono font-bold text-green-700">
                                        {{ number_format($totals['debit'], 2) }}</td>
                                </tr>
                                <tr class="border-b border-gray-300">
                                    <td class="px-3 py-1.5 font-semibold">Recoveries (credit)</td>
                                    <td class="px-3 py-1.5 text-right font-mono font-bold text-red-700">
                                        {{ number_format($totals['credit'], 2) }}</td>
                                </tr>
                                <tr
                                    class="{{ $closingBalance >= 0 ? 'bg-green-50' : 'bg-red-50' }}">
                                    <td class="px-3 py-1.5 font-semibold">Balance</td>
                                    <td
                                        class="px-3 py-1.5 text-right font-mono font-extrabold {{ $closingBalance >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                        {{ number_format($closingBalance, 2) }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- Entry type legend: tap one to open its explanation --}}
                <div class="mt-4 no-print">
                    <div class="text-xs font-semibold text-gray-600 mb-1">Entry types <span class="font-normal text-gray-400">· tap one to see what it means</span></div>
                    <div class="rg-legend flex flex-wrap gap-2">
                        <button type="button" class="doc-badge rg-type-btn bg-blue-100 text-blue-800" x-data @click="$dispatch('rg-help', { topic: 'col-debit' })" title="What DR means"><b>DR</b> Claim</button>
                        <button type="button" class="doc-badge rg-type-btn bg-green-100 text-green-800" x-data @click="$dispatch('rg-help', { topic: 'col-credit' })" title="What CR means"><b>CR</b> Recovery</button>
                    </div>
                </div>

                {{-- Pagination --}}
                @if ($claims->hasPages())
                    <div class="mt-4 no-print">
                        {{ $claims->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- Edit Modal with Backdrop Blur --}}
    @can('claim-register-edit')
        <div x-data="claimEditModal()" x-show="open" x-cloak
            class="fixed inset-0 z-50 overflow-y-auto no-print" style="display: none;"
            aria-labelledby="edit-modal-title" role="dialog" aria-modal="true">

            {{-- Backdrop with blur --}}
            <div x-show="open"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="rg-scrim fixed inset-0 bg-gray-900/40 backdrop-blur-sm transition-all"
                @click="close()">
            </div>

            {{-- Modal Panel --}}
            <div class="fixed inset-0 z-10 flex items-center justify-center overflow-y-auto p-4">
                <div x-show="open"
                    x-transition:enter="duration-300 [transition-timing-function:cubic-bezier(.23,1,.32,1)]"
                    x-transition:enter-start="opacity-0 translate-y-3 scale-[.97]"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    class="rg-sheet relative transform overflow-hidden rounded-xl bg-white text-left shadow-2xl transition-all sm:w-full sm:max-w-2xl"
                    @click.outside="close()">

                    <div class="rg-sheet-head bg-gray-800 text-white px-6 py-3 rounded-t-xl flex justify-between items-center">
                        <h3 class="text-lg font-bold" id="edit-modal-title">Edit Claim Entry</h3>
                        <button type="button" @click="close()" class="rg-close text-gray-300 hover:text-white" aria-label="Close">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form :action="formAction" method="POST" class="p-6">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-label value="Supplier" />
                                <select name="supplier_id" x-model="entry.supplier_id"
                                    class="rg-select mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    required>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">{{ $supplier->supplier_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-label value="Transaction Date" />
                                <input type="date" name="transaction_date" x-model="entry.transaction_date"
                                    max="{{ now()->format('Y-m-d') }}"
                                    @if(!$claimRegisterDateEditable) min="{{ now()->format('Y-m-d') }}" @endif
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    required>
                            </div>
                            <div>
                                <x-label value="Transaction Type" />
                                <select name="transaction_type" x-model="entry.transaction_type"
                                    class="rg-select mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    required>
                                    @foreach ($transactionTypeOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-label value="Amount" />
                                <x-input type="number" name="amount" step="0.01" min="0"
                                    x-model="entry.amount" class="mt-1 block w-full" required />
                            </div>
                            <div>
                                <x-label value="Reference Number" />
                                <x-input type="text" name="reference_number" x-model="entry.reference_number"
                                    class="mt-1 block w-full" placeholder="e.g. ST-26-014" />
                            </div>
                            <div>
                                <x-label value="Description" />
                                <x-input type="text" name="description" x-model="entry.description"
                                    class="mt-1 block w-full" placeholder="e.g. Trade offer, October" />
                            </div>
                            <div>
                                <x-label value="Claim Month" />
                                <x-input type="text" name="claim_month" x-model="entry.claim_month"
                                    class="mt-1 block w-full" placeholder="e.g. October 2026" />
                            </div>
                            <div>
                                <x-label value="Date of Dispatch" />
                                <input type="date" name="date_of_dispatch" x-model="entry.date_of_dispatch"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            </div>
                            <div class="col-span-2">
                                <x-label value="Notes" />
                                <textarea name="notes" x-model="entry.notes" rows="2"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    placeholder="Anything the next reader should know"></textarea>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 mt-6">
                            <button type="button" @click="close()"
                                class="rg-pill rg-pill-grey px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 text-sm font-medium">
                                Cancel
                            </button>
                            <button type="submit"
                                class="rg-pill px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm font-medium">
                                Update Entry
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan

    @can('claim-register-post')
        <div class="rg-confirm">
        <x-alpine-confirmation-modal eventName="open-claim-post-modal" title="Post to General Ledger"
            confirmButtonText="Post to GL" confirmButtonClass="bg-green-600 hover:bg-green-700"
            iconBgClass="bg-green-100" iconColorClass="text-green-600"
            iconPath="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z">
            <p class="text-sm text-gray-600">
                Post this entry? A claim is booked to Pending Claims against claim income. A recovery is recorded here only; the Supplier Ledger Register's claim adjustment books it. Once posted, the entry can no longer be edited or deleted.
            </p>
        </x-alpine-confirmation-modal>
        </div>
    @endcan

    @push('scripts')
        <script>
            function claimRegister() {
                return {
                    showAddRow: false,
                    openEditModal(data) {
                        window.dispatchEvent(new CustomEvent('open-claim-edit-modal', {
                            detail: data
                        }));
                    }
                }
            }

            function claimEditModal() {
                return {
                    open: false,
                    entry: {},
                    formAction: '',
                    init() {
                        window.addEventListener('open-claim-edit-modal', (e) => {
                            this.entry = e.detail;
                            this.$nextTick(() => window.rgSyncSelects && window.rgSyncSelects(this.$el));
                            this.formAction = '{{ url("reports/claim-register") }}/' + this.entry.id;
                            this.open = true;
                        });
                    },
                    close() {
                        this.open = false;
                        this.entry = {};
                    }
                }
            }
        </script>
    @endpush
</x-app-layout>
