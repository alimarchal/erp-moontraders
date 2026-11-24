<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\TicketItem;
use Illuminate\Support\Collection;

/**
 * Customer codes look like K00000509411: one letter for the company the customer buys from, then an
 * 11-digit number. The letter is only a label: all companies (Kausar, EBM, PTC, Meezan, …) take their
 * number from one shared series, so K00000509462 and B00000509462 can never both be handed out.
 * Nestle (N) keeps its own series, which already runs far ahead of the others.
 * A number used with any letter is never given out again.
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

    /**
     * Letters that keep a number series of their own instead of the shared one.
     */
    private const OWN_SERIES_LETTERS = ['N'];

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
     * Next free code for the company: one above the highest number in its series, skipping any number
     * already used with another letter. Looks at saved customers (deleted ones too) and at customer tickets still waiting.
     */
    public function next(?int $supplierId): string
    {
        $letter = $this->letterFor($supplierId);
        $ownSeries = in_array($letter, self::OWN_SERIES_LETTERS, true);

        $codes = $this->usedCodes()
            ->filter(fn ($code) => is_string($code) && preg_match('/^[A-Z]\d{'.self::NUMBER_DIGITS.'}$/', $code))
            ->values();

        $usedNumbers = $codes->mapWithKeys(fn (string $code) => [(int) substr($code, 1) => true]);

        $number = ($codes
            ->filter(fn (string $code) => $ownSeries ? $code[0] === $letter : ! in_array($code[0], self::OWN_SERIES_LETTERS, true))
            ->map(fn (string $code) => (int) substr($code, 1))
            ->max() ?? 0) + 1;

        while ($usedNumbers->has($number)) {
            $number++;
        }

        return $letter.str_pad((string) $number, self::NUMBER_DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * The 11-digit number of a code like K00000509462, or null when the code does not follow that shape.
     */
    public function numberOf(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));

        return preg_match('/^[A-Z](\d{'.self::NUMBER_DIGITS.'})$/', $code, $matches) ? $matches[1] : null;
    }

    /**
     * Whether two codes clash: the same code, or the same number under a different letter.
     */
    public function clashes(?string $code, ?string $other): bool
    {
        if ($code === null || $other === null) {
            return false;
        }

        $code = strtoupper(trim($code));
        $other = strtoupper(trim($other));
        $number = $this->numberOf($code);

        return $code === $other || ($number !== null && $this->numberOf($other) === $number);
    }

    /**
     * Whether a saved customer (deleted ones too) already has this code or its number under another letter.
     */
    public function usedByCustomer(string $code, ?int $exceptCustomerId = null): bool
    {
        $code = strtoupper(trim($code));
        $number = $this->numberOf($code);

        return Customer::withTrashed()
            ->when($exceptCustomerId, fn ($query) => $query->whereKeyNot($exceptCustomerId))
            ->where(fn ($query) => $query->where('customer_code', $code)
                ->when($number, fn ($query) => $query->orWhere('customer_code', 'like', '_'.$number)))
            ->pluck('customer_code')
            ->contains(fn (?string $existing) => $this->clashes($code, $existing));
    }

    /**
     * @return Collection<int, mixed>
     */
    private function usedCodes(): Collection
    {
        return Customer::withTrashed()
            ->whereRaw('LENGTH(customer_code) = ?', [self::NUMBER_DIGITS + 1])
            ->pluck('customer_code')
            ->merge(
                TicketItem::query()
                    ->whereHas('ticket', fn ($q) => $q->where('type', TicketType::NewCustomer)->where('status', TicketStatus::Pending))
                    ->get()
                    ->pluck('payload.customer_code')
            );
    }
}
