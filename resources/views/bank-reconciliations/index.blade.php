<x-app-layout>
    <x-slot name="header">
        <x-bank.head title="Reconciliation" sub="Match the bank statement with the books, period by period." :crumbs="['Reconciliation' => null]">
            @can('bank-reconciliation-create')
                <a href="{{ route('bank-reconciliations.create') }}" class="ak-btn ak-btn-primary"><span aria-hidden="true">＋</span> New reconciliation</a>
            @endcan
            <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
        </x-bank.head>
    </x-slot>

    <x-bank.print-styles />

    <div class="ak-page max-w-7xl mx-auto sm:px-6 lg:px-8 pb-16">
        <x-bank.subnav inside />
        <x-bank.print-title title="Bank Reconciliations" />
        <x-status-message />

        <x-bank.table :items="$reconciliations" :headers="[
            ['label' => 'Bank account', 'align' => 'text-left'],
            ['label' => 'Period', 'align' => 'text-left'],
            ['label' => 'Statement balance', 'align' => 'text-right'],
            ['label' => 'Book balance', 'align' => 'text-right'],
            ['label' => 'Difference', 'align' => 'text-right'],
            ['label' => 'Status', 'align' => 'text-center'],
        ]" emptyMessage="No reconciliations yet." :emptyRoute="route('bank-reconciliations.create')" emptyLinkText="Start the first one">
            @foreach ($reconciliations as $reconciliation)
                <tr>
                    <td><a href="{{ route('bank-reconciliations.show', $reconciliation) }}" class="ak-primary-link">{{ $reconciliation->bankAccount->account_name }}</a></td>
                    <td>{{ $reconciliation->period_from?->format('d-m-Y') }} → {{ $reconciliation->period_to?->format('d-m-Y') }}</td>
                    <td class="ak-num"><x-bank.money :value="$reconciliation->statement_balance" /></td>
                    <td class="ak-num"><x-bank.money :value="$reconciliation->book_balance" /></td>
                    <td class="ak-num"><b class="{{ abs((float) $reconciliation->difference) < 0.01 ? 'bk-in' : 'bk-out' }}"><x-bank.money :value="$reconciliation->difference" /></b></td>
                    <td class="ak-c"><span class="ak-status {{ ['completed' => 'ak-status-green', 'in_progress' => 'ak-status-amber', 'cancelled' => 'ak-status-slate'][$reconciliation->status] ?? 'ak-status-slate' }}"><i aria-hidden="true"></i>{{ ucfirst(str_replace('_', ' ', $reconciliation->status)) }}</span></td>
                </tr>
            @endforeach
        </x-bank.table>
    </div>
</x-app-layout>
