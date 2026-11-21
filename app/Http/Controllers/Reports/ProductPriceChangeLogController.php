<?php

namespace App\Http\Controllers\Reports;

use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductPriceChangeLog;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilderContract;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProductPriceChangeLogController extends Controller implements HasMiddleware
{
    /** Rows-per-page choices on the list. */
    public const PER_PAGE = [20, 50, 100, 500];

    /** @var array<string, string> */
    public const PRICE_TYPES = [
        'selling_price' => 'Selling Price',
        'cost_price' => 'Cost Price',
        'expiry_price' => 'Expiry Price',
    ];

    /** @var array<string, string> */
    public const SOURCES = [
        'ticket' => 'Price ticket',
        'stock_adjustment' => 'Stock adjustment',
        'product_edit' => 'Product edit',
        'import' => 'Excel import',
    ];

    /**
     * Rows logged since the source column was added carry their own source. Older rows are matched to
     * the ticket or stock adjustment that wrote them when they were approved / posted within this many
     * minutes of each other; anything else counts as a product edit.
     */
    private const SOURCE_WINDOW_MINUTES = 2;

    public static function middleware(): array
    {
        return [
            new Middleware('can:report-audit-product-price-change-log'),
        ];
    }

    public function index(Request $request): View
    {
        $canViewAllSuppliers = $this->canViewAllSuppliers();
        $userSupplierId = $this->getUserSupplierScope();
        $requestedSupplierId = $request->input('filter.supplier_id');

        if ($requestedSupplierId && ! $canViewAllSuppliers && (int) $requestedSupplierId !== $userSupplierId) {
            abort(403, 'You do not have permission to filter by this supplier.');
        }

        $supplierId = $userSupplierId ?? ($requestedSupplierId ? (int) $requestedSupplierId : null);
        $this->authorizeProductFilter($request->input('filter.product_id'), $supplierId, $canViewAllSuppliers);

        if (! $request->filled('filter.changed_from') && ! $request->filled('filter.changed_to')) {
            $request->merge([
                'filter' => array_merge((array) $request->input('filter', []), [
                    'changed_from' => now()->subDays(29)->toDateString(),
                    'changed_to' => now()->toDateString(),
                ]),
            ]);
        }

        $perPage = (string) $request->input('per_page', '20');
        $perPage = in_array($perPage, array_map('strval', self::PER_PAGE), true) ? $perPage : '20';

        /** Logs this user may see: their own supplier when scoped, nothing when scoped without a supplier. */
        $visible = function () use ($supplierId, $canViewAllSuppliers): Builder {
            return ProductPriceChangeLog::query()
                ->when($supplierId, fn (Builder $query) => $query->whereHas('product', fn (Builder $q) => $q->where('supplier_id', $supplierId)))
                ->when(! $supplierId && ! $canViewAllSuppliers, fn (Builder $query) => $query->whereRaw('1 = 0'));
        };

        $allowedFilters = $this->allowedFilters();
        $allowedSorts = ['changed_at', 'old_price', 'new_price', 'impacted_batch_count'];

        $logs = QueryBuilder::for($visible()->with(['product:id,product_code,product_name,supplier_id', 'product.supplier:id,supplier_name', 'changedBy:id,name']))
            ->allowedFilters($allowedFilters)
            ->allowedSorts($allowedSorts)
            ->defaultSort('-changed_at', '-id')
            ->addSelect($this->sourceReferenceColumns())
            ->paginate((int) $perPage)
            ->withQueryString();

        $this->attachImpactedBatchCodes($logs->getCollection());

        // Tab and total figures use every filter except the price type, so each tab shows what it would hold.
        $statsRequest = new Request(['filter' => Arr::except((array) $request->input('filter', []), ['price_type'])]);
        $byType = QueryBuilder::for($visible(), $statsRequest)
            ->allowedFilters($allowedFilters)
            ->getEloquentBuilder()
            ->reorder()
            ->groupBy('price_type')
            ->selectRaw('price_type, COUNT(*) as changes, SUM(CASE WHEN new_price > old_price THEN 1 ELSE 0 END) as increases, SUM(CASE WHEN new_price < old_price THEN 1 ELSE 0 END) as decreases')
            ->get()
            ->keyBy('price_type');

        $activeType = $request->input('filter.price_type');
        $inView = $activeType ? $byType->only([$activeType]) : $byType;

        $stats = [
            'total' => (int) $byType->sum('changes'),
            'by_type' => collect(array_keys(self::PRICE_TYPES))->mapWithKeys(fn (string $type) => [$type => (int) ($byType[$type]->changes ?? 0)])->all(),
            'increases' => (int) $inView->sum('increases'),
            'decreases' => (int) $inView->sum('decreases'),
        ];

        return view('reports.product-price-change-log.index', [
            'logs' => $logs,
            'stats' => $stats,
            'perPage' => $perPage,
            'supplierOptions' => Supplier::query()
                ->when($supplierId, fn ($query) => $query->where('id', $supplierId))
                ->when(! $canViewAllSuppliers && ! $supplierId, fn ($query) => $query->whereRaw('1 = 0'))
                ->orderBy('supplier_name')
                ->get(['id', 'supplier_name']),
            'productOptions' => Product::query()
                ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
                ->when(! $canViewAllSuppliers && ! $supplierId, fn ($query) => $query->whereRaw('1 = 0'))
                ->orderBy('product_name')
                ->get(['id', 'product_code', 'product_name']),
            'priceTypeOptions' => self::PRICE_TYPES,
            'sourceOptions' => self::SOURCES,
            'userOptions' => User::query()
                ->whereIn('id', ProductPriceChangeLog::query()->select('changed_by')->distinct())
                ->orderBy('name')
                ->get(['id', 'name']),
            'supplierId' => $supplierId,
            'canViewAllSuppliers' => $canViewAllSuppliers,
        ]);
    }

    /**
     * @return array<int, AllowedFilter>
     */
    private function allowedFilters(): array
    {
        return [
            AllowedFilter::callback('supplier_id', fn (Builder $query, $value) => $query->whereHas('product', fn (Builder $q) => $q->where('supplier_id', $value))),
            AllowedFilter::exact('product_id', 'product_price_change_logs.product_id'),
            AllowedFilter::exact('price_type', 'product_price_change_logs.price_type'),
            AllowedFilter::exact('changed_by', 'product_price_change_logs.changed_by'),
            AllowedFilter::callback('changed_from', fn (Builder $query, $value) => $query->whereDate('product_price_change_logs.changed_at', '>=', $value)),
            AllowedFilter::callback('changed_to', fn (Builder $query, $value) => $query->whereDate('product_price_change_logs.changed_at', '<=', $value)),
            AllowedFilter::callback('batch', function (Builder $query, $value) {
                $term = '%'.mb_strtolower(trim($this->filterText($value))).'%';
                $batchIds = DB::table('stock_batches')->whereRaw('LOWER(batch_code) LIKE ?', [$term])->limit(500)->pluck('id');

                $query->where(function (Builder $q) use ($batchIds) {
                    $q->whereRaw('1 = 0');
                    foreach ($batchIds as $batchId) {
                        $q->orWhereJsonContains('product_price_change_logs.impacted_batch_ids', (int) $batchId);
                    }
                });
            }),
            AllowedFilter::callback('source', function (Builder $query, $value) {
                $source = $this->filterText($value);

                if (! array_key_exists($source, self::SOURCES)) {
                    return;
                }

                $query->where(fn (Builder $q) => $q
                    ->where('product_price_change_logs.source', $source)
                    ->orWhere(fn (Builder $legacy) => $legacy->whereNull('product_price_change_logs.source')->where(fn (Builder $inferred) => match ($source) {
                        'stock_adjustment' => $this->whereFromStockAdjustment($inferred),
                        'ticket' => $inferred->whereNot(fn (Builder $x) => $this->whereFromStockAdjustment($x))->whereExists($this->priceTicketQuery()),
                        'product_edit' => $inferred->whereNot(fn (Builder $x) => $this->whereFromStockAdjustment($x))->whereNotExists($this->priceTicketQuery()),
                        default => $inferred->whereRaw('1 = 0'),
                    })));
            }),
            AllowedFilter::callback('search', function (Builder $query, $value) {
                $term = '%'.mb_strtolower(trim($this->filterText($value))).'%';
                $query->where(fn (Builder $q) => $q
                    ->whereHas('product', fn (Builder $p) => $p
                        ->whereRaw('LOWER(product_name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(product_code) LIKE ?', [$term])
                        ->orWhereHas('supplier', fn (Builder $s) => $s->whereRaw('LOWER(supplier_name) LIKE ?', [$term])))
                    ->orWhereHas('changedBy', fn (Builder $u) => $u->whereRaw('LOWER(name) LIKE ?', [$term])));
            }),
        ];
    }

    /**
     * Ticket and adjustment numbers each row was written by, read as sub-selects so the list needs no extra queries.
     *
     * @return array<string, QueryBuilderContract>
     */
    private function sourceReferenceColumns(): array
    {
        return [
            'adjustment_number' => $this->postedAdjustmentQuery()->select('stock_adjustments.adjustment_number')->limit(1),
            'adjustment_ticket_number' => $this->adjustmentTicketQuery()->select('tickets.ticket_number')->limit(1),
            'price_ticket_number' => $this->priceTicketQuery()->select('tickets.ticket_number')->limit(1),
        ];
    }

    /**
     * A selling price change made while a stock adjustment was posted, directly or from an approved ticket.
     */
    private function whereFromStockAdjustment(Builder $query): Builder
    {
        return $query->where('product_price_change_logs.price_type', 'selling_price')
            ->where(fn (Builder $q) => $q
                ->whereExists($this->postedAdjustmentQuery())
                ->orWhereExists($this->adjustmentTicketQuery()));
    }

    private function postedAdjustmentQuery(): QueryBuilderContract
    {
        return DB::table('stock_adjustment_items')
            ->join('stock_adjustments', 'stock_adjustments.id', '=', 'stock_adjustment_items.stock_adjustment_id')
            ->whereColumn('stock_adjustment_items.product_id', 'product_price_change_logs.product_id')
            ->where('stock_adjustments.status', 'posted')
            ->whereRaw('stock_adjustments.posted_at BETWEEN '.$this->shiftedChangedAt('-').' AND '.$this->shiftedChangedAt('+'));
    }

    private function adjustmentTicketQuery(): QueryBuilderContract
    {
        return DB::table('tickets')
            ->where('tickets.type', TicketType::StockAdjustment->value)
            ->where('tickets.status', TicketStatus::Approved->value)
            ->whereColumn('tickets.reviewed_by', 'product_price_change_logs.changed_by')
            ->whereRaw('tickets.reviewed_at BETWEEN '.$this->shiftedChangedAt('-').' AND '.$this->shiftedChangedAt('+'));
    }

    private function priceTicketQuery(): QueryBuilderContract
    {
        return DB::table('ticket_items')
            ->join('tickets', 'tickets.id', '=', 'ticket_items.ticket_id')
            ->whereColumn('ticket_items.product_id', 'product_price_change_logs.product_id')
            ->where('tickets.type', TicketType::PriceUpdate->value)
            ->where('tickets.status', TicketStatus::Approved->value)
            ->whereColumn('tickets.reviewed_by', 'product_price_change_logs.changed_by')
            ->whereRaw('tickets.reviewed_at BETWEEN '.$this->shiftedChangedAt('-').' AND '.$this->shiftedChangedAt('+'));
    }

    /**
     * The log's changed_at moved by the source window, written for the connection's SQL dialect.
     */
    private function shiftedChangedAt(string $sign): string
    {
        $minutes = self::SOURCE_WINDOW_MINUTES;

        return match (DB::connection()->getDriverName()) {
            'pgsql' => "(product_price_change_logs.changed_at {$sign} interval '{$minutes} minutes')",
            'sqlite' => "datetime(product_price_change_logs.changed_at, '{$sign}{$minutes} minutes')",
            default => "(product_price_change_logs.changed_at {$sign} INTERVAL {$minutes} MINUTE)",
        };
    }

    /**
     * Puts the batch codes of each row's impacted batches on the row, with one query for the page.
     *
     * @param  Collection<int, ProductPriceChangeLog>  $logs
     */
    private function attachImpactedBatchCodes($logs): void
    {
        $batchIds = $logs->flatMap(fn (ProductPriceChangeLog $log) => (array) $log->impacted_batch_ids)->map(fn ($id) => (int) $id)->unique()->values();
        $codes = $batchIds->isEmpty() ? collect() : DB::table('stock_batches')->whereIn('id', $batchIds)->pluck('batch_code', 'id');

        $logs->each(fn (ProductPriceChangeLog $log) => $log->setAttribute(
            'impacted_batch_codes',
            collect((array) $log->impacted_batch_ids)->map(fn ($id) => $codes[(int) $id] ?? '#'.$id)->values()->all()
        ));
    }

    /**
     * Query-builder filter values arrive as arrays when they contain a comma; filters here want the text.
     */
    private function filterText(mixed $value): string
    {
        return is_array($value) ? implode(',', $value) : (string) $value;
    }

    private function getUserSupplierScope(): ?int
    {
        $user = auth()->user();

        if ($this->canViewAllSuppliers()) {
            return null;
        }

        return $user->supplier_id ? (int) $user->supplier_id : null;
    }

    private function canViewAllSuppliers(): bool
    {
        $user = auth()->user();

        return $user->is_super_admin === 'Yes'
            || $user->hasRole('super-admin')
            || $user->hasRole('admin');
    }

    private function authorizeProductFilter(mixed $productId, ?int $supplierId, bool $canViewAllSuppliers): void
    {
        if (! $productId || $canViewAllSuppliers) {
            return;
        }

        if (! $supplierId) {
            abort(403, 'You do not have permission to filter by this product.');
        }

        $isAllowedProduct = Product::query()
            ->where('id', $productId)
            ->where('supplier_id', $supplierId)
            ->exists();

        if (! $isAllowedProduct) {
            abort(403, 'You do not have permission to filter by this product.');
        }
    }
}
