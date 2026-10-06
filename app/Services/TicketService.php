<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\Product;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

            return $ticket;
        });
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
                };
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
                'new_sku_data' => $data['sku'],
                'remarks' => $data['sku']['remarks'] ?? null,
            ]),
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

    private function applySellingPrice(Product $product, TicketItem $item, User $admin): void
    {
        $new = $item->new_unit_sell_price;
        $old = $product->unit_sell_price;

        if ($item->apply_to_all_batches) {
            $batchIds = $this->pricing->batchIdsWithStock($product);

            if ((float) $new !== (float) $old) {
                $product->update(['unit_sell_price' => $new]);
            }
            $this->pricing->applySellingPriceToBatches($product, $batchIds, $new);
        } else {
            $batchIds = collect($item->batch_ids)->map(fn ($id) => (int) $id)->values();
            $this->pricing->applySellingPriceToBatches($product, $batchIds, $new);
        }

        $this->pricing->logChange($product, 'selling_price', $old, $new, $admin->id, $batchIds);
    }

    private function applyNewSku(TicketItem $item): void
    {
        // Blank optional fields fall back to the column defaults (several are NOT NULL).
        $payload = collect($item->new_sku_data)
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
