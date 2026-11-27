<?php

namespace App\Services;

use App\Models\SalesSettlement;
use App\Models\SchemeReceived;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Figures behind the Daily Business Summary report (reports/daily-business-summary): the
 * management sheet with month-to-date sales by category, the investment position, the TTS
 * balance and one row per salesman for the day (sale, advance tax and scheme, expiry, credit and
 * cash). The salesman "%age" is advance tax (1161), "Scheme" is the scheme discount (5292) entered
 * on the settlement and "Amount" is cash counted plus bank slips and cheques, as on the management
 * sheet this report replaces.
 */
class DailyBusinessSummaryService
{
    public const PERCENTAGE_ACCOUNT_CODE = '5223';

    /** The sheet's "%age" column: advance tax withheld on the day's sale. */
    public const ADVANCE_TAX_ACCOUNT_CODE = '1161';

    public const SCHEME_DISCOUNT_ACCOUNT_CODE = '5292';

    /** "Promotion OFF": the promo discount salesmen give in the market; counted in the TTS "Promo Passed", not the salesman's scheme. */
    public const PROMO_ACCOUNT_CODE = '5288';

    public const AMR_LIQUID_ACCOUNT_CODE = '5262';

    public const AMR_POWDER_ACCOUNT_CODE = '5252';

    /**
     * Product categories that roll up into the Nutrition and Water groups; every other category is F&B.
     *
     * @var array<string, array<int, string>>
     */
    public const CATEGORY_GROUPS = [
        'Nutrition' => ['Nutrition', 'Baby Food'],
        'Water' => ['Water'],
    ];

    public function __construct(private InvestmentSummaryService $investment) {}

    /**
     * @param  int|null  $supplierId  one supplier, or null for all companies
     * @param  array<int, int|string>  $employeeIds
     * @return array{
     *     month_start: string,
     *     total_sales_mtd: float,
     *     categories: Collection<int, object{name: string, amount: float}>,
     *     category_groups: array<string, float>,
     *     investment: array{powder_expiry: float, liquid_expiry: float, claims: float, stock: float, credit: float, ledger: float, total: float},
     *     tts: array{tts_received: float, promo_received: float, total_received: float, tts_passed: float, promo_passed: float, total_passed: float, percentage_passed: float, balance: float},
     *     salesmen: Collection<int, object>,
     *     totals: array{sales: float, percentage: float, scheme: float, discount: float, expiry_liquid: float, expiry_powder: float, credit: float, cash: float}
     * }
     */
    public function build(string $date, ?int $supplierId, ?string $designation, array $employeeIds): array
    {
        $monthStart = Carbon::parse($date)->startOfMonth()->toDateString();

        $salesmen = $this->salesmanRows($date, $supplierId, $designation, $employeeIds);
        $totals = [
            'sales' => (float) $salesmen->sum('sales'),
            'percentage' => (float) $salesmen->sum('percentage'),
            'scheme' => (float) $salesmen->sum('scheme'),
            'discount' => (float) $salesmen->sum('discount'),
            'expiry_liquid' => (float) $salesmen->sum('expiry_liquid'),
            'expiry_powder' => (float) $salesmen->sum('expiry_powder'),
            'credit' => (float) $salesmen->sum('credit'),
            'cash' => (float) $salesmen->sum('cash'),
        ];

        $investment = [
            'powder_expiry' => $this->investment->getPowderExpiry($supplierId, $date),
            'liquid_expiry' => $this->investment->getLiquidExpiry($supplierId, $date),
            'claims' => $this->investment->getClaimAmount($supplierId, $date),
            'stock' => $this->investment->getStockAmount($supplierId, $date),
            'credit' => $totals['credit'],
            'ledger' => $this->investment->getLedgerAmount($supplierId, $date),
        ];
        $investment['total'] = array_sum($investment);

        $categories = $this->categorySales($monthStart, $date, $supplierId);

        return [
            'month_start' => $monthStart,
            'total_sales_mtd' => (float) $this->postedSettlements($monthStart, $date, $supplierId)->sum('total_sales_amount'),
            'categories' => $categories,
            'category_groups' => $this->categoryGroups($categories),
            'investment' => $investment,
            'tts' => $this->ttsSummary($monthStart, $date, $supplierId),
            'salesmen' => $salesmen,
            'totals' => $totals,
        ];
    }

