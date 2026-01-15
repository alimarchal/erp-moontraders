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
            <x-status-message />

            <x-data-table :items="$roles" :headers="[
        ['label' => 'Name'],
        ['label' => 'Guard'],
        ['label' => 'Permissions'],
        ['label' => 'Created At'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]">
                @foreach($roles as $role)
                    <tr class="border-b border-gray-200 text-sm hover:bg-gray-50">
                        <td class="py-1 px-2 font-medium text-gray-900">
                            {{ $role->name }}
                        </td>
                        <td class="py-1 px-2">
                            <span
                                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                {{ $role->guard_name }}
                            </span>
                        </td>
                        <td class="py-1 px-2 text-sm text-gray-600">
                            {{ $role->permissions_count ?? $role->permissions->count() }} permissions
                        </td>
                        <td class="py-1 px-2 text-sm text-gray-600">
                            {{ $role->created_at->format('M d, Y') }}
                        </td>
                        <td class="py-1 px-2 text-center">
                            <div class="flex justify-center space-x-2">
                                @can('role-edit')
                                    <a href="{{ route('roles.edit', $role) }}" class="text-indigo-600 hover:text-indigo-900"
                                        title="Edit">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                        </svg>
                                    </a>
                                @endcan

                                @if($role->name !== 'super-admin')
                                    @can('role-delete')
                                        <form action="{{ route('roles.destroy', $role) }}" method="POST" class="inline"
                                            onsubmit="return confirm('Are you sure you want to delete this role?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900" title="Delete">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                    stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="m14.74 9-.34 6m-4.74 0-.34-6m4.74-3-.34 6m-4.74 0-.34-6M12 21a9 9 0 1 1 0-18 9 9 0 0 1 0 18Zm0 0V9m0 12h-3m3 0h3" />
                                                </svg>
                                            </button>
                                        </form>
                                    @endcan
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        </div>
    </div>
</x-app-layout>