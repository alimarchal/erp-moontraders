<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * Arranges the flat permission list ("user-list", "report-financial-trial-balance" ...)
 * the way the application's own screens are arranged, so an admin finds a
 * permission under the same heading they see in the menu:
 *
 *   Settings › Access & Identity › Users › List / Create / Edit ...
 *   Reports  › Financial Statements › Trial Balance › View report
 *
 * The sections copy the headings on /settings and /reports. Permissions that
 * are not mapped yet still appear (under "Other") so nothing is ever hidden.
 */
class PermissionCatalog
{
    /**
     * Settings screen sections (same headings as settings/index) => permission groups.
     *
     * @var array<string, array<int, string>>
     */
    public const SETTINGS_SECTIONS = [
        'Menu access' => ['setting', 'inventory'],
        'Access & Identity' => ['user', 'role', 'permission', 'company', 'bank-account'],
        'Financial Core' => ['account-type', 'chart-of-account', 'currency', 'accounting-period', 'investment-opening-balance'],
        'Ledger & Costing' => ['accounting', 'journal-entry', 'cost-center'],
        'Tax & Expenses' => ['tax', 'expense-detail', 'category-revenue', 'revenue-detail', 'profit-category', 'profit-after-category', 'scheme-received'],
        'Partners & Stakeholders' => ['supplier', 'customer', 'employee', 'employee-salary', 'employee-salary-transaction', 'opening-customer-balance', 'promotional-campaign'],
        'Inventory & Products' => ['product', 'category', 'uom', 'opening-stock', 'goods-receipt-note'],
        'Inventory Operations' => ['stock-adjustment', 'stock-transfer', 'product-recall', 'supplier-payment', 'warehouse-type', 'claim-register'],
        'Asset Management' => ['vehicle', 'warehouse'],
        'Daily Operations' => ['goods-issue', 'sales-settlement'],
    ];

    /**
     * Reports screen sections (same headings as reports/index) => report permission groups.
     *
     * @var array<string, array<int, string>>
     */
    public const REPORT_SECTIONS = [
        'Financial Statements' => [
            'report-financial-general-ledger', 'report-financial-trial-balance', 'report-financial-account-balances',
            'report-financial-balance-sheet', 'report-financial-income-statement', 'report-audit-opening-customer-balance',
        ],
        'Receivables & Core Reports' => [
            'report-audit-creditors-ledger', 'report-audit-customer-account-statement', 'report-sales-credit-sales',
            'report-audit-cash-detail', 'report-audit-investment-summary', 'report-audit-claim-register',
            'report-audit-ledger-register', 'report-audit-cheque-register', 'report-audit-amr-dispose-register',
        ],
        'Audit & Product Reports' => [
            'report-sales-fmr-amr-comparison', 'report-audit-sku-fmr-amr', 'report-audit-percentage-expense',
            'report-audit-stock-availability', 'report-sales-shop-list', 'report-audit-expense-detail',
            'report-audit-revenue-detail', 'report-audit-profit-after-category', 'report-audit-supplier-ledger',
            'report-sales-scheme-received',
        ],
        'Inventory Management' => [
            'report-inventory-daily-stock-register', 'report-inventory-salesman-stock-register', 'report-inventory-van-stock-ledger',
            'report-inventory-van-stock-batch', 'report-inventory-inventory-ledger',
        ],
        'Distribution & Logistics' => [
            'report-sales-goods-issue', 'report-sales-daily-sales', 'report-sales-vehicle',
            'report-audit-product-price-change-log', 'report-sales-tts-summary',
        ],
        'Supplier Reports' => [
            'report-audit-invoice-summary', 'report-audit-custom-settlement', 'report-sales-sku-rates',
            'report-audit-advance-tax', 'report-audit-advance-tax-sales-register',
        ],
        'Sales & Revenue' => [
            'report-sales-settlement', 'report-sales-roi', 'report-sales-summary-roi', 'report-sales-scheme-discount',
        ],
    ];

