@php($preview = $import->preview ?? [])
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Import Summary — {{ $import->file_name }}" :showSearch="false" :showRefresh="false" backRoute="bank-statement-imports.index" />
    </x-slot>

    <div class="py-6">
    <x-bank.print-styles />
    <x-bank.subnav inside />
    <x-bank.print-title title="Statement Import" />
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-status-message />

            <ol class="flex items-center gap-2 text-sm">
                <li class="flex items-center gap-2 text-gray-400"><span class="w-6 h-6 rounded-full bg-gray-200 text-xs grid place-items-center">✓</span>Upload</li>
                <li class="text-gray-300">——</li>
                <li class="flex items-center gap-2"><span class="w-6 h-6 rounded-full {{ $import->status === 'imported' ? 'bg-gray-200 text-gray-500' : 'bg-blue-950 text-white' }} text-xs grid place-items-center">2</span><b>Check summary</b></li>
                <li class="text-gray-300">——</li>
                <li class="flex items-center gap-2 {{ $import->status === 'imported' ? '' : 'text-gray-400' }}"><span class="w-6 h-6 rounded-full {{ $import->status === 'imported' ? 'bg-blue-950 text-white' : 'bg-gray-200' }} text-xs grid place-items-center">3</span>Confirm</li>
            </ol>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                @foreach ([
                    ['Rows read', $preview['valid_rows'] ?? 0, 'text-gray-900'],
                    ['New rows', $preview['new_rows'] ?? 0, 'text-emerald-700'],
                    ['Already in system', $preview['duplicate_rows'] ?? 0, 'text-amber-700'],
                    ['Rows with problems', $preview['error_rows'] ?? 0, ($preview['error_rows'] ?? 0) > 0 ? 'text-red-700' : 'text-gray-900'],
                ] as [$label, $value, $color])
                    <div class="bg-white rounded-lg shadow p-4">
                        <p class="text-xs uppercase tracking-wide text-gray-500">{{ $label }}</p>
                        <p class="text-2xl font-bold {{ $color }} tabular-nums">{{ number_format($value) }}</p>
                    </div>
                @endforeach
            </div>

            <div class="bg-white rounded-lg shadow p-4 grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                <div><p class="text-xs uppercase text-gray-500">Bank account</p><p class="font-semibold">{{ $import->bankAccount->account_name }}</p></div>
                <div><p class="text-xs uppercase text-gray-500">Dates in file</p><p class="font-semibold">{{ $preview['date_from'] ?? '—' }} → {{ $preview['date_to'] ?? '—' }}</p></div>
                <div><p class="text-xs uppercase text-gray-500">Need review (unidentified)</p><p class="font-semibold">{{ number_format($preview['needs_review_rows'] ?? 0) }}</p></div>
                <div><p class="text-xs uppercase text-gray-500">Total debits in file</p><p class="font-semibold"><x-bank.money :value="$preview['total_debit'] ?? 0" /></p></div>
                <div><p class="text-xs uppercase text-gray-500">Total credits in file</p><p class="font-semibold"><x-bank.money :value="$preview['total_credit'] ?? 0" /></p></div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
                <p class="font-semibold mb-2">Checks against the printed statement</p>
                @forelse ($preview['checks'] ?? [] as $check)
                    <div class="flex items-center justify-between py-1.5 border-b last:border-0 text-sm">
                        <span>{{ $check['label'] }}</span>
                        <span class="tabular-nums">
                            expected <b>{{ number_format($check['expected'], 2) }}</b> · file <b>{{ number_format($check['actual'], 2) }}</b>
                            <span class="ml-2 inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $check['ok'] ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">{{ $check['ok'] ? 'Tallies' : 'Does not tally' }}</span>
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No control figures were entered, so nothing was compared. You can still import.</p>
                @endforelse
            </div>

            @if (! empty($preview['errors']))
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <p class="font-semibold text-red-800 mb-2">Rows that will be skipped</p>
                    <ul class="text-sm text-red-800 list-disc ps-5">
                        @foreach ($preview['errors'] as $error)
                            <li>Row {{ $error['row'] }}: {{ $error['message'] }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white rounded-lg shadow overflow-x-auto">
                <p class="font-semibold p-4 pb-2">First rows</p>
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-3 py-2 text-left">Row</th><th class="px-3 py-2 text-left">Date</th><th class="px-3 py-2 text-left">Party</th><th class="px-3 py-2 text-left">Reference</th><th class="px-3 py-2 text-right">Debit</th><th class="px-3 py-2 text-right">Credit</th></tr></thead>
                    <tbody>
                        @foreach ($preview['sample'] ?? [] as $row)
                            <tr class="border-t {{ $row['needs_review'] ? 'bg-yellow-50' : '' }}">
                                <td class="px-3 py-1.5">{{ $row['row'] }}</td>
                                <td class="px-3 py-1.5">{{ $row['posting_date'] }}</td>
                                <td class="px-3 py-1.5" dir="auto">{{ $row['party'] }}</td>
                                <td class="px-3 py-1.5">{{ $row['reference'] }}</td>
                                <td class="px-3 py-1.5 text-right"><x-bank.money :value="$row['debit']" /></td>
                                <td class="px-3 py-1.5 text-right"><x-bank.money :value="$row['credit']" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($import->status === 'previewed')
                <div class="flex items-center justify-between">
                    <form method="POST" action="{{ route('bank-statement-imports.destroy', $import) }}" onsubmit="return confirm('Discard this import?')">
                        @csrf @method('DELETE')
                        <button class="text-sm text-red-600 hover:underline">Discard</button>
                    </form>
                    <form method="POST" action="{{ route('bank-statement-imports.confirm', $import) }}" x-data="{ busy: false }" @submit="busy = true">
                        @csrf
                        <x-button class="bg-green-800" x-bind:disabled="busy" :disabled="! ($preview['checks_ok'] ?? false)">
                            Confirm import of {{ number_format($preview['new_rows'] ?? 0) }} new rows
                        </x-button>
                    </form>
                </div>
                @unless ($preview['checks_ok'] ?? false)
                    <p class="text-sm text-red-700 text-right">The figures do not tally, so the import is blocked. Correct the figures or the file and upload again.</p>
                @endunless
            @else
                <a href="{{ route('bank-statement-entries.index', ['filter' => ['import_id' => $import->id]]) }}" class="inline-flex text-blue-700 hover:underline">View the {{ number_format($import->imported_rows) }} imported entries →</a>
            @endif
        </div>
    </div>
</x-app-layout>
