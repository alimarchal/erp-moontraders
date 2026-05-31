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
        'view-own',
        'view-all',
        'revert',
        'carton-entry',
    ];

    $actionOrder = [
        'view',
        'view-own',
        'view-all',
        'list',
        'create',
        'edit',
        'update',
        'delete',
        'post',
        'reverse',
        'revert',
        'import',
        'sync',
        'manage',
        'manage-mapping',
        'open',
        'close',
        'bulk-update',
        'cancel',
        'carton-entry',
    ];

    $actionPriority = array_flip($actionOrder);

    $groupedPermissions = $permissions->groupBy(function ($item) use ($knownActions, $actionPriority) {
        $name = $item->name;

        if (str_starts_with($name, 'report-')) {
            $parts = explode('-', $name);
            $domain = $parts[1] ?? null;
            $tail = array_slice($parts, 2);

            if ($domain !== null && ! empty($tail)) {
                $lastSegment = end($tail);

                if (isset($actionPriority[$lastSegment])) {
                    array_pop($tail);
                }

                if (! empty($tail)) {
                    return 'report-' . $domain . '-' . implode('-', $tail);
                }
            }
        }

        foreach ($knownActions as $action) {
            if (str_ends_with($name, '-' . $action)) {
                return substr($name, 0, strlen($name) - strlen($action) - 1);
            }
        }
        return 'general';
    })->sortKeys();

    $settingsGroups = [
        'user',
        'role',
        'permission',
        'account-type',
        'accounting-period',
        'investment-opening-balance',
        'chart-of-account',
        'currency',
        'cost-center',
        'bank-account',
        'tax',
        'category-revenue',
        'profit-category',
        'company',
        'warehouse',
        'warehouse-type',
        'vehicle',
        'product',
        'category',
        'uom',
        'supplier',
        'employee',
        'customer',
        'setting',
        'inventory',
    ];

    $reportGroupOrder = [
        'report-financial-general-ledger',
        'report-financial-trial-balance',
        'report-financial-account-balances',
        'report-financial-balance-sheet',
        'report-financial-income-statement',
        'report-audit-opening-customer-balance',

        'report-audit-creditors-ledger',
        'report-sales-credit-sales',
        'report-audit-cash-detail',
        'report-audit-investment-summary',
        'report-audit-claim-register',
        'report-audit-ledger-register',
        'report-audit-cheque-register',
        'report-audit-amr-dispose-register',

        'report-sales-fmr-amr-comparison',
        'report-audit-sku-fmr-amr',
        'report-audit-percentage-expense',
        'report-audit-stock-availability',
        'report-sales-shop-list',
        'report-audit-expense-detail',
        'report-audit-revenue-detail',
        'report-audit-profit-after-category',
        'report-audit-supplier-ledger',
        'report-sales-scheme-received',

        'report-inventory-daily-stock-register',
        'report-inventory-salesman-stock-register',
        'report-inventory-van-stock-ledger',
        'report-inventory-van-stock-batch',
        'report-inventory-inventory-ledger',

        'report-sales-goods-issue',
        'report-sales-daily-sales',
        'report-sales-vehicle',
        'report-audit-product-price-change-log',
        'report-sales-tts-summary',

        'report-audit-invoice-summary',
        'report-audit-custom-settlement',
        'report-sales-sku-rates',
        'report-audit-advance-tax',
        'report-audit-advance-tax-sales-register',

        'report-sales-settlement',
        'report-sales-roi',
        'report-sales-summary-roi',
        'report-sales-scheme-discount',
    ];

    $settingsGroupOrder = $settingsGroups;

    $orderedReportGroups = collect($reportGroupOrder)
        ->filter(fn ($group) => $groupedPermissions->has($group))
        ->mapWithKeys(fn ($group) => [$group => $groupedPermissions->get($group)])
        ->merge(
            $groupedPermissions
                ->filter(fn ($groupPerms, $group) => str_starts_with($group, 'report-') && ! in_array($group, $reportGroupOrder))
        );

    $nonReportGroups = $groupedPermissions
        ->filter(fn ($groupPerms, $group) => ! str_starts_with($group, 'report-'));

    $orderedSettingsGroups = collect($settingsGroupOrder)
        ->filter(fn ($group) => $nonReportGroups->has($group))
        ->mapWithKeys(fn ($group) => [$group => $nonReportGroups->get($group)])
        ->merge(
            $nonReportGroups
                ->filter(fn ($groupPerms, $group) => ! in_array($group, $settingsGroupOrder))
        );

    $permissionBuckets = [
        'reports' => $orderedReportGroups,
        'settings' => $orderedSettingsGroups,
    ];

    $bucketLabels = [
        'reports' => 'Reports Permissions',
        'settings' => 'Settings Permissions',
    ];

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
        'report-financial-general-ledger' => 'General Ledger',
        'report-financial-trial-balance' => 'Trial Balance',
        'report-financial-account-balances' => 'Account Balances',
        'report-financial-balance-sheet' => 'Balance Sheet',
        'report-financial-income-statement' => 'Income Statement',
        'report-audit-opening-customer-balance' => 'Opening Customer Balance',
        'report-audit-creditors-ledger' => 'Creditors Ledger',
        'report-sales-credit-sales' => 'Salesman Credit History',
        'report-audit-cash-detail' => 'Cash Collection Detail',
        'report-audit-investment-summary' => 'Investment Summary',
        'report-audit-claim-register' => 'Claim Register',
        'report-audit-ledger-register' => 'Supplier Ledger Register',
        'report-audit-cheque-register' => 'Cheque Register',
        'report-audit-amr-dispose-register' => 'AMR Dispose Register',
        'report-sales-fmr-amr-comparison' => 'FMR vs AMR Comparison',
        'report-audit-sku-fmr-amr' => 'SKU-wise FMR vs AMR',
        'report-audit-percentage-expense' => 'Percentage Summary',
        'report-audit-stock-availability' => 'Stock Availability',
        'report-sales-shop-list' => 'Shop Directory',
        'report-audit-expense-detail' => 'Expense Detail',
        'report-audit-revenue-detail' => 'Revenue Detail',
        'report-audit-profit-after-category' => 'Profit After Category',
        'report-audit-supplier-ledger' => 'Supplier Ledger',
        'report-sales-scheme-received' => 'Scheme Received',
        'report-inventory-daily-stock-register' => 'Daily Stock Register',
        'report-inventory-salesman-stock-register' => 'Salesman Stock Register',
        'report-inventory-van-stock-ledger' => 'Van Stock Ledger',
        'report-inventory-van-stock-batch' => 'Van Stock by Batch',
        'report-inventory-inventory-ledger' => 'Inventory Ledger',
        'report-sales-goods-issue' => 'Goods Issue Report',
        'report-sales-daily-sales' => 'Daily Sales Summary',
        'report-sales-vehicle' => 'Vehicle Report',
        'report-audit-product-price-change-log' => 'Product Price Change Log',
        'report-sales-tts-summary' => 'TTS Summary',
        'report-audit-invoice-summary' => 'Invoice Summary',
        'report-audit-custom-settlement' => 'Custom Settlement',
        'report-sales-sku-rates' => 'SKU & Pricing',
        'report-audit-advance-tax' => 'Advance Tax Report',
        'report-audit-advance-tax-sales-register' => 'Advance Tax Sales Register',
        'report-sales-settlement' => 'Sales Settlement',
        'report-sales-roi' => 'Return on Investment',
        'report-sales-summary-roi' => 'Summary ROI Report',
        'report-sales-scheme-discount' => 'Schemes & Discounts',
        'setting' => 'Settings',
        'inventory' => 'Inventory Navigation',
        'general' => 'Uncategorized Permissions',
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

    @foreach($permissionBuckets as $bucketKey => $bucketGroups)
        @continue($bucketGroups->isEmpty())

        <div class="pt-2">
            <h4 class="text-xs font-bold text-gray-900 dark:text-gray-100 uppercase tracking-wider mb-2">
                {{ $bucketLabels[$bucketKey] }}
            </h4>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($bucketGroups as $group => $groupPerms)
                    @php
                        $sortedGroupPerms = $groupPerms
                            ->sortBy(function ($permission) use ($group, $actionPriority) {
                                $actionName = str_starts_with($permission->name, $group . '-')
                                    ? substr($permission->name, strlen($group) + 1)
                                    : $permission->name;

                                $priority = $actionPriority[$actionName] ?? 999;

                                return sprintf('%03d-%s', $priority, $permission->name);
                            })
                            ->values();

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
                                <div class="grid grid-cols-1 gap-1.5">
                                    @foreach($sortedGroupPerms as $permission)
                                        @php
                                            $isInherited = in_array($permission->id, $rolePermissions);
                                            $isDirect = in_array($permission->id, $directPermissions);
                                            $actionName = str_starts_with($permission->name, $group . '-')
                                                ? substr($permission->name, strlen($group) + 1)
                                                : $permission->name;
                                        @endphp
                                        <label for="perm-{{ $permission->id }}"
                                            class="flex items-start gap-2 py-1 cursor-pointer {{ $isInherited ? 'opacity-60' : '' }}">
                                            <input id="perm-{{ $permission->id }}" name="permissions[]" value="{{ $permission->id }}"
                                                type="checkbox"
                                                class="permission-checkbox mt-0.5 h-3.5 w-3.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                {{ $isDirect || $isInherited ? 'checked' : '' }} {{ $isInherited ? 'disabled' : '' }}>
                                            <span class="min-w-0 flex-1">
                                                <span class="block text-xs font-medium text-gray-700 dark:text-gray-300 capitalize">{{ str_replace('-', ' ', $actionName) }}</span>
                                                <span class="block text-[10px] text-gray-500 dark:text-gray-400 font-mono break-all">{{ $permission->name }}</span>
                                            </span>
                                            <span class="flex items-center gap-1">
                                                @if($isInherited)
                                                    <span
                                                        class="text-[8px] text-blue-500 font-bold uppercase bg-blue-50 dark:bg-blue-900/30 px-1 py-0.5 rounded">Role</span>
                                                @elseif($isDirect && isset($user))
                                                    <span
                                                        class="text-[8px] text-green-600 font-bold uppercase bg-green-50 dark:bg-green-900/30 px-1 py-0.5 rounded">Direct</span>
                                                @endif
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>