    /**
     * One row per salesman: the day's sale, advance tax and scheme, expiry taken back, closing
     * market credit and the money handed in (counted notes plus bank slips and cheques).
     *
     * @param  array<int, int|string>  $employeeIds
     * @return Collection<int, object>
     */
    private function salesmanRows(string $date, ?int $supplierId, ?string $designation, array $employeeIds): Collection
    {
        $employees = $this->investment->getSalesmanCreditData($date, $supplierId, $designation, $employeeIds);
        $employees->load('supplier:id,short_name,supplier_name');

        $settlements = SalesSettlement::query()
            ->with('cashDenominations')
            ->where('status', 'posted')
            ->whereDate('settlement_date', $date)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()
            ->groupBy('employee_id');

        $expenses = DB::table('sales_settlement_expenses')
            ->join('sales_settlements', 'sales_settlement_expenses.sales_settlement_id', '=', 'sales_settlements.id')
            ->join('chart_of_accounts', 'sales_settlement_expenses.expense_account_id', '=', 'chart_of_accounts.id')
            ->where('sales_settlements.status', 'posted')
            ->whereDate('sales_settlements.settlement_date', $date)
            ->whereIn('sales_settlements.employee_id', $employees->pluck('id'))
            ->whereIn('chart_of_accounts.account_code', [
                self::ADVANCE_TAX_ACCOUNT_CODE, self::SCHEME_DISCOUNT_ACCOUNT_CODE,
                self::AMR_LIQUID_ACCOUNT_CODE, self::AMR_POWDER_ACCOUNT_CODE,
            ])
            ->groupBy('sales_settlements.employee_id', 'chart_of_accounts.account_code')
            ->select('sales_settlements.employee_id', 'chart_of_accounts.account_code', DB::raw('SUM(sales_settlement_expenses.amount) as amount'))
            ->get()
            ->groupBy('employee_id');

        return $employees->map(function ($employee) use ($settlements, $expenses) {
            $employeeSettlements = $settlements->get($employee->id, collect());
            $byCode = $expenses->get($employee->id, collect())->pluck('amount', 'account_code');
            $amount = fn (string $code) => (float) ($byCode[$code] ?? 0);

            $percentage = $amount(self::ADVANCE_TAX_ACCOUNT_CODE);
            $scheme = $amount(self::SCHEME_DISCOUNT_ACCOUNT_CODE);

            return (object) [
                'id' => $employee->id,
                'name' => $employee->name,
                'company' => $employee->supplier?->short_name ?: $employee->supplier?->supplier_name,
                'sales' => (float) $employeeSettlements->sum('total_sales_amount'),
                'percentage' => $percentage,
                'scheme' => $scheme,
                'discount' => $percentage + $scheme,
                'expiry_liquid' => $amount(self::AMR_LIQUID_ACCOUNT_CODE),
                'expiry_powder' => $amount(self::AMR_POWDER_ACCOUNT_CODE),
                'credit' => (float) $employee->total_credit,
                'cash' => (float) $employeeSettlements->sum(function (SalesSettlement $settlement) {
                    $counted = $settlement->cashDenominations->first();

                    $cash = ($counted && $counted->total_amount > 0)
                        ? (float) $counted->total_amount
                        : (float) $settlement->cash_collected;

                    return $cash + (float) $settlement->bank_slips_amount + (float) $settlement->cheques_collected;
                }),
            ];
        })->values();
    }

    /**
     * @return Collection<int, object{name: string, amount: float}>
     */
    private function categorySales(string $from, string $to, ?int $supplierId): Collection
    {
        return DB::table('sales_settlement_items')
            ->join('sales_settlements', 'sales_settlement_items.sales_settlement_id', '=', 'sales_settlements.id')
            ->join('products', 'sales_settlement_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('sales_settlements.status', 'posted')
            ->whereNull('sales_settlements.deleted_at')
            ->whereBetween('sales_settlements.settlement_date', [$from, $to])
            ->when($supplierId, fn ($query) => $query->where('sales_settlements.supplier_id', $supplierId))
            ->groupBy('categories.name')
            ->select(DB::raw("COALESCE(categories.name, 'Uncategorised') as name"), DB::raw('SUM(sales_settlement_items.total_sales_value) as amount'))
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($row) => (object) ['name' => (string) $row->name, 'amount' => (float) $row->amount])
            ->filter(fn ($row) => abs($row->amount) >= 0.005)
            ->values();
    }

    /**
     * @param  Collection<int, object{name: string, amount: float}>  $categories
     * @return array<string, float>
     */
    private function categoryGroups(Collection $categories): array
    {
        $groups = ['F&B' => 0.0, 'Nutrition' => 0.0, 'Water' => 0.0];

        foreach ($categories as $category) {
            $group = collect(self::CATEGORY_GROUPS)
                ->search(fn (array $names) => in_array($category->name, $names, true)) ?: 'F&B';
            $groups[$group] += $category->amount;
        }

        return $groups;
    }

    /**
     * TTS and promo received from the company against what was passed to the market, month to date.
     *
     * @return array{tts_received: float, promo_received: float, total_received: float, tts_passed: float, promo_passed: float, total_passed: float, percentage_passed: float, balance: float}
     */
    private function ttsSummary(string $from, string $to, ?int $supplierId): array
    {
        $received = fn (string $category) => (float) SchemeReceived::query()
            ->where('category', $category)
            ->where('is_active', true)
            ->whereBetween('transaction_date', [$from, $to])
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->sum('amount');

        $passed = fn (string $code) => (float) DB::table('sales_settlement_expenses')
            ->join('sales_settlements', 'sales_settlement_expenses.sales_settlement_id', '=', 'sales_settlements.id')
            ->join('chart_of_accounts', 'sales_settlement_expenses.expense_account_id', '=', 'chart_of_accounts.id')
            ->where('sales_settlements.status', 'posted')
            ->whereBetween('sales_settlements.settlement_date', [$from, $to])
            ->when($supplierId, fn ($query) => $query->where('sales_settlements.supplier_id', $supplierId))
            ->where('chart_of_accounts.account_code', $code)
            ->sum('sales_settlement_expenses.amount');

        $tts = [
            'tts_received' => $received('tts_received'),
            'promo_received' => $received('promo_received'),
            'tts_passed' => $passed(self::SCHEME_DISCOUNT_ACCOUNT_CODE),
            'promo_passed' => $passed(self::PROMO_ACCOUNT_CODE),
            'percentage_passed' => $passed(self::PERCENTAGE_ACCOUNT_CODE),
        ];
        $tts['total_received'] = $tts['tts_received'] + $tts['promo_received'];
        $tts['total_passed'] = $tts['tts_passed'] + $tts['promo_passed'];
        $tts['balance'] = $tts['total_received'] - $tts['total_passed'] - $tts['percentage_passed'];

        return $tts;
    }

    private function postedSettlements(string $from, string $to, ?int $supplierId)
    {
        return SalesSettlement::query()
            ->where('status', 'posted')
            ->whereBetween('settlement_date', [$from, $to])
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId));
    }
}
