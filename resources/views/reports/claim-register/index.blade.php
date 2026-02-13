<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Claim Register Report" :showSearch="true" :showRefresh="true"
            backRoute="reports.index" />
    </x-slot>

    @push('header')
        <style>
            .report-table {
                width: 100%;
                border-collapse: collapse;
                border: 1px solid black;
                font-size: 12px;
            }

            .report-table th,
            .report-table td {
                border: 1px solid black;
                padding: 4px;
                text-align: center;
            }

            .report-table th {
                background-color: #f3f4f6;
                font-weight: bold;
            }

            .text-left {
                text-align: left !important;
            }

            .text-right {
                text-align: right !important;
            }

            @media print {
                @page {
                    margin: 10mm;
                    size: landscape;
                }

                body {
                    margin: 0;
                    padding: 0;
                }

                .no-print {
                    display: none !important;
                }

                .report-table {
                    font-size: 10px;
                }
            }
        </style>
    @endpush

    <x-filter-section :action="route('reports.claim-register.index')" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
            {{-- Supplier --}}
            <div>
                <x-label for="supplier_id" value="Supplier" />
                <select id="supplier_id" name="supplier_id"
                    class="select2 border-gray-300 rounded-md w-full">
                    <option value="">All Suppliers</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}"
                            {{ $supplierId == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->supplier_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Status --}}
            <div>
                <x-label for="status" value="Status" />
                <select id="status" name="status"
                    class="border-gray-300 rounded-md w-full">
                    <option value="">All Statuses</option>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" {{ $status === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Date From --}}
            <div>
                <x-label for="date_from" value="Date From" />
                <x-input id="date_from" class="block mt-1 w-full" type="date" name="date_from"
                    :value="$dateFrom" />
            </div>

            {{-- Date To --}}
            <div>
                <x-label for="date_to" value="Date To" />
                <x-input id="date_to" class="block mt-1 w-full" type="date" name="date_to"
                    :value="$dateTo" />
            </div>

            {{-- Claim Month --}}
            <div>
                <x-label for="claim_month" value="Claim Month" />
                <x-input id="claim_month" class="block mt-1 w-full" type="text" name="claim_month"
                    :value="$claimMonth" placeholder="June-Aug, September" />
            </div>

            {{-- Transaction Type --}}
            <div>
                <x-label for="transaction_type" value="Transaction Type" />
                <select id="transaction_type" name="transaction_type"
                    class="border-gray-300 rounded-md w-full">
                    <option value="">All Types</option>
                    @foreach ($transactionTypeOptions as $value => $label)
                        <option value="{{ $value }}" {{ $transactionType === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16 mt-4">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 print:shadow-none">
            <div class="overflow-x-auto">
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
                </p>

                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Sr.#</th>
                            <th class="text-left">Supplier</th>
                            <th>Txn Date</th>
                            <th>Reference</th>
                            <th class="text-left">Description</th>
                            <th>Claim Month</th>
                            <th>Type</th>
                            <th class="text-right">Amount</th>
                            <th class="text-right">Balance</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($dateFrom && $openingBalance != 0)
                            <tr class="bg-yellow-50 font-semibold">
                                <td colspan="7" class="text-right">Opening Balance</td>
                                <td class="text-right font-bold">{{ number_format($openingBalance, 2) }}</td>
                                <td></td>
                            </tr>
                        @endif

                        @php $runningBalance = $openingBalance; @endphp
                        @foreach ($claims as $claim)
                            @php
                                if ($claim->transaction_type === 'claim') {
                                    $runningBalance += (float) $claim->amount;
                                } else {
                                    $runningBalance -= (float) $claim->amount;
                                }
                                $typeLabel = $transactionTypeOptions[$claim->transaction_type] ?? ucfirst($claim->transaction_type);
                                $stLabel = $statusOptions[$claim->status] ?? $claim->status;
                            @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td class="text-left whitespace-nowrap">{{ $claim->supplier?->supplier_name ?? '-' }}</td>
                                <td class="whitespace-nowrap">{{ $claim->transaction_date->format('d-M-Y') }}</td>
                                <td>{{ $claim->reference_number ?? '-' }}</td>
                                <td class="text-left">{{ $claim->description ?? '-' }}</td>
                                <td class="whitespace-nowrap">{{ $claim->claim_month ?? '-' }}</td>
                                <td>{{ $typeLabel }}</td>
                                <td class="text-right">{{ number_format($claim->amount, 2) }}</td>
                                <td class="text-right font-bold">{{ number_format($runningBalance, 2) }}</td>
                                <td>{{ $stLabel }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-100 font-bold">
                        <tr>
                            <td colspan="6" class="text-right">Period Totals:</td>
                            <td class="text-left">
                                Claim: {{ number_format($totals['claim_amount'], 2) }}<br>
                                Recovery: {{ number_format($totals['recovery_amount'], 2) }}
                            </td>
                            <td class="text-right">{{ number_format($totals['net_balance'], 2) }}</td>
                            <td colspan="2"></td>
                        </tr>
                        @if ($dateFrom)
                            <tr class="bg-emerald-50">
                                <td colspan="7" class="text-right">Closing Balance:</td>
                                <td class="text-right font-extrabold">{{ number_format($closingBalance, 2) }}</td>
                                <td></td>
                            </tr>
                        @endif
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
