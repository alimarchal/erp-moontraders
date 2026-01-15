@php
    $permissions = \Spatie\Permission\Models\Permission::all();
    $groupedPermissions = $permissions->groupBy(function ($item) {
        $parts = explode('-', $item->name);
        return count($parts) > 1 ? $parts[0] : 'general';
    });

    // For User Edit: Get permissions already granted via roles
    $rolePermissions = isset($user) ? $user->getPermissionsViaRoles()->pluck('id')->toArray() : [];
    $directPermissions = isset($user) ? $user->getDirectPermissions()->pluck('id')->toArray() : (isset($role) ? $role->permissions->pluck('id')->toArray() : []);
@endphp

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 italic underline">Permissions Selection</h3>
        <div class="flex space-x-2">
            <button type="button" @click="$root.querySelectorAll('.permission-checkbox').forEach(c => c.checked = true)"
                class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold uppercase">Select All</button>
            <span class="text-gray-300">|</span>
            <button type="button"
                @click="$root.querySelectorAll('.permission-checkbox').forEach(c => c.checked = false)"
                class="text-xs text-red-600 hover:text-red-800 font-semibold uppercase">Deselect All</button>
        </div>
    </div>

    @foreach($groupedPermissions as $group => $groupPermissions)
        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 border border-gray-100 dark:border-gray-600">
            <div class="flex items-center justify-between mb-3 border-b border-gray-200 dark:border-gray-600 pb-2">
                <h4 class="text-md font-bold text-gray-800 dark:text-gray-200 uppercase tracking-wider">
                    {{ ucfirst($group) }} Management</h4>
                <label class="inline-flex items-center cursor-pointer">
                    <input type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                        @change="$el.closest('.bg-gray-50').querySelectorAll('.permission-checkbox').forEach(c => c.checked = $el.checked)">
                    <span class="ml-2 text-xs text-gray-500 uppercase font-semibold">Toggle Group</span>
                </label>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                @foreach($groupPermissions as $permission)
                    @php
                        $isInherited = in_array($permission->id, $rolePermissions);
                        $isDirect = in_array($permission->id, $directPermissions);
                    @endphp
                    <div class="flex items-start">
                        <div class="flex items-center h-5">
                            <input id="perm-{{ $permission->id }}" name="permissions[]" value="{{ $permission->id }}"
                                type="checkbox"
                                class="permission-checkbox h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 {{ $isInherited ? 'opacity-50' : '' }}"
                                {{ $isDirect || $isInherited ? 'checked' : '' }} {{ $isInherited ? 'disabled' : '' }}>
                        </div>
                        <div class="ml-3 text-sm">
                            <label for="perm-{{ $permission->id }}"
                                class="font-medium text-gray-700 dark:text-gray-300 flex flex-col">
                                <span>{{ str_replace($group . '-', '', $permission->name) }}</span>
                                @if($isInherited)
                                    <span class="text-[10px] text-blue-500 font-bold uppercase">Inherited via Role</span>
                                @elseif($isDirect && isset($user))
                                    <span class="text-[10px] text-green-500 font-bold uppercase">Directly Assigned</span>
                                @endif
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>