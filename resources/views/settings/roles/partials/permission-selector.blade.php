@php
    $permissions = \Spatie\Permission\Models\Permission::all();

    $knownActions = ['list', 'create', 'edit', 'delete', 'post', 'reverse', 'sync', 'view', 'manage', 'close', 'open', 'bulk-update', 'manage-mapping', 'view-financial', 'view-inventory', 'view-sales', 'view-audit', 'update'];

    $groupedPermissions = $permissions->groupBy(function ($item) use ($knownActions) {
        $name = $item->name;
        foreach ($knownActions as $action) {
            if (str_ends_with($name, '-' . $action)) {
                return substr($name, 0, strlen($name) - strlen($action) - 1);
            }
        }
        return 'general';
    })->sortKeys();

    $groupLabels = [
        'user' => 'User Management',
        'role' => 'Role Management',
        'permission' => 'Permission Management',
        'accounting' => 'Accounting',
        'accounting-period' => 'Accounting Periods',
        'chart-of-account' => 'Chart of Accounts',
        'journal-entry' => 'Journal Entries',
        'account-type' => 'Account Types',
        'currency' => 'Currencies',
        'cost-center' => 'Cost Centers',
        'bank-account' => 'Bank Accounts',
        'tax' => 'Tax Configuration',
        'company' => 'Companies',
        'supplier' => 'Suppliers',
        'customer' => 'Customers',
        'employee' => 'Employees',
        'goods-receipt-note' => 'Goods Receipt Notes',
        'goods-issue' => 'Goods Issues',
        'stock-transfer' => 'Stock Transfers',
        'stock-adjustment' => 'Stock Adjustments',
        'warehouse' => 'Warehouses',
        'warehouse-type' => 'Warehouse Types',
        'product' => 'Products',
        'category' => 'Categories',
        'uom' => 'Units of Measure',
        'sales-settlement' => 'Sales Settlements',
        'supplier-payment' => 'Supplier Payments',
        'vehicle' => 'Vehicles',
        'promotional-campaign' => 'Promotional Campaigns',
        'report' => 'Reports',
        'setting' => 'Settings',
    ];

    $rolePermissions = isset($user) ? $user->getPermissionsViaRoles()->pluck('id')->toArray() : [];
    $directPermissions = isset($user) ? $user->getDirectPermissions()->pluck('id')->toArray() : (isset($role) ? $role->permissions->pluck('id')->toArray() : []);
@endphp

<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 italic underline">Permissions Selection</h3>
        <div class="flex gap-2">
            <button type="button"
                onclick="document.querySelectorAll('.permission-checkbox:not([disabled])').forEach(c => c.checked = true)"
                class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold uppercase">Select All</button>
            <span class="text-gray-300">|</span>
            <button type="button"
                onclick="document.querySelectorAll('.permission-checkbox:not([disabled])').forEach(c => c.checked = false)"
                class="text-xs text-red-600 hover:text-red-800 font-semibold uppercase">Deselect All</button>
            <span class="text-gray-300">|</span>
            <button type="button"
                onclick="document.querySelectorAll('[data-perm-group]').forEach(el => { el.__x && el.__x.$data && (el.__x.$data.open = true) })"
                class="text-xs text-gray-600 hover:text-gray-800 font-semibold uppercase">Expand All</button>
            <span class="text-gray-300">|</span>
            <button type="button"
                onclick="document.querySelectorAll('[data-perm-group]').forEach(el => { el.__x && el.__x.$data && (el.__x.$data.open = false) })"
                class="text-xs text-gray-600 hover:text-gray-800 font-semibold uppercase">Collapse All</button>
        </div>
    </div>

    @foreach($groupedPermissions as $group => $groupPerms)
        <div x-data="{ open: {{ (isset($user) || isset($role)) ? 'true' : 'false' }} }" data-perm-group="{{ $group }}"
            class="bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-100 dark:border-gray-600 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2.5 cursor-pointer select-none hover:bg-gray-100 dark:hover:bg-gray-600/50 transition-colors"
                @click="open = !open">
                <div class="flex items-center gap-3">
                    <svg :class="{ 'rotate-90': open }" class="w-4 h-4 text-gray-500 transition-transform duration-200"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    <h4 class="text-sm font-bold text-gray-800 dark:text-gray-200 uppercase tracking-wider">
                        {{ $groupLabels[$group] ?? ucwords(str_replace('-', ' ', $group)) }}
                    </h4>
                    <span
                        class="text-[10px] bg-gray-200 dark:bg-gray-600 text-gray-600 dark:text-gray-300 px-1.5 py-0.5 rounded-full font-semibold">
                        {{ $groupPerms->count() }}
                    </span>
                </div>
                <label class="inline-flex items-center cursor-pointer" @click.stop>
                    <input type="checkbox"
                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 h-4 w-4"
                        @change="$el.closest('[data-perm-group]').querySelectorAll('.permission-checkbox:not([disabled])').forEach(c => c.checked = $el.checked)">
                    <span class="ml-1.5 text-[10px] text-gray-500 uppercase font-semibold">Toggle</span>
                </label>
            </div>

            <div x-show="open" x-collapse>
                <div class="px-4 pb-3 pt-1 border-t border-gray-200 dark:border-gray-600">
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-4 gap-2">
                        @foreach($groupPerms as $permission)
                            @php
                                $isInherited = in_array($permission->id, $rolePermissions);
                                $isDirect = in_array($permission->id, $directPermissions);
                                $actionName = str_replace($group . '-', '', $permission->name);
                            @endphp
                            <label for="perm-{{ $permission->id }}"
                                class="flex items-center gap-2 px-2 py-1.5 rounded-md cursor-pointer hover:bg-white dark:hover:bg-gray-600 transition-colors {{ $isInherited ? 'opacity-60' : '' }}">
                                <input id="perm-{{ $permission->id }}" name="permissions[]" value="{{ $permission->id }}"
                                    type="checkbox"
                                    class="permission-checkbox h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    {{ $isDirect || $isInherited ? 'checked' : '' }} {{ $isInherited ? 'disabled' : '' }}>
                                <div class="text-sm leading-tight">
                                    <span
                                        class="font-medium text-gray-700 dark:text-gray-300 capitalize">{{ str_replace('-', ' ', $actionName) }}</span>
                                    @if($isInherited)
                                        <span class="block text-[9px] text-blue-500 font-bold uppercase">Via Role</span>
                                    @elseif($isDirect && isset($user))
                                        <span class="block text-[9px] text-green-500 font-bold uppercase">Direct</span>
                                    @endif
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>