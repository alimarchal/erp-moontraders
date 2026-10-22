@php
    $uses = fn (string $field) => in_array($field, $definition['filters'], true);
    $exportQuery = ['filter' => $filters];
    $isNumeric = fn (int $i) => in_array($i, $data['numeric'], true);
    $format = fn ($cell, int $i) => is_float($cell) || (is_int($cell) && $isNumeric($i) && $i > 1) ? number_format($cell, 2) : $cell;
    $selectClass = 'select2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full';
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$data['title']" :createRoute="null" createLabel="" :showSearch="true" :showRefresh="true" backRoute="reports.index">
            <x-slot name="actions">
                <a href="{{ route('reports.bank.export.excel', ['report' => $slug] + $exportQuery) }}" title="Excel"
                    class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-950 transition">Excel</a>
                <a href="{{ route('reports.bank.export.pdf', ['report' => $slug] + $exportQuery) }}" title="PDF"
                    class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-950 transition">PDF</a>
            </x-slot>
        </x-page-header>
    </x-slot>

    @push('header')
        <style>
            .report-table { width: 100%; border-collapse: collapse; border: 1px solid #000; font-size: 14px; }
            .report-table th, .report-table td { border: 1px solid #000; padding: 5px 8px; }
            .report-table th { background-color: #1f2937; color: #fff; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
            .report-table tbody tr:nth-child(even) { background-color: #f9fafb; }
            .report-table tbody tr:hover { background-color: #e5e7eb; }
            .report-table tfoot td { background-color: #1f2937; color: #fff; font-weight: bold; }
            .print-only { display: none; }
            @media print {
                @page { margin: 05mm; }
                body { margin: 0; padding: 0; }
                .no-print { display: none !important; }
                .print-only { display: block !important; }
                .max-w-7xl { max-width: 100% !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
                .bg-white { margin: 0 !important; padding: 10px !important; box-shadow: none !important; }
                .overflow-x-auto { overflow: visible !important; }
                .report-table { font-size: 10px; }
                .report-table th, .report-table tfoot td { background-color: #e5e7eb !important; color: #000 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                .report-table tbody tr:nth-child(even) { background-color: #f3f4f6 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                .report-table tbody tr:hover { background-color: transparent; }
            }
        </style>
    @endpush

    <x-filter-section :action="route('reports.bank.show', $slug)" class="no-print">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            @if ($uses('group_by'))
                <div>
                    <x-label for="r_group" value="Group by" />
                    <select id="r_group" name="filter[group_by]" class="{{ $selectClass }}">
                        @foreach (['company' => 'Company / party', 'category' => 'Category', 'account' => 'Bank account', 'month' => 'Month', 'day' => 'Day', 'company_month' => 'Company by month', 'category_month' => 'Category by month'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['group_by'] ?? 'company') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if ($uses('reconciliation_id'))
                <div class="lg:col-span-2">
                    <x-label for="r_rec" value="Reconciliation" />
                    <select id="r_rec" name="filter[reconciliation_id]" class="{{ $selectClass }}">
                        <option value="">Choose…</option>
                        @foreach ($reconciliations as $reconciliation)
                            <option value="{{ $reconciliation->id }}" @selected((string) ($filters['reconciliation_id'] ?? '') === (string) $reconciliation->id)>{{ $reconciliation->bankAccount->account_name }} · {{ $reconciliation->period_from->format('d-m-Y') }} → {{ $reconciliation->period_to->format('d-m-Y') }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if ($uses('bank_account_id'))
                <div>
                    <x-label for="r_bank" value="Bank account" />
                    <select id="r_bank" name="filter[bank_account_id]" class="{{ $selectClass }}">
                        <option value="">All</option>
                        @foreach ($bankAccounts as $account)
                            <option value="{{ $account->id }}" @selected((string) ($filters['bank_account_id'] ?? '') === (string) $account->id)>{{ $account->account_name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if ($uses('date_from'))
                <div>
                    <x-label for="r_from" value="From" />
                    <x-input id="r_from" name="filter[date_from]" type="date" class="mt-1 block w-full" :value="$filters['date_from'] ?? ''" />
                </div>
                <div>
                    <x-label for="r_to" value="To" />
                    <x-input id="r_to" name="filter[date_to]" type="date" class="mt-1 block w-full" :value="$filters['date_to'] ?? ''" />
                </div>
            @endif
            @if ($uses('direction'))
                <div>
                    <x-label for="r_dir" value="Money" />
                    <select id="r_dir" name="filter[direction]" class="{{ $selectClass }}">
                        <option value="">Received and paid out</option>
                        <option value="credit" @selected(($filters['direction'] ?? '') === 'credit')>Received only</option>
                        <option value="debit" @selected(($filters['direction'] ?? '') === 'debit')>Paid out only</option>
                    </select>
                </div>
            @endif
            @if ($uses('company_id'))
                <div>
                    <x-label for="r_company" value="Company / principal" />
                    <select id="r_company" name="filter[company_id]" class="{{ $selectClass }}">
                        <option value="">All</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}" @selected((string) ($filters['company_id'] ?? '') === (string) $company->id)>{{ $company->supplier_name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if ($uses('category_id'))
                <div>
                    <x-label for="r_cat" value="Category" />
                    <select id="r_cat" name="filter[category_id]" class="{{ $selectClass }}">
                        <option value="">All</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if ($uses('status'))
                <div>
                    <x-label for="r_status" value="Status" />
                    <select id="r_status" name="filter[status]" class="{{ $selectClass }}">
                        <option value="">All</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if ($uses('receipt'))
                <div>
                    <x-label for="r_receipt" value="Receipt" />
                    <select id="r_receipt" name="filter[receipt]" class="{{ $selectClass }}">
                        <option value="">All</option>
                        <option value="without" @selected(($filters['receipt'] ?? '') === 'without')>Without receipt</option>
                        <option value="with" @selected(($filters['receipt'] ?? '') === 'with')>With receipt</option>
                    </select>
                </div>
            @endif
            @if ($uses('sort'))
                <div>
                    <x-label for="r_sort" value="Sort by" />
                    <select id="r_sort" name="filter[sort]" class="{{ $selectClass }}">
                        <option value="date" @selected(($filters['sort'] ?? 'date') === 'date')>Date</option>
                        <option value="amount" @selected(($filters['sort'] ?? '') === 'amount')>Amount (largest first)</option>
                        <option value="party" @selected(($filters['sort'] ?? '') === 'party')>Party name</option>
                    </select>
                </div>
            @endif
        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16 mt-4">
        <div class="bg-white overflow-hidden p-4 shadow-xl sm:rounded-lg mb-4 print:shadow-none">
            <div class="overflow-x-auto">
                <p class="text-center font-extrabold mb-2">
                    Moon Traders<br>
                    {{ $data['title'] }}<br>
                    <span class="text-sm font-semibold">{{ $data['subtitle'] }}</span>
                    <br>
                    <span class="print-only text-xs font-normal">
                        Printed by: {{ auth()->user()->name }} | {{ now()->format('d-M-Y h:i A') }}
                    </span>
                </p>

                <table class="report-table">
                    <thead>
                        <tr>
                            <th class="text-center">Sr.#</th>
                            @foreach ($data['headings'] as $i => $heading)
                                <th class="{{ $isNumeric($i) ? 'text-right' : 'text-left' }}">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($data['rows'] as $n => $row)
                            <tr>
                                <td class="text-center">{{ $n + 1 }}</td>
                                @foreach ($row as $i => $cell)
                                    <td class="{{ $isNumeric($i) ? 'text-right tabular-nums' : 'text-left' }}" @unless($isNumeric($i)) dir="auto" @endunless>{{ $format($cell, $i) }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($data['headings']) + 1 }}" class="text-center py-8 text-gray-500">No data for these filters.</td></tr>
                        @endforelse
                    </tbody>
                    @if ($data['totals'] && count($data['rows']))
                        <tfoot>
                            <tr>
                                <td></td>
                                @foreach ($data['totals'] as $i => $cell)
                                    <td class="{{ $isNumeric($i) ? 'text-right tabular-nums' : 'text-left' }}">{{ $format($cell, $i) }}</td>
                                @endforeach
                            </tr>
                        </tfoot>
                    @endif
                </table>

                @foreach ($data['notes'] as $note)
                    <p class="text-sm text-gray-600 mt-2">{{ $note }}</p>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
