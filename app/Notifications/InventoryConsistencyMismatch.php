<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when the nightly consistency check finds that the stock_movements ledger,
 * current_stock_by_batch, stock_valuation_layers and current_stock no longer hold the same
 * quantity. Until it is resolved the /inventory/current-stock page, the batch modal and the
 * stock reports can each show a different number.
 */
class InventoryConsistencyMismatch extends Notification
{
    /**
     * @param  array<int, array{product_id: int, warehouse_id: int, name: string, ledger: float, stock: float, layers: float, current: float}>  $products
     */
    public function __construct(
        public array $products,
        public int $batchCount,
        public ?string $supplierId = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = count($this->products);

        $message = (new MailMessage)
            ->error()
            ->subject(config('app.name')." — inventory consistency: {$count} product(s) out of step");

        if ($count > 0) {
            $message->line(sprintf(
                'The nightly inventory consistency check%s found %d product(s) whose stock records disagree:',
                $this->supplierId ? " (supplier {$this->supplierId})" : '',
                $count
            ));

            foreach ($this->products as $product) {
                $message->line(sprintf(
                    '• %s (#%d, warehouse %d): ledger %s, by batch %s, valuation layers %s, current_stock %s',
                    $product['name'],
                    $product['product_id'],
                    $product['warehouse_id'],
                    number_format($product['ledger'], 3),
                    number_format($product['stock'], 3),
                    number_format($product['layers'], 3),
                    number_format($product['current'], 3)
                ));
            }
        }

        if ($this->batchCount > 0) {
            $message->line(sprintf(
                '%d batch(es) hold a different quantity in their valuation layers than in current stock.',
                $this->batchCount
            ));
        }

        return $message
            ->line('Where the ledger agrees with "by batch", run: php artisan inventory:verify-consistency --fix')
            ->line('Where the ledger itself differs, the postings need looking at — --fix deliberately leaves the ledger alone.');
    }
}
