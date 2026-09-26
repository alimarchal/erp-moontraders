<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when the nightly check finds Stock In Hand or Van Stock in the general ledger
 * away from the value of the stock itself, so the balance sheet no longer matches the
 * stock reports.
 */
class StockLedgerOutOfStep extends Notification
{
    /**
     * @param  array<string, array{ledger: float, stock: float, gap: float}>  $gaps
     */
    public function __construct(public array $gaps) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->error()
            ->subject(config('app.name').' — stock ledger out of step with stock')
            ->line('The nightly check found the general ledger holding a different stock value than the stock records:');

        foreach ($this->gaps as $code => $row) {
            $message->line(sprintf(
                '• %s: ledger %s, stock %s, difference %s',
                $code === '1151' ? '1151 Stock In Hand' : '1155 Van Stock',
                number_format($row['ledger'], 2),
                number_format($row['stock'], 2),
                number_format($row['gap'], 2)
            ));
        }

        return $message
            ->line('Something posted a journal entry at a different value than it moved stock. Find that document first.')
            ->line('Once it is understood, php artisan accounting:reconcile-stock-gl --post --dry-run shows the adjusting entry.');
    }
}
