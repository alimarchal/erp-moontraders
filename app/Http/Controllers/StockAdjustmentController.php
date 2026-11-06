<?php

namespace App\Http\Controllers;

use App\Models\InventoryLedgerEntry;
use App\Models\Product;
use App\Models\ProductPriceChangeLog;
use App\Models\StockAdjustment;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\Warehouse;
use App\Services\StockAdjustmentService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;

class StockAdjustmentController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:stock-adjustment-list', only: ['index', 'show', 'help']),
            new Middleware('permission:stock-adjustment-create', only: ['create', 'store']),
            new Middleware('permission:stock-adjustment-edit', only: ['edit', 'update']),
            new Middleware('permission:stock-adjustment-delete', only: ['destroy']),
            new Middleware('permission:stock-adjustment-post', only: ['post']),
        ];
    }

    /** Rows-per-page choices on the list. */
    public const PER_PAGE = [25, 50, 100, 200, 'all'];

    private const SORTS = ['adjustment_date', 'adjustment_number', 'created_at'];

    public function index(Request $request)
    {
        $filters = array_filter((array) $request->input('filter', []), fn ($value) => $value !== null && $value !== '');
        $sort = (string) $request->input('sort', '-adjustment_date');
        $sort = in_array(ltrim($sort, '-'), self::SORTS, true) ? $sort : '-adjustment_date';
        $perPage = (string) $request->input('per_page', '25');
        $perPage = in_array($perPage, array_map('strval', self::PER_PAGE), true) ? $perPage : '25';
        $userSupplierId = $this->getUserSupplierScope();

        $scoped = fn () => StockAdjustment::query()
            ->when($userSupplierId !== null, fn ($query) => $query->whereHas('items.product', fn ($product) => $product->where('supplier_id', $userSupplierId)))
            ->when($filters['search'] ?? null, function ($query, $value) {
                $term = '%'.mb_strtolower(trim((string) $value)).'%';
                $query->where(fn ($q) => $q->whereRaw('LOWER(adjustment_number) LIKE ?', [$term])->orWhereRaw('LOWER(reason) LIKE ?', [$term]));
            })
            ->when($filters['warehouse_id'] ?? null, fn ($query, $value) => $query->where('warehouse_id', $value))
            ->when($filters['adjustment_type'] ?? null, fn ($query, $value) => $query->where('adjustment_type', $value))
            ->when($filters['date_from'] ?? null, fn ($query, $value) => $query->whereDate('adjustment_date', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($query, $value) => $query->whereDate('adjustment_date', '<=', $value));

        $statusCounts = $scoped()->reorder()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $query = $scoped()
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->with(['warehouse', 'createdBy', 'postedBy'])
            ->withCount('items')
            ->withSum('items', 'adjustment_quantity')
            ->withSum('items', 'adjustment_value')
            ->addSelect(['revaluation_value' => InventoryLedgerEntry::query()
                ->selectRaw('COALESCE(SUM(total_value), 0)')
                ->whereColumn('stock_adjustment_id', 'stock_adjustments.id')
                ->where('debit_qty', 0)->where('credit_qty', 0),
            ])
            ->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc')
            ->orderByDesc('id');

        $adjustments = $query->paginate($perPage === 'all' ? max(1, (int) $statusCounts->sum()) : (int) $perPage)->withQueryString();

        return view('stock-adjustments.index', [
            'adjustments' => $adjustments,
            'filters' => $filters,
            'sort' => $sort,
            'perPage' => $perPage,
            'statusCounts' => $statusCounts,
            'warehouses' => Warehouse::orderBy('warehouse_name')->get(['id', 'warehouse_name']),
        ]);
    }

    /**
     * Step-by-step guide, English and Urdu.
     */
    public function help()
    {
        return view('stock-adjustments.help');
    }

    public function create()
    {
        $userSupplierId = $this->getUserSupplierScope();

        return view('stock-adjustments.create', [
            'warehouses' => Warehouse::where('disabled', false)->orderBy('warehouse_name')->get(),
            'suppliers' => Supplier::where('disabled', false)
                ->when($userSupplierId !== null, fn ($query) => $query->where('id', $userSupplierId))
                ->orderBy('supplier_name')->get(['id', 'supplier_name']),
            'uoms' => Uom::where('enabled', true)->orderBy('uom_name')->get(),
        ]);
    }

    public function getProductsBySupplier(Request $request, int $supplierId)
    {
        $userSupplierId = $this->getUserSupplierScope();

        if ($userSupplierId !== null && $supplierId !== $userSupplierId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json(Product::where('is_active', true)
            ->where('supplier_id', $supplierId)
            ->with('uom:id,uom_name')
            ->orderBy('product_name')
            ->get(['id', 'product_name', 'uom_id', 'supplier_id']));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'adjustment_date' => 'required|date|before_or_equal:today',
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'adjustment_type' => 'required|in:damage,theft,count_variance,expiry,recall,other',
            'reason' => 'required|string|max:1000',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.stock_batch_id' => 'required|exists:stock_batches,id',
            'items.*.system_quantity' => 'required|numeric|min:0',
            'items.*.actual_quantity' => 'required|numeric|min:0',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.selling_price' => 'nullable|numeric|min:0',
            'items.*.selling_scope' => 'nullable|in:batch,all',
            'items.*.uom_id' => 'required|exists:uoms,id',
        ]);

        $this->authorizeSupplierId((int) $validated['supplier_id']);
        $this->validateProductsBelongToSupplier($validated['items'], (int) $validated['supplier_id']);

        if ($error = $this->unconfirmedRevaluationError($request, $validated['items'])) {
            return redirect()->back()->with('error', $error)->withInput();
        }

        $validated['items'] = $this->normalizeSellingCosts($validated['items']);

        foreach ($validated['items'] as &$item) {
            $item['adjustment_quantity'] = $item['actual_quantity'] - $item['system_quantity'];
            $item['adjustment_value'] = $item['adjustment_quantity'] * $item['unit_cost'];
        }
        unset($item);

        $service = app(StockAdjustmentService::class);
        $result = $service->createAdjustment($validated);

        if ($result['success']) {
            return redirect()->route('stock-adjustments.show', $result['data']->id)
                ->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message'])->withInput();
    }

    public function show(StockAdjustment $stockAdjustment)
    {
        $this->authorizeAdjustmentAccess($stockAdjustment);
        $stockAdjustment->load(['warehouse', 'items.product', 'items.stockBatch', 'items.uom', 'journalEntry', 'createdBy', 'postedBy']);

        // A posted line whose unit cost changed has a zero-quantity inventory ledger row carrying the
        // revaluation, so the cost the batch was carried at before is: new cost - revaluation / quantity.
        $costChanges = [];
        if ($stockAdjustment->status === 'posted') {
            $revaluations = InventoryLedgerEntry::query()
                ->where('stock_adjustment_id', $stockAdjustment->id)
                ->where('debit_qty', 0)->where('credit_qty', 0)
                ->get(['stock_batch_id', 'total_value'])
                ->groupBy('stock_batch_id');

            foreach ($stockAdjustment->items as $item) {
                $revaluation = (float) ($revaluations->get($item->stock_batch_id)?->sum('total_value') ?? 0);
                $quantity = (float) $item->system_quantity;

                if (abs($revaluation) > 0.004 && $quantity > 0) {
                    $costChanges[$item->id] = ['old' => (float) $item->unit_cost - $revaluation / $quantity, 'new' => (float) $item->unit_cost, 'value' => $revaluation];
                }
            }
        }

        // Selling cost: what the batch sells at now, and the change a ticket made to it when this was posted
        // (the price log row is written in the same transaction, so it sits within moments of the posting).
        $sellingPrices = StockBatch::query()->whereIn('id', $stockAdjustment->items->pluck('stock_batch_id'))->pluck('selling_price', 'id');
        $sellingChanges = [];
        if ($stockAdjustment->posted_at) {
            foreach ($stockAdjustment->items as $item) {
                $log = ProductPriceChangeLog::query()
                    ->where('product_id', $item->product_id)
                    ->where('price_type', 'selling_price')
                    ->whereBetween('changed_at', [$stockAdjustment->posted_at->copy()->subMinutes(2), $stockAdjustment->posted_at->copy()->addMinutes(2)])
                    ->latest('id')->get()
                    ->first(fn ($row) => in_array((int) $item->stock_batch_id, array_map('intval', (array) $row->impacted_batch_ids), true));

                if ($log) {
                    $sellingChanges[$item->id] = ['old' => (float) $log->old_price, 'new' => (float) $log->new_price];
                }
            }
        }

        // A draft has not changed anything yet: show what posting will change.
        $pending = [];
        if ($stockAdjustment->status === 'draft') {
            $batchCosts = StockBatch::query()->whereIn('id', $stockAdjustment->items->pluck('stock_batch_id'))->pluck('unit_cost', 'id');
            foreach ($stockAdjustment->items as $item) {
                $pending[$item->id] = [
                    'selling' => $item->selling_price !== null && abs((float) $item->selling_price - (float) ($sellingPrices[$item->stock_batch_id] ?? 0)) > 0.004
                        ? ['old' => (float) ($sellingPrices[$item->stock_batch_id] ?? 0), 'new' => (float) $item->selling_price, 'all' => $item->selling_scope === 'all'] : null,
                    'cost' => abs((float) $item->unit_cost - (float) ($batchCosts[$item->stock_batch_id] ?? $item->unit_cost)) > 0.01
                        ? ['old' => (float) $batchCosts[$item->stock_batch_id], 'new' => (float) $item->unit_cost] : null,
                ];
            }
        }

        return view('stock-adjustments.show', ['stockAdjustment' => $stockAdjustment, 'costChanges' => $costChanges, 'sellingPrices' => $sellingPrices, 'sellingChanges' => $sellingChanges, 'pending' => $pending]);
    }

    public function edit(StockAdjustment $stockAdjustment)
    {
        $this->authorizeAdjustmentAccess($stockAdjustment);

        if ($stockAdjustment->status !== 'draft') {
            return redirect()->route('stock-adjustments.show', $stockAdjustment)
                ->with('error', 'Only draft adjustments can be edited');
        }

        $stockAdjustment->load('items.product', 'items.stockBatch', 'items.uom');

        return view('stock-adjustments.edit', [
            'adjustment' => $stockAdjustment,
            'warehouses' => Warehouse::where('disabled', false)->orderBy('warehouse_name')->get(),
            'suppliers' => Supplier::query()
                ->when($this->getUserSupplierScope() !== null, fn ($query) => $query->where('id', $this->getUserSupplierScope()))
                ->orderBy('supplier_name')->get(['id', 'supplier_name']),
        ]);
    }

    public function update(Request $request, StockAdjustment $stockAdjustment)
    {
        $this->authorizeAdjustmentAccess($stockAdjustment);

        if ($stockAdjustment->status !== 'draft') {
            return redirect()->route('stock-adjustments.show', $stockAdjustment)
                ->with('error', 'Only draft adjustments can be updated');
        }

        $validated = $request->validate([
            'adjustment_date' => 'required|date|before_or_equal:today',
            'warehouse_id' => 'required|exists:warehouses,id',
            'adjustment_type' => 'required|in:damage,theft,count_variance,expiry,recall,other',
            'reason' => 'required|string|max:1000',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.stock_batch_id' => 'required|exists:stock_batches,id',
            'items.*.system_quantity' => 'required|numeric|min:0',
            'items.*.actual_quantity' => 'required|numeric|min:0',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.selling_price' => 'nullable|numeric|min:0',
            'items.*.selling_scope' => 'nullable|in:batch,all',
            'items.*.uom_id' => 'required|exists:uoms,id',
        ]);

        $validated['items'] = $this->normalizeSellingCosts($validated['items']);

        foreach ($validated['items'] as &$item) {
            $item['adjustment_quantity'] = $item['actual_quantity'] - $item['system_quantity'];
            $item['adjustment_value'] = $item['adjustment_quantity'] * $item['unit_cost'];
        }
        unset($item);

        if ($error = $this->unconfirmedRevaluationError($request, $validated['items'])) {
            return redirect()->back()->with('error', $error)->withInput();
        }

        $stockAdjustment->update(Arr::except($validated, ['items']));
        $stockAdjustment->items()->delete();

        foreach ($validated['items'] as $item) {
            $stockAdjustment->items()->create($item);
        }

        return redirect()->route('stock-adjustments.show', $stockAdjustment)
            ->with('success', 'Stock adjustment updated successfully');
    }

    public function destroy(StockAdjustment $stockAdjustment)
    {
        $this->authorizeAdjustmentAccess($stockAdjustment);

        if ($stockAdjustment->status !== 'draft') {
            return redirect()->route('stock-adjustments.index')
                ->with('error', 'Only draft adjustments can be deleted');
        }

        $stockAdjustment->delete();

        return redirect()->route('stock-adjustments.index')
            ->with('success', 'Stock adjustment deleted successfully');
    }

    public function post(Request $request, StockAdjustment $stockAdjustment)
    {
        $this->authorizeAdjustmentAccess($stockAdjustment);

        $request->validate([
            'password' => 'required',
        ]);

        if (! Hash::check($request->password, auth()->user()->password)) {
            return redirect()->back()->with('error', 'Invalid password');
        }

        $service = app(StockAdjustmentService::class);
        $result = $service->postAdjustment($stockAdjustment);

        if ($result['success']) {
            return redirect()->route('stock-adjustments.show', $stockAdjustment)
                ->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }

    public function getBatchesForProduct(Request $request, $productId, $warehouseId)
    {
        $batches = StockBatch::where('product_id', $productId)
            ->where('status', 'active')
            ->with(['currentStockByBatch' => function ($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId)->where('quantity_on_hand', '>', 0);
            }])
            ->get()
            ->filter(function ($batch) {
                return $batch->currentStockByBatch->isNotEmpty() &&
                       $batch->currentStockByBatch->sum('quantity_on_hand') > 0;
            })
            ->values();

        return response()->json($batches);
    }

    /**
     * A selling cost is kept on a line only when it changes the batch's selling price (or is meant for all
     * batches), so a draft never puts back an old price when it is posted later.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeSellingCosts(array $items): array
    {
        foreach ($items as &$item) {
            $price = $item['selling_price'] ?? null;
            $scope = ($item['selling_scope'] ?? 'batch') === 'all' ? 'all' : 'batch';
            $filled = $price !== null && $price !== '';
            $current = (float) StockBatch::whereKey($item['stock_batch_id'])->value('selling_price');
            $keep = $filled && ($scope === 'all' || abs((float) $price - $current) > 0.004);

            $item['selling_price'] = $keep ? $price : null;
            $item['selling_scope'] = $keep ? $scope : 'batch';
        }
        unset($item);

        return $items;
    }

    private function getUserSupplierScope(): ?int
    {
        $user = auth()->user();

        if ($user->is_super_admin === 'Yes' || $user->hasAnyRole(['super-admin', 'admin'])) {
            return null;
        }

        return $user->supplier_id ? (int) $user->supplier_id : null;
    }

    /**
     * A line priced off its batch's cost revalues the batch when it posts, which moves the
     * whole quantity on hand to the new cost. A mistyped cost (3060.35 for 360.35) does that
     * silently, so the person has to say the change is deliberate.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function unconfirmedRevaluationError(Request $request, array $items): ?string
    {
        if ($request->boolean('confirm_revaluation')) {
            return null;
        }

        $batches = StockBatch::query()
            ->whereIn('id', collect($items)->pluck('stock_batch_id'))
            ->get(['id', 'batch_code', 'unit_cost'])
            ->keyBy('id');

        $differs = collect($items)->filter(function (array $item) use ($batches): bool {
            $batch = $batches->get($item['stock_batch_id']);

            return $batch !== null && abs((float) $item['unit_cost'] - (float) $batch->unit_cost) > 0.01;
        });

        if ($differs->isEmpty()) {
            return null;
        }

        $lines = $differs->map(fn (array $item) => sprintf(
            '%s: entered %s, batch cost %s',
            $batches[$item['stock_batch_id']]->batch_code,
            number_format((float) $item['unit_cost'], 2),
            number_format((float) $batches[$item['stock_batch_id']]->unit_cost, 2)
        ))->implode('; ');

        return "A unit cost differs from its batch's cost ({$lines}). Correct it, or tick the revaluation box if the batch should be carried at the new cost.";
    }

    private function authorizeSupplierId(int $supplierId): void
    {
        $userSupplierId = $this->getUserSupplierScope();

        if ($userSupplierId !== null && $supplierId !== $userSupplierId) {
            abort(403, 'You do not have permission to access this supplier.');
        }
    }

    private function authorizeAdjustmentAccess(StockAdjustment $stockAdjustment): void
    {
        $userSupplierId = $this->getUserSupplierScope();

        if ($userSupplierId !== null && ! $stockAdjustment->items()->whereHas('product', fn ($query) => $query->where('supplier_id', $userSupplierId))->exists()) {
            abort(403, 'You do not have permission to access this stock adjustment.');
        }
    }

    private function validateProductsBelongToSupplier(array $items, int $supplierId): void
    {
        $productIds = collect($items)->pluck('product_id')->unique();
        $authorizedProductCount = Product::whereIn('id', $productIds)->where('supplier_id', $supplierId)->count();

        if ($authorizedProductCount !== $productIds->count()) {
            abort(422, 'All products must belong to the selected supplier.');
        }
    }
}
