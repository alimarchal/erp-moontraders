<?php

namespace App\Enums;

enum TicketType: string
{
    case PriceUpdate = 'price_update';
    case NewSku = 'new_sku';
    case ReactivateSku = 'reactivate_sku';

    public function label(): string
    {
        return match ($this) {
            self::PriceUpdate => 'Product Price / Reorder Update',
            self::NewSku => 'New SKU Add',
            self::ReactivateSku => 'Re-Activate SKU',
        };
    }
}
