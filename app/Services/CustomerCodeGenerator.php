<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\TicketItem;

/**
 * Customer codes look like K00000509411: one letter for the company the customer buys from, then an
 * 11-digit number that runs on from the highest number already used with that letter (Kausar and Kausar Rice
 * share K, both Nestle companies share N), so a code is never used twice.
 */
class CustomerCodeGenerator
{
    private const LETTER_BY_SHORT_NAME = [
        'Meezan' => 'M',
        'Nestle' => 'N',
        'NPH19L' => 'N',
        'Kausar' => 'K',
        'KRM' => 'K',
        'Lipton' => 'L',
        'PTC' => 'P',
        'Engro' => 'E',
        'EBM' => 'B',
        'Unilever' => 'U',
    ];

    private const NUMBER_DIGITS = 11;

    public function letterFor(?int $supplierId): string
    {
        $supplier = $supplierId ? Supplier::find($supplierId) : null;

        if (! $supplier) {
            return 'C';
        }

        return self::LETTER_BY_SHORT_NAME[$supplier->short_name]
            ?? strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) $supplier->supplier_name) ?: 'C', 0, 1));
    }

    /**
     * Next free code for the company; looks at saved customers (deleted ones too) and at customer tickets still waiting.
     */
    public function next(?int $supplierId): string
    {
        $letter = $this->letterFor($supplierId);

        $codes = Customer::withTrashed()
            ->whereRaw('LENGTH(customer_code) = ?', [self::NUMBER_DIGITS + 1])
            ->pluck('customer_code')
            ->merge(
                TicketItem::query()
                    ->whereHas('ticket', fn ($q) => $q->where('type', TicketType::NewCustomer)->where('status', TicketStatus::Pending))
                    ->get()
                    ->pluck('payload.customer_code')
            );

        $highest = $codes
            ->filter(fn ($code) => is_string($code) && str_starts_with($code, $letter) && preg_match('/^[A-Z]\d{'.self::NUMBER_DIGITS.'}$/', $code))
            ->map(fn (string $code) => (int) substr($code, 1))
            ->max() ?? 0;

        return $letter.str_pad((string) ($highest + 1), self::NUMBER_DIGITS, '0', STR_PAD_LEFT);
    }
}
