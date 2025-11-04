<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight inline-block">
                    Journal Entry #{{ $journalEntry->id }}
                </h2>
                <p class="text-sm text-gray-500">
                    {{ optional($journalEntry->entry_date)->format('F d, Y') }} · {{ ucfirst($journalEntry->status) }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if($journalEntry->status === 'draft')
                <form method="POST" action="{{ route('journal-entries.post', $journalEntry->id) }}">
                    @csrf
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        Post Entry
                    </button>
                </form>
                <a href="{{ route('journal-entries.edit', $journalEntry->id) }}"
                    class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    Edit
                </a>
                <form method="POST" action="{{ route('journal-entries.destroy', $journalEntry->id) }}"
                    onsubmit="return confirm('Delete this draft journal entry?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        Delete Draft
                    </button>
                </form>
                @elseif($journalEntry->status === 'posted')
                <form method="POST" action="{{ route('journal-entries.reverse', $journalEntry->id) }}"
                    onsubmit="return confirm('Create a reversing entry for this journal?');">
                    @csrf
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-orange-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        Reverse Entry
                    </button>
                </form>
                @endif
                <a href="{{ route('journal-entries.index') }}"
                    class="inline-flex items-center px-4 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 focus:bg-green-800 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    Back
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-status-message class="shadow-md" />

            @php
            $totalDebit = $journalEntry->details->sum('debit');
            $totalCredit = $journalEntry->details->sum('credit');
            @endphp

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow sm:rounded-lg">
                <div
                    class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-sm text-gray-700 dark:text-gray-200">
                    <div>
                        <span class="font-semibold block text-gray-500 uppercase text-xs">Entry Date</span>
                        <span>{{ optional($journalEntry->entry_date)->format('F d, Y') }}</span>
                    </div>
                    <div>
                        <span class="font-semibold block text-gray-500 uppercase text-xs">Reference</span>
                        <span>{{ $journalEntry->reference ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="font-semibold block text-gray-500 uppercase text-xs">Currency</span>
                        <span>{{ $journalEntry->currency?->currency_code ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="font-semibold block text-gray-500 uppercase text-xs">FX Rate</span>
                        <span>{{ number_format((float) $journalEntry->fx_rate_to_base, 6) }}</span>
                    </div>
                    <div>
                        <span class="font-semibold block text-gray-500 uppercase text-xs">Status</span>
                        <span class="inline-flex items-center py-1 rounded-full text-xs font-semibold uppercase tracking-wide
                            @class([
                                'bg-yellow-100 text-yellow-800' => $journalEntry->status === 'draft',
                                'bg-green-100 text-green-800' => $journalEntry->status === 'posted',
                                'bg-red-100 text-red-800' => $journalEntry->status === 'void',
                            ])">
                            {{ ucfirst($journalEntry->status) }}
                        </span>
                    </div>
                    <div>
                        <span class="font-semibold block text-gray-500 uppercase text-xs">Posted At</span>
                        <span>{{ optional($journalEntry->posted_at)->format('F d, Y H:i') ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="font-semibold block text-gray-500 uppercase text-xs">Posted By</span>
                        <span>{{ $journalEntry->postedBy?->name ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="font-semibold block text-gray-500 uppercase text-xs">Accounting Period</span>
                        <span>{{ $journalEntry->accountingPeriod?->name ?? '—' }}</span>
                    </div>
                </div>
                @if ($journalEntry->description)
                <div class="px-6 pb-6">
                    <span class="font-semibold block text-gray-500 uppercase text-xs mb-1">Description</span>
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $journalEntry->description }}</p>
                </div>
                @endif
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow sm:rounded-lg">
                <div class="pb-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full table-auto text-sm">
                            <thead>
                                <tr class="bg-green-800 text-white uppercase text-xs">
                                    <th class="text-lg font-semibold text-white dark:text-gray-200 py-2" colspan="6">
                                        Detail Lines</th>
                                </tr>
                            </thead>
                            <thead>
                                <tr class="bg-green-800 text-white uppercase text-xs">
                                    <th class="py-2 px-2 text-left">Line</th>
                                    <th class="py-2 px-2 text-left">Account</th>
                                    <th class="py-2 px-2 text-left">Cost Center</th>
                                    <th class="py-2 px-2 text-right">Debit</th>
                                    <th class="py-2 px-2 text-right">Credit</th>
                                    <th class="py-2 px-2 text-left">Description</th>
                                </tr>
                            </thead>
                            <tbody class="text-black text-md leading-normal font-semibold">
                                @foreach ($journalEntry->details as $index => $detail)
                                <tr class="border-b border-gray-200">
                                    <td class="py-2 px-2 text-left">
                                        {{ $detail->line_no ?? ($index + 1) }}
                                    </td>
                                    <td class="py-2 px-2 text-left">
                                        {{ $detail->account?->account_code }} · {{ $detail->account?->account_name }}
                                    </td>
                                    <td class="py-2 px-2 text-left">
                                        {{ $detail->costCenter?->code ? $detail->costCenter->code . ' · ' .
                                        $detail->costCenter->name : '—' }}
                                    </td>
                                    <td class="py-2 px-2 text-right">
                                        {{ number_format((float) $detail->debit, 2) }}
                                    </td>
                                    <td class="py-2 px-2 text-right">
                                        {{ number_format((float) $detail->credit, 2) }}
                                    </td>
                                    <td class="py-2 px-2 text-left text-sm text-gray-600 dark:text-gray-300">
                                        {{ $detail->description ?? '—' }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-100 text-sm font-semibold">
                                <tr>
                                    <td colspan="3" class="py-2 px-2 text-right">Totals</td>
                                    <td class="py-2 px-2 text-right">{{ number_format((float) $totalDebit, 2) }}</td>
                                    <td class="py-2 px-2 text-right">{{ number_format((float) $totalCredit, 2) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                        <p class="text-xs text-gray-500 mt-2 ml-4">Journal entries must remain balanced. Debits should
                            equal
                            credits.</p>
                    </div>

                </div>
            </div>

            @if ($journalEntry->attachments->isNotEmpty())
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-4">Attachments</h3>
                    <ul class="space-y-2 text-sm text-gray-700 dark:text-gray-300">
                        @foreach ($journalEntry->attachments as $attachment)
                        <li
                            class="flex items-center justify-between border border-gray-200 dark:border-gray-700 rounded-md px-3 py-2">
                            <div>
                                <p class="font-semibold">{{ $attachment->file_name }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ number_format(($attachment->file_size ?? 0) / 1024, 2) }} KB
                                    · {{ $attachment->file_type ?? 'Unknown type' }}
                                </p>
                            </div>
                            <a href="{{ Storage::url($attachment->file_path) }}" target="_blank"
                                class="inline-flex items-center px-3 py-2 bg-blue-950 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                View
                            </a>
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>