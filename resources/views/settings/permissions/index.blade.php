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
            <x-filter-section :action="route('permissions.index')" method="GET">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <x-label for="search" value="Search" />
                        <x-input id="search" name="filter[name]" type="text" class="mt-1 block w-full"
                            :value="request('filter.name')" placeholder="Search permission name..." />
                    </div>
                </div>
            </x-filter-section>

            <div class="mt-6">
                <x-data-table :items="$permissions" :headers="[
        ['label' => 'Name'],
        ['label' => 'Guard'],
        ['label' => 'Roles Count'],
        ['label' => 'Created At'],
        ['label' => 'Actions', 'align' => 'text-right'],
    ]">
                    @foreach($permissions as $permission)
                        <tr class="border-b border-gray-200 text-sm">
                            <td class="py-1 px-2 font-semibold">{{ $permission->name }}</td>
                            <td class="py-1 px-2">{{ $permission->guard_name }}</td>
                            <td class="py-1 px-2 text-center">{{ $permission->roles->count() }}</td>
                            <td class="py-1 px-2 text-center">{{ optional($permission->created_at)->format('d-m-Y') }}</td>
                            <td class="py-1 px-2 text-right">
                                <a href="{{ route('permissions.edit', $permission) }}" class="text-blue-600">Edit</a>
                            </td>
                        </tr>
                    @endforeach
                </x-data-table>
            </div>
        </div>
    </div>
</x-app-layout>