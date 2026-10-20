<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Party Aliases" :createRoute="route('bank-party-aliases.create')" createLabel="Add Alias"
            createPermission="bank-party-alias-create" :showSearch="true" :showRefresh="true" backRoute="settings.index" />
    </x-slot>

    <x-filter-section :action="route('bank-party-aliases.index')">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <x-label for="filter_alias_name" value="Statement name" />
                <x-input id="filter_alias_name" name="filter[alias_name]" type="text" dir="auto" class="mt-1 block w-full" :value="request('filter.alias_name')" />
            </div>
            <div>
                <x-label for="filter_canonical_name" value="Correct name" />
                <x-input id="filter_canonical_name" name="filter[canonical_name]" type="text" dir="auto" class="mt-1 block w-full" :value="request('filter.canonical_name')" />
            </div>
            <div>
                <x-label for="filter_company_id" value="Company" />
                <select id="filter_company_id" name="filter[company_id]" class="border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    @foreach ($companies as $company)
                        <option value="{{ $company->id }}" @selected((string) request('filter.company_id') === (string) $company->id)>{{ $company->company_name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$aliases" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Name on statement', 'align' => 'text-left'],
        ['label' => 'Correct name', 'align' => 'text-left'],
        ['label' => 'Company', 'align' => 'text-left'],
        ['label' => 'Status', 'align' => 'text-center'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No aliases yet. Add one for every spelling of the same party." :emptyRoute="route('bank-party-aliases.create')" emptyLinkText="Add an alias">
        @foreach ($aliases as $index => $alias)
            <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-gray-50' }}">
                <td class="py-2 px-2 text-center">{{ $aliases->firstItem() + $index }}</td>
                <td class="py-2 px-2" dir="auto">{{ $alias->alias_name }}</td>
                <td class="py-2 px-2 font-normal" dir="auto">{{ $alias->canonical_name }}</td>
                <td class="py-2 px-2 font-normal">{{ $alias->company?->company_name ?? '—' }}</td>
                <td class="py-2 px-2 text-center">
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $alias->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">{{ $alias->is_active ? 'Active' : 'Inactive' }}</span>
                </td>
                <td class="py-2 px-2 text-center">
                    <div class="flex justify-center gap-3 font-normal">
                        @can('bank-party-alias-edit')
                            <a href="{{ route('bank-party-aliases.edit', $alias) }}" class="text-green-700 hover:underline">Edit</a>
                        @endcan
                        @can('bank-party-alias-delete')
                            <form method="POST" action="{{ route('bank-party-aliases.destroy', $alias) }}" onsubmit="return confirm('Delete this alias?')">
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
