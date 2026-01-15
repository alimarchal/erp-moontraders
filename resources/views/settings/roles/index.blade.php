<x-app-layout title="Roles Management">
    <x-page-header title="Roles" :breadcrumbs="[
        ['label' => 'Settings', 'url' => '#'],
        ['label' => 'Roles', 'url' => route('roles.index')],
    ]">
        @can('role-create')
            <a href="{{ route('roles.create') }}"
                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Create Role
            </a>
        @endcan
    </x-page-header>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-data-table :entries="$roles">
                <x-slot name="header">
                    <x-data-table.header>Name</x-data-table.header>
                    <x-data-table.header>Guard</x-data-table.header>
                    <x-data-table.header>Permissions</x-data-table.header>
                    <x-data-table.header>Created At</x-data-table.header>
                    <x-data-table.header align="right">Actions</x-data-table.header>
                </x-slot>

                <x-slot name="body">
                    @forelse($roles as $role)
                        <x-data-table.row>
                            <x-data-table.cell>
                                <div class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ $role->name }}
                                </div>
                            </x-data-table.cell>
                            <x-data-table.cell>
                                <span
                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                    {{ $role->guard_name }}
                                </span>
                            </x-data-table.cell>
                            <x-data-table.cell>
                                <span class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ $role->permissions_count ?? $role->permissions->count() }} permissions
                                </span>
                            </x-data-table.cell>
                            <x-data-table.cell>
                                <span class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ $role->created_at->format('M d, Y') }}
                                </span>
                            </x-data-table.cell>
                            <x-data-table.cell align="right">
                                <div class="flex justify-end space-x-2">
                                    @can('role-edit')
                                        <a href="{{ route('roles.edit', $role) }}"
                                            class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                            Edit
                                        </a>
                                    @endcan

                                    @if($role->name !== 'super-admin')
                                        @can('role-delete')
                                            <form action="{{ route('roles.destroy', $role) }}" method="POST" class="inline"
                                                onsubmit="return confirm('Are you sure you want to delete this role?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                    Delete
                                                </button>
                                            </form>
                                        @endcan
                                    @endif
                                </div>
                            </x-data-table.cell>
                        </x-data-table.row>
                    @empty
                        <x-data-table.row>
                            <x-data-table.cell colspan="5" class="text-center py-4 text-gray-500">
                                No roles found.
                            </x-data-table.cell>
                        </x-data-table.row>
                    @endforelse
                </x-slot>
            </x-data-table>

            <div class="mt-4">
                {{ $roles->links() }}
            </div>
        </div>
    </div>
</x-app-layout>