<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Journal Entries" :createRoute="route('journal-entries.create')" createLabel="New Journal Entry"
            :showSearch="true" :showRefresh="true" backRoute="dashboard" />
    </x-slot>

    <x-filter-section :action="route('journal-entries.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_reference" value="Reference" />
                <x-input id="filter_reference" name="filter[reference]" type="text"
                    class="mt-1 block w-full" :value="request('filter.reference')" placeholder="Search by reference" />
            </div>

            <div>
                <x-label for="filter_status" value="Status" />
                <select id="filter_status" name="filter[status]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All statuses</option>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" {{ request('filter.status') === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_date_from" value="Date From" />
                <x-input id="filter_date_from" name="filter[date_from]" type="date"
                    class="mt-1 block w-full" :value="request('filter.date_from')" />
            </div>

            <div>
                <x-label for="filter_date_to" value="Date To" />
                <x-input id="filter_date_to" name="filter[date_to]" type="date"
                    class="mt-1 block w-full" :value="request('filter.date_to')" />
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$entries" :headers="[
            ['label' => '#', 'align' => 'text-center'],
            ['label' => 'Entry Date', 'align' => 'text-left'],
            ['label' => 'Reference', 'align' => 'text-left'],
            ['label' => 'Description', 'align' => 'text-left'],
            ['label' => 'Currency', 'align' => 'text-left'],
            ['label' => 'Amount', 'align' => 'text-right'],
            ['label' => 'Status', 'align' => 'text-center'],
            ['label' => 'Actions', 'align' => 'text-center print:hidden'],
        ]" emptyMessage="No journal entries found." :emptyRoute="route('journal-entries.create')"
        emptyLinkText="Create the first journal entry">

        @foreach ($entries as $index => $entry)
            <tr class="border-b border-gray-200 hover:bg-gray-100">
                <td class="py-1 px-2 text-center">
                    {{ $entries->firstItem() + $index }}
                </td>
                <td class="py-1 px-2 text-left">
                    {{ optional($entry->entry_date)->format('Y-m-d') }}
                </td>
                <td class="py-1 px-2 text-left">
                    {{ $entry->reference ?? '-' }}
                </td>
                <td class="py-1 px-2 text-left">
                    {{ $entry->description ? \Illuminate\Support\Str::limit($entry->description, 120) : '-' }}
                </td>
                <td class="py-1 px-2 text-left">
                    {{ $entry->currency?->currency_code ?? '—' }}
                </td>
                <td class="py-1 px-2 text-right">
                    {{ number_format((float) ($entry->total_debit ?? 0), 2) }}
                </td>
                <td class="py-1 px-2 text-center">
                    <span @class([
                        'inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide',
                        'bg-yellow-100 text-yellow-800' => $entry->status === 'draft',
                        'bg-green-100 text-green-800' => $entry->status === 'posted',
                        'bg-red-100 text-red-800' => $entry->status === 'void',
                    ])>
                        {{ ucfirst($entry->status) }}
                    </span>
                </td>
                <td class="py-1 px-2 text-center">
                    <div class="flex justify-center flex-wrap gap-2">
                        <a href="{{ route('journal-entries.show', $entry->id) }}"
                            class="inline-flex items-center justify-center w-8 h-8 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded-md transition-colors duration-150"
                            title="View">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </a>

                        @if ($entry->status === 'draft')
                            <a href="{{ route('journal-entries.edit', $entry->id) }}"
                                class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors duration-150"
                                title="Edit">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </a>

                            <form method="POST" action="{{ route('journal-entries.post', $entry->id) }}">
                                @csrf
                                <button type="submit"
                                    class="inline-flex items-center justify-center w-8 h-8 text-indigo-600 hover:text-indigo-800 hover:bg-indigo-100 rounded-md transition-colors duration-150"
                                    title="Post entry">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m5 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </button>
                            </form>

                            <form method="POST" action="{{ route('journal-entries.destroy', $entry->id) }}"
                                onsubmit="return confirm('Delete this draft journal entry?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-md transition-colors duration-150"
                                    title="Delete draft">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </form>
                        @elseif ($entry->status === 'posted')
                            <form method="POST" action="{{ route('journal-entries.reverse', $entry->id) }}"
                                onsubmit="return confirm('Create a reversing entry for this journal?');">
                                @csrf
                                <button type="submit"
                                    class="inline-flex items-center justify-center w-8 h-8 text-orange-600 hover:text-orange-800 hover:bg-orange-100 rounded-md transition-colors duration-150"
                                    title="Reverse entry">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                                        viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M6.75 15.75L3 12m0 0l3.75-3.75M3 12h13.5a4.5 4.5 0 0 1 0 9H12" />
                                    </svg>
                                </button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-app-layout>
