<x-app-layout>
    <x-slot name="header">
        <x-bank.head title="Bank Cheque Register" :crumbs="['Cheque register' => null]">
            @can('bank-cheque-create')
                <a href="{{ route('bank-cheques.create') }}" class="ak-btn ak-btn-primary" title="Add Cheque" aria-label="Add Cheque"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:18px;height:18px" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg></a>
            @endcan
            <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
        </x-bank.head>
    </x-slot>

    <x-bank.print-styles />
    <div class="ak-page max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <x-bank.subnav inside />
        <x-bank.print-title title="Bank Cheque Register" />
        <x-status-message />

    <x-bank.filters :action="route('bank-cheques.index')">
                    <div class="ak-field">
                <label for="filter_cheque_no">Cheque no.</label>
                <input id="filter_cheque_no" name="filter[cheque_no]" type="text" value="{{ request('filter.cheque_no') }}">
            </div>
            <div class="ak-field">
                <label for="filter_bank_account_id">Bank account</label>
                <select data-placeholder="All" id="filter_bank_account_id" name="filter[bank_account_id]" class="select2 bank-select">
                    <option value="">All</option>
                    @foreach ($bankAccounts as $account)
                        <option value="{{ $account->id }}" @selected((string) request('filter.bank_account_id') === (string) $account->id)>{{ $account->account_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ak-field">
                <label for="filter_direction">Direction</label>
                <select data-placeholder="All" id="filter_direction" name="filter[direction]" class="select2 bank-select">
                    <option value="">All</option>
                    @foreach (\App\Models\BankCheque::DIRECTIONS as $value => $label)
                        <option value="{{ $value }}" @selected(request('filter.direction') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ak-field">
                <label for="filter_status">Status</label>
                <select data-placeholder="All" id="filter_status" name="filter[status]" class="select2 bank-select">
                    <option value="">All</option>
                    @foreach (\App\Models\BankCheque::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected(request('filter.status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
</x-bank.filters>

    <x-bank.table :items="$cheques" :headers="[
        ['label' => 'Cheque no.', 'align' => 'text-left'],
        ['label' => 'Date', 'align' => 'text-left'],
        ['label' => 'Direction', 'align' => 'text-left'],
        ['label' => 'Payee / company', 'align' => 'text-left'],
        ['label' => 'Bank account', 'align' => 'text-left'],
        ['label' => 'Amount', 'align' => 'text-right'],
        ['label' => 'Status', 'align' => 'text-center'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No cheques recorded." :emptyRoute="route('bank-cheques.create')" emptyLinkText="Add a cheque">
        @foreach ($cheques as $index => $cheque)
            <tr>
                <td>{{ $cheque->cheque_no }}</td>
                <td>{{ $cheque->cheque_date->format('d-m-Y') }}</td>
                <td>{{ \App\Models\BankCheque::DIRECTIONS[$cheque->direction] }}</td>
                <td dir="auto">{{ $cheque->company?->supplier_name ?? $cheque->payee_name ?? '—' }}</td>
                <td>{{ $cheque->bankAccount->account_name }}</td>
                <td class="ak-num"><x-bank.money :value="$cheque->amount" /></td>
                <td class="ak-c">
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ ['pending' => 'bg-amber-100 text-amber-800', 'cleared' => 'bg-emerald-100 text-emerald-700', 'bounced' => 'bg-red-100 text-red-700'][$cheque->status] ?? 'bg-gray-100 text-gray-700' }}">{{ \App\Models\BankCheque::STATUSES[$cheque->status] }}</span>
                    @if ($cheque->statementEntry)
                        <a href="{{ route('bank-statement-entries.show', $cheque->statementEntry) }}" class="block text-xs text-blue-700 hover:underline font-normal">statement line</a>
                    @endif
                </td>
                <td class="ak-c">
                    <div class="bk-row" style="justify-content:center">
                        @can('bank-cheque-edit')
                            <a href="{{ route('bank-cheques.edit', $cheque) }}" class="ak-btn ak-btn-outline ak-btn-sm">Edit</a>
                        @endcan
                        @can('bank-cheque-delete')
                            <form method="POST" action="{{ route('bank-cheques.destroy', $cheque) }}" onsubmit="return confirm('Delete this cheque?')">
                                @csrf @method('DELETE')
                                <button class="ak-btn ak-btn-danger-outline ak-btn-sm">Delete</button>
                            </form>
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach
    </x-bank.table>
    </div>
</x-app-layout>
