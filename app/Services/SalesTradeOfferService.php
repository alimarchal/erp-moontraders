<?php

namespace App\Services;

use App\Models\SalesSettlement;
use App\Models\SchemeReceived;
use App\Models\Supplier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Figures behind the Daily Sales & Trade Offer Dashboard (reports/sales-trade-offer): one column per
 * company with its sale for the period, the trade offer (TTS) and promotion received from the company,
 * what was passed on to the market, the percentage given and the resulting balance.
 *
 * Per company it is the same arithmetic as the TTS Summary report, laid side by side:
 *   Total TO Received = TO Received (scheme received "tts_received") + Promotion Received ("promo_received")
 *   Total TO Passed   = TO Passed (5292 scheme discount) + Promotion Passed (5288 Promotion OFF)
 *   Balance           = Total TO Received − Total TO Passed − Percentage (5223)
 * Sale and passed figures come from posted sales settlements, by the settlement's company.
 */
class SalesTradeOfferService
{
    public const TO_PASSED_ACCOUNT_CODE = '5292';

    /** "Promotion OFF": the promo discount salesmen give in the market, booked on the sales settlement. */
    public const PROMOTION_PASSED_ACCOUNT_CODE = '5288';

    public const PERCENTAGE_ACCOUNT_CODE = '5223';

    public const SORT_BY_NAME = 'name';

    public const SORT_BY_SALES = 'sales_desc';

    /**
     * The rows of the dashboard, top to bottom: figure key => label.
     *
     * @var array<string, string>
     */
    public const ROWS = [
        'total_sale' => 'Total Sale',
        'to_received' => 'TO Received',
        'promotion_received' => 'Promotion Received',
        'total_to_received' => 'Total TO Received',
        'to_passed' => 'TO Passed',
        'promotion_passed' => 'Promotion Passed',
        'total_to_passed' => 'Total TO Passed',
        'percentage' => 'Percentage',
        'balance' => 'Balance',
    ];

    /**
     * @param  array<int, int>|null  $supplierIds  the companies to show, or null for all companies (every active company, plus a disabled one with figures in the period)
     * @return array{
     *     columns: Collection<int, object{id: int, name: string, label: string, figures: array<string, float>}>,
     *     totals: array<string, float>
     * }
     */
    public function build(string $from, string $to, ?array $supplierIds, string $sort = self::SORT_BY_NAME): array
    {
        $sales = $this->salesBySupplier($from, $to, $supplierIds);
        $received = $this->receivedBySupplier($from, $to, $supplierIds);
        $passed = $this->passedBySupplier($from, $to, $supplierIds);

        $activeIds = $sales->keys()->merge($received->keys())->merge($passed->keys())->unique();

        $columns = Supplier::query()
            ->when(
                $supplierIds === null,
                fn ($query) => $query->where(fn ($query) => $query->where('disabled', false)->orWhereIn('id', $activeIds->all())),
                fn ($query) => $query->whereIn('id', $supplierIds),
            )
            ->get(['id', 'supplier_name', 'short_name'])
            ->map(function (Supplier $supplier) use ($sales, $received, $passed) {
                $supplierReceived = $received->get($supplier->id, collect());
                $supplierPassed = $passed->get($supplier->id, collect());

                return (object) [
                    'id' => $supplier->id,
                    'name' => $supplier->supplier_name,
                    'label' => $supplier->short_name ?: $supplier->supplier_name,
                    'figures' => $this->figures(
                        (float) $sales->get($supplier->id, 0),
                        (float) $supplierReceived->get('tts_received', 0),
                        (float) $supplierReceived->get('promo_received', 0),
                        (float) $supplierPassed->get(self::TO_PASSED_ACCOUNT_CODE, 0),
                        (float) $supplierPassed->get(self::PROMOTION_PASSED_ACCOUNT_CODE, 0),
                        (float) $supplierPassed->get(self::PERCENTAGE_ACCOUNT_CODE, 0),
                    ),
                ];
            });

        $columns = ($sort === self::SORT_BY_SALES
            ? $columns->sortBy([fn ($a, $b) => $b->figures['total_sale'] <=> $a->figures['total_sale'], fn ($a, $b) => strcasecmp($a->label, $b->label)])
            : $columns->sortBy(fn ($column) => mb_strtolower($column->label)))
            ->values();

        $totals = [];
        foreach (array_keys(self::ROWS) as $key) {
            $totals[$key] = round((float) $columns->sum(fn ($column) => $column->figures[$key]), 2);
        }

        return ['columns' => $columns, 'totals' => $totals];
    }

