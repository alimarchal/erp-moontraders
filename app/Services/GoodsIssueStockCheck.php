<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Where a goods issue's products stand in the warehouse before it is posted.
 *
 * Drafts do not hold stock: they are written in the morning and posted one by one, and
 * every draft posted before this one takes stock it was counting on. So a line that fitted
 * when it was written can fall short by the time it is posted. This answers, per product,
 * what the issue needs, what is on hand now, and which other goods issues are drawing on
 * the same stock, so the person posting can see the product by name and where it went.
 */
class GoodsIssueStockCheck
{
    /**
     * @param  iterable<object{product_id: int|string, quantity_issued: float|string, exclude_promotional?: bool|int|null}>  $lines
     * @return Collection<int, array{product_id: int, product_code: string, product_name: string, conversion_factor: float, required: float, required_non_promotional: float, on_hand: float, on_hand_non_promotional: float, other_drafts: list<array{issue_number: string, vehicle: string, quantity: float}>, in_other_drafts: float, issued_same_day: list<array{issue_number: string, vehicle: string, quantity: float}>, free: float, short: float, short_non_promotional: float}>
     */
    public function positions(iterable $lines, int $warehouseId, ?int $goodsIssueId = null, ?string $issueDate = null): Collection
    {
        $required = [];

        foreach ($lines as $line) {
            $productId = (int) $line->product_id;
            $quantity = (float) $line->quantity_issued;

            $required[$productId] ??= ['total' => 0.0, 'non_promotional' => 0.0];
            $required[$productId]['total'] += $quantity;

            if (! empty($line->exclude_promotional)) {
                $required[$productId]['non_promotional'] += $quantity;
            }
        }

        if ($required === []) {
            return collect();
        }

        $productIds = array_keys($required);

        $products = DB::table('products')->whereIn('id', $productIds)
            ->get(['id', 'product_code', 'product_name', 'uom_conversion_factor'])
            ->keyBy('id');

        $onHand = DB::table('current_stock')
            ->where('warehouse_id', $warehouseId)
            ->whereIn('product_id', $productIds)
            ->pluck('quantity_on_hand', 'product_id');

        $onHandNonPromotional = DB::table('stock_valuation_layers')
            ->where('warehouse_id', $warehouseId)
            ->whereIn('product_id', $productIds)
            ->where('is_depleted', false)
            ->where('quantity_remaining', '>', 0)
            ->where('is_promotional', false)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity_remaining) as quantity')
            ->pluck('quantity', 'product_id');

        $otherDrafts = $this->otherGoodsIssues($productIds, $warehouseId, $goodsIssueId, 'draft');
        $issuedSameDay = $issueDate === null
            ? collect()
            : $this->otherGoodsIssues($productIds, $warehouseId, $goodsIssueId, 'issued', $issueDate);

