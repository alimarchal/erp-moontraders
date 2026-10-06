<?php

namespace App\Enums;

enum TicketType: string
{
    case PriceUpdate = 'price_update';
    case NewSku = 'new_sku';
    case ReactivateSku = 'reactivate_sku';
    case StockAdjustment = 'stock_adjustment';

    public function label(): string
    {
        return match ($this) {
            self::PriceUpdate => 'Price / Reorder Update',
            self::NewSku => 'New SKU Add',
            self::ReactivateSku => 'Re-Activate SKU',
            self::StockAdjustment => 'Stock Adjustment',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PriceUpdate => 'Change selling, cost, expiry price or reorder level of active products, for all or selected batches.',
            self::NewSku => 'Ask for a brand new product (SKU) to be added to the catalogue.',
            self::ReactivateSku => 'Bring an inactive SKU back to Active.',
            self::StockAdjustment => 'Damage, expiry, theft or count variance. The entry is created and posted when an admin approves.',
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
            self::ReactivateSku => 'M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99',
        };
    }
}
