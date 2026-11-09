<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Nightly notice, in Urdu, of the goods issues whose sale is not yet in the books: a settlement
 * saved as draft, stock on a van with no settlement at all, or an issue still in draft. Each
 * user receives their own documents; super admins and admins receive the whole list.
 */
class PendingSettlementsNotice extends Notification
{
    /**
     * @param  array{draft_settlements: list<array<string, mixed>>, unsettled_issues: list<array<string, mixed>>, draft_issues: list<array<string, mixed>>}  $sections
     * @param  bool  $isOverview  True for super admins and admins, who see every user's documents.
     */
    public function __construct(
        public array $sections,
        public bool $isOverview,
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
        $settlementCount = count($this->sections['draft_settlements']);
        $issueCount = count($this->sections['unsettled_issues']);

        $urdu = collect([
            $settlementCount > 0 ? "{$settlementCount} سیل سیٹلمنٹ پوسٹ نہیں ".($settlementCount === 1 ? 'ہوئی' : 'ہوئیں') : null,
            $issueCount > 0 ? "{$issueCount} گڈز اِشو کی سیٹلمنٹ نہیں بنی" : null,
        ])->filter()->whenEmpty(fn ($parts) => $parts->push('گڈز اِشو ابھی ڈرافٹ میں ہیں'))->implode('، ');

        $english = collect([
            $settlementCount > 0 ? "{$settlementCount} settlement(s) not posted" : null,
            $issueCount > 0 ? "{$issueCount} goods issue(s) without a settlement" : null,
        ])->filter()->whenEmpty(fn ($parts) => $parts->push('goods issues still in draft'))->implode(', ');

        return (new MailMessage)
            ->subject("نوٹس: {$urdu}".($this->isOverview ? ' (تمام یوزرز)' : '')." | Notice: {$english} — ".config('app.name'))
            ->view('emails.pending-settlements', [
                'recipientName' => $notifiable->name ?? '',
                'sections' => $this->sections,
                'isOverview' => $this->isOverview,
            ]);
    }
}
