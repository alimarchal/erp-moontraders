<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Roles" :createRoute="route('roles.create')" createLabel="Add Role"
            createPermission="role-create" :showSearch="true" :showRefresh="true" backRoute="settings.index" />
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4">
        <x-status-message />

        <x-filter-section :action="route('roles.index')" method="GET">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <x-label for="search" value="Search" />
                    <x-input id="search" name="filter[name]" type="text" class="mt-1 block w-full"
                        :value="request('filter.name')" placeholder="Search role name..." />
                </div>
            </div>
        </x-filter-section>

        <div class="mt-6">
            <x-data-table :items="$roles" :headers="[
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Name'],
        ['label' => 'Guard Name', 'align' => 'text-center'],
        ['label' => 'Permissions', 'align' => 'text-center'],
        ['label' => 'Created At', 'align' => 'text-center'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No roles found." :emptyRoute="route('roles.create')" emptyLinkText="Add a role">
                @foreach ($roles as $index => $role)
                    <tr class="border-b border-gray-200 text-sm hover:bg-gray-50">
                        <td class="py-1 px-2 text-center">
                            {{ $roles->firstItem() + $index }}
                        </td>
                        <td class="py-1 px-2 font-semibold">
                            {{ $role->name }}
                        </td>
                        <td class="py-1 px-2 text-center">
                            <span
                                class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-700">
                                {{ $role->guard_name }}
                            </span>
                        </td>
                        <td class="py-1 px-2 text-center">
                            <span
                                class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-700">
                                {{ $role->permissions_count ?? $role->permissions->count() }} permissions
                            </span>
                        </td>
                        <td class="py-1 px-2 text-center text-gray-600">
                            {{ $role->created_at->format('d-m-Y') }}
                        </td>
                        <td class="py-1 px-2 text-center">
                            <div class="flex justify-center space-x-2">
                                <a href="{{ route('roles.show', $role) }}"
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
                                @can('role-edit')
                                    <a href="{{ route('roles.edit', $role) }}"
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
                                @if ($role->name !== 'Super Admin' && $role->name !== 'super-admin')
                                    <form method="POST" action="{{ route('roles.destroy', $role) }}"
                                        onsubmit="return confirm('Are you sure you want to delete this role?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-md transition-colors duration-150"
                                            title="Delete">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </form>
                                @endif
                                @endrole
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        </div>
    </div>
</x-app-layout>