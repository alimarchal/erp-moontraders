<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Profit Categories" :createRoute="route('profit-categories.create')" createLabel="Add Profit Category"
            createPermission="profit-category-create" :showSearch="true" backRoute="settings.index" />
    </x-slot>

    <x-filter-section :action="route('profit-categories.index')">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <x-label for="supplier_id" value="Supplier" />
                <select id="supplier_id" name="supplier_id"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Suppliers</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ (string) $supplierId === (string) $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->supplier_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-label for="name" value="Name" />
                <x-input id="name" type="text" name="name" class="block mt-1 w-full" value="{{ request('name') }}" />
            </div>
            <div>
                <x-label for="is_active" value="Status" />
                <select id="is_active" name="is_active"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">Both</option>
                    <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Active</option>
                    <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
        </div>
    </x-filter-section>

    <x-data-table :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Name', 'align' => 'text-left'],
        ['label' => 'Supplier', 'align' => 'text-left'],
        ['label' => 'Status', 'align' => 'text-center'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" :items="$categories" emptyMessage="No profit categories found." :emptyRoute="route('profit-categories.create')"
        emptyLinkText="Add Profit Category">
        @foreach ($categories as $index => $category)
            <tr class="border-b border-gray-200 text-sm hover:bg-gray-50 transition-colors duration-150">
                <td class="py-1 px-2 text-center">{{ $categories->firstItem() + $index }}</td>
                <td class="py-1 px-2 font-semibold">
                    {{ $category->name }}
                    <div class="text-xs text-gray-500 font-normal">{{ $category->slug }}</div>
                </td>
                <td class="py-1 px-2">{{ $category->supplier->supplier_name ?? 'All Suppliers' }}</td>
                <td class="py-1 px-2 text-center">
                    <span
                        class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full {{ $category->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                        {{ $category->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </td>
                <td class="py-1 px-2 text-center">
                    <div class="flex justify-center space-x-2">
                        @can('profit-category-edit')
                            <a href="{{ route('profit-categories.edit', $category) }}"
                                class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors duration-150"
                                title="Edit">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </a>
                        @endcan
                        @can('profit-category-delete')
                            <form action="{{ route('profit-categories.destroy', $category) }}" method="POST"
                                onsubmit="return confirm('Are you sure you want to delete this profit category?');" class="inline">
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
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-app-layout>
