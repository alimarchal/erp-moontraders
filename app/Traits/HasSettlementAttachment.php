<?php

namespace App\Traits;

use App\Services\SettlementAttachmentService;

/**
 * Exposes a protected URL for the row's optional attachment (settlement child rows).
 * The using model must define attachmentSection() returning a SettlementAttachmentService section key.
 */
trait HasSettlementAttachment
{
    abstract protected function attachmentSection(): string;

    public function getAttachmentUrlAttribute(): ?string
    {
        if (! $this->attachment || ! SettlementAttachmentService::enabled()) {
            return null;
        }

        return route('sales-settlements.attachments.show', [
            'salesSettlement' => $this->sales_settlement_id,
            'section' => $this->attachmentSection(),
            'id' => $this->getKey(),
        ]);
    }

    /**
     * Attachment fields carried inside an entry's JSON on the edit forms.
     *
     * @return array{attachment: ?string, attachment_url: ?string, attachment_ext: ?string, attachment_name: ?string}
     */
    public function attachmentPayload(): array
    {
        return [
            'attachment' => $this->attachment,
            'attachment_url' => $this->attachment_url,
            'attachment_ext' => $this->attachment ? strtolower(pathinfo($this->attachment, PATHINFO_EXTENSION)) : null,
            'attachment_name' => $this->attachment ? 'Attachment' : null,
        ];
    }
}
