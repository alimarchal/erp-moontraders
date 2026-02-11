<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Users" :createRoute="route('users.create')" createLabel="Add User"
            createPermission="user-create" :showSearch="true" :showRefresh="true" backRoute="settings.index" />
    </x-slot>

    <x-filter-section :action="route('users.index')">
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
            <div>
                <x-label for="filter_name" value="Name" />
                <x-input id="filter_name" name="filter[name]" type="text" class="mt-1 block w-full"
                    :value="request('filter.name')" placeholder="Search by name..." />
            </div>

            <div>
                <x-label for="filter_email" value="Email" />
                <x-input id="filter_email" name="filter[email]" type="text" class="mt-1 block w-full"
                    :value="request('filter.email')" placeholder="Search by email..." />
            </div>

            <div>
                <x-label for="filter_designation" value="Designation" />
                <x-input id="filter_designation" name="filter[designation]" type="text" class="mt-1 block w-full"
                    :value="request('filter.designation')" placeholder="Search by designation..." />
            </div>

            <div>
                <x-label for="filter_role" value="Role" />
                <select id="filter_role" name="filter[role]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Roles</option>
                    @foreach (\Spatie\Permission\Models\Role::all() as $role)
                        <option value="{{ $role->name }}" {{ request('filter.role') == $role->name ? 'selected' : '' }}>
                            {{ $role->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-label for="filter_is_super_admin" value="Super Admin" />
                <select id="filter_is_super_admin" name="filter[is_super_admin]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All</option>
                    <option value="Yes" {{ request('filter.is_super_admin') == 'Yes' ? 'selected' : '' }}>Yes</option>
                    <option value="No" {{ request('filter.is_super_admin') == 'No' ? 'selected' : '' }}>No</option>
                </select>
            </div>

            <div>
                <x-label for="filter_is_active" value="Status" />
                <select id="filter_is_active" name="filter[is_active]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">All Status</option>
                    <option value="Yes" {{ request('filter.is_active') == 'Yes' ? 'selected' : '' }}>Active</option>
                    <option value="No" {{ request('filter.is_active') == 'No' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
        </div>
    </x-filter-section>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4">
        @role('super-admin')
        <form id="bulk-action-form" action="{{ route('users.bulk-update') }}" method="POST">
            @csrf
            <div class="mb-4 flex items-center gap-2">
                <select name="action"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"
                    required>
                    <option value="">Bulk Actions</option>
                    <option value="activate">Activate Selected</option>
                    <option value="deactivate">Deactivate Selected</option>
                    @role('super-admin')
                    <option value="delete">Delete Selected</option>
                    @endrole
                </select>
                <x-button type="submit" onclick="return confirm('Apply action to selected users?')">Apply</x-button>
            </div>
            @endrole

            <x-data-table :items="$users" :headers="[
        ['label' => '<input type=\'checkbox\' id=\'select-all\' class=\'rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500\'>', 'align' => 'text-center'],
        ['label' => 'User'],
        ['label' => 'Designation'],
        ['label' => 'Email', 'align' => 'text-center'],
        ['label' => 'Roles', 'align' => 'text-center'],
        ['label' => 'Permissions', 'align' => 'text-center'],
        ['label' => 'Status', 'align' => 'text-center'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No users found." :emptyRoute="route('users.create')"
                emptyLinkText="Add a user">
                @foreach ($users as $index => $user)
                    <tr class="border-b border-gray-200 text-sm hover:bg-gray-50">
                        <td class="py-1 px-2 text-center">
                            @if($user->id !== auth()->id())
                                <input type="checkbox" name="ids[]" value="{{ $user->id }}"
                                    class="user-checkbox rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            @else
                                <span class="text-[10px] text-gray-400 font-medium italic">Self</span>
                            @endif
                        </td>
                        <td class="py-1 px-2">
                            <div class="font-semibold">{{ $user->name }}</div>
                            @if($user->is_super_admin === 'Yes')
                                <div class="text-[9px] text-red-600 font-bold uppercase tracking-tighter">Super Admin</div>
                            @endif
                        </td>
                        <td class="py-1 px-2 text-gray-600 font-medium">{{ $user->designation ?? 'N/A' }}</td>
                        <td class="py-1 px-2 text-center">{{ $user->email }}</td>
                        <td class="py-1 px-2 text-center">
                            <div class="flex flex-wrap justify-center gap-1">
                                @forelse($user->roles as $role)
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 text-[10px] font-semibold rounded-full bg-blue-100 text-blue-700">
                                        {{ $role->name }}
                                    </span>
                                @empty
                                    <span class="text-xs text-gray-400 italic">No Roles</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="py-1 px-2 text-center">
                            @php
                                $allPerms = $user->getAllPermissions();
                            @endphp
                            @if($allPerms->count() > 0)
                                <span
                                    class="inline-flex items-center px-2 py-0.5 text-[10px] font-semibold rounded-full bg-purple-100 text-purple-700">
                                    {{ $allPerms->first()->name }}
                                </span>
                                @if($allPerms->count() > 1)
                                    <span class="text-[10px] text-gray-500 font-medium"
                                        title="{{ $allPerms->pluck('name')->join(', ') }}">
                                        +{{ $allPerms->count() - 1 }} more
                                    </span>
                                @endif
                            @else
                                <span class="text-xs text-gray-400 italic">None</span>
                            @endif
                        </td>
                        <td class="py-1 px-2 text-center">
                            <span @class([
                                'inline-flex items-center px-2 py-1 text-[10px] font-bold uppercase rounded-full',
                                'bg-emerald-100 text-emerald-700' => $user->is_active === 'Yes',
                                'bg-red-100 text-red-700' => $user->is_active === 'No',
                            ])>
                                {{ $user->is_active === 'Yes' ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="py-1 px-2 text-center">
                            <div class="flex justify-center space-x-2">
                                @can('user-edit')
                                    <a href="{{ route('users.edit', $user) }}"
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
                                @if($user->id !== auth()->id())
                                    <form method="POST" action="{{ route('users.destroy', $user) }}"
                                        onsubmit="return confirm('Are you sure you want to delete this user?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-md transition-colors duration-150"
                                            title="Delete">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
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

            @role('super-admin')
        </form>
        @endrole
    </div>

    @push('scripts')
        <script>
            document.getElementById('select-all').addEventListener('change', function () {
                const checkboxes = document.querySelectorAll('.user-checkbox');
                checkboxes.forEach(cb => cb.checked = this.checked);
            });
        </script>
    @endpush
</x-app-layout>