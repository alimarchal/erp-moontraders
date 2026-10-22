<x-app-layout>
    <x-slot name="header">
        <x-bank.head title="Bank Entry Categories" :crumbs="['Categories' => null]">
            @can('bank-entry-category-create')
                <a href="{{ route('bank-entry-categories.create') }}" class="ak-btn ak-btn-primary"><span aria-hidden="true">＋</span> Add Category</a>
            @endcan
            <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
        </x-bank.head>
    </x-slot>

    <x-bank.print-styles />
    <div class="ak-page">
        <x-bank.subnav inside />
        <x-bank.print-title title="Bank Entry Categories" />
        <x-status-message />

    <x-bank.filters :action="route('bank-entry-categories.index')">
                    <div class="ak-field">
                <label for="filter_name">Name</label>
                <input id="filter_name" name="filter[name]" type="text" value="{{ request('filter.name') }}">
            </div>
            <div class="ak-field">
                <label for="filter_type">Used for</label>
                <select data-placeholder="All" id="filter_type" name="filter[type]" class="select2 bank-select">
                    <option value="">All</option>
                    @foreach (\App\Models\BankEntryCategory::TYPES as $value => $label)
                        <option value="{{ $value }}" @selected(request('filter.type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ak-field">
                <label for="filter_is_active">Status</label>
                <select data-placeholder="Both" id="filter_is_active" name="filter[is_active]" class="select2 bank-select">
                    <option value="">Both</option>
                    <option value="1" @selected(request('filter.is_active') === '1')>Active</option>
                    <option value="0" @selected(request('filter.is_active') === '0')>Inactive</option>
                </select>
            </div>
</x-bank.filters>

    <x-bank.table :items="$categories" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Category', 'align' => 'text-left'],
        ['label' => 'Used for', 'align' => 'text-left'],
        ['label' => 'Default ledger account', 'align' => 'text-left'],
        ['label' => 'Entries', 'align' => 'text-right'],
        ['label' => 'Status', 'align' => 'text-center'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No categories found." :emptyRoute="route('bank-entry-categories.create')" emptyLinkText="Add a category">
        @foreach ($categories as $index => $category)
            <tr>
                <td class="ak-c">{{ $categories->firstItem() + $index }}</td>
                <td>{{ $category->name }}</td>
                <td>{{ \App\Models\BankEntryCategory::TYPES[$category->type] ?? $category->type }}</td>
                <td class="py-2 px-2 font-normal text-sm">{{ $category->defaultAccount ? $category->defaultAccount->account_code.' — '.$category->defaultAccount->account_name : 'Company receivable / payable' }}</td>
                <td class="py-2 px-2 text-right tabular-nums">{{ number_format($category->entries_count) }}</td>
                <td class="ak-c">
                    <span class="ak-status {{ $category->is_active ? 'ak-status-green' : 'ak-status-red' }}"><i aria-hidden="true"></i>{{ $category->is_active ? 'Active' : 'Inactive' }}</span>
                </td>
                <td class="ak-c">
                    <div class="bk-row" style="justify-content:center">
                        @can('bank-entry-category-edit')
                            <a href="{{ route('bank-entry-categories.edit', $category) }}" class="ak-btn ak-btn-outline ak-btn-sm">Edit</a>
                        @endcan
                        @can('bank-entry-category-delete')
                            <form method="POST" action="{{ route('bank-entry-categories.destroy', $category) }}" onsubmit="return confirm('Delete this category?')">
                                @csrf @method('DELETE')
                                <button class="ak-btn ak-btn-danger-outline ak-btn-sm">Delete</button>
                            </form>
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach
    </x-bank.table>
    </div>
</x-app-layout>
