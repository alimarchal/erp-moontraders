<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Accounting Periods" :createRoute="route('accounting-periods.create')"
            createLabel="Add Period" :showSearch="true" :showRefresh="true" backRoute="settings.index" />
    </x-slot>

    <x-filter-section :action="route('accounting-periods.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <x-label for="filter_name" value="Period Name" />
                <x-input id="filter_name" name="filter[name]" type="text" class="mt-1 block w-full"
                    :value="request('filter.name')" placeholder="Fiscal Year 2025" />
            </div>

            <div>
                <x-label for="filter_status" value="Status" />
                <select id="filter_status" name="filter[status]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Statuses</option>
                    @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}" {{ request('filter.status')===$value ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_start_date_from" value="Start Date (From)" />
                <x-input id="filter_start_date_from" name="filter[start_date_from]" type="date"
                    class="mt-1 block w-full" :value="request('filter.start_date_from')" />
            </div>

            <div>
                <x-label for="filter_start_date_to" value="Start Date (To)" />
                <x-input id="filter_start_date_to" name="filter[start_date_to]" type="date" class="mt-1 block w-full"
                    :value="request('filter.start_date_to')" />
            </div>

            <div>
                <x-label for="filter_end_date_from" value="End Date (From)" />
                <x-input id="filter_end_date_from" name="filter[end_date_from]" type="date" class="mt-1 block w-full"
                    :value="request('filter.end_date_from')" />
            </div>

            <div>
                <x-label for="filter_end_date_to" value="End Date (To)" />
                <x-input id="filter_end_date_to" name="filter[end_date_to]" type="date" class="mt-1 block w-full"
                    :value="request('filter.end_date_to')" />
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$periods" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Name'],
        ['label' => 'Start Date', 'align' => 'text-center'],
        ['label' => 'End Date', 'align' => 'text-center'],
        ['label' => 'Duration', 'align' => 'text-center'],
        ['label' => 'Status', 'align' => 'text-center'],
        ['label' => 'Current', 'align' => 'text-center'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No accounting periods found." :emptyRoute="route('accounting-periods.create')"
        emptyLinkText="Add a period">
        @foreach ($periods as $index => $period)
        @php
        $duration = $period->start_date->diffInDays($period->end_date) + 1;
        $statusLabel = $statusOptions[$period->status] ?? ucfirst($period->status);
        $deleteDisabled = $period->isCurrent();
        @endphp
        <tr
            class="border-b border-gray-200 text-sm {{ $period->isCurrent() ? 'bg-emerald-50/40' : '' }}">
            <td class="py-1 px-2 text-center">
                {{ $periods->firstItem() + $index }}
            </td>
            <td class="py-1 px-2 font-semibold">
                {{ $period->name }}
            </td>
            <td class="py-1 px-2 text-center">
                {{ $period->start_date->format('d-m-Y') }}
            </td>
            <td class="py-1 px-2 text-center">
                {{ $period->end_date->format('d-m-Y') }}
            </td>
            <td class="py-1 px-2 text-center">
                {{ $duration }} days
            </td>
            <td class="py-1 px-2 text-center">
                <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full
                        @class([
                            'bg-emerald-100 text-emerald-700' => $period->status === 'open',
                            'bg-blue-100 text-blue-700' => $period->status === 'closed',
                            'bg-gray-100 text-gray-600' => $period->status === 'archived',
                        ])">
                    {{ $statusLabel }}
                </span>
            </td>
            <td class="py-1 px-2 text-center">
                <span
                    class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full {{ $period->isCurrent() ? 'bg-emerald-200 text-emerald-800' : 'bg-gray-100 text-gray-600' }}">
                    {{ $period->isCurrent() ? 'Yes' : 'No' }}
                </span>
            </td>
            <td class="py-1 px-2 text-center">
                <div class="flex justify-center space-x-2">
                    <a href="{{ route('accounting-periods.show', $period) }}"
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
                    <a href="{{ route('accounting-periods.edit', $period) }}"
                        class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors duration-150"
                        title="Edit">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </a>
                    <form method="POST" action="{{ route('accounting-periods.destroy', $period) }}"
                        onsubmit="return confirm('Are you sure you want to delete this period?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-md transition-colors duration-150 {{ $deleteDisabled ? 'opacity-40 cursor-not-allowed hover:bg-transparent hover:text-red-600 pointer-events-none' : '' }}"
                            title="Delete" @if ($deleteDisabled) disabled aria-disabled="true" @endif>
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </form>
                </div>
            </td>
        </tr>
        @endforeach
    </x-data-table>
</x-app-layout>
