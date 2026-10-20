<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Bank Reconciliations" :createRoute="route('bank-reconciliations.create')" createLabel="New reconciliation"
            createPermission="bank-reconciliation-create" :showSearch="false" :showRefresh="true" backRoute="bank-statement-entries.index" />
    </x-slot>

    <x-data-table :items="$reconciliations" :headers="[
        ['label' => 'Bank account', 'align' => 'text-left'],
        ['label' => 'Period', 'align' => 'text-left'],
        ['label' => 'Statement balance', 'align' => 'text-right'],
        ['label' => 'Book balance', 'align' => 'text-right'],
        ['label' => 'Difference', 'align' => 'text-right'],
        ['label' => 'Status', 'align' => 'text-center'],
    ]" emptyMessage="No reconciliations yet." :emptyRoute="route('bank-reconciliations.create')" emptyLinkText="Start the first one">
        @foreach ($reconciliations as $index => $reconciliation)
            <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-gray-50' }}">
                <td class="py-2 px-2"><a href="{{ route('bank-reconciliations.show', $reconciliation) }}" class="text-blue-700 hover:underline">{{ $reconciliation->bankAccount->account_name }}</a></td>
                <td class="py-2 px-2 font-normal">{{ $reconciliation->period_from?->format('d-m-Y') }} → {{ $reconciliation->period_to?->format('d-m-Y') }}</td>
                <td class="py-2 px-2 text-right"><x-bank.money :value="$reconciliation->statement_balance" /></td>
                <td class="py-2 px-2 text-right"><x-bank.money :value="$reconciliation->book_balance" /></td>
                <td class="py-2 px-2 text-right {{ abs((float) $reconciliation->difference) < 0.01 ? 'text-emerald-700' : 'text-red-700' }}"><x-bank.money :value="$reconciliation->difference" /></td>
                <td class="py-2 px-2 text-center">
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ ['completed' => 'bg-emerald-100 text-emerald-700', 'in_progress' => 'bg-amber-100 text-amber-800', 'cancelled' => 'bg-gray-200 text-gray-600'][$reconciliation->status] }}">{{ ucfirst(str_replace('_', ' ', $reconciliation->status)) }}</span>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-app-layout>
