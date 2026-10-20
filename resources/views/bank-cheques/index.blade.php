<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Bank Cheque Register" :createRoute="route('bank-cheques.create')" createLabel="Add Cheque"
            createPermission="bank-cheque-create" :showSearch="true" :showRefresh="true" backRoute="settings.index" />
    </x-slot>

    <x-filter-section :action="route('bank-cheques.index')">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_cheque_no" value="Cheque no." />
                <x-input id="filter_cheque_no" name="filter[cheque_no]" type="text" class="mt-1 block w-full" :value="request('filter.cheque_no')" />
            </div>
            <div>
                <x-label for="filter_bank_account_id" value="Bank account" />
                <select id="filter_bank_account_id" name="filter[bank_account_id]" class="border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    @foreach ($bankAccounts as $account)
                        <option value="{{ $account->id }}" @selected((string) request('filter.bank_account_id') === (string) $account->id)>{{ $account->account_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-label for="filter_direction" value="Direction" />
                <select id="filter_direction" name="filter[direction]" class="border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    @foreach (\App\Models\BankCheque::DIRECTIONS as $value => $label)
                        <option value="{{ $value }}" @selected(request('filter.direction') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-label for="filter_status" value="Status" />
                <select id="filter_status" name="filter[status]" class="border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    @foreach (\App\Models\BankCheque::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected(request('filter.status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$cheques" :headers="[
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
            <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-gray-50' }}">
                <td class="py-2 px-2">{{ $cheque->cheque_no }}</td>
                <td class="py-2 px-2 font-normal">{{ $cheque->cheque_date->format('d-m-Y') }}</td>
                <td class="py-2 px-2 font-normal">{{ \App\Models\BankCheque::DIRECTIONS[$cheque->direction] }}</td>
                <td class="py-2 px-2 font-normal" dir="auto">{{ $cheque->company?->company_name ?? $cheque->payee_name ?? '—' }}</td>
                <td class="py-2 px-2 font-normal">{{ $cheque->bankAccount->account_name }}</td>
                <td class="py-2 px-2 text-right"><x-bank.money :value="$cheque->amount" /></td>
                <td class="py-2 px-2 text-center">
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ ['pending' => 'bg-amber-100 text-amber-800', 'cleared' => 'bg-emerald-100 text-emerald-700', 'bounced' => 'bg-red-100 text-red-700'][$cheque->status] ?? 'bg-gray-100 text-gray-700' }}">{{ \App\Models\BankCheque::STATUSES[$cheque->status] }}</span>
                    @if ($cheque->statementEntry)
                        <a href="{{ route('bank-statement-entries.show', $cheque->statementEntry) }}" class="block text-xs text-blue-700 hover:underline font-normal">statement line</a>
                    @endif
                </td>
                <td class="py-2 px-2 text-center">
                    <div class="flex justify-center gap-3 font-normal">
                        @can('bank-cheque-edit')
                            <a href="{{ route('bank-cheques.edit', $cheque) }}" class="text-green-700 hover:underline">Edit</a>
                        @endcan
                        @can('bank-cheque-delete')
                            <form method="POST" action="{{ route('bank-cheques.destroy', $cheque) }}" onsubmit="return confirm('Delete this cheque?')">
                                @csrf @method('DELETE')
                                <button class="text-red-600 hover:underline">Delete</button>
                            </form>
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-app-layout>
