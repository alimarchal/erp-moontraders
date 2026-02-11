<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Permissions" :createRoute="route('permissions.create')" createLabel="Add Permission"
            createPermission="permission-create" :showSearch="true" :showRefresh="true" backRoute="settings.index" />
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4">
        <x-status-message />

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
        ['label' => '#', 'align' => 'text-center'],
        ['label' => 'Name'],
        ['label' => 'Guard Name', 'align' => 'text-center'],
        ['label' => 'Roles Count', 'align' => 'text-center'],
        ['label' => 'Created At', 'align' => 'text-center'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No permissions found." :emptyRoute="route('permissions.create')"
                emptyLinkText="Add a permission">
                @foreach ($permissions as $index => $permission)
                    <tr class="border-b border-gray-200 text-sm hover:bg-gray-50">
                        <td class="py-1 px-2 text-center text-gray-500">
                            {{ $permissions->firstItem() + $index }}
                        </td>
                        <td class="py-1 px-2 font-semibold">
                            {{ $permission->name }}
                        </td>
                        <td class="py-1 px-2 text-center">
                            <span
                                class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-700">
                                {{ $permission->guard_name }}
                            </span>
                        </td>
                        <td class="py-1 px-2 text-center">
                            <span
                                class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-700">
                                {{ $permission->roles->count() }} roles
                            </span>
                        </td>
                        <td class="py-1 px-2 text-center text-gray-600">
                            {{ optional($permission->created_at)->format('d-m-Y') }}
                        </td>
                        <td class="py-1 px-2 text-center">
                            <div class="flex justify-center space-x-2">
                                @can('permission-edit')
                                    <a href="{{ route('permissions.edit', $permission) }}"
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
                                <form method="POST" action="{{ route('permissions.destroy', $permission) }}"
                                    onsubmit="return confirm('Are you sure you want to delete this permission?');">
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
                                @endrole
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        </div>
    </div>
</x-app-layout>