    /**
     * @return array<string, float>
     */
    private function figures(float $sale, float $toReceived, float $promotionReceived, float $toPassed, float $promotionPassed, float $percentage): array
    {
        $totalReceived = $toReceived + $promotionReceived;
        $totalPassed = $toPassed + $promotionPassed;

        return array_map(fn (float $value) => round($value, 2), [
            'total_sale' => $sale,
            'to_received' => $toReceived,
            'promotion_received' => $promotionReceived,
            'total_to_received' => $totalReceived,
            'to_passed' => $toPassed,
            'promotion_passed' => $promotionPassed,
            'total_to_passed' => $totalPassed,
            'percentage' => $percentage,
            'balance' => $totalReceived - $totalPassed - $percentage,
        ]);
    }

    /**
     * Sale of posted settlements in the period, by company.
     *
     * @param  array<int, int>|null  $supplierIds
     * @return Collection<int, float>
     */
    private function salesBySupplier(string $from, string $to, ?array $supplierIds): Collection
    {
        return SalesSettlement::query()
            ->where('status', 'posted')
            ->whereNotNull('supplier_id')
            ->whereBetween('settlement_date', [$from, $to])
            ->when($supplierIds !== null, fn ($query) => $query->whereIn('supplier_id', $supplierIds))
            ->groupBy('supplier_id')
            ->select('supplier_id', DB::raw('SUM(total_sales_amount) as amount'))
            ->pluck('amount', 'supplier_id')
            ->map(fn ($amount) => (float) $amount);
    }

    /**
     * Active trade offer and promotion received from each company in the period.
     *
     * @param  array<int, int>|null  $supplierIds
     * @return Collection<int, Collection<string, float>>
     */
    private function receivedBySupplier(string $from, string $to, ?array $supplierIds): Collection
    {
        return SchemeReceived::query()
            ->where('is_active', true)
            ->whereIn('category', ['tts_received', 'promo_received'])
            ->whereBetween('transaction_date', [$from, $to])
            ->when($supplierIds !== null, fn ($query) => $query->whereIn('supplier_id', $supplierIds))
            ->groupBy('supplier_id', 'category')
            ->select('supplier_id', 'category', DB::raw('SUM(amount) as amount'))
            ->toBase()
            ->get()
            ->groupBy('supplier_id')
            ->map(fn (Collection $rows) => $rows->mapWithKeys(fn ($row) => [$row->category => (float) $row->amount]));
    }

    /**
     * Trade offer, promotion and percentage passed on posted settlements in the period, by company.
     *
     * @param  array<int, int>|null  $supplierIds
     * @return Collection<int, Collection<string, float>>
     */
    private function passedBySupplier(string $from, string $to, ?array $supplierIds): Collection
    {
        return DB::table('sales_settlement_expenses')
            ->join('sales_settlements', 'sales_settlement_expenses.sales_settlement_id', '=', 'sales_settlements.id')
            ->join('chart_of_accounts', 'sales_settlement_expenses.expense_account_id', '=', 'chart_of_accounts.id')
            ->where('sales_settlements.status', 'posted')
            ->whereNull('sales_settlements.deleted_at')
            ->whereNotNull('sales_settlements.supplier_id')
            ->whereBetween('sales_settlements.settlement_date', [$from, $to])
            ->when($supplierIds !== null, fn ($query) => $query->whereIn('sales_settlements.supplier_id', $supplierIds))
            ->whereIn('chart_of_accounts.account_code', [
                self::TO_PASSED_ACCOUNT_CODE, self::PROMOTION_PASSED_ACCOUNT_CODE, self::PERCENTAGE_ACCOUNT_CODE,
            ])
            ->groupBy('sales_settlements.supplier_id', 'chart_of_accounts.account_code')
            ->select('sales_settlements.supplier_id', 'chart_of_accounts.account_code', DB::raw('SUM(sales_settlement_expenses.amount) as amount'))
            ->get()
            ->groupBy('supplier_id')
            ->map(fn (Collection $rows) => $rows->mapWithKeys(fn ($row) => [(string) $row->account_code => (float) $row->amount]));
    }
}
