<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Categories" :createRoute="route('categories.create')" createLabel="Add Category"
            :showSearch="true" backRoute="settings.index" />
    </x-slot>

    <x-filter-section :action="route('categories.index')">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <x-label for="filter_name" value="Name" />
                <x-input id="filter_name" type="text" name="filter[name]" class="block mt-1 w-full"
                    value="{{ request('filter.name') }}" />
            </div>

            <div>
                <x-label for="filter_is_active" value="Status" />
                <select id="filter_is_active" name="filter[is_active]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">Both</option>
                    <option value="1" {{ request('filter.is_active') === '1' ? 'selected' : '' }}>Active</option>
                    <option value="0" {{ request('filter.is_active') === '0' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
        </div>
    </x-filter-section>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <x-data-table :headers="[
        ['label' => 'Name', 'align' => 'text-left'],
        ['label' => 'Status', 'align' => 'text-center'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" :items="$categories">
                    @foreach ($categories as $category)
                        <tr class="hover:bg-gray-50 transition-colors duration-200">
                            <td class="py-2 px-2">
                                <div class="font-semibold">{{ $category->name }}</div>
                                <div class="text-xs text-gray-500">{{ $category->slug }}</div>
                            </td>
                            <td class="py-2 px-2 text-center">
                                <span
                                    class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full {{ $category->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                    {{ $category->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="py-2 px-2 text-center">
                                <div class="flex justify-center space-x-2">
                                    <a href="{{ route('categories.edit', $category) }}"
                                        class="text-indigo-600 hover:text-indigo-900" title="Edit">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    @can('category-delete')
                                        <form action="{{ route('categories.destroy', $category) }}" method="POST"
                                            onsubmit="return confirm('Are you sure you want to delete this category?');"
                                            class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900" title="Delete">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-data-table>
            </div>
        </div>
    </div>
</x-app-layout>