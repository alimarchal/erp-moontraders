<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Http\Requests\TicketRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ProductPricingService;
use App\Services\TicketEntryForms;
use App\Services\TicketService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TicketController extends Controller implements HasMiddleware
{
    public function __construct(private TicketService $tickets, private ProductPricingService $pricing) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:ticket-list', only: ['index', 'show', 'help', 'checkCustomerCode']),
            new Middleware('can:ticket-create', only: ['create', 'store', 'batches', 'adjustmentBatches']),
            new Middleware('can:ticket-edit', only: ['edit', 'update']),
            new Middleware('can:ticket-delete', only: ['destroy']),
            new Middleware('can:ticket-approve', only: ['approve', 'reject', 'updateCustomerCode']),
        ];
    }

    public const PER_PAGE = [15, 25, 50, 100, 500, 'all'];

    private const SORTS = ['created_at', 'ticket_number', 'title', 'status'];

    /** Most products a free-text search or a batch code is expanded to before matching the ticket lines. */
    private const MATCH_LIMIT = 300;

    /** Payload keys the free-text search also looks at (new SKU, customer, claim, ledger and stock adjustment tickets). */
    private const SEARCHED_PAYLOAD_KEYS = ['product_code', 'product_name', 'customer_name', 'business_name', 'customer_code', 'city', 'stock_adjustment_number', 'reference_number', 'document_number'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = array_filter((array) $request->input('filter', []), fn ($value) => $value !== null && $value !== '');

        // The list opens on the tickets waiting for a decision; the "All" tab sends filter[status]=all.
        if (! isset($filters['status'])) {
            $filters['status'] = TicketStatus::Pending->value;
            $request->merge(['filter' => array_merge((array) $request->input('filter', []), ['status' => $filters['status']])]);
        }

        $sort = (string) $request->input('sort', '-created_at');
        if (! in_array(ltrim($sort, '-'), self::SORTS, true)) {
            $sort = '-created_at';
        }

        $perPage = (string) $request->input('per_page', '25');
        if (! in_array($perPage, array_map('strval', self::PER_PAGE), true)) {
            $perPage = '25';
        }

        $counts = $this->filteredTickets($user, Arr::except($filters, 'status'))
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $tickets = $this->filteredTickets($user, $filters)
            ->with(['supplier', 'creator', 'reviewer', 'items'])
            ->withCount('items')
            ->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc')
            ->orderByDesc('id')
            ->paginate($perPage === 'all' ? max(1, (int) $counts->sum()) : (int) $perPage)
            ->withQueryString();

        return view('tickets.index', [
            'tickets' => $tickets,
            'lookups' => $this->detailLookups($tickets->getCollection()),
            'stats' => [
                'total' => (int) $counts->sum(),
                'pending' => (int) ($counts[TicketStatus::Pending->value] ?? 0),
                'approved' => (int) ($counts[TicketStatus::Approved->value] ?? 0),
                'rejected' => (int) ($counts[TicketStatus::Rejected->value] ?? 0),
            ],
            'types' => TicketType::cases(),
            'suppliers' => Supplier::query()
                ->when(! $user->isTicketAdmin() && $user->supplier_id, fn ($q) => $q->where('id', $user->supplier_id))
                ->orderBy('supplier_name')->get(['id', 'supplier_name']),
            'creators' => User::query()
                ->whereIn('id', Ticket::query()->visibleTo($user)->select('created_by'))
                ->orderBy('name')->get(['id', 'name']),
            'reviewers' => User::query()
                ->whereIn('id', Ticket::query()->visibleTo($user)->whereNotNull('reviewed_by')->select('reviewed_by'))
                ->orderBy('name')->get(['id', 'name']),
            'products' => $this->productsFor($request)->orderBy('product_code')->get(['id', 'product_code', 'product_name']),
            'warehouses' => Warehouse::query()->orderBy('warehouse_name')->get(['id', 'warehouse_name']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'adjustmentTypes' => TicketRequest::ADJUSTMENT_TYPES,
            // Counted like the Details cell: each stock adjustment line, and one per other ticket line.
            'itemsTotal' => TicketItem::query()->whereIn('ticket_id', $this->filteredTickets($user, $filters)->select('id'))->get(['id', 'payload'])
                ->sum(fn (TicketItem $item) => max(1, count((array) (($item->payload ?? [])['items'] ?? [])))),
            'perPage' => $perPage,
        ]);
    }

    /**
     * Tickets the user may see, narrowed by the list filters (status "all" keeps every status).
     *
     * Product, batch, warehouse and adjustment type also look inside the stock adjustment lines kept in the
     * item payload, where ids may be stored as strings or as numbers.
     *
     * @param  array{status?: string, type?: string, supplier_id?: string, created_by?: string, reviewed_by?: string, date_from?: string, date_to?: string, search?: string, product_id?: string, batch?: string, warehouse_id?: string, adjustment_type?: string, category_id?: string}  $filters
     * @return Builder<Ticket>
     */
    private function filteredTickets(User $user, array $filters): Builder
    {
        return Ticket::query()
            ->visibleTo($user)
            ->when($filters['status'] ?? null, fn ($q, $value) => $value === 'all' ? $q : $q->where('status', $value))
            ->when($filters['type'] ?? null, fn ($q, $value) => $q->where('type', $value))
            ->when($filters['supplier_id'] ?? null, fn ($q, $value) => $q->where('supplier_id', $value))
            ->when($filters['created_by'] ?? null, fn ($q, $value) => $q->where('created_by', $value))
            ->when($filters['reviewed_by'] ?? null, fn ($q, $value) => $q->where('reviewed_by', $value))
            ->when($filters['date_from'] ?? null, fn ($q, $value) => $q->whereDate('created_at', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($q, $value) => $q->whereDate('created_at', '<=', $value))
            ->when($filters['product_id'] ?? null, fn ($q, $value) => $this->whereProduct($q, (int) $value))
            ->when($filters['batch'] ?? null, fn ($q, $value) => $this->whereBatch($q, (string) $value))
            ->when($filters['warehouse_id'] ?? null, fn ($q, $value) => $q->whereHas('items', fn ($items) => $items->where('ticket_items.payload->warehouse_id', (string) $value)))
            ->when($filters['adjustment_type'] ?? null, fn ($q, $value) => $q->whereHas('items', fn ($items) => $items->where('ticket_items.payload->adjustment_type', (string) $value)))
            ->when($filters['category_id'] ?? null, fn ($q, $value) => $q->whereHas('items', fn ($items) => $items->where(fn ($items) => $items
                ->where('ticket_items.payload->category_id', (string) $value)
                ->orWhereIn('ticket_items.product_id', Product::withTrashed()->select('id')->where('category_id', (int) $value)))))
            ->when($filters['search'] ?? null, fn ($q, $value) => $this->whereSearch($q, (string) $value));
    }

    /**
     * Tickets about one product: price / re-activation lines, stock adjustment lines, or a new SKU with its code.
     *
     * @param  Builder<Ticket>  $query
     */
    private function whereProduct(Builder $query, int $productId): void
    {
        $code = Product::withTrashed()->whereKey($productId)->value('product_code');

        $query->whereHas('items', fn ($items) => $items->where(function ($items) use ($productId, $code) {
            $items->where('ticket_items.product_id', $productId);
            $this->orWhereAdjustmentLine($items, 'product_id', [$productId]);

            if ($code !== null) {
                $items->orWhere('ticket_items.payload->product_code', $code);
            }
        }));
    }

    /**
     * Tickets touching a batch whose code contains the text: stock adjustment lines or price changes for chosen batches.
     *
     * @param  Builder<Ticket>  $query
     */
    private function whereBatch(Builder $query, string $code): void
    {
        $batchIds = DB::table('stock_batches')
            ->whereRaw('LOWER(batch_code) LIKE ?', ['%'.mb_strtolower(trim($code)).'%'])
            ->limit(self::MATCH_LIMIT)->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($batchIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('items', fn ($items) => $items->where(function ($items) use ($batchIds) {
            $this->orWhereAdjustmentLine($items, 'stock_batch_id', $batchIds);

            foreach ($batchIds as $batchId) {
                $items->orWhereJsonContains('ticket_items.batch_ids', [$batchId])
                    ->orWhereJsonContains('ticket_items.batch_ids', [(string) $batchId]);
            }
        }));
    }

    /**
     * Case-insensitive search over the ticket itself, its products (by code or name) and the main payload fields.
     *
     * @param  Builder<Ticket>  $query
     */
    private function whereSearch(Builder $query, string $value): void
    {
        $term = '%'.mb_strtolower(trim($value)).'%';

        $productIds = Product::withTrashed()
            ->where(fn ($q) => $q->whereRaw('LOWER(product_code) LIKE ?', [$term])->orWhereRaw('LOWER(product_name) LIKE ?', [$term]))
            ->limit(self::MATCH_LIMIT)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $query->where(fn ($q) => $q
            ->whereRaw('LOWER(title) LIKE ?', [$term])
            ->orWhereRaw('LOWER(ticket_number) LIKE ?', [$term])
            ->orWhereRaw('LOWER(description) LIKE ?', [$term])
            ->orWhereHas('items', fn ($items) => $items->where(function ($items) use ($term, $productIds) {
                $grammar = $items->getQuery()->getGrammar();

                foreach (self::SEARCHED_PAYLOAD_KEYS as $key) {
                    $items->orWhereRaw('LOWER('.$grammar->wrap("ticket_items.payload->{$key}").') LIKE ?', [$term]);
                }

                if ($productIds !== []) {
                    $items->orWhereIn('ticket_items.product_id', $productIds);
                    $this->orWhereAdjustmentLine($items, 'product_id', $productIds);
                }
            })));
    }

    /**
     * OR-matches stock adjustment lines (payload "items") whose key holds one of the ids, stored as a string or a number.
     *
     * @param  Builder<TicketItem>  $items
     * @param  array<int, int>  $ids
     */
    private function orWhereAdjustmentLine(Builder $items, string $key, array $ids): void
    {
        foreach ($ids as $id) {
            $items->orWhereJsonContains('ticket_items.payload->items', [[$key => (string) $id]])
                ->orWhereJsonContains('ticket_items.payload->items', [[$key => $id]]);
        }
    }

    /**
     * Names behind the ids kept on this page's ticket lines, one query per kind, for the Details cell.
     *
     * @param  Collection<int, Ticket>  $tickets
     * @return array{products: Collection<int, Product>, batches: Collection<int, string>, warehouses: Collection<int, string>, suppliers: Collection<int, string>}
     */
    private function detailLookups(Collection $tickets): array
    {
        $items = $tickets->flatMap(fn (Ticket $ticket) => $ticket->items);
        $payloads = $items->map(fn (TicketItem $item) => (array) ($item->payload ?? []));
        $lines = $payloads->flatMap(fn (array $payload) => (array) ($payload['items'] ?? []));
        $ids = fn ($values) => collect($values)->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->unique()->values()->all();

        $productIds = $ids($items->pluck('product_id')->merge($lines->pluck('product_id')));
        $batchIds = $ids($lines->pluck('stock_batch_id'));
        $warehouseIds = $ids($payloads->pluck('warehouse_id'));
        $supplierIds = $ids($payloads->pluck('supplier_id'));

        return [
            'products' => $productIds === [] ? collect() : Product::withTrashed()->whereIn('id', $productIds)->get(['id', 'product_code', 'product_name'])->keyBy('id'),
            'batches' => $batchIds === [] ? collect() : DB::table('stock_batches')->whereIn('id', $batchIds)->pluck('batch_code', 'id'),
            'warehouses' => $warehouseIds === [] ? collect() : Warehouse::query()->whereIn('id', $warehouseIds)->pluck('warehouse_name', 'id'),
            'suppliers' => $supplierIds === [] ? collect() : Supplier::query()->whereIn('id', $supplierIds)->pluck('supplier_name', 'id'),
        ];
    }

    /**
     * Short user manual in Urdu (opens in its own tab, printable).
     */
    public function help(): View
    {
        return view('tickets.help');
    }

    public function create(Request $request): View
    {
        return view('tickets.create', $this->formData($request, TicketType::tryFrom((string) $request->query('type')) ?? TicketType::PriceUpdate));
    }

    public function store(TicketRequest $request): RedirectResponse
    {
        // A double click (or a replayed form) carries the same one-time token: only the first one creates a ticket.
        $token = (string) $request->input('_ticket_token');
        $tokenKey = 'ticket-submitted:'.$request->user()->id.':'.$token;

        if ($token !== '' && ! Cache::add($tokenKey, true, now()->addMinutes(10))) {
            return redirect()->route('tickets.index')->with('warning', 'This ticket was already submitted — it is in the list below.');
        }

        try {
            $ticket = $this->tickets->create($request->user(), $request->validated());
        } catch (\Throwable $e) {
            Cache::forget($tokenKey);

            throw $e;
        }

        return redirect()->route('tickets.show', $ticket)
            ->with('success', "Ticket {$ticket->ticket_number} submitted for approval.");
    }

    public function show(Request $request, Ticket $ticket): View
    {
        $this->authorizeVisible($request, $ticket);

        $ticket->load(['supplier', 'creator', 'reviewer', 'items.product', 'histories.user']);

        return view('tickets.show', [
            'ticket' => $ticket,
            'batchLabels' => $this->batchLabels($ticket),
            'categories' => Category::pluck('name', 'id'),
            'uoms' => Uom::pluck('uom_name', 'id'),
            'suppliers' => Supplier::pluck('supplier_name', 'id'),
            'entryFields' => TicketEntryForms::fields($ticket->type, Supplier::pluck('supplier_name', 'id')->all()),
        ]);
    }

    public function edit(Request $request, Ticket $ticket): View
    {
        $this->authorizeEditable($request, $ticket);

        $ticket->load('items');

        return view('tickets.edit', ['ticket' => $ticket] + $this->formData($request, $ticket->type));
    }

    public function update(TicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->authorizeEditable($request, $ticket);

        $this->tickets->update($ticket, $request->user(), $request->validated());

        return redirect()->route('tickets.show', $ticket)->with('success', "Ticket {$ticket->ticket_number} updated.");
    }

    public function destroy(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorizeEditable($request, $ticket);

        $number = $ticket->ticket_number;
        $this->tickets->delete($ticket);

        return redirect()->route('tickets.index')->with('success', "Ticket {$number} deleted.");
    }

    public function approve(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorizeVisible($request, $ticket);
        $data = $request->validate(['review_remarks' => ['nullable', 'string', 'max:2000']]);
        $this->authorizeStockAdjustmentPosting($request, $ticket);

        $this->tickets->approve($ticket, $request->user(), $data['review_remarks'] ?? null);

        return redirect()->route('tickets.show', $ticket)
            ->with('success', "Ticket {$ticket->ticket_number} approved and applied to the system.");
    }

    /**
     * Live "is this code free?" answer for the customer code boxes.
     */
    public function checkCustomerCode(Request $request): JsonResponse
    {
        $code = strtoupper(trim((string) $request->query('code')));
        $except = $request->query('except') ? Ticket::query()->where('uuid', $request->query('except'))->first() : null;
        $available = $this->tickets->customerCodeAvailable($code, $except);

        return response()->json([
            'code' => $code,
            'available' => $available,
            'message' => $code === '' ? 'Enter a customer code.' : ($available ? "{$code} is available." : "{$code} is already used by a customer or another waiting ticket."),
        ]);
    }

    public function updateCustomerCode(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorizeVisible($request, $ticket);
        $data = $request->validate(['customer_code' => ['required', 'string', 'max:191']]);

        $this->tickets->changeCustomerCode($ticket, $request->user(), $data['customer_code']);

        return redirect()->route('tickets.show', $ticket)->with('success', 'Customer code updated.');
    }

    public function reject(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorizeVisible($request, $ticket);
        $data = $request->validate(['review_remarks' => ['required', 'string', 'max:2000']]);

        $this->tickets->reject($ticket, $request->user(), $data['review_remarks']);

        return redirect()->route('tickets.show', $ticket)->with('success', "Ticket {$ticket->ticket_number} rejected.");
    }

    /**
     * Approving performs the real action (posting stock, creating a ledger entry, claim or customer), so the
     * approver needs that right too; stock adjustments, which move stock and accounts, also ask for the password.
     */
    private function authorizeStockAdjustmentPosting(Request $request, Ticket $ticket): void
    {
        $permission = $ticket->type->applyPermission();

        if ($permission === null) {
            return;
        }

        if (! $request->user()->can($permission)) {
            throw ValidationException::withMessages(['ticket' => "You cannot approve this ticket: it needs the {$permission} permission. Ask a super admin to add it to your role."]);
        }

        if ($ticket->type !== TicketType::StockAdjustment) {
            return;
        }

        $request->validate(['password' => ['required']]);

        if (! Hash::check((string) $request->input('password'), $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Invalid password.']);
        }
    }

    /**
     * Batches of one product that hold stock in a warehouse, for the stock adjustment lines.
     */
    public function adjustmentBatches(Request $request, Product $product, Warehouse $warehouse): JsonResponse
    {
        abort_unless($this->productsFor($request)->whereKey($product->id)->exists(), 403);

        $batches = DB::table('current_stock_by_batch as csb')
            ->join('stock_batches as sb', 'sb.id', '=', 'csb.stock_batch_id')
            ->where('csb.product_id', $product->id)
            ->where('csb.warehouse_id', $warehouse->id)
            ->where('csb.quantity_on_hand', '>', 0)
            ->where('sb.status', 'active')
            ->groupBy('sb.id', 'sb.batch_code', 'sb.expiry_date', 'sb.unit_cost', 'sb.selling_price')
            ->orderBy('sb.expiry_date')
            ->get(['sb.id', 'sb.batch_code', 'sb.expiry_date', 'sb.unit_cost', 'sb.selling_price', DB::raw('SUM(csb.quantity_on_hand) as quantity')]);

        return response()->json($batches);
    }

    /**
     * Batches of a product that currently hold sellable stock, for the batch drop-down.
     */
    public function batches(Request $request, Product $product): JsonResponse
    {
        abort_unless($this->productsFor($request)->whereKey($product->id)->exists(), 403);

        // The very same batches an "All batches" approval would update.
        $batchIds = $this->pricing->batchIdsWithStock($product);

        $onHand = DB::table('current_stock_by_batch')->whereIn('stock_batch_id', $batchIds)
            ->groupBy('stock_batch_id')->selectRaw('stock_batch_id, sum(quantity_on_hand) as quantity')->pluck('quantity', 'stock_batch_id');
        $inLayers = DB::table('stock_valuation_layers')->whereIn('stock_batch_id', $batchIds)->where('is_depleted', false)
            ->groupBy('stock_batch_id')->selectRaw('stock_batch_id, sum(quantity_remaining) as quantity')->pluck('quantity', 'stock_batch_id');

        $batches = DB::table('stock_batches')
            ->whereIn('id', $batchIds)
            ->where('is_promotional', false)
            ->orderBy('expiry_date')
            ->get(['id', 'batch_code', 'expiry_date', 'selling_price'])
            ->map(fn ($batch) => (object) [
                ...(array) $batch,
                'quantity' => (float) ($onHand[$batch->id] ?? $inLayers[$batch->id] ?? 0),
            ])->values();

        return response()->json($batches);
    }

    private function authorizeVisible(Request $request, Ticket $ticket): void
    {
        // 404, not 403: a ticket of another company must not even reveal that it exists.
        abort_unless($ticket->canBeSeenBy($request->user()), 404);
    }

    /**
     * Only pending tickets can change, by their creator or an admin.
     */
    private function authorizeEditable(Request $request, Ticket $ticket): void
    {
        $this->authorizeVisible($request, $ticket);

        $user = $request->user();

        abort_unless($ticket->created_by === $user->id || $user->isTicketAdmin(), 403);
        abort_unless($ticket->isPending(), 403, 'Only pending tickets can be changed.');
    }

    private function productsFor(Request $request)
    {
        $user = $request->user();

        return Product::query()
            ->when(! $user->isTicketAdmin() && $user->supplier_id, fn ($q) => $q->where('supplier_id', $user->supplier_id));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Request $request, TicketType $type): array
    {
        $user = $request->user();
        $products = $this->productsFor($request)->orderBy('product_name')
            ->get(['id', 'product_code', 'product_name', 'is_active', 'supplier_id', 'uom_id', 'unit_sell_price', 'cost_price', 'expiry_price', 'reorder_level']);

        $stock = $type === TicketType::ReactivateSku
            ? DB::table('current_stock_by_batch')->whereIn('product_id', $products->pluck('id'))->groupBy('product_id')->selectRaw('product_id, sum(quantity_on_hand) as quantity')->pluck('quantity', 'product_id')
            : collect();
        $vanStock = $type === TicketType::ReactivateSku
            ? DB::table('van_stock_batches')->whereIn('product_id', $products->pluck('id'))->groupBy('product_id')->selectRaw('product_id, sum(quantity_on_hand) as quantity')->pluck('quantity', 'product_id')
            : collect();

        return [
            'type' => $type,
            'types' => TicketType::cases(),
            'products' => $products->map(fn (Product $p) => [
                'id' => $p->id,
                'label' => "{$p->product_code} — {$p->product_name}",
                'active' => $p->is_active,
                'stock' => (float) ($stock[$p->id] ?? 0) + (float) ($vanStock[$p->id] ?? 0),
                'supplier_id' => $p->supplier_id,
                'uom_id' => $p->uom_id,
                'unit_sell_price' => (float) $p->unit_sell_price,
                'cost_price' => (float) $p->cost_price,
                'expiry_price' => (float) $p->expiry_price,
                'reorder_level' => (float) $p->reorder_level,
            ])->values(),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'uoms' => Uom::orderBy('uom_name')->get(['id', 'uom_name', 'symbol']),
            'suppliers' => Supplier::query()
                ->where('disabled', false)
                ->when(! $user->isTicketAdmin() && $user->supplier_id, fn ($q) => $q->where('id', $user->supplier_id))
                ->orderBy('supplier_name')->get(['id', 'supplier_name']),
            'valuationMethods' => Product::VALUATION_METHODS,
            'warehouses' => Warehouse::query()->where('disabled', false)->orderBy('warehouse_name')->get(['id', 'warehouse_name']),
            'adjustmentTypes' => TicketRequest::ADJUSTMENT_TYPES,
            'entryFields' => TicketEntryForms::fields($type, Supplier::query()
                ->where('disabled', false)
                ->when(! $user->isTicketAdmin() && $user->supplier_id, fn ($q) => $q->where('id', $user->supplier_id))
                ->orderBy('supplier_name')->pluck('supplier_name', 'id')->all()),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function batchLabels(Ticket $ticket): array
    {
        $ids = $ticket->items->flatMap(fn ($item) => $item->batch_ids ?? [])->unique()->all();

        return $ids === [] ? [] : DB::table('stock_batches')
            ->whereIn('id', $ids)
            ->get(['id', 'batch_code', 'selling_price'])
            ->mapWithKeys(fn ($b) => [$b->id => $b->batch_code.' (current '.number_format((float) $b->selling_price, 2).')'])
            ->all();
    }
}
