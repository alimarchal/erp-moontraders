<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when the nightly cost check finds GRN lines whose receipt movement, batch or later
 * issues and sales carry a cost other than the line's unit_cost. Until repaired, the Daily
 * Stock Register, the daily snapshots and the profit reports value that stock differently.
 */
class GrnCostDriftDetected extends Notification
{
    /**
     * @param  array<int, array{grn_number: string, product_id: int, stock_batch_id: int, problems: string}>  $lines
     */
    public function __construct(public array $lines) {}

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
            ->subject(config('app.name').' — GRN cost check: '.count($this->lines).' line(s) out of step')
            ->line('The nightly cost check found GRN lines whose stock is carried at a different cost than the line itself:');

        foreach (array_slice($this->lines, 0, 20) as $line) {
            $message->line(sprintf('• %s, product #%d, batch #%d: %s', $line['grn_number'], $line['product_id'], $line['stock_batch_id'], $line['problems']));
        }

        if (count($this->lines) > 20) {
            $message->line('… and '.(count($this->lines) - 20).' more.');
        }

        return $message
            ->line('Review with: php artisan stock:repair-grn-cost-drift --dry-run')
            ->line('Apply with: php artisan stock:repair-grn-cost-drift');
    }
}
