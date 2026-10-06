<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\Customer;
use App\Models\LedgerRegister;
use App\Models\Product;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\User;
use App\Notifications\TicketSubmitted;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

use function Illuminate\Support\defer;

class TicketService
{
    public function __construct(private ProductPricingService $pricing) {}

    /**
     * @param  array<string, mixed>  $data  validated TicketRequest payload
     */
    public function create(User $user, array $data): Ticket
    {
        return DB::transaction(function () use ($user, $data) {
            $type = TicketType::from($data['type']);

            $ticket = Ticket::create([
                'title' => $data['title'],
                'type' => $type,
                'status' => TicketStatus::Pending,
                'description' => $data['description'] ?? null,
                'supplier_id' => $this->resolveSupplierId($user, $type, $data),
                'created_by' => $user->id,
            ]);

            $ticket->update(['ticket_number' => 'TKT-'.str_pad((string) $ticket->id, 6, '0', STR_PAD_LEFT)]);

            $this->syncItems($ticket, $data);
            $this->record($ticket, $user, 'created', null, TicketStatus::Pending, $data['description'] ?? null);

            // Mailing happens after the response is sent, so submitting never waits for the mail server.
            DB::afterCommit(fn () => defer(fn () => $this->notifyApprovers($ticket)));

            return $ticket;
        });
    }

