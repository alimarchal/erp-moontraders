<?php

namespace App\Support\Menus;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * The links of the Reports page in the grouping of issue #197. Screens that are not reports
 * (expense vouchers, shops, payroll…) sit in the last group, "Expenses & payroll", because no
 * other menu links them.
 */
class ReportsMenu extends Menu
{
    /**
     * Any one of these opens the Bank Register Reports hub.
     *
     * @var list<string>
     */
    public const BANK_REPORT_PERMISSIONS = [
        'report-bank-audit-log',
        'report-bank-entries',
        'report-bank-live-gap',
        'report-bank-reconciliation-statement',
        'report-bank-summary',
        'report-bank-unlinked-scans',
    ];

    /**
     * The categories of the Apple layout (Reports, Option C): the same reports regrouped by the job they are for,
     * so a manager starts the day in one place and every customer or stock question has one home. Each report id
     * (the slug of its label) sits in exactly one category; a report missing here still shows, under "More".
     *
     * @var list<array{key: string, name: string, blurb: string, color: string, icon: string, ids: list<string>}>
     */
    public const APPLE_CATEGORIES = [
        ['key' => 'daily', 'name' => 'Daily overview', 'blurb' => 'Start the day here: the summary, sales, cash and stock of one day.', 'color' => '#0a84ff', 'icon' => 'sparkle',
            'ids' => ['daily-business-summary', 'daily-sales-summary', 'investment-summary', 'cash-collection-distributor', 'daily-stock-register']],
        ['key' => 'sales', 'name' => 'Sales', 'blurb' => 'What was sold, by product, salesman and settlement, and against target.', 'color' => '#30a46c', 'icon' => 'trend',
            'ids' => ['sku-wise-sales', 'sales-by-salesman', 'sales-settlement', 'company-target-summary', 'goods-issue-report', 'salesman-credit-history']],
        ['key' => 'customers', 'name' => 'Customers & credit', 'blurb' => 'Who owes what, for how long, and every entry of a customer account.', 'color' => '#ff375f', 'icon' => 'users',
            'ids' => ['customer-balances', 'customer-account-statement', 'debtors-ledger', 'credit-aging', 'salesman-wise-debtors', 'opening-customer-balance', 'shop-directory']],
        ['key' => 'cash', 'name' => 'Cash & bank', 'blurb' => 'Cash counted, cheques and the bank register.', 'color' => '#34c759', 'icon' => 'bank',
            'ids' => ['cash-collection-detail', 'cheque-register', 'bank-register-reports']],
        ['key' => 'stock', 'name' => 'Stock & vans', 'blurb' => 'Stock in the warehouse and on each van, movements, expiry and vehicles.', 'color' => '#ff9f0a', 'icon' => 'box',
            'ids' => ['stock-availability', 'inventory-ledger', 'salesman-stock-register', 'van-stock-status', 'van-stock-ledger', 'van-stock-by-batch', 'fmr-vs-amr', 'sku-wise-fmr-vs-amr', 'amr-dispose-register', 'vehicle-report']],
        ['key' => 'suppliers', 'name' => 'Suppliers & claims', 'blurb' => 'Supplier ledgers, invoices, claims and price lists.', 'color' => '#bf5af2', 'icon' => 'truck',
            'ids' => ['supplier-ledger', 'supplier-ledger-register', 'invoice-summary', 'custom-settlement', 'claim-register', 'sku-pricing', 'product-price-change-log']],
        ['key' => 'schemes', 'name' => 'Schemes & trade offers', 'blurb' => 'TTS, promotions and discounts received and passed on.', 'color' => '#ff6482', 'icon' => 'tag',
            'ids' => ['schemes-discounts', 'scheme-received', 'tts-summary', 'daily-sales-trade-offer-dashboard']],
        ['key' => 'profit', 'name' => 'Profit & expenses', 'blurb' => 'Return on investment, revenue, expenses and their share.', 'color' => '#00c7be', 'icon' => 'chart',
            'ids' => ['return-on-investment', 'summary-roi', 'profit-after-category', 'revenue-detail', 'expense-detail', 'percentage-summary']],
        ['key' => 'accounts', 'name' => 'Accounts & tax', 'blurb' => 'Ledgers, trial balance, statements and advance tax.', 'color' => '#5e5ce6', 'icon' => 'book',
            'ids' => ['general-ledger', 'trial-balance', 'account-balances', 'balance-sheet', 'income-statement', 'advance-tax-report', 'advance-tax-sales-register', 'advance-tax-customer-summary']],
        ['key' => 'expenses', 'name' => 'Expenses & payroll', 'blurb' => 'Expense vouchers, shops paid for expenses, and salaries.', 'color' => '#8e8e93', 'icon' => 'cash',
            'ids' => ['expense-vouchers', 'expense-categories', 'shops', 'vendor-payments', 'incentives-adjustments', 'payroll']],
    ];

