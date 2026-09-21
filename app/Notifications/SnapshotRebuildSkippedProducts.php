<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when the snapshot rebuild leaves products alone because their stock ledger no longer
 * ends where current stock stands. Until the ledger is corrected, those products' daily
 * snapshots (and every report read from them) stay as they were.
 */
class SnapshotRebuildSkippedProducts extends Notification
{
    /**
     * @param  array<int, array{name: string, ledger: float, stock: float}>  $products
     */
    public function __construct(
        public array $products,
        public string $startDate,
        public string $endDate,
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
            ->subject(config('app.name')." — stock snapshots: {$count} product(s) skipped")
            ->line(sprintf(
                'The inventory snapshot rebuild for %s to %s%s skipped %d product(s) because their stock ledger does not match current stock:',
                $this->startDate,
                $this->endDate,
                $this->supplierId ? " (supplier {$this->supplierId})" : '',
                $count
            ));

        foreach ($this->products as $productId => $product) {
            $message->line(sprintf(
                '• %s (#%d): ledger %s, current stock %s',
                $product['name'],
                $productId,
                number_format($product['ledger'], 3),
                number_format($product['stock'], 3)
            ));
        }

        return $message
            ->line('Their daily snapshots were left unchanged, so reports for past dates may be wrong for these products until the ledger is corrected.')
            ->line('Nothing else was affected — every other product was rebuilt as usual.');
    }
}
