@php
    $permissions = \Spatie\Permission\Models\Permission::all();

    $knownActions = [
        'general-ledger',
        'trial-balance',
        'account-balances',
        'balance-sheet',
        'income-statement',
        'daily-sales',
        'credit-sales',
        'fmr-amr-comparison',
        'settlement',
        'scheme-discount',
        'shop-list',
        'sku-rates',
        'daily-stock-register',
        'salesman-stock-register',
        'inventory-ledger',
        'van-stock-batch',
        'van-stock-ledger',
        'cash-detail',
        'custom-settlement',
        'creditors-ledger',
        'claim-register',
        'advance-tax',
        'percentage-expense',
        'goods-issue',
        'roi',
        'list',
        'create',
        'edit',
        'delete',
        'post',
        'reverse',
        'sync',
        'view',
        'manage',
        'close',
        'open',
        'bulk-update',
        'manage-mapping',
        'update',
        'import',
        'cancel',
    ];

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
        'claim-register' => 'Claim Register',
        'employee-salary' => 'Employee Salaries',
        'employee-salary-transaction' => 'Salary Transactions',
        'product-recall' => 'Product Recalls',
        'report-financial' => 'Financial Reports',
        'report-sales' => 'Sales Reports',
        'report-inventory' => 'Inventory Reports',
        'report-audit' => 'Audit & Operational Reports',
        'setting' => 'Settings',
        'inventory' => 'Inventory Navigation',
    ];

    $rolePermissions = isset($user) ? $user->getPermissionsViaRoles()->pluck('id')->toArray() : [];
    $directPermissions = isset($user) ? $user->getDirectPermissions()->pluck('id')->toArray() : (isset($role) ? $role->permissions->pluck('id')->toArray() : []);
@endphp

<div class="space-y-3">
    <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wider">Permissions
            Selection</h3>
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
                onclick="document.querySelectorAll('[data-perm-group]').forEach(el => { Alpine.$data(el).open = true })"
                class="text-xs text-gray-600 hover:text-gray-800 font-semibold uppercase">Expand All</button>
            <span class="text-gray-300">|</span>
            <button type="button"
                onclick="document.querySelectorAll('[data-perm-group]').forEach(el => { Alpine.$data(el).open = false })"
                class="text-xs text-gray-600 hover:text-gray-800 font-semibold uppercase">Collapse All</button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
        @foreach($groupedPermissions as $group => $groupPerms)
            @php
                $hasChecked = $groupPerms->contains(fn($p) => in_array($p->id, $rolePermissions) || in_array($p->id, $directPermissions));
                $checkedCount = $groupPerms->filter(fn($p) => in_array($p->id, $rolePermissions) || in_array($p->id, $directPermissions))->count();
            @endphp
            <div x-data="{ open: false }" data-perm-group="{{ $group }}"
                class="bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-100 dark:border-gray-600 overflow-hidden">
                <div class="flex items-center justify-between px-3 py-2 cursor-pointer select-none hover:bg-gray-100 dark:hover:bg-gray-600/50 transition-colors"
                    @click="open = !open">
                    <div class="flex items-center gap-2">
                        <svg :class="{ 'rotate-90': open }"
                            class="w-3.5 h-3.5 text-gray-400 transition-transform duration-200" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                        <h4 class="text-xs font-bold text-gray-800 dark:text-gray-200 uppercase tracking-wider">
                            {{ $groupLabels[$group] ?? ucwords(str_replace('-', ' ', $group)) }}
                        </h4>
                        <span
                            class="text-[10px] bg-gray-200 dark:bg-gray-600 text-gray-600 dark:text-gray-300 px-1.5 py-0.5 rounded-full font-semibold">
                            {{ $checkedCount }}/{{ $groupPerms->count() }}
                        </span>
                    </div>
                    <label class="inline-flex items-center cursor-pointer" @click.stop>
                        <input type="checkbox"
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 h-3.5 w-3.5"
                            @change="$el.closest('[data-perm-group]').querySelectorAll('.permission-checkbox:not([disabled])').forEach(c => c.checked = $el.checked)">
                        <span class="ml-1 text-[10px] text-gray-500 uppercase font-semibold">All</span>
                    </label>
                </div>

                <div x-show="open" x-collapse>
                    <div class="px-3 pb-2 pt-1 border-t border-gray-200 dark:border-gray-600">
                        <div class="flex flex-wrap gap-x-4 gap-y-1">
                            @foreach($groupPerms as $permission)
                                @php
                                    $isInherited = in_array($permission->id, $rolePermissions);
                                    $isDirect = in_array($permission->id, $directPermissions);
                                    $actionName = str_replace($group . '-', '', $permission->name);
                                @endphp
                                <label for="perm-{{ $permission->id }}"
                                    class="inline-flex items-center gap-1.5 py-1 cursor-pointer {{ $isInherited ? 'opacity-60' : '' }}">
                                    <input id="perm-{{ $permission->id }}" name="permissions[]" value="{{ $permission->id }}"
                                        type="checkbox"
                                        class="permission-checkbox h-3.5 w-3.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        {{ $isDirect || $isInherited ? 'checked' : '' }} {{ $isInherited ? 'disabled' : '' }}>
                                    <span
                                        class="text-xs font-medium text-gray-700 dark:text-gray-300 capitalize">{{ str_replace('-', ' ', $actionName) }}</span>
                                    @if($isInherited)
                                        <span
                                            class="text-[8px] text-blue-500 font-bold uppercase bg-blue-50 dark:bg-blue-900/30 px-1 py-0.5 rounded">Role</span>
                                    @elseif($isDirect && isset($user))
                                        <span
                                            class="text-[8px] text-green-600 font-bold uppercase bg-green-50 dark:bg-green-900/30 px-1 py-0.5 rounded">Direct</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>