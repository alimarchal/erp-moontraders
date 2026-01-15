<x-app-layout title="Permissions Management">
    <x-page-header title="Permissions" :breadcrumbs="[
        ['label' => 'Settings', 'url' => '#'],
        ['label' => 'Permissions', 'url' => route('permissions.index')],
    ]">
        @can('permission-create')
            <a href="{{ route('permissions.create') }}"
                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Create Permission
            </a>
        @endcan
    </x-page-header>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-filter-section>
                <form action="{{ route('permissions.index') }}" method="GET"
                    class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <x-label for="search" value="Search" />
                        <x-input id="search" name="filter[name]" type="text" class="mt-1 block w-full"
                            :value="request('filter.name')" placeholder="Search permission name..." />
                    </div>
                    <div class="flex items-end space-x-2">
                        <x-button type="submit">Filter</x-button>
                        @if(request()->has('filter'))
                            <a href="{{ route('permissions.index') }}"
                                class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300 focus:bg-gray-300 active:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">Reset</a>
                        @endif
                    </div>
                </form>
            </x-filter-section>

            <div class="mt-6">
                <x-data-table :entries="$permissions">
                    <x-slot name="header">
                        <x-data-table.header>Name</x-data-table.header>
                        <x-data-table.header>Guard</x-data-table.header>
                        <x-data-table.header>Roles Count</x-data-table.header>
                        <x-data-table.header>Created At</x-data-table.header>
                        <x-data-table.header align="right">Actions</x-data-table.header>
                    </x-slot>

                    <x-slot name="body">
                        @forelse($permissions as $permission)
                            <x-data-table.row>
                                <x-data-table.cell>
                                    <div class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ $permission->name }}
                                    </div>
                                </x-data-table.cell>
                                <x-data-table.cell>
                                    <span
                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                        {{ $permission->guard_name }}
                                    </span>
                                </x-data-table.cell>
                                <x-data-table.cell>
                                    <span class="text-sm text-gray-600 dark:text-gray-400">
                                        Used in {{ $permission->roles->count() }} roles
                                    </span>
                                </x-data-table.cell>
                                <x-data-table.cell>
                                    <span class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ $permission->created_at->format('M d, Y') }}
                                    </span>
                                </x-data-table.cell>
                                <x-data-table.cell align="right">
                                    <div class="flex justify-end space-x-2">
                                        @can('permission-edit')
                                            <a href="{{ route('permissions.edit', $permission) }}"
                                                class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                                Edit
                                            </a>
                                        @endcan

                                        @can('permission-delete')
                                            <form action="{{ route('permissions.destroy', $permission) }}" method="POST"
                                                class="inline"
                                                onsubmit="return confirm('Are you sure you want to delete this permission? This might break access control!');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                    Delete
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </x-data-table.cell>
                            </x-data-table.row>
                        @empty
                            <x-data-table.row>
                                <x-data-table.cell colspan="5" class="text-center py-4 text-gray-500">
                                    No permissions found.
                                </x-data-table.cell>
                            </x-data-table.row>
                        @endforelse
                    </x-slot>
                </x-data-table>

                <div class="mt-4">
                    {{ $permissions->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>