    /**
     * Mail every active approver who may see this ticket. A mail problem must never lose the ticket.
     */
    private function notifyApprovers(Ticket $ticket): void
    {
        try {
            $approvers = User::query()
                ->whereNotNull('email')
                ->where(fn ($q) => $q->whereNull('is_active')->orWhere('is_active', '!=', 'No'))
                ->get()
                ->filter(fn (User $user) => $user->can('ticket-approve') && $ticket->canBeSeenBy($user));

            Notification::send($approvers, new TicketSubmitted($ticket));
        } catch (\Throwable $e) {
            Log::warning('Could not mail ticket approvers', ['ticket' => $ticket->ticket_number, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Ticket $ticket, User $user, array $data): Ticket
    {
        return DB::transaction(function () use ($ticket, $user, $data) {
            $ticket->update([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'supplier_id' => $this->resolveSupplierId($user, $ticket->type, $data) ?? $ticket->supplier_id,
            ]);

            $ticket->items()->delete();
            $this->syncItems($ticket, $data);
            $this->record($ticket, $user, 'updated', TicketStatus::Pending, TicketStatus::Pending);

            return $ticket;
        });
    }

    /**
     * Apply the requested change to the live data and close the ticket.
     *
     * @throws ValidationException when the ticket is no longer pending or the change clashes with current data
     */
    public function approve(Ticket $ticket, User $admin, ?string $remarks = null): Ticket
    {
        return DB::transaction(function () use ($ticket, $admin, $remarks) {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->guardPending($ticket);

            $ticket->load('items');

            foreach ($ticket->items as $item) {
                match ($ticket->type) {
                    TicketType::PriceUpdate => $this->applyPriceUpdate($item, $admin),
                    TicketType::NewSku => $this->applyNewSku($item),
                    TicketType::ReactivateSku => $this->applyStatusChange($item),
                    TicketType::StockAdjustment => $postedAdjustment = $this->applyStockAdjustment($item),
                    TicketType::LedgerEntry => $created = $this->applyLedgerEntry($item),
                    TicketType::ClaimEntry => $created = $this->applyClaimEntry($item),
                    TicketType::NewCustomer => $created = $this->applyNewCustomer($item),
                };
            }

            if (isset($created)) {
                $remarks = trim(($remarks ? $remarks.' — ' : '').$created);
            }

            if (isset($postedAdjustment)) {
                $remarks = trim(($remarks ? $remarks.' — ' : '')."Stock adjustment {$postedAdjustment} created and posted.");
            }

            $this->close($ticket, $admin, TicketStatus::Approved, $remarks);

            return $ticket;
        });
    }

    /**
     * @throws ValidationException when the ticket is no longer pending
     */
    public function reject(Ticket $ticket, User $admin, string $remarks): Ticket
    {
        return DB::transaction(function () use ($ticket, $admin, $remarks) {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->guardPending($ticket);

            $this->close($ticket, $admin, TicketStatus::Rejected, $remarks);

            return $ticket;
        });
    }

    /**
     * Company users raise tickets for their own supplier; for admins it follows the data they picked.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveSupplierId(User $user, TicketType $type, array $data): ?int
    {
        if (! $user->isTicketAdmin() && $user->supplier_id) {
            return (int) $user->supplier_id;
        }

        if ($type === TicketType::NewSku) {
            return isset($data['sku']['supplier_id']) ? (int) $data['sku']['supplier_id'] : null;
        }

        if ($type === TicketType::StockAdjustment) {
            return (int) $data['supplier_id'];
        }

        if (in_array($type, [TicketType::LedgerEntry, TicketType::ClaimEntry], true)) {
            return (int) $data['data']['supplier_id'];
        }

        if ($type === TicketType::NewCustomer) {
            return $user->supplier_id ? (int) $user->supplier_id : null;
        }

        $productId = $data['items'][0]['product_id'] ?? null;

        return $productId ? Product::whereKey($productId)->value('supplier_id') : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncItems(Ticket $ticket, array $data): void
    {
        match ($ticket->type) {
            TicketType::NewSku => $ticket->items()->create([
                'payload' => $data['sku'],
                'remarks' => $data['sku']['remarks'] ?? null,
            ]),
            TicketType::StockAdjustment => $ticket->items()->create(['payload' => $this->adjustmentPayload($data)]),
            TicketType::LedgerEntry, TicketType::ClaimEntry, TicketType::NewCustomer => $ticket->items()->create(['payload' => TicketEntryForms::normalize($ticket->type, $data['data'])]),
            TicketType::PriceUpdate => collect($data['items'])->each(fn (array $row) => $this->createPriceItem($ticket, $row)),
            TicketType::ReactivateSku => collect($data['items'])->each(function (array $row) use ($ticket) {
                $product = Product::findOrFail($row['product_id']);
                $ticket->items()->create([
                    'product_id' => $product->id,
                    'old_is_active' => $product->is_active,
                    'new_is_active' => (bool) $row['new_is_active'],
                    'remarks' => $row['remarks'] ?? null,
                ]);
            }),
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function createPriceItem(Ticket $ticket, array $row): void
    {
        $product = Product::findOrFail($row['product_id']);
        $allBatches = ($row['batch_scope'] ?? 'all') === 'all';

        $attributes = [
            'product_id' => $product->id,
            'apply_to_all_batches' => $allBatches,
            'batch_ids' => $allBatches ? null : array_map('intval', $row['batch_ids']),
            'remarks' => $row['remarks'] ?? null,
        ];

        foreach (array_keys(TicketItem::PRICE_FIELDS) as $field) {
            $new = $row[$field] ?? null;
            $attributes['old_'.$field] = $product->{$field};
            $attributes['new_'.$field] = ($new === null || $new === '') ? null : $new;
        }

        $ticket->items()->create($attributes);
    }

    private function applyPriceUpdate(TicketItem $item, User $admin): void
    {
        $product = Product::query()->lockForUpdate()->find($item->product_id);

        if (! $product) {
            throw ValidationException::withMessages(['ticket' => 'A product on this ticket no longer exists.']);
        }

        if ($item->new_unit_sell_price !== null) {
            $this->applySellingPrice($product, $item, $admin);
        }

        foreach (['cost_price', 'expiry_price', 'reorder_level'] as $field) {
            $new = $item->{'new_'.$field};
            if ($new === null || (float) $new === (float) $product->{$field}) {
                continue;
            }

            $old = $product->{$field};
            $product->update([$field => $new]);

            if ($field !== 'reorder_level') {
                $this->pricing->logChange($product, $field, $old, $new, $admin->id);
            }
        }
    }

    /**
     * Same effect as editing the product's selling price: the product master price always changes
     * (new stock and every price lookup use it) and the price is pushed to the batches — all batches
     * with stock, or only the ones the ticket names.
     */
    private function applySellingPrice(Product $product, TicketItem $item, User $admin): void
    {
        $new = $item->new_unit_sell_price;
        $old = $product->unit_sell_price;
        $priceChanged = (float) $new !== (float) $old;

        if ($item->apply_to_all_batches) {
            // Same behaviour as editing the product: nothing to do when the price is not actually changing.
            if (! $priceChanged) {
                return;
            }

            $product->update(['unit_sell_price' => $new]);
            $batchIds = $this->pricing->cascadeSellingPrice($product, $new);
        } else {
            if ($priceChanged) {
                $product->update(['unit_sell_price' => $new]);
            }

            $batchIds = $this->pricing->cascadeSellingPrice(
                $product,
                $new,
                collect($item->batch_ids)->map(fn ($id) => (int) $id)->values()
            );
        }

        $this->pricing->logChange($product, 'selling_price', $old, $new, $admin->id, $batchIds);
    }

    /**
     * Creates the supplier ledger register line only — it is not posted — and refreshes the running balances,
     * exactly like the Ledger Register screen does.
     */
    private function applyLedgerEntry(TicketItem $item): string
    {
        $data = TicketEntryForms::normalize(TicketType::LedgerEntry, $item->payload);

        $entry = DB::transaction(function () use ($data) {
            $entry = LedgerRegister::create($data);
            LedgerRegister::recalculateBalances((int) $data['supplier_id']);

            return $entry;
        });

        $item->update(['payload' => $item->payload + ['created_id' => $entry->id]]);

        return "Ledger register entry #{$entry->id} created (not posted).";
    }

    /**
     * Creates the claim register line with the default accounts, like the Claim Register screen; it is not posted.
     */
    private function applyClaimEntry(TicketItem $item): string
    {
        $service = app(ClaimRegisterService::class);
        $result = $service->createClaim($service->withDefaultAccounts(TicketEntryForms::normalize(TicketType::ClaimEntry, $item->payload)));

        if (! $result['success']) {
            throw ValidationException::withMessages(['ticket' => $result['message']]);
        }

        $item->update(['payload' => $item->payload + ['created_id' => $result['data']->id]]);

        return "Claim register entry {$result['data']->reference_number} created (not posted).";
    }

    private function applyNewCustomer(TicketItem $item): string
    {
        $data = TicketEntryForms::normalize(TicketType::NewCustomer, $item->payload);

        $taken = Customer::query()->where('customer_code', $data['customer_code'])
            ->when($data['email'] ?? null, fn ($q, $email) => $q->orWhere('email', $email))->exists();

        if ($taken) {
            throw ValidationException::withMessages(['ticket' => "A customer with code '{$data['customer_code']}' or that e-mail already exists, so this ticket cannot be approved."]);
        }

        // Blank optional fields fall back to the column defaults (payment terms, country, … are NOT NULL).
        $customer = Customer::create(array_filter($data, fn ($value) => $value !== null && $value !== ''));
        $item->update(['payload' => $item->payload + ['created_id' => $customer->id]]);

        return "Customer {$customer->customer_code} created.";
    }

    /**
     * Header and lines of a stock adjustment request. System quantities are taken from the live
     * stock at the moment the ticket is raised, never from the browser.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function adjustmentPayload(array $data): array
    {
        return [
            'adjustment_date' => $data['adjustment_date'],
            'supplier_id' => (int) $data['supplier_id'],
            'warehouse_id' => (int) $data['warehouse_id'],
            'adjustment_type' => $data['adjustment_type'],
            'reason' => $data['reason'],
            'notes' => $data['notes'] ?? null,
            'items' => $this->adjustmentLines((int) $data['warehouse_id'], $data['items'])->all(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return Collection<int, array<string, mixed>>
     */
    private function adjustmentLines(int $warehouseId, array $lines): Collection
    {
        return collect($lines)->map(function (array $line) use ($warehouseId) {
            $onHand = (float) DB::table('current_stock_by_batch')
                ->where('stock_batch_id', $line['stock_batch_id'])->where('warehouse_id', $warehouseId)->sum('quantity_on_hand');

            $line['system_quantity'] = $onHand;
            $line['adjustment_quantity'] = (float) $line['actual_quantity'] - $onHand;
            $line['adjustment_value'] = $line['adjustment_quantity'] * (float) $line['unit_cost'];

            return $line;
        })->values();
    }

    /**
     * Creates the draft stock adjustment and posts it with the very same service the
     * Stock Adjustments screen uses (stock movements, valuation layers, current stock, journal entry).
     * System quantities are refreshed first, so a count taken against old stock cannot post a wrong difference.
     *
     * @return string the posted adjustment number
     */
    private function applyStockAdjustment(TicketItem $item): string
    {
        $payload = $item->payload;
        $service = app(StockAdjustmentService::class);

        $lines = $this->adjustmentLines((int) $payload['warehouse_id'], $payload['items'])
            ->reject(fn (array $line) => abs($line['adjustment_quantity']) < 0.0005)
            ->values();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['ticket' => 'Stock has moved since the count: the counted quantities now match the system, so there is nothing left to adjust.']);
        }

        $created = $service->createAdjustment(Arr::except($payload, 'items') + ['items' => $lines->all()]);

        if (! $created['success']) {
            throw ValidationException::withMessages(['ticket' => $created['message']]);
        }

        $adjustment = $created['data'];
        $posted = $service->postAdjustment($adjustment);

        if (! $posted['success']) {
            throw ValidationException::withMessages(['ticket' => $posted['message']]);
        }

        $item->update(['payload' => $payload + ['stock_adjustment_id' => $adjustment->id, 'stock_adjustment_number' => $adjustment->adjustment_number]]);

        return $adjustment->adjustment_number;
    }

    private function applyNewSku(TicketItem $item): void
    {
        // Blank optional fields fall back to the column defaults (several are NOT NULL).
        $payload = collect($item->payload)
            ->except('remarks')
            ->reject(fn ($value) => $value === null || $value === '')
            ->all();

        $taken = Product::withTrashed()
            ->where('product_code', $payload['product_code'])
            ->when(! empty($payload['barcode']), fn ($q) => $q->orWhere('barcode', $payload['barcode']))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'ticket' => "SKU code or barcode '{$payload['product_code']}' already exists, so this ticket cannot be approved.",
            ]);
        }

        $product = Product::create($payload + ['is_active' => true]);
        $item->update(['product_id' => $product->id]);
    }

    private function applyStatusChange(TicketItem $item): void
    {
        $product = Product::query()->lockForUpdate()->find($item->product_id);

        if (! $product) {
            throw ValidationException::withMessages(['ticket' => 'A product on this ticket no longer exists.']);
        }

        $product->update(['is_active' => $item->new_is_active]);
    }

    private function guardPending(Ticket $ticket): void
    {
        if (! $ticket->isPending()) {
            throw ValidationException::withMessages(['ticket' => 'This ticket has already been '.$ticket->status->value.'.']);
        }
    }

    private function close(Ticket $ticket, User $admin, TicketStatus $status, ?string $remarks): void
    {
        $ticket->update([
            'status' => $status,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'review_remarks' => $remarks,
        ]);

        $this->record($ticket, $admin, $status->value, TicketStatus::Pending, $status, $remarks);
    }

    private function record(Ticket $ticket, User $user, string $action, ?TicketStatus $from, ?TicketStatus $to, ?string $remarks = null): void
    {
        $ticket->histories()->create([
            'user_id' => $user->id,
            'action' => $action,
            'from_status' => $from?->value,
            'to_status' => $to?->value,
            'remarks' => $remarks,
        ]);
    }
}
