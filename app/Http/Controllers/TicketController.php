<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Http\Requests\TicketRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Ticket;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ProductPricingService;
use App\Services\TicketEntryForms;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Arr;
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
            new Middleware('can:ticket-list', only: ['index', 'show']),
            new Middleware('can:ticket-create', only: ['create', 'store', 'batches', 'adjustmentBatches']),
            new Middleware('can:ticket-edit', only: ['edit', 'update']),
            new Middleware('can:ticket-delete', only: ['destroy']),
            new Middleware('can:ticket-approve', only: ['approve', 'reject']),
        ];
    }

    public const PER_PAGE = [15, 25, 50, 100, 500, 'all'];

    private const SORTS = ['created_at', 'ticket_number', 'title', 'status'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = array_filter((array) $request->input('filter', []), fn ($value) => $value !== null && $value !== '');

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
            ->with(['supplier', 'creator', 'reviewer'])
            ->withCount('items')
            ->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc')
            ->orderByDesc('id')
            ->paginate($perPage === 'all' ? max(1, (int) $counts->sum()) : (int) $perPage)
            ->withQueryString();

        return view('tickets.index', [
            'tickets' => $tickets,
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
            'perPage' => $perPage,
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredTickets(User $user, array $filters)
    {
        return Ticket::query()
            ->visibleTo($user)
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->when($filters['type'] ?? null, fn ($q, $value) => $q->where('type', $value))
            ->when($filters['supplier_id'] ?? null, fn ($q, $value) => $q->where('supplier_id', $value))
            ->when($filters['created_by'] ?? null, fn ($q, $value) => $q->where('created_by', $value))
            ->when($filters['date_from'] ?? null, fn ($q, $value) => $q->whereDate('created_at', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($q, $value) => $q->whereDate('created_at', '<=', $value))
            ->when($filters['search'] ?? null, function ($q, $value) {
                $term = '%'.$value.'%';
                $q->where(fn ($q) => $q->where('title', 'like', $term)->orWhere('ticket_number', 'like', $term));
            });
    }

    public function create(Request $request): View
    {
        return view('tickets.create', $this->formData($request, TicketType::tryFrom((string) $request->query('type')) ?? TicketType::PriceUpdate));
    }

    public function store(TicketRequest $request): RedirectResponse
    {
        $ticket = $this->tickets->create($request->user(), $request->validated());

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
        $ticket->delete();

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

        abort_unless($request->user()->can($permission), 403, "Approving this ticket needs the {$permission} permission.");

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
            ->groupBy('sb.id', 'sb.batch_code', 'sb.expiry_date', 'sb.unit_cost')
            ->orderBy('sb.expiry_date')
            ->get(['sb.id', 'sb.batch_code', 'sb.expiry_date', 'sb.unit_cost', DB::raw('SUM(csb.quantity_on_hand) as quantity')]);

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

        return [
            'type' => $type,
            'types' => TicketType::cases(),
            'products' => $products->map(fn (Product $p) => [
                'id' => $p->id,
                'label' => "{$p->product_code} — {$p->product_name}",
                'active' => $p->is_active,
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
                ->when(! $user->isTicketAdmin() && $user->supplier_id, fn ($q) => $q->where('id', $user->supplier_id))
                ->orderBy('supplier_name')->get(['id', 'supplier_name']),
            'valuationMethods' => Product::VALUATION_METHODS,
            'warehouses' => Warehouse::query()->where('disabled', false)->orderBy('warehouse_name')->get(['id', 'warehouse_name']),
            'adjustmentTypes' => TicketRequest::ADJUSTMENT_TYPES,
            'entryFields' => TicketEntryForms::fields($type, Supplier::query()
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
