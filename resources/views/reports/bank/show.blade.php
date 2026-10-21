@php
    $uses = fn (string $field) => in_array($field, $definition['filters'], true);
    $exportQuery = ['filter' => $filters];
    $isNumeric = fn (int $i) => in_array($i, $data['numeric'], true);
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $data['title'] }}</h2>
            <div class="flex gap-2">
                <a href="{{ route('reports.bank.export.excel', ['report' => $slug] + $exportQuery) }}" class="rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-emerald-800">Excel</a>
                <a href="{{ route('reports.bank.export.pdf', ['report' => $slug] + $exportQuery) }}" class="rounded-md bg-red-700 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-red-800">PDF</a>
                <a href="{{ route('reports.bank.index') }}" class="rounded-md bg-blue-950 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-green-900">All reports</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" action="{{ route('reports.bank.show', $slug) }}" class="bg-white shadow rounded-lg p-4 flex flex-wrap items-end gap-3">
                @if ($uses('type'))
                    <div>
                        <x-label for="r_type" value="Money" />
                        <select id="r_type" name="filter[type]" class="border-gray-300 rounded-md shadow-sm block mt-1">
                            <option value="credit" @selected(($filters['type'] ?? 'credit') === 'credit')>Received (credit)</option>
                            <option value="debit" @selected(($filters['type'] ?? '') === 'debit')>Paid (debit)</option>
                        </select>
                    </div>
                @endif
                @if ($uses('bank_account_id'))
                    <div>
                        <x-label for="r_bank" value="Bank account" />
                        <select id="r_bank" name="filter[bank_account_id]" class="border-gray-300 rounded-md shadow-sm block mt-1">
                            <option value="">All</option>
                            @foreach ($bankAccounts as $account)<option value="{{ $account->id }}" @selected((string) ($filters['bank_account_id'] ?? '') === (string) $account->id)>{{ $account->account_name }}</option>@endforeach
                        </select>
                    </div>
                @endif
                @if ($uses('company_id'))
                    <div>
                        <x-label for="r_company" value="Company" />
                        <select id="r_company" name="filter[company_id]" class="border-gray-300 rounded-md shadow-sm block mt-1">
                            <option value="">Choose…</option>
                            @foreach ($companies as $company)<option value="{{ $company->id }}" @selected((string) ($filters['company_id'] ?? '') === (string) $company->id)>{{ $company->company_name }}</option>@endforeach
                        </select>
                    </div>
                @endif
                @if ($uses('reconciliation_id'))
                    <div>
                        <x-label for="r_rec" value="Reconciliation" />
                        <select id="r_rec" name="filter[reconciliation_id]" class="border-gray-300 rounded-md shadow-sm block mt-1">
                            <option value="">Choose…</option>
                            @foreach ($reconciliations as $reconciliation)<option value="{{ $reconciliation->id }}" @selected((string) ($filters['reconciliation_id'] ?? '') === (string) $reconciliation->id)>{{ $reconciliation->bankAccount->account_name }} · {{ $reconciliation->period_from->format('d-m-Y') }} → {{ $reconciliation->period_to->format('d-m-Y') }}</option>@endforeach
                        </select>
                    </div>
                @endif
                @if ($uses('opening_balance'))
                    <div><x-label for="r_open" value="Opening balance" /><x-input id="r_open" name="filter[opening_balance]" type="number" step="0.01" class="mt-1 w-40" :value="$filters['opening_balance'] ?? ''" placeholder="0.00" /></div>
                @endif
                @if ($uses('date_from'))
                    <div><x-label for="r_from" value="From" /><x-input id="r_from" name="filter[date_from]" type="date" class="mt-1" :value="$filters['date_from'] ?? ''" /></div>
                    <div><x-label for="r_to" value="To" /><x-input id="r_to" name="filter[date_to]" type="date" class="mt-1" :value="$filters['date_to'] ?? ''" /></div>
                @endif
                <x-button class="bg-blue-950">Show</x-button>
            </form>

            <div class="bg-white shadow-xl sm:rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b">
                    <p class="font-semibold">{{ $data['title'] }}</p>
                    <p class="text-sm text-gray-500">{{ $data['subtitle'] }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-green-800 text-white uppercase text-xs">
                                @foreach ($data['headings'] as $i => $heading)<th class="py-2 px-3 {{ $isNumeric($i) ? 'text-right' : 'text-left' }}">{{ $heading }}</th>@endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($data['rows'] as $r => $row)
                                <tr class="border-t {{ $r % 2 ? 'bg-gray-50' : 'bg-white' }}">
                                    @foreach ($row as $i => $cell)
                                        <td class="py-1.5 px-3 {{ $isNumeric($i) ? 'text-right tabular-nums' : '' }}" @unless($isNumeric($i)) dir="auto" @endunless>{{ is_float($cell) || (is_int($cell) && $isNumeric($i) && $i > 1) ? number_format($cell, 2) : $cell }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($data['headings']) }}" class="py-8 text-center text-gray-500">No data for these filters.</td></tr>
                            @endforelse
                        </tbody>
                        @if ($data['totals'] && count($data['rows']))
                            <tfoot>
                                <tr class="bg-gray-100 font-bold border-t-2">
                                    @foreach ($data['totals'] as $i => $cell)
                                        <td class="py-2 px-3 {{ $isNumeric($i) ? 'text-right tabular-nums' : '' }}">{{ is_float($cell) || (is_int($cell) && $isNumeric($i) && $i > 1) ? number_format($cell, 2) : $cell }}</td>
                                    @endforeach
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
                @foreach ($data['notes'] as $note)
                    <p class="px-4 py-2 text-sm bg-yellow-50 border-t text-yellow-900">{{ $note }}</p>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