    /**
     * Friendly names for permission groups (anything missing is headlined).
     *
     * @var array<string, string>
     */
    public const GROUP_LABELS = [
        'setting' => 'Settings menu',
        'inventory' => 'Inventory menu',
        'user' => 'Users',
        'role' => 'Roles',
        'permission' => 'Permissions',
        'company' => 'Companies',
        'bank-account' => 'Bank Accounts',
        'account-type' => 'Account Types',
        'chart-of-account' => 'Chart of Accounts',
        'currency' => 'Currencies',
        'accounting-period' => 'Accounting Periods',
        'investment-opening-balance' => 'Investment Opening Balance',
        'accounting' => 'Accounting (general)',
        'journal-entry' => 'Journal Entries',
        'cost-center' => 'Cost Centers',
        'tax' => 'Tax Codes, Rates & Mapping',
        'expense-detail' => 'Expense Detail',
        'category-revenue' => 'Category Revenue',
        'revenue-detail' => 'Revenue Detail',
        'profit-category' => 'Profit Categories',
        'profit-after-category' => 'Profit After Category',
        'scheme-received' => 'Scheme Received',
        'supplier' => 'Suppliers',
        'customer' => 'Customers',
        'employee' => 'Employees',
        'employee-salary' => 'Employee Salaries',
        'employee-salary-transaction' => 'Salary Transactions',
        'opening-customer-balance' => 'Opening Customer Balance',
        'promotional-campaign' => 'Promotional Campaigns',
        'product' => 'Products',
        'category' => 'Categories',
        'uom' => 'Units of Measure',
        'opening-stock' => 'Opening Stock',
        'goods-receipt-note' => 'Goods Receipt Notes (GRN)',
        'stock-adjustment' => 'Stock Adjustments',
        'stock-transfer' => 'Stock Transfers',
        'product-recall' => 'Product Recalls',
        'supplier-payment' => 'Supplier Payments',
        'warehouse-type' => 'Warehouse Types',
        'claim-register' => 'Claim Register',
        'vehicle' => 'Vehicles',
        'warehouse' => 'Warehouses',
        'goods-issue' => 'Goods Issues',
        'sales-settlement' => 'Sales Settlements',
        'report-financial-general-ledger' => 'General Ledger',
        'report-financial-trial-balance' => 'Trial Balance',
        'report-financial-account-balances' => 'Account Balances',
        'report-financial-balance-sheet' => 'Balance Sheet',
        'report-financial-income-statement' => 'Income Statement',
        'report-audit-opening-customer-balance' => 'Opening Customer Balance',
        'report-audit-creditors-ledger' => 'Creditors Ledger',
        'report-audit-customer-account-statement' => 'Customer Account Statement',
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
    ];

