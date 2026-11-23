<?php

namespace App\Support\Menus;

use App\Models\AccountingPeriod;
use App\Models\AccountType;
use App\Models\BankAccount;
use App\Models\BankEntryCategory;
use App\Models\BankEntryRule;
use App\Models\BankPartyAlias;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\ClaimRegister;
use App\Models\Company;
use App\Models\CompanyTarget;
use App\Models\CostCenter;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerEmployeeAccountTransaction;
use App\Models\Employee;
use App\Models\InvestmentOpeningBalance;
use App\Models\JournalEntry;
use App\Models\ProductRecall;
use App\Models\ProfitCategory;
use App\Models\PromotionalCampaign;
use App\Models\RevenueCategory;
use App\Models\StockAdjustment;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\TaxRate;
use App\Models\Uom;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Models\WarehouseType;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The links of the Settings page in the grouping of issue #196. Screens that are daily work
 * rather than setup sit in the last group, "Day-to-day records", because no other menu links them.
 */
class SettingsMenu extends Menu
{
    /**
     * @param  int  $productCount  Products the user can see (a supplier-scoped user only counts their supplier's).
     */
    public function __construct(private readonly int $productCount) {}

    protected function definitions(): array
    {
        $productCount = $this->productCount;

        return [
            [
                'key' => 'company', 'name' => 'Company & access', 'urdu' => 'کمپنی اور رسائی', 'color' => 'bg-slate-700', 'icon' => 'building',
                'items' => [
                    ['label' => 'Companies', 'description' => 'Legal entities and details', 'url' => route('companies.index'), 'permission' => 'company-list', 'icon' => 'building', 'count' => fn (): int => Company::count(), 'keywords' => 'organization'],
                    ['label' => 'Users', 'description' => 'System users and logins', 'url' => route('users.index'), 'permission' => 'user-list', 'icon' => 'users', 'count' => fn (): int => User::count(), 'keywords' => 'login'],
                    ['label' => 'Roles', 'description' => 'Groups of permissions', 'url' => route('roles.index'), 'permission' => 'role-list', 'icon' => 'shield', 'count' => fn (): int => Role::count(), 'keywords' => 'access levels'],
                    ['label' => 'Permissions', 'description' => 'Fine-grained access control', 'url' => route('permissions.index'), 'permission' => 'permission-list', 'icon' => 'key', 'count' => fn (): int => Permission::count()],
                    ['label' => 'Appearance', 'description' => 'Choose the layout of the dashboard, Settings, Reports and GRNs', 'url' => route('settings.appearance.edit'), 'permission' => 'manage-appearance', 'icon' => 'swatch', 'keywords' => 'layout look'],
                ],
            ],
            [
                'key' => 'products', 'name' => 'Products & pricing', 'urdu' => 'پروڈکٹس اور قیمت', 'color' => 'bg-indigo-500', 'icon' => 'cube',
                'items' => [
                    ['label' => 'Products', 'description' => 'Item master and specifications', 'url' => route('products.index'), 'permission' => 'product-list', 'icon' => 'cube', 'count' => fn (): int => $productCount, 'keywords' => 'product catalog items'],
                    ['label' => 'Categories', 'description' => 'Product groups', 'url' => route('categories.index'), 'permission' => 'category-list', 'icon' => 'tag', 'count' => fn (): int => Category::count(), 'keywords' => 'classifications'],
                    ['label' => 'Units of measure', 'description' => 'KG, PCS, litre', 'url' => route('uoms.index'), 'permission' => 'uom-list', 'icon' => 'scale', 'count' => fn (): int => Uom::count(), 'keywords' => 'uom'],
                    ['label' => 'Promotions', 'description' => 'Campaigns and discount schemes', 'url' => route('promotional-campaigns.index'), 'permission' => 'promotional-campaign-list', 'icon' => 'sparkle', 'count' => fn (): int => PromotionalCampaign::count()],
                ],
            ],
            [
                'key' => 'warehouses', 'name' => 'Warehouses & fleet', 'urdu' => 'گودام اور گاڑیاں', 'color' => 'bg-orange-500', 'icon' => 'store',
                'items' => [
                    ['label' => 'Warehouses', 'description' => 'Storage locations and facilities', 'url' => route('warehouses.index'), 'permission' => 'warehouse-list', 'icon' => 'store', 'count' => fn (): int => Warehouse::count()],
                    ['label' => 'Warehouse types', 'description' => 'Storage classifications', 'url' => route('warehouse-types.index'), 'permission' => 'warehouse-type-list', 'icon' => 'box', 'count' => fn (): int => WarehouseType::count()],
                    ['label' => 'Vehicles', 'description' => 'Vans and fleet details', 'url' => route('vehicles.index'), 'permission' => 'vehicle-list', 'icon' => 'truck', 'count' => fn (): int => Vehicle::count()],
                ],
            ],
            [
                'key' => 'parties', 'name' => 'Customers & suppliers', 'urdu' => 'گاہک اور سپلائرز', 'color' => 'bg-pink-500', 'icon' => 'users',
                'items' => [
                    ['label' => 'Suppliers', 'description' => 'Companies we buy from', 'url' => route('suppliers.index'), 'permission' => 'supplier-list', 'icon' => 'truck', 'count' => fn (): int => Supplier::count(), 'keywords' => 'vendor'],
                    ['label' => 'Customers', 'description' => 'Shops we sell to', 'url' => route('customers.index'), 'permission' => 'customer-list', 'icon' => 'users', 'count' => fn (): int => Customer::count(), 'keywords' => 'client'],
                    ['label' => 'Opening balances', 'description' => 'Customer opening balance entries', 'url' => route('opening-customer-balances.index'), 'permission' => 'opening-customer-balance-list', 'icon' => 'scale', 'count' => fn (): int => CustomerEmployeeAccountTransaction::count()],
                ],
            ],
            [
                'key' => 'staff', 'name' => 'Staff & targets', 'urdu' => 'عملہ اور ٹارگٹ', 'color' => 'bg-lime-600', 'icon' => 'users',
                'items' => [
                    ['label' => 'Employees', 'description' => 'Staff records and pay details', 'url' => route('employees.index'), 'permission' => 'employee-list', 'icon' => 'users', 'count' => fn (): int => Employee::count(), 'keywords' => 'salesman staff'],
                    ['label' => 'Company targets', 'description' => 'Monthly sales targets per salesman', 'url' => route('company-targets.index'), 'permission' => 'company-target-list', 'icon' => 'chart', 'count' => fn (): int => CompanyTarget::count()],
                ],
            ],
            [
                'key' => 'expenses', 'name' => 'Expenses', 'urdu' => 'اخراجات', 'color' => 'bg-rose-500', 'icon' => 'cash',
                'items' => [
                    ['label' => 'Expense categories', 'description' => 'What vouchers are for (toner, grocery, rent)', 'url' => route('expense-categories.index'), 'permission' => 'expense-category-manage', 'icon' => 'tag'],
                    ['label' => 'Shops (payees)', 'description' => 'Shops and parties paid for daily expenses', 'url' => route('expense-vendors.index'), 'permission' => 'expense-vendor-list', 'icon' => 'store', 'keywords' => 'expense vendors'],
                ],
            ],
            [
                'key' => 'accounting', 'name' => 'Accounting', 'urdu' => 'اکاؤنٹنگ', 'color' => 'bg-emerald-600', 'icon' => 'book',
                'items' => [
                    ['label' => 'Chart of accounts', 'description' => 'Every ledger account', 'url' => route('chart-of-accounts.index'), 'permission' => 'chart-of-account-list', 'icon' => 'book', 'count' => fn (): int => ChartOfAccount::count(), 'keywords' => 'coa ledger'],
                    ['label' => 'Account types', 'description' => 'Asset, liability, income…', 'url' => route('account-types.index'), 'permission' => 'account-type-list', 'icon' => 'tag', 'count' => fn (): int => AccountType::count()],
                    ['label' => 'Fiscal periods', 'description' => 'Months and years, open or closed', 'url' => route('accounting-periods.index'), 'permission' => 'accounting-period-list', 'icon' => 'calendar', 'count' => fn (): int => AccountingPeriod::count(), 'keywords' => 'accounting periods'],
                    ['label' => 'Currencies', 'description' => 'Currencies and rates', 'url' => route('currencies.index'), 'permission' => 'currency-list', 'icon' => 'cash', 'count' => fn (): int => Currency::count()],
                    ['label' => 'Cost centers', 'description' => 'Departments and projects', 'url' => route('cost-centers.index'), 'permission' => 'cost-center-list', 'icon' => 'chart', 'count' => fn (): int => CostCenter::count()],
                    ['label' => 'Bank accounts', 'description' => 'Company bank accounts and balances', 'url' => route('bank-accounts.index'), 'permission' => 'bank-account-list', 'icon' => 'bank', 'count' => fn (): int => BankAccount::count()],
                    ['label' => 'Investment balances', 'description' => 'Opening investment per supplier', 'url' => route('investment-opening-balances.index'), 'permission' => 'investment-opening-balance-list', 'icon' => 'trend', 'count' => fn (): int => InvestmentOpeningBalance::count()],
                ],
            ],
            [
                'key' => 'tax', 'name' => 'Tax & profit rules', 'urdu' => 'ٹیکس اور منافع', 'color' => 'bg-red-500', 'icon' => 'percent',
                'items' => [
                    ['label' => 'Tax codes', 'description' => 'Sales tax, advance tax…', 'url' => route('tax-codes.index'), 'permission' => 'tax-list', 'icon' => 'percent', 'count' => fn (): int => TaxCode::count(), 'keywords' => 'vat'],
                    ['label' => 'Tax rates', 'description' => 'Rate per tax code', 'url' => route('tax-rates.index'), 'permission' => 'tax-list', 'icon' => 'percent', 'count' => fn (): int => TaxRate::count()],
                    ['label' => 'Category revenue', 'description' => 'Revenue categories per supplier', 'url' => route('category-revenue.index'), 'permission' => 'category-revenue-list', 'icon' => 'trend', 'count' => fn (): int => RevenueCategory::count()],
                    ['label' => 'Profit categories', 'description' => 'Profit after tax categories per supplier', 'url' => route('profit-categories.index'), 'permission' => 'profit-category-list', 'icon' => 'chart', 'count' => fn (): int => ProfitCategory::count()],
                ],
            ],
            [
                'key' => 'bank', 'name' => 'Bank register', 'urdu' => 'بینک رجسٹر', 'color' => 'bg-sky-500', 'icon' => 'bank',
                'items' => [
                    ['label' => 'Bank categories', 'description' => 'Kinds of receipt and payment, and the account each posts to', 'url' => route('bank-entry-categories.index'), 'permission' => 'bank-entry-category-list', 'icon' => 'tag', 'count' => fn (): int => BankEntryCategory::count()],
                    ['label' => 'Party aliases', 'description' => 'Other spellings of one party on bank statements', 'url' => route('bank-party-aliases.index'), 'permission' => 'bank-party-alias-list', 'icon' => 'users', 'count' => fn (): int => BankPartyAlias::count()],
                    ['label' => 'Auto-classify rules', 'description' => 'Tag bank lines by text in the narration', 'url' => route('bank-entry-rules.index'), 'permission' => 'bank-entry-rule-list', 'icon' => 'sparkle', 'count' => fn (): int => BankEntryRule::count()],
                ],
            ],
            [
                'key' => 'records', 'name' => 'Day-to-day records', 'urdu' => 'روزمرہ کے ریکارڈ', 'color' => 'bg-violet-500', 'icon' => 'doc',
                'items' => [
                    ['label' => 'Journal entries', 'description' => 'Manual GL postings and adjustments', 'url' => route('journal-entries.index'), 'permission' => 'journal-entry-list', 'icon' => 'book', 'count' => fn (): int => JournalEntry::count()],
                    ['label' => 'Stock adjustments', 'description' => 'Damage, theft, expiry and variance', 'url' => route('stock-adjustments.index'), 'permission' => 'stock-adjustment-list', 'icon' => 'box', 'count' => fn (): int => StockAdjustment::count()],
                    ['label' => 'Product recalls', 'description' => 'Supplier recalls and quality issues', 'url' => route('product-recalls.index'), 'permission' => 'product-recall-list', 'icon' => 'arrowout', 'count' => fn (): int => ProductRecall::count()],
                    ['label' => 'Claim register', 'description' => 'Supplier and damage claims log', 'url' => route('claim-registers.index'), 'permission' => 'claim-register-list', 'icon' => 'doc', 'count' => fn (): int => ClaimRegister::count()],
                    ['label' => 'Vendor payments', 'description' => 'Month-end payments to shops for credit vouchers', 'url' => route('expense-vendor-payments.index'), 'permission' => 'expense-vendor-payment-list', 'icon' => 'cash'],
                    ['label' => 'Payroll', 'description' => 'Salaries, allowances, incentives and monthly payslips', 'url' => route('payroll.index'), 'permission' => ['payroll-list', 'payroll-salary-manage', 'payroll-adjustment-manage'], 'icon' => 'users', 'keywords' => 'salary payslip'],
                ],
            ],
        ];
    }
}
