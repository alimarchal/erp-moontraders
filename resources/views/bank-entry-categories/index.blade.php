<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Bank Entry Categories" :createRoute="route('bank-entry-categories.create')" createLabel="Add Category"
            createPermission="bank-entry-category-create" :showSearch="true" :showRefresh="true" backRoute="settings.index" />
    </x-slot>

    <x-bank.print-styles />
    <x-bank.subnav />
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4"><x-bank.print-title title="Bank Entry Categories" /></div>

    <x-filter-section :action="route('bank-entry-categories.index')">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <x-label for="filter_name" value="Name" />
                <x-input id="filter_name" name="filter[name]" type="text" class="mt-1 block w-full" :value="request('filter.name')" />
            </div>
            <div>
                <x-label for="filter_type" value="Used for" />
                <select data-placeholder="All" id="filter_type" name="filter[type]" class="select2 bank-select border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    @foreach (\App\Models\BankEntryCategory::TYPES as $value => $label)
                        <option value="{{ $value }}" @selected(request('filter.type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-label for="filter_is_active" value="Status" />
                <select data-placeholder="Both" id="filter_is_active" name="filter[is_active]" class="select2 bank-select border-gray-300 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">Both</option>
                    <option value="1" @selected(request('filter.is_active') === '1')>Active</option>
                    <option value="0" @selected(request('filter.is_active') === '0')>Inactive</option>
                </select>
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$categories" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Category', 'align' => 'text-left'],
        ['label' => 'Used for', 'align' => 'text-left'],
        ['label' => 'Default ledger account', 'align' => 'text-left'],
        ['label' => 'Entries', 'align' => 'text-right'],
        ['label' => 'Status', 'align' => 'text-center'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No categories found." :emptyRoute="route('bank-entry-categories.create')" emptyLinkText="Add a category">
        @foreach ($categories as $index => $category)
            <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-gray-50' }}">
                <td class="py-2 px-2 text-center">{{ $categories->firstItem() + $index }}</td>
                <td class="py-2 px-2">{{ $category->name }}</td>
                <td class="py-2 px-2 font-normal">{{ \App\Models\BankEntryCategory::TYPES[$category->type] ?? $category->type }}</td>
                <td class="py-2 px-2 font-normal text-sm">{{ $category->defaultAccount ? $category->defaultAccount->account_code.' — '.$category->defaultAccount->account_name : 'Company receivable / payable' }}</td>
                <td class="py-2 px-2 text-right tabular-nums">{{ number_format($category->entries_count) }}</td>
                <td class="py-2 px-2 text-center">
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $category->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span>
                </td>
                <td class="py-2 px-2 text-center">
                    <div class="flex justify-center gap-3 font-normal">
                        @can('bank-entry-category-edit')
                            <a href="{{ route('bank-entry-categories.edit', $category) }}" class="text-green-700 hover:underline">Edit</a>
                        @endcan
                        @can('bank-entry-category-delete')
                            <form method="POST" action="{{ route('bank-entry-categories.destroy', $category) }}" onsubmit="return confirm('Delete this category?')">
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
