<?php

namespace App\Sorts;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

/**
 * Sorts settlements by net profit (sales - COGS - expenses), using the sums the settlement list already loads.
 * Same formula as SalesSettlement::getCalculatedNetProfitAttribute().
 */
class NetProfitSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): void
    {
        $direction = $descending ? 'desc' : 'asc';

        // Postgres cannot reference select aliases inside an ORDER BY expression, so repeat the sums.
        $query->orderByRaw('(
            COALESCE((select sum(i.total_sales_value) from sales_settlement_items i where i.sales_settlement_id = sales_settlements.id), 0)
            - COALESCE((select sum(i.total_cogs) from sales_settlement_items i where i.sales_settlement_id = sales_settlements.id), 0)
            - COALESCE((select sum(e.amount) from sales_settlement_expenses e where e.sales_settlement_id = sales_settlements.id), 0)
        ) '.$direction);
    }
}
