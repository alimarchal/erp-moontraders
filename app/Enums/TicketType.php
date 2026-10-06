<?php

namespace App\Enums;

enum TicketType: string
{
    case PriceUpdate = 'price_update';
    case NewSku = 'new_sku';
    case ReactivateSku = 'reactivate_sku';
    case StockAdjustment = 'stock_adjustment';
    case LedgerEntry = 'ledger_entry';
    case ClaimEntry = 'claim_entry';
    case NewCustomer = 'new_customer';

    public function label(): string
    {
        return match ($this) {
            self::PriceUpdate => 'Price / Reorder Update',
            self::NewSku => 'New SKU Add',
            self::ReactivateSku => 'Re-Activate SKU',
            self::StockAdjustment => 'Stock Adjustment',
            self::LedgerEntry => 'Supplier Ledger Entry',
            self::ClaimEntry => 'Claim Register Entry',
            self::NewCustomer => 'New Customer',
        };
    }

    /**
     * Types whose approval only stores a small record described by TicketEntryForms.
     */
    public function isSimpleEntry(): bool
    {
        return in_array($this, [self::LedgerEntry, self::ClaimEntry, self::NewCustomer], true);
    }

    /**
     * Permission the approver needs on top of ticket-approve, because approving performs that action.
     */
    public function applyPermission(): ?string
    {
        return match ($this) {
            self::StockAdjustment => 'stock-adjustment-post',
            self::LedgerEntry => 'report-audit-ledger-register-create',
            self::ClaimEntry => 'claim-register-create',
            self::NewCustomer => 'customer-create',
            default => null,
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PriceUpdate => 'Change selling, cost, expiry price or reorder level of active products, for all or selected batches.',
            self::NewSku => 'Ask for a brand new product (SKU) to be added to the catalogue.',
            self::ReactivateSku => 'Bring an inactive SKU back to Active.',
            self::StockAdjustment => 'Damage, expiry, theft or count variance. The entry is created and posted when an admin approves.',
            self::LedgerEntry => 'Add a line to the supplier ledger register. Only the entry is created; it is not posted.',
            self::ClaimEntry => 'Add a claim or recovery to the claim register. Only the entry is created; it is not posted.',
            self::NewCustomer => 'Ask for a new customer to be created.',
        };
    }

    /**
     * Heroicons (outline) path for the type icon.
     */
    public function iconPath(): string
    {
        return match ($this) {
            self::PriceUpdate => 'M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
            self::NewSku => 'M12 4.5v15m7.5-7.5h-15',
            self::StockAdjustment => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
            self::LedgerEntry => 'M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25',
            self::ClaimEntry => 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z',
            self::NewCustomer => 'M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM3 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 019.374 21c-2.331 0-4.512-.645-6.374-1.766z',
            self::ReactivateSku => 'M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99',
        };
    }
}
