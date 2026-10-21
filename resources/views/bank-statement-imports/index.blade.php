<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Statement Imports" :createRoute="route('bank-statement-imports.create')" createLabel="Import statement"
            createPermission="bank-statement-import-create" :showSearch="false" :showRefresh="true" backRoute="bank-statement-entries.index" />
    </x-slot>

    <x-bank.print-styles />
    <x-bank.subnav />
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4"><x-bank.print-title title="Statement Imports" /></div>

    <x-data-table :items="$imports" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'File', 'align' => 'text-left'],
        ['label' => 'Bank account', 'align' => 'text-left'],
        ['label' => 'Period', 'align' => 'text-left'],
        ['label' => 'Rows', 'align' => 'text-right'],
        ['label' => 'Imported', 'align' => 'text-right'],
        ['label' => 'Status', 'align' => 'text-center'],
        ['label' => 'By', 'align' => 'text-left'],
    ]" emptyMessage="No statements imported yet." :emptyRoute="route('bank-statement-imports.create')" emptyLinkText="Import the first statement">
        @foreach ($imports as $index => $import)
            <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-gray-50' }}">
                <td class="py-2 px-2 text-center">{{ $import->id }}</td>
                <td class="py-2 px-2"><a href="{{ route('bank-statement-imports.show', $import) }}" class="text-blue-700 hover:underline">{{ $import->file_name }}</a></td>
                <td class="py-2 px-2 font-normal">{{ $import->bankAccount->account_name }}</td>
                <td class="py-2 px-2 font-normal">{{ $import->period_from?->format('d-m-Y') }} → {{ $import->period_to?->format('d-m-Y') }}</td>
                <td class="py-2 px-2 text-right tabular-nums">{{ number_format($import->total_rows) }}</td>
                <td class="py-2 px-2 text-right tabular-nums">{{ number_format($import->imported_rows) }}</td>
                <td class="py-2 px-2 text-center">
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ ['imported' => 'bg-emerald-100 text-emerald-700', 'previewed' => 'bg-amber-100 text-amber-800', 'failed' => 'bg-red-100 text-red-700'][$import->status] ?? 'bg-gray-100' }}">{{ ucfirst($import->status) }}</span>
                </td>
                <td class="py-2 px-2 font-normal">{{ $import->importer?->name }}</td>
            </tr>
        @endforeach
    </x-data-table>
</x-app-layout>
