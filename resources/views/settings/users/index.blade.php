<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Settings User Management" :createRoute="route('users.create')" createLabel="Add User"
            :showSearch="true" :showRefresh="true" backRoute="settings.index" />
    </x-slot>

    <x-filter-section :action="route('users.index')">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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
                <x-label for="filter_role" value="Role" />
                <select id="filter_role" name="filter[role]"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">Select a role</option>
                    @foreach (\Spatie\Permission\Models\Role::all() as $role)
                        <option value="{{ $role->name }}" {{ request('filter.role') == $role->name ? 'selected' : '' }}>
                            {{ $role->name }}
                        </option>
                    @endforeach
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
        <x-status-message />

        <form id="bulk-action-form" action="{{ route('users.bulk-update') }}" method="POST">
            @csrf
            <div class="mb-4 flex items-center gap-2">
                <select name="action"
                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"
                    required>
                    <option value="">Bulk Actions</option>
                    <option value="activate">Activate Selected</option>
                    <option value="deactivate">Deactivate Selected</option>
                    <option value="delete">Delete Selected</option>
                </select>
                <x-button type="submit" onclick="return confirm('Apply action to selected users?')">Apply</x-button>
            </div>

            <x-data-table :items="$users" :headers="[
        ['label' => '<input type=\'checkbox\' id=\'select-all\'>', 'align' => 'text-center'],
        ['label' => 'Name'],
        ['label' => 'Designation'],
        ['label' => 'Email', 'align' => 'text-center'],
        ['label' => 'Roles', 'align' => 'text-center'],
        ['label' => 'Status', 'align' => 'text-center'],
        ['label' => 'Actions', 'align' => 'text-center'],
    ]" emptyMessage="No users found."
                :emptyRoute="route('users.create')" emptyLinkText="Add a user">
                @foreach ($users as $index => $user)
                    <tr class="border-b border-gray-200 text-sm hover:bg-gray-50">
                        <td class="py-1 px-2 text-center">
                            @if($user->id !== auth()->id())
                                <input type="checkbox" name="ids[]" value="{{ $user->id }}" class="user-checkbox">
                            @else
                                <span class="text-xs text-gray-400 italic">Self</span>
                            @endif
                        </td>
                        <td class="py-1 px-2 font-semibold">
                            {{ $user->name }}
                            @if($user->is_super_admin === 'Yes')
                                <span class="block text-[10px] text-red-600 font-bold uppercase">Super Admin</span>
                            @endif
                        </td>
                        <td class="py-1 px-2">{{ $user->designation ?? 'N/A' }}</td>
                        <td class="py-1 px-2 text-center">{{ $user->email }}</td>
                        <td class="py-1 px-2 text-center">
                            <div class="flex flex-wrap justify-center gap-1">
                                @foreach($user->roles as $role)
                                    <span
                                        class="bg-blue-100 text-blue-700 text-[10px] px-2 py-0.5 rounded-full border border-blue-200">
                                        {{ $role->name }}
                                    </span>
                                @endforeach
                            </div>
                        </td>
                        <td class="py-1 px-2 text-center">
                            <span @class([
                                'px-2 py-1 rounded-full text-[10px] font-bold uppercase',
                                'bg-emerald-100 text-emerald-700' => $user->is_active === 'Yes',
                                'bg-red-100 text-red-700' => $user->is_active === 'No',
                            ])>
                                {{ $user->is_active === 'Yes' ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="py-1 px-2 text-center">
                            <div class="flex justify-center gap-2">
                                <a href="{{ route('users.edit', $user) }}" class="text-emerald-600 hover:text-emerald-900"
                                    title="Edit">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                    </svg>
                                </a>
                                @if($user->id !== auth()->id())
                                    <form action="{{ route('users.destroy', $user) }}" method="POST" class="inline delete-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900" title="Delete"
                                            onclick="return confirm('Are you sure?')">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="m14.74 9-.34 6m-4.74 0-.34-6m4.74-3-.34 6m-4.74 0-.34-6M12 21a9 9 0 1 1 0-18 9 9 0 0 1 0 18Zm0 0V9m0 12h-3m3 0h3" />
                                            </svg>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        </form>
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