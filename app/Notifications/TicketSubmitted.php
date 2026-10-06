<?php

namespace App\Notifications;

use App\Enums\TicketType;
use App\Models\Product;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\Warehouse;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mailed to every approver when a company user submits a ticket, with everything needed to decide.
 */
class TicketSubmitted extends Notification
{
    public function __construct(public Ticket $ticket) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticket = $this->ticket->loadMissing(['creator', 'supplier', 'items.product']);

        $message = (new MailMessage)
            ->subject(sprintf('[%s] %s — %s%s', $ticket->ticket_number, $ticket->type->label(), $ticket->title, $ticket->supplier ? ' ('.$ticket->supplier->supplier_name.')' : ''))
            ->greeting('Hello '.($notifiable->name ?? '').',')
            ->line(sprintf('**%s** raised a ticket that is waiting for your approval. Nothing changes in the system until you approve it.', $ticket->creator->name ?? 'A user'))
            ->line('**Ticket:** '.$ticket->ticket_number)
            ->line('**Type:** '.$ticket->type->label())
            ->line('**Company:** '.($ticket->supplier->supplier_name ?? '—'))
            ->line('**Raised:** '.$ticket->created_at->format('d M Y, h:i A'))
            ->line('**Title:** '.$ticket->title);

        if ($ticket->description) {
            $message->line('**Description:** '.$ticket->description);
        }

        $message->line('---')->line('**What is being asked**');

        foreach ($this->summaryLines($ticket) as $line) {
            $message->line($line);
        }

        return $message
            ->action('Review ticket', route('tickets.show', $ticket))
            ->line('You can approve or reject it from the ticket page. The requester sees your decision and remarks.');
    }

    /**
     * @return array<int, string>
     */
    private function summaryLines(Ticket $ticket): array
    {
        return match ($ticket->type) {
            TicketType::PriceUpdate => $ticket->items->flatMap(fn (TicketItem $item) => $this->priceLines($item))->all(),
            TicketType::ReactivateSku => $ticket->items->map(fn (TicketItem $item) => sprintf(
                '• %s: %s → **%s**', $this->productName($item->product), $item->old_is_active ? 'Active' : 'Inactive', $item->new_is_active ? 'Active' : 'Inactive'
            ))->all(),
            TicketType::NewSku => $ticket->items->map(function (TicketItem $item) {
                $sku = $item->payload;

                return sprintf(
                    '• New SKU **%s — %s**; selling price %s, cost price %s, reorder level %s',
                    $sku['product_code'] ?? '', $sku['product_name'] ?? '', $this->money($sku['unit_sell_price'] ?? null), $this->money($sku['cost_price'] ?? null), $this->money($sku['reorder_level'] ?? null)
                );
            })->all(),
            TicketType::StockAdjustment => $ticket->items->flatMap(fn (TicketItem $item) => $this->adjustmentLines($item))->all(),
        };
    }

    /**
     * @return array<int, string>
     */
    private function priceLines(TicketItem $item): array
    {
        $changes = collect($item->priceChanges())->map(fn (array $c) => sprintf(
            '%s %s → **%s** (%s%s)', $c['label'], $this->money($c['old']), $this->money($c['new']), $c['difference'] >= 0 ? '+' : '', number_format($c['difference'], 2)
        ))->implode('; ');

        $scope = '';
        if ($item->new_unit_sell_price !== null) {
            $scope = $item->apply_to_all_batches
                ? ' — selling price for all batches with stock'
                : ' — selling price only for batches '.DB::table('stock_batches')->whereIn('id', $item->batch_ids ?? [])->pluck('batch_code')->implode(', ');
        }

        return ['• '.$this->productName($item->product).': '.$changes.$scope];
    }

    /**
     * @return array<int, string>
     */
    private function adjustmentLines(TicketItem $item): array
    {
        $data = $item->payload;
        $lines = collect($data['items'] ?? []);
        $products = Product::whereIn('id', $lines->pluck('product_id'))->pluck('product_name', 'id');
        $batches = DB::table('stock_batches')->whereIn('id', $lines->pluck('stock_batch_id'))->pluck('batch_code', 'id');

        $out = [
            sprintf(
                '• %s on %s in warehouse **%s** — reason: %s',
                Str::headline($data['adjustment_type'] ?? ''), $data['adjustment_date'] ?? '', Warehouse::whereKey($data['warehouse_id'] ?? 0)->value('warehouse_name') ?? '—', $data['reason'] ?? ''
            ),
        ];

        foreach ($lines as $line) {
            $out[] = sprintf(
                '  – %s (batch %s): system %s → counted %s, difference %s, value %s',
                $products[$line['product_id']] ?? '—', $batches[$line['stock_batch_id']] ?? '—',
                $this->qty($line['system_quantity']), $this->qty($line['actual_quantity']), $this->qty($line['adjustment_quantity']), $this->money($line['adjustment_value'])
            );
        }

        $out[] = '  **Total value: '.$this->money($lines->sum('adjustment_value')).'** — approving creates and posts the adjustment.';

        return $out;
    }

    private function productName(?Product $product): string
    {
        return $product ? $product->product_code.' — '.$product->product_name : 'Deleted product';
    }

    private function money(mixed $value): string
    {
        return $value === null || $value === '' ? '—' : number_format((float) $value, 2);
    }

    private function qty(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.') ?: '0';
    }
}
