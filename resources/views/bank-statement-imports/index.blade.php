<x-app-layout>
    <x-slot name="header">
        <x-bank.head title="Statement imports" sub="Excel or CSV statements read into the register, with control-total checks." :crumbs="['Statement imports' => null]">
            @can('bank-statement-import-create')
                <a href="{{ route('bank-statement-imports.create') }}" class="ak-btn ak-btn-primary"><span aria-hidden="true">＋</span> Import statement</a>
            @endcan
            <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
        </x-bank.head>
    </x-slot>

    <x-bank.print-styles />

    <div class="ak-page">
        <x-bank.subnav inside />
        <x-bank.print-title title="Statement Imports" />
        <x-status-message />

        <x-bank.table :items="$imports" :headers="[
            ['label' => '#', 'align' => 'text-center'],
            ['label' => 'File', 'align' => 'text-left'],
            ['label' => 'Bank account', 'align' => 'text-left'],
            ['label' => 'Period', 'align' => 'text-left'],
            ['label' => 'Rows', 'align' => 'text-right'],
            ['label' => 'Imported', 'align' => 'text-right'],
            ['label' => 'Status', 'align' => 'text-center'],
            ['label' => 'By', 'align' => 'text-left'],
        ]" emptyMessage="No statements imported yet." :emptyRoute="route('bank-statement-imports.create')" emptyLinkText="Import the first statement">
            @foreach ($imports as $import)
                <tr>
                    <td class="ak-c">{{ $import->id }}</td>
                    <td><a href="{{ route('bank-statement-imports.show', $import) }}" class="ak-primary-link">{{ $import->file_name }}</a></td>
                    <td>{{ $import->bankAccount->account_name }}</td>
                    <td>{{ $import->period_from?->format('d-m-Y') }} → {{ $import->period_to?->format('d-m-Y') }}</td>
                    <td class="ak-num">{{ number_format($import->total_rows) }}</td>
                    <td class="ak-num">{{ number_format($import->imported_rows) }}</td>
                    <td class="ak-c"><span class="ak-status {{ ['imported' => 'ak-status-green', 'previewed' => 'ak-status-amber', 'failed' => 'ak-status-red'][$import->status] ?? 'ak-status-slate' }}"><i aria-hidden="true"></i>{{ ucfirst($import->status) }}</span></td>
                    <td>{{ $import->importer?->name }}</td>
                </tr>
            @endforeach
        </x-bank.table>
    </div>
</x-app-layout>