    /**
     * Action order and wording: [label, what it allows].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const ACTIONS = [
        'view-report' => ['View report', 'Open this report from the Reports page'],
        'view' => ['View', 'Open / see this screen or menu'],
        'list' => ['List', 'Open the list and view records'],
        'view-own' => ['Own records', 'Only records this user created'],
        'view-all' => ['All records', 'Records created by every user'],
        'create' => ['Create', 'Add new records'],
        'edit' => ['Edit', 'Change existing records'],
        'update' => ['Update', 'Save changes'],
        'delete' => ['Delete', 'Remove records'],
        'import' => ['Import', 'Import records from a file'],
        'post' => ['Post', 'Post to the ledger (final)'],
        'reverse' => ['Reverse', 'Reverse a posted entry'],
        'revert' => ['Revert', 'Revert a posted entry'],
        'cancel' => ['Cancel', 'Cancel a record'],
        'close' => ['Close', 'Close a period'],
        'open' => ['Re-open', 'Re-open a closed period'],
        'manage' => ['Manage', 'Add / change entries on this report'],
        'manage-mapping' => ['Manage mapping', 'Map products to tax codes'],
        'set-opening-balance' => ['Opening balance', 'Set opening balances'],
        'carton-entry' => ['Carton entry', 'Enter quantities in cartons'],
        'sync' => ['Sync', 'Sync role permissions'],
        'bulk-update' => ['Bulk update', 'Activate / deactivate / delete many at once'],
    ];

    /**
     * @param  Collection<int, Permission>  $permissions
     * @return array<int, array{key: string, label: string, hint: string, sections: array<int, array{label: string, groups: array<int, array{key: string, label: string, permissions: array<int, array{id: string, name: string, action: string, label: string, help: string}>}>}>}>
     */
    public static function build(Collection $permissions): array
    {
        $groupKeys = collect(self::SETTINGS_SECTIONS)->flatten()
            ->merge(collect(self::REPORT_SECTIONS)->flatten())
            ->sortByDesc(fn (string $key) => strlen($key))
            ->values()
            ->all();

        /** @var array<string, array<int, array{id: string, name: string, action: string, label: string, help: string}>> $grouped */
        $grouped = [];
        foreach ($permissions as $permission) {
            [$group, $action] = self::split($permission->name, $groupKeys);
            [$label, $help] = self::ACTIONS[$action] ?? [Str::headline($action), ''];
            $grouped[$group][] = [
                'id' => (string) $permission->id,
                'name' => $permission->name,
                'action' => $action,
                'label' => $label,
                'help' => $help,
            ];
        }

        $order = array_flip(array_keys(self::ACTIONS));
        foreach ($grouped as &$items) {
            usort($items, fn (array $a, array $b) => [$order[$a['action']] ?? 99, $a['name']] <=> [$order[$b['action']] ?? 99, $b['name']]);
        }
        unset($items);

        $placed = [];
        $sectionsFor = function (array $map) use ($grouped, &$placed): array {
            $sections = [];
            foreach ($map as $sectionLabel => $keys) {
                $groups = [];
                foreach ($keys as $key) {
                    if (isset($grouped[$key]) && ! isset($placed[$key])) {
                        $placed[$key] = true;
                        $groups[] = ['key' => $key, 'label' => self::groupLabel($key), 'permissions' => $grouped[$key]];
                    }
                }
                if ($groups) {
                    $sections[] = ['label' => $sectionLabel, 'groups' => $groups];
                }
            }

            return $sections;
        };

        $settings = $sectionsFor(self::SETTINGS_SECTIONS);
        $reports = $sectionsFor(self::REPORT_SECTIONS);

        // Anything not mapped above (new permissions) is still shown, never lost.
        $leftovers = collect($grouped)->reject(fn ($items, $key) => isset($placed[$key]))->sortKeys();
        $otherReports = $leftovers->filter(fn ($items, $key) => str_starts_with($key, 'report-'));
        $other = $leftovers->reject(fn ($items, $key) => str_starts_with($key, 'report-'));
        $asGroups = fn (Collection $items) => $items->map(fn ($perms, $key) => ['key' => $key, 'label' => self::groupLabel($key), 'permissions' => $perms])->values()->all();

        if ($otherReports->isNotEmpty()) {
            $reports[] = ['label' => 'Other reports', 'groups' => $asGroups($otherReports)];
        }

        $modules = [
            ['key' => 'settings', 'label' => 'Settings & operations', 'hint' => 'Screens under Settings. “List” opens the screen; add Create / Edit / Post only where the user must change data.', 'sections' => $settings],
            ['key' => 'reports', 'label' => 'Reports', 'hint' => 'One permission per report. The Reports menu appears automatically when at least one report is granted.', 'sections' => $reports],
        ];

        if ($other->isNotEmpty()) {
            $modules[] = ['key' => 'other', 'label' => 'Other', 'hint' => 'Permissions not yet placed on a screen.', 'sections' => [['label' => 'Other', 'groups' => $asGroups($other)]]];
        }

        return array_values(array_filter($modules, fn (array $module) => $module['sections'] !== []));
    }

    /**
     * Split a permission name into [group, action].
     *
     * @param  array<int, string>  $groupKeys  known groups, longest first
     * @return array{0: string, 1: string}
     */
    public static function split(string $name, array $groupKeys): array
    {
        foreach ($groupKeys as $key) {
            if ($name === $key) {
                return [$key, str_starts_with($key, 'report-') ? 'view-report' : 'view'];
            }
            if (str_starts_with($name, $key.'-')) {
                return [$key, substr($name, strlen($key) + 1)];
            }
        }

        // Unknown: peel a known action off the end ("widget-create" -> widget / create).
        foreach (array_keys(self::ACTIONS) as $action) {
            if (str_ends_with($name, '-'.$action) && strlen($name) > strlen($action) + 1) {
                return [substr($name, 0, -strlen($action) - 1), $action];
            }
        }

        return str_starts_with($name, 'report-') ? [$name, 'view-report'] : ['general', $name];
    }

    public static function groupLabel(string $key): string
    {
        return self::GROUP_LABELS[$key] ?? Str::headline(preg_replace('/^report-(financial|audit|sales|inventory)-/', '', $key));
    }
}