        return collect($required)->map(function (array $need, int $productId) use ($products, $onHand, $onHandNonPromotional, $otherDrafts, $issuedSameDay) {
            $product = $products->get($productId);
            $available = (float) ($onHand[$productId] ?? 0);
            $availableNonPromotional = (float) ($onHandNonPromotional[$productId] ?? 0);
            $drafts = $otherDrafts->get($productId, []);
            $inOtherDrafts = array_sum(array_column($drafts, 'quantity'));

            return [
                'product_id' => $productId,
                'product_code' => (string) ($product->product_code ?? ''),
                'product_name' => (string) ($product->product_name ?? "Product #{$productId}"),
                'conversion_factor' => (float) ($product->uom_conversion_factor ?? 1) ?: 1.0,
                'required' => $need['total'],
                'required_non_promotional' => $need['non_promotional'],
                'on_hand' => $available,
                'on_hand_non_promotional' => $availableNonPromotional,
                'other_drafts' => $drafts,
                'in_other_drafts' => $inOtherDrafts,
                'issued_same_day' => $issuedSameDay->get($productId, []),
                'free' => $available - $inOtherDrafts,
                'short' => max(0.0, $need['total'] - $available),
                'short_non_promotional' => max(0.0, $need['non_promotional'] - $availableNonPromotional),
            ];
        })->values();
    }

    /**
     * Refuse the lines when the warehouse cannot cover them, naming every product short.
     *
     * @param  iterable<object{product_id: int|string, quantity_issued: float|string, exclude_promotional?: bool|int|null}>  $lines
     *
     * @throws RuntimeException listing each product that is short
     */
    public function assertCovered(iterable $lines, int $warehouseId, string $issueNumber): void
    {
        $shortfalls = $this->positions($lines, $warehouseId)
            ->filter(fn (array $position) => $position['short'] > StockValuationService::QTY_EPSILON
                || $position['short_non_promotional'] > StockValuationService::QTY_EPSILON);

        if ($shortfalls->isEmpty()) {
            return;
        }

        $lines = $shortfalls->map(function (array $position) {
            $factor = $position['conversion_factor'];

            if ($position['short'] <= StockValuationService::QTY_EPSILON) {
                return sprintf(
                    '• %s: needs %s of non-promotional stock, only %s in stock (short %s).',
                    $this->productLabel($position),
                    self::formatQuantity($position['required_non_promotional'], $factor),
                    self::formatQuantity($position['on_hand_non_promotional'], $factor),
                    self::formatQuantity($position['short_non_promotional'], $factor)
                );
            }

            return sprintf(
                '• %s: needs %s, only %s in stock (short %s).',
                $this->productLabel($position),
                self::formatQuantity($position['required'], $factor),
                self::formatQuantity($position['on_hand'], $factor),
                self::formatQuantity($position['short'], $factor)
            );
        });

        throw new RuntimeException(
            "Not enough stock to post {$issueNumber}. Reduce or remove these lines:\n".$lines->implode("\n")
        );
    }

    /**
     * A quantity in stock units, shown as cartons and pieces when the product is packed in cartons.
     */
    public static function formatQuantity(float $quantity, float $conversionFactor): string
    {
        $pieces = rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');

        if ($conversionFactor <= 1) {
            return "{$pieces} pcs";
        }

        $cartons = (int) floor(($quantity + StockValuationService::QTY_EPSILON) / $conversionFactor);
        $loose = rtrim(rtrim(number_format($quantity - $cartons * $conversionFactor, 3, '.', ''), '0'), '.');

        return "{$cartons} ctn + {$loose} pcs ({$pieces} pcs)";
    }

    /**
     * @param  array{product_code: string, product_name: string}  $position
     */
    private function productLabel(array $position): string
    {
        return $position['product_code'] !== '' && $position['product_code'] !== $position['product_name']
            ? "{$position['product_code']} – {$position['product_name']}"
            : $position['product_name'];
    }

    /**
     * Other goods issues from this warehouse carrying these products, per product.
     *
     * @param  list<int>  $productIds
     * @return Collection<int, list<array{issue_number: string, vehicle: string, quantity: float}>>
     */
    private function otherGoodsIssues(array $productIds, int $warehouseId, ?int $goodsIssueId, string $status, ?string $issueDate = null): Collection
    {
        return DB::table('goods_issue_items as item')
            ->join('goods_issues as issue', 'issue.id', '=', 'item.goods_issue_id')
            ->leftJoin('vehicles as vehicle', 'vehicle.id', '=', 'issue.vehicle_id')
            ->whereIn('item.product_id', $productIds)
            ->where('issue.warehouse_id', $warehouseId)
            ->where('issue.status', $status)
            ->whereNull('issue.deleted_at')
            ->when($goodsIssueId, fn ($query, $id) => $query->where('issue.id', '!=', $id))
            ->when($issueDate, fn ($query, $date) => $query->whereDate('issue.issue_date', $date))
            ->groupBy('item.product_id', 'issue.id', 'issue.issue_number', 'vehicle.vehicle_number')
            ->orderBy('issue.issue_number')
            ->selectRaw('item.product_id, issue.issue_number, vehicle.vehicle_number, SUM(item.quantity_issued) as quantity')
            ->get()
            ->groupBy('product_id')
            ->map(fn (Collection $rows) => $rows->map(fn ($row) => [
                'issue_number' => (string) $row->issue_number,
                'vehicle' => (string) ($row->vehicle_number ?? ''),
                'quantity' => (float) $row->quantity,
            ])->values()->all());
    }
}
