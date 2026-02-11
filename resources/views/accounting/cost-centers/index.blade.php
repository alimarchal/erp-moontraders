<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Cost Centers" :createRoute="route('cost-centers.create')" createLabel="Add Cost Center"
            createPermission="cost-center-create" :showSearch="true" :showRefresh="true" backRoute="settings.index" />
    </x-slot>

    <x-filter-section :action="route('cost-centers.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_code" value="Code" />
                <x-input id="filter_code" name="filter[code]" type="text" class="mt-1 block w-full"
                    :value="request('filter.code')" placeholder="CC001" />
            </div>

            <div>
                <x-label for="filter_name" value="Name" />
                <x-input id="filter_name" name="filter[name]" type="text" class="mt-1 block w-full"
                    :value="request('filter.name')" placeholder="Marketing" />
            </div>

            <div>
                <x-label for="filter_type" value="Type" />
                <select id="filter_type" name="filter[type]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All types</option>
                    @foreach ($typeOptions as $value => $label)
                        <option value="{{ $value }}" {{ request('filter.type') === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_is_active" value="Active Only" />
                <select id="filter_is_active" name="filter[is_active]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    <option value="1" {{ request('filter.is_active') === '1' ? 'selected' : '' }}>Active</option>
                    <option value="0" {{ request('filter.is_active') === '0' ? 'selected' : '' }}>Inactive</option>
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

    <x-data-table :items="$costCenters" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Code'],
        ['label' => 'Name'],
        ['label' => 'Type', 'align' => 'text-center'],
        ['label' => 'Parent'],
        ['label' => 'Active', 'align' => 'text-center'],
        ['label' => 'Dates', 'align' => 'text-left'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No cost centers found." :emptyRoute="route('cost-centers.create')"
        emptyLinkText="Create a cost center">

        @foreach ($costCenters as $index => $costCenter)
            <tr class="border-b border-gray-200 text-sm">
                <td class="py-1 px-2 text-center">
                    {{ $costCenters->firstItem() + $index }}
                </td>
                <td class="py-1 px-2 font-semibold">
                    {{ $costCenter->code }}
                </td>
                <td class="py-1 px-2">
                    <div class="font-semibold text-gray-900">
                        {{ $costCenter->name }}
                    </div>
                    @if ($costCenter->description)
                        <p class="text-xs text-gray-500">
                            {{ \Illuminate\Support\Str::limit($costCenter->description, 120) }}
                        </p>
                    @endif
                </td>
                <td class="py-1 px-2 text-center">
                    <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full
                            @class([
                                'bg-blue-100 text-blue-700' => $costCenter->type === \App\Models\CostCenter::TYPE_COST_CENTER,
                                'bg-purple-100 text-purple-700' => $costCenter->type === \App\Models\CostCenter::TYPE_PROJECT,
                            ])">
                        {{ $typeOptions[$costCenter->type] ?? ucfirst(str_replace('_', ' ', $costCenter->type)) }}
                    </span>
                </td>
                <td class="py-1 px-2">
                    {{ $costCenter->parent ? $costCenter->parent->code . ' · ' . $costCenter->parent->name : '—' }}
                </td>
                <td class="py-1 px-2 text-center">
                    <span
                        class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full {{ $costCenter->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-700' }}">
                        {{ $costCenter->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </td>
                <td class="py-1 px-2 text-left">
                    <div class="text-xs">
                        <strong>Start:</strong>
                        {{ optional($costCenter->start_date)->format('d-m-Y') ?? '—' }}
                    </div>
                    <div class="text-xs">
                        <strong>End:</strong>
                        {{ optional($costCenter->end_date)->format('d-m-Y') ?? '—' }}
                    </div>
                </td>
                <td class="py-1 px-2 text-center">
                    <div class="flex justify-center space-x-2">
                        <a href="{{ route('cost-centers.show', $costCenter->id) }}"
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
                        @can('cost-center-edit')
                            <a href="{{ route('cost-centers.edit', $costCenter->id) }}"
                                class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors duration-150"
                                title="Edit">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </a>
                        @endcan
                        @role('super-admin')
                        <form method="POST" action="{{ route('cost-centers.destroy', $costCenter->id) }}"
                            onsubmit="return confirm('Delete this cost center?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-md transition-colors duration-150"
                                title="Delete">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </form>
                        @endrole
                    </div>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-app-layout>