    /**
     * The user's reports in the Apple layout's categories. Empty categories are dropped; a report no category
     * lists lands in a last "More" category so a new report never disappears from the page.
     *
     * @return list<array{key: string, name: string, blurb: string, color: string, icon: string, items: list<array{id: string, label: string, description: string, url: string, icon: string, count: int|null, search: string}>}>
     */
    public function appleCategoriesFor(User $user): array
    {
        $items = collect($this->groupsFor($user))->flatMap(fn (array $group) => $group['items'])->keyBy('id');
        $placed = [];
        $categories = [];

        foreach ([...self::APPLE_CATEGORIES, ['key' => 'more', 'name' => 'More', 'blurb' => 'Other reports.', 'color' => '#8e8e93', 'icon' => 'doc', 'ids' => null]] as $category) {
            $ids = $category['ids'] ?? $items->keys()->diff($placed)->values()->all();
            $categoryItems = collect($ids)
                ->filter(fn (string $id) => $items->has($id))
                ->map(fn (string $id) => [...$items[$id], 'search' => Str::lower($items[$id]['search'].' '.$category['name'])])
                ->values()
                ->all();
            $placed = [...$placed, ...$ids];

            if ($categoryItems !== []) {
                $categories[] = [...Arr::except($category, 'ids'), 'items' => $categoryItems];
            }
        }

        return $categories;
    }

