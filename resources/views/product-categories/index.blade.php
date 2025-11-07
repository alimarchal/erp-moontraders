<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Product Categories" :createRoute="route('product-categories.create')" createLabel="Add Category"
            :showSearch="true" :showRefresh="true" backRoute="settings.index" />
    </x-slot>

    <x-filter-section :action="route('product-categories.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <x-label for="filter_category_name" value="Category Name" />
                <x-input id="filter_category_name" name="filter[category_name]" type="text"
                    class="mt-1 block w-full" :value="request('filter.category_name')" placeholder="Biscuits" />
            </div>

            <div>
                <x-label for="filter_category_code" value="Category Code" />
                <x-input id="filter_category_code" name="filter[category_code]" type="text"
                    class="mt-1 block w-full uppercase" :value="request('filter.category_code')" placeholder="BISCUITS" />
            </div>

            <div>
                <x-label for="filter_parent_id" value="Parent Category" />
                <select id="filter_parent_id" name="filter[parent_id]"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    @foreach ($parentOptions as $parent)
                    <option value="{{ $parent->id }}" {{ request('filter.parent_id') === (string) $parent->id ? 'selected' : '' }}>
                        {{ $parent->category_name }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_description" value="Description" />
                <x-input id="filter_description" name="filter[description]" type="text"
                    class="mt-1 block w-full" :value="request('filter.description')" placeholder="Search description" />
            </div>

            <div>
                <x-label for="filter_is_active" value="Status" />
                <select id="filter_is_active" name="filter[is_active]"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">Both</option>
                    @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}" {{ request('filter.is_active') === $value ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filter-section>

    <x-data-table :items="$categories" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Code', 'align' => 'text-left'],
        ['label' => 'Category', 'align' => 'text-left'],
        ['label' => 'Parent', 'align' => 'text-left'],
        ['label' => 'Default Accounts', 'align' => 'text-left'],
        ['label' => 'Status', 'align' => 'text-center'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No product categories found." :emptyRoute="route('product-categories.create')" emptyLinkText="Add a category">
        @foreach ($categories as $index => $category)
        <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-gray-50' }}">
            <td class="py-2 px-2 text-center">
                {{ $categories->firstItem() + $index }}
            </td>
            <td class="py-2 px-2 font-semibold">
                <div>{{ $category->category_code }}</div>
                <div class="text-xs text-gray-500">ID: {{ $category->id }}</div>
            </td>
            <td class="py-2 px-2">
                <div class="font-semibold">{{ $category->category_name }}</div>
                <div class="text-xs text-gray-500 line-clamp-2">{{ $category->description ?? '—' }}</div>
            </td>
            <td class="py-2 px-2 text-sm">
                {{ $category->parent?->category_name ?? 'Top level' }}
            </td>
            <td class="py-2 px-2 text-xs text-gray-600">
                <div>Inv: {{ $category->defaultInventoryAccount?->account_code ?? '—' }}</div>
                <div>COGS: {{ $category->defaultCogsAccount?->account_code ?? '—' }}</div>
                <div>Revenue: {{ $category->defaultSalesRevenueAccount?->account_code ?? '—' }}</div>
            </td>
            <td class="py-2 px-2 text-center">
                <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full {{ $category->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                    {{ $category->is_active ? 'Active' : 'Inactive' }}
                </span>
            </td>
            <td class="py-2 px-2 text-center">
                <div class="flex justify-center space-x-2">
                    <a href="{{ route('product-categories.show', $category) }}"
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
                    <a href="{{ route('product-categories.edit', $category) }}"
                        class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors duration-150"
                        title="Edit">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </a>
                </div>
            </td>
        </tr>
        @endforeach
    </x-data-table>
</x-app-layout>