    protected function definitions(): array
    {
        return [
            [
                'key' => 'sales', 'name' => 'Sales', 'urdu' => 'سیلز', 'color' => 'bg-blue-500', 'icon' => 'trend',
                'items' => [
                    ['label' => 'Daily sales summary', 'description' => 'Consolidated daily sales', 'url' => route('reports.daily-sales.index'), 'permission' => 'report-sales-daily-sales', 'keywords' => 'revenue'],
                    ['label' => 'SKU-wise sales', 'description' => 'Sales by product', 'url' => route('reports.daily-sales.product-wise'), 'permission' => 'report-sales-daily-sales', 'keywords' => 'sku wise sales report product'],
                    ['label' => 'Sales by salesman', 'description' => 'Salesman performance', 'url' => route('reports.daily-sales.salesman-wise'), 'permission' => 'report-sales-daily-sales'],
                    ['label' => 'Sales settlement', 'description' => 'Settlement and reconciliation records', 'url' => route('reports.sales-settlement.index'), 'permission' => 'report-sales-settlement'],
                    ['label' => 'Company target summary', 'description' => 'Target vs achieved, remaining per day', 'url' => route('reports.company-target-summary.index'), 'permission' => 'report-audit-company-target-summary', 'keywords' => 'salesman'],
                    ['label' => 'Goods issue report', 'description' => 'Dispatch and delivery to salesmen', 'url' => route('reports.goods-issue.index'), 'permission' => 'report-sales-goods-issue', 'keywords' => 'outward'],
                    ['label' => 'Shop directory', 'description' => 'Registered retail outlets', 'url' => route('reports.shop-list.index'), 'permission' => 'report-sales-shop-list', 'keywords' => 'shop list'],
                ],
            ],
            [
                'key' => 'receivables', 'name' => 'Receivables & cash', 'urdu' => 'وصولیاں اور کیش', 'color' => 'bg-rose-500', 'icon' => 'cash',
                'items' => [
                    ['label' => 'Cash collection distributor', 'description' => 'Daily cash, cheque clearing and bank slips', 'url' => route('reports.cash-collection-distributor.index'), 'permission' => 'report-audit-cash-collection-distributor'],
                    ['label' => 'Cash collection detail', 'description' => 'Daily cash collection breakdown', 'url' => route('reports.cash-detail.index'), 'permission' => 'report-audit-cash-detail'],
                    ['label' => 'Cheque register', 'description' => 'Cheques received, cleared, bounced', 'url' => route('reports.cheque-register.index'), 'permission' => 'report-audit-cheque-register'],
                    ['label' => 'Customer account statement', 'description' => 'One customer, every entry', 'url' => route('reports.customer-account-statement.index'), 'permission' => 'report-audit-customer-account-statement'],
                    ['label' => 'Customer balances', 'description' => 'What each customer owes, last sale and recovery', 'url' => route('reports.customer-balances.index'), 'permission' => 'report-audit-customer-balances', 'keywords' => 'customers report receivable outstanding due recovery'],
                    ['label' => 'Debtors ledger', 'description' => 'What each customer owes', 'url' => route('reports.creditors-ledger.index'), 'permission' => 'report-audit-creditors-ledger', 'keywords' => 'creditors ledger outstanding balances'],
                    ['label' => 'Credit aging', 'description' => '0–30, 31–60, 61–90 and 90+ days since last payment', 'url' => route('reports.creditors-ledger.aging-report'), 'permission' => 'report-audit-creditors-ledger', 'keywords' => 'credit aging report'],
                    ['label' => 'Salesman-wise debtors', 'description' => 'Outstanding, recoveries and 60+ days credit per salesman', 'url' => route('reports.creditors-ledger.salesman-creditors'), 'permission' => 'report-audit-creditors-ledger', 'keywords' => 'salesman-wise creditors'],
                    ['label' => 'Salesman credit history', 'description' => 'Credit sales per salesman', 'url' => route('reports.credit-sales.salesman-history'), 'permission' => 'report-sales-credit-sales'],
                    ['label' => 'Opening customer balance', 'description' => 'Opening balances per salesman', 'url' => route('reports.opening-customer-balance.index'), 'permission' => 'report-audit-opening-customer-balance'],
                ],
            ],
            [
                'key' => 'stock', 'name' => 'Stock & vans', 'urdu' => 'اسٹاک اور وین', 'color' => 'bg-amber-500', 'icon' => 'box',
                'items' => [
                    ['label' => 'Daily stock register', 'description' => 'End-of-day stock position', 'url' => route('reports.daily-stock-register.index'), 'permission' => 'report-inventory-daily-stock-register'],
                    ['label' => 'Stock availability', 'description' => 'Current and past stock by supplier', 'url' => route('reports.stock-availability.index'), 'permission' => 'report-audit-stock-availability'],
                    ['label' => 'Inventory ledger', 'description' => 'Every stock movement', 'url' => route('reports.inventory-ledger.index'), 'permission' => 'report-inventory-inventory-ledger'],
                    ['label' => 'Salesman stock register', 'description' => 'Stock given per salesman', 'url' => route('reports.salesman-stock-register.index'), 'permission' => 'report-inventory-salesman-stock-register'],
                    ['label' => 'Van stock status', 'description' => 'Stock in each van now', 'url' => route('reports.daily-sales.van-stock'), 'permission' => 'report-sales-daily-sales'],
                    ['label' => 'Van stock ledger', 'description' => 'Van stock movements', 'url' => route('reports.van-stock-ledger.index'), 'permission' => 'report-inventory-van-stock-ledger'],
                    ['label' => 'Van stock by batch', 'description' => 'Batch-level van stock', 'url' => route('reports.van-stock-batch.index'), 'permission' => 'report-inventory-van-stock-batch'],
                    ['label' => 'FMR vs AMR', 'description' => 'Factory vs actual movement', 'url' => route('reports.fmr-amr-comparison.index'), 'permission' => 'report-sales-fmr-amr-comparison', 'keywords' => 'comparison'],
                    ['label' => 'SKU-wise FMR vs AMR', 'description' => 'FMR and AMR by SKU', 'url' => route('reports.sku-fmr-amr.index'), 'permission' => 'report-audit-sku-fmr-amr'],
                    ['label' => 'AMR dispose register', 'description' => 'Liquids and powders disposed, by supplier', 'url' => route('reports.amr-dispose-register.index'), 'permission' => 'report-audit-amr-dispose-register'],
                ],
            ],
            [
                'key' => 'suppliers', 'name' => 'Suppliers & claims', 'urdu' => 'سپلائرز اور کلیمز', 'color' => 'bg-violet-500', 'icon' => 'truck',
                'items' => [
                    ['label' => 'Supplier ledger', 'description' => 'Opening, credit sales, recoveries, closing', 'url' => route('reports.supplier-ledger.index'), 'permission' => 'report-audit-supplier-ledger'],
                    ['label' => 'Supplier ledger register', 'description' => 'Running balance per supplier', 'url' => route('reports.supplier-ledger-register.index'), 'permission' => 'report-audit-ledger-register'],
                    ['label' => 'Invoice summary', 'description' => 'Supplier invoices with tax', 'url' => route('reports.invoice-summary.index'), 'permission' => 'report-audit-invoice-summary'],
                    ['label' => 'Custom settlement', 'description' => 'Custom settlement entries', 'url' => route('reports.custom-settlement.index'), 'permission' => 'report-audit-custom-settlement'],
                    ['label' => 'Claim register', 'description' => 'Claims and recovery', 'url' => route('reports.claim-register.index'), 'permission' => 'report-audit-claim-register'],
                    ['label' => 'Investment summary', 'description' => 'Daily investment position', 'url' => route('reports.investment-summary.index'), 'permission' => 'report-audit-investment-summary'],
                    ['label' => 'Daily business summary', 'description' => 'Sales, cash, credit, discounts and TTS for a day', 'url' => route('reports.daily-business-summary.index'), 'permission' => 'report-audit-daily-business-summary', 'keywords' => 'management daily summary'],
                    ['label' => 'SKU & pricing', 'description' => 'Product codes and price list', 'url' => route('reports.sku-rates.index'), 'permission' => 'report-sales-sku-rates', 'keywords' => 'rates'],
                    ['label' => 'Product price change log', 'description' => 'Price changes and affected batches', 'url' => route('reports.product-price-change-log.index'), 'permission' => 'report-audit-product-price-change-log', 'keywords' => 'audit'],
                ],
            ],
            [
                'key' => 'schemes', 'name' => 'Schemes & profit', 'urdu' => 'اسکیمیں اور منافع', 'color' => 'bg-emerald-600', 'icon' => 'chart',
                'items' => [
                    ['label' => 'Return on investment', 'description' => 'Profit and ROI', 'url' => route('reports.roi.index'), 'permission' => 'report-sales-roi', 'keywords' => 'roi'],
                    ['label' => 'Summary ROI', 'description' => 'Sale, expenses and result by category', 'url' => route('reports.summary-roi.index'), 'permission' => 'report-sales-summary-roi'],
                    ['label' => 'Profit after category', 'description' => 'Profit deductions by category', 'url' => route('reports.profit-after-category.index'), 'permission' => 'report-audit-profit-after-category'],
                    ['label' => 'Revenue detail', 'description' => 'Revenue by category', 'url' => route('reports.revenue-detail.index'), 'permission' => 'report-audit-revenue-detail'],
                    ['label' => 'Expense detail', 'description' => 'Expenses by category', 'url' => route('reports.expense-detail.index'), 'permission' => 'report-audit-expense-detail'],
                    ['label' => 'Percentage summary', 'description' => 'Expense share', 'url' => route('reports.percentage-expense.index'), 'permission' => 'report-audit-percentage-expense', 'keywords' => 'percentage expense'],
                    ['label' => 'Schemes & discounts', 'description' => 'Scheme and discount tracking', 'url' => route('reports.scheme-discount.index'), 'permission' => 'report-sales-scheme-discount', 'keywords' => 'promotional'],
                    ['label' => 'Scheme received', 'description' => 'TTS and promo received', 'url' => route('reports.scheme-received.index'), 'permission' => 'report-sales-scheme-received'],
                    ['label' => 'TTS summary', 'description' => 'Received vs passed', 'url' => route('reports.tts-summary.index'), 'permission' => 'report-sales-tts-summary'],
                    ['label' => 'Daily sales & trade offer dashboard', 'description' => 'Company-wise TO received vs passed', 'url' => route('reports.sales-trade-offer.index'), 'permission' => 'report-sales-trade-offer-dashboard', 'keywords' => 'tts to promotion percentage balance all companies'],
                ],
            ],
            [
                'key' => 'tax', 'name' => 'Tax', 'urdu' => 'ٹیکس', 'color' => 'bg-red-500', 'icon' => 'percent',
                'items' => [
                    ['label' => 'Advance tax report', 'description' => 'Advance tax computation', 'url' => route('reports.advance-tax.index'), 'permission' => 'report-audit-advance-tax'],
                    ['label' => 'Advance tax sales register', 'description' => 'Daily register by supplier', 'url' => route('reports.advance-tax-sales-register.index'), 'permission' => 'report-audit-advance-tax-sales-register'],
                    ['label' => 'Advance tax customer summary', 'description' => 'Customer-wise by company', 'url' => route('reports.advance-tax-customer-summary.index'), 'permission' => 'report-audit-advance-tax-customer-summary'],
                ],
            ],
            [
                'key' => 'accounts', 'name' => 'Accounts & bank', 'urdu' => 'اکاؤنٹس اور بینک', 'color' => 'bg-slate-700', 'icon' => 'book',
                'items' => [
                    ['label' => 'General ledger', 'description' => 'Every posting', 'url' => route('reports.general-ledger.index'), 'permission' => 'report-financial-general-ledger', 'keywords' => 'gl'],
                    ['label' => 'Trial balance', 'description' => 'Debits equal credits', 'url' => route('reports.trial-balance.index'), 'permission' => 'report-financial-trial-balance'],
                    ['label' => 'Account balances', 'description' => 'Balance of each account', 'url' => route('reports.account-balances.index'), 'permission' => 'report-financial-account-balances'],
                    ['label' => 'Balance sheet', 'description' => 'Assets, liabilities, equity', 'url' => route('reports.balance-sheet.index'), 'permission' => 'report-financial-balance-sheet'],
                    ['label' => 'Income statement', 'description' => 'Income, expenses, net profit', 'url' => route('reports.income-statement.index'), 'permission' => 'report-financial-income-statement', 'keywords' => 'profit and loss'],
                    ['label' => 'Bank register reports', 'description' => 'Bank entries, summary, reconciliation and gaps', 'url' => route('reports.bank.index'), 'permission' => self::BANK_REPORT_PERMISSIONS, 'icon' => 'bank'],
                    ['label' => 'Vehicle report', 'description' => 'Fleet details and assignments', 'url' => route('reports.vehicle.index'), 'permission' => 'report-sales-vehicle', 'icon' => 'truck'],
                ],
            ],
            [
                'key' => 'expenses', 'name' => 'Expenses & payroll', 'urdu' => 'اخراجات اور تنخواہ', 'color' => 'bg-teal-600', 'icon' => 'cash',
                'items' => [
                    ['label' => 'Expense vouchers', 'description' => 'Daily expense vouchers with maker and checker', 'url' => route('expense-vouchers.index'), 'permission' => 'expense-voucher-list'],
                    ['label' => 'Expense categories', 'description' => 'What expense vouchers are for', 'url' => route('expense-categories.index'), 'permission' => 'expense-category-manage', 'icon' => 'tag'],
                    ['label' => 'Shops', 'description' => 'Shops and parties paid for daily expenses, with what is owed to each', 'url' => route('expense-vendors.index'), 'permission' => 'expense-vendor-list', 'icon' => 'store'],
                    ['label' => 'Vendor payments', 'description' => 'Month-end payments that clear the credit vouchers of shops', 'url' => route('expense-vendor-payments.index'), 'permission' => 'expense-vendor-payment-list'],
                    ['label' => 'Incentives & adjustments', 'description' => 'Extra pay or cuts for chosen employees, one time or every month', 'url' => route('payroll.index', ['tab' => 'adjustments']), 'permission' => 'payroll-adjustment-manage', 'icon' => 'users', 'keywords' => 'bonus commission incentive overtime payroll'],
                    ['label' => 'Payroll', 'description' => 'Monthly salaries, salary slips and advance recovery', 'url' => route('payroll.index'), 'permission' => 'payroll-list', 'icon' => 'users', 'keywords' => 'salary'],
                ],
            ],
        ];
    